<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\Control\Licencia;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClienteRequest;
use Illuminate\Support\Facades\Http;
use Tests\CasoClinico;

/**
 * Integración con CONTROL: tokens Ed25519 firmados como lo hace
 * backend/src/firmas.js, verificación local, latido, bloqueo por estado,
 * código de emergencia y pantalla /licencia.
 */
class LicenciaTest extends CasoClinico
{
    private const CLAVE = 'CTL-AB12-CD34-EF56-GH78';

    private const HUELLA = 'equipo-de-pruebas';

    private const URL = 'https://control.pruebas.test';

    private string $secreta;

    private string $archivo;

    protected function setUp(): void
    {
        parent::setUp();

        $par = sodium_crypto_sign_keypair();
        $this->secreta = sodium_crypto_sign_secretkey($par);
        $this->archivo = storage_path('framework/testing/control-'.uniqid().'/licencia.json');

        config([
            'control.activo' => true,
            'control.url' => self::URL,
            'control.licencia' => null,
            'control.clave_publica' => base64_encode(sodium_crypto_sign_publickey($par)),
            'control.huella' => self::HUELLA,
            'control.archivo' => $this->archivo,
            'app.version' => '2.0.0',
        ]);

        $this->reiniciar();
    }

    protected function tearDown(): void
    {
        if (is_file($this->archivo)) {
            unlink($this->archivo);
            @rmdir(dirname($this->archivo));
        }

        parent::tearDown();
    }

    /** Reconstruye el singleton para que lea la configuración y el archivo actuales. */
    private function reiniciar(): void
    {
        $this->app->forgetInstance(Licencia::class);
    }

    /** Firma como CONTROL (firmas.js): base64url(payload).base64url(firma). */
    private function firmar(array $payload): string
    {
        $b64url = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $cuerpo = $b64url(json_encode($payload));

        return $cuerpo.'.'.$b64url(sodium_crypto_sign_detached($cuerpo, $this->secreta));
    }

    private function token(array $extra = [], int $expiraEnDias = 7, string $estado = 'activa'): string
    {
        return $this->firmar(array_merge([
            'clave' => self::CLAVE,
            'producto' => 'dental-pro',
            'plan' => 'anual',
            'huella' => self::HUELLA,
            'estado' => $estado,
            'etiqueta' => 'Sede Central',
            'vence_en' => now()->addYear()->toIso8601String(),
            'soporte_hasta' => now()->addYear()->toIso8601String(),
            'emitido_en' => now()->toIso8601String(),
            'expira_en' => now()->addDays($expiraEnDias)->toIso8601String(),
        ], $extra));
    }

    private function guardarArchivo(array $datos): void
    {
        @mkdir(dirname($this->archivo), 0775, true);
        file_put_contents($this->archivo, json_encode($datos));
        $this->reiniciar();
    }

    private function archivo(): array
    {
        return is_file($this->archivo) ? json_decode(file_get_contents($this->archivo), true) : [];
    }

    private function respuestaOk(string $token, array $licencia = []): array
    {
        return ['ok' => true, 'token' => $token, 'licencia' => array_merge([
            'estado' => 'activa', 'etiqueta' => 'Sede Central', 'vence_en' => now()->addYear()->toIso8601String(),
            'soporte_hasta' => null, 'producto' => 'dental-pro', 'plan' => 'anual', 'motivo' => null,
            'version_actual' => '2.0.0', 'desactualizada' => false,
        ], $licencia)];
    }

    private function usuarioSinPermisos(): Usuario
    {
        $usuario = Usuario::create([
            'nombre' => 'Doctor Sin Ajustes',
            'email' => 'doctor@pruebas.test',
            'password' => 'secreto123',
            'estado' => 'activo',
        ]);
        $usuario->assignRole('DOCTOR');

        return $usuario;
    }

    public function test_con_control_desactivado_todo_pasa_sin_licencia(): void
    {
        config(['control.activo' => false]);
        $this->reiniciar();
        Http::fake();

        // La API sin token sigue respondiendo 401 (no 402) y el panel entra.
        $this->getJson('/api/v1/yo')->assertStatus(401);
        $this->actingAs($this->admin)->get('/admin/home')->assertOk();
        Http::assertNothingSent();
    }

    public function test_sin_clave_registrada_redirige_a_la_pantalla_de_licencia(): void
    {
        Http::fake();

        $this->actingAs($this->admin)
            ->get('/admin/home')
            ->assertRedirect(route('licencia.mostrar'));

        $this->actingAs($this->admin)
            ->get('/licencia')
            ->assertOk()
            ->assertSee('Falta registrar la clave de licencia')
            ->assertSee(self::HUELLA)
            ->assertSee('Ingresar la clave de licencia');

        Http::assertNothingSent();
    }

    public function test_registrar_la_clave_activa_guarda_el_token_y_deja_entrar(): void
    {
        Http::fake([self::URL.'/api/v1/licencias/activar' => Http::response($this->respuestaOk($this->token()))]);

        $this->actingAs($this->admin)
            ->post('/licencia/clave', ['clave' => strtolower(self::CLAVE)])
            ->assertRedirect(route('licencia.mostrar'))
            ->assertSessionHas('exito');

        $archivo = $this->archivo();
        $this->assertSame(self::CLAVE, $archivo['clave']);
        $this->assertNotEmpty($archivo['token']);
        $this->assertSame('2.0.0', $archivo['info']['version_actual']);

        Http::assertSent(fn (ClienteRequest $r) => str_ends_with($r->url(), '/api/v1/licencias/activar')
            && $r['clave'] === self::CLAVE && $r['producto'] === 'dental-pro'
            && $r['huella'] === self::HUELLA && $r['version'] === '2.0.0');

        $this->actingAs($this->admin)->get('/admin/home')->assertOk();
        $this->actingAs($this->admin)->get('/licencia')->assertOk()->assertSee('Licencia activa')->assertSee('Sede Central');
    }

    public function test_token_guardado_valido_entra_sin_red(): void
    {
        // Expira en un día: se intenta el latido, pero sin red se sigue trabajando.
        $this->guardarArchivo(['clave' => self::CLAVE, 'token' => $this->token(expiraEnDias: 1)]);
        $intentos = 0;
        Http::fake(function () use (&$intentos) {
            $intentos++;
            throw new ConnectionException('Sin conexión');
        });

        $this->actingAs($this->admin)->get('/admin/home')->assertOk();
        $this->assertSame(1, $intentos);

        // El siguiente intento espera al menos una hora: no vuelve a llamar.
        $this->actingAs($this->admin)->get('/admin/pacientes')->assertOk();
        $this->assertSame(1, $intentos);
    }

    public function test_token_vigente_por_varios_dias_no_llama_a_control(): void
    {
        $this->guardarArchivo(['clave' => self::CLAVE, 'token' => $this->token(expiraEnDias: 7)]);
        Http::fake();

        $this->actingAs($this->admin)->get('/admin/home')->assertOk();
        Http::assertNothingSent();
    }

    public function test_token_expirado_y_control_revoca_bloquea_con_el_motivo(): void
    {
        $this->guardarArchivo(['clave' => self::CLAVE, 'token' => $this->token(expiraEnDias: -1)]);
        Http::fake([
            self::URL.'/api/v1/licencias/activar' => Http::response([
                'ok' => false, 'error' => 'Licencia revocada', 'codigo' => 'revocada',
                'licencia' => ['estado' => 'revocada', 'motivo' => 'Clonación detectada'],
            ], 403),
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/home')
            ->assertRedirect(route('licencia.mostrar'));

        $this->actingAs($this->admin)
            ->get('/licencia')
            ->assertOk()
            ->assertSee('Licencia no válida')
            ->assertSee('Licencia revocada');

        // El rechazo queda guardado: sin token y con el motivo para la pantalla.
        $this->assertNull($this->archivo()['token']);
        $this->assertSame('revocada', $this->archivo()['ultimo_error']['codigo']);
    }

    public function test_un_token_de_otro_equipo_o_de_otra_clave_no_vale(): void
    {
        $this->guardarArchivo(['clave' => self::CLAVE, 'token' => $this->token(['huella' => 'otro-equipo'])]);
        Http::fake(fn () => throw new ConnectionException('Sin conexión'));

        $this->actingAs($this->admin)->get('/admin/home')->assertRedirect(route('licencia.mostrar'));
    }

    public function test_en_mora_entra_con_aviso(): void
    {
        $this->guardarArchivo(['clave' => self::CLAVE, 'token' => $this->token(estado: 'mora')]);
        Http::fake([
            self::URL.'/api/v1/licencias/latido' => Http::response($this->respuestaOk($this->token(estado: 'mora'), ['estado' => 'mora'])),
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/home')
            ->assertOk()
            ->assertSee('periodo de gracia');

        Http::assertSent(fn (ClienteRequest $r) => str_ends_with($r->url(), '/api/v1/licencias/latido'));

        $this->actingAs($this->admin)->get('/licencia')->assertOk()->assertSee('Licencia vencida: renueva pronto');
    }

    public function test_suspendida_bloquea_aunque_haya_token(): void
    {
        $this->guardarArchivo(['clave' => self::CLAVE, 'token' => $this->token(expiraEnDias: 1)]);
        Http::fake([
            self::URL.'/api/v1/licencias/latido' => Http::response([
                'ok' => false, 'error' => 'Licencia suspendida', 'codigo' => 'suspendida', 'licencia' => ['estado' => 'suspendida'],
            ], 403),
        ]);

        $this->actingAs($this->admin)->get('/admin/home')->assertRedirect(route('licencia.mostrar'));
        $this->actingAs($this->admin)->get('/licencia')->assertSee('Licencia suspendida');
    }

    public function test_codigo_de_emergencia_desbloquea_72_horas_solo_para_esta_huella(): void
    {
        $this->guardarArchivo(['clave' => self::CLAVE, 'token' => null, 'ultimo_error' => ['codigo' => 'suspendida', 'motivo' => 'Licencia suspendida']]);
        Http::fake(fn () => throw new ConnectionException('CONTROL caído'));

        $this->actingAs($this->admin)->get('/admin/home')->assertRedirect(route('licencia.mostrar'));

        $expira = now()->addHours(72)->toIso8601String();

        // De otro equipo: se rechaza y sigue bloqueado.
        $ajeno = $this->token(['huella' => 'otro-equipo', 'emergencia' => true, 'expira_en' => $expira]);
        $this->actingAs($this->admin)
            ->post('/licencia/emergencia', ['codigo' => $ajeno])
            ->assertRedirect()
            ->assertSessionHas('error', 'Código inválido, vencido o de otro equipo');
        $this->actingAs($this->admin)->get('/admin/home')->assertRedirect(route('licencia.mostrar'));

        // Un token normal (sin `emergencia`) tampoco sirve como código.
        $this->actingAs($this->admin)
            ->post('/licencia/emergencia', ['codigo' => $this->token()])
            ->assertSessionHas('error', 'Ese código no es de emergencia');

        // Para esta huella: desbloquea hasta expira_en.
        $propio = $this->token(['emergencia' => true, 'expira_en' => $expira]);
        $this->actingAs($this->admin)
            ->post('/licencia/emergencia', ['codigo' => $propio])
            ->assertSessionHas('exito');

        $this->assertSame($propio, $this->archivo()['token']);
        $this->actingAs($this->admin)->get('/admin/home')->assertOk();
        $this->actingAs($this->admin)->get('/licencia')->assertSee('código de emergencia');
    }

    public function test_la_api_responde_402_cuando_la_licencia_no_es_valida(): void
    {
        Http::fake();

        $this->getJson('/api/v1/yo')
            ->assertStatus(402)
            ->assertJson(['ok' => false, 'codigo' => 'sin_clave'])
            ->assertJsonStructure(['ok', 'error', 'codigo']);

        // Una petición web que pide JSON tampoco recibe una redirección.
        $this->actingAs($this->admin)->getJson('/admin/home')->assertStatus(402);
    }

    public function test_el_latido_programado_renueva_el_token(): void
    {
        $viejo = $this->token(expiraEnDias: 1);
        $nuevo = $this->token(expiraEnDias: 7);
        $this->guardarArchivo(['clave' => self::CLAVE, 'token' => $viejo]);
        Http::fake([self::URL.'/api/v1/licencias/latido' => Http::response($this->respuestaOk($nuevo, ['version_actual' => '2.1.0', 'desactualizada' => true]))]);

        $this->artisan('licencia:latido')
            ->expectsOutputToContain('Licencia vigente')
            ->assertSuccessful();

        Http::assertSent(fn (ClienteRequest $r) => str_ends_with($r->url(), '/api/v1/licencias/latido')
            && $r['clave'] === self::CLAVE && $r['huella'] === self::HUELLA && $r['version'] === '2.0.0');

        $this->assertSame($nuevo, $this->archivo()['token']);
        $this->assertTrue($this->archivo()['info']['desactualizada']);

        $this->actingAs($this->admin)->get('/licencia')->assertSee('Hay una versión nueva (2.1.0)');
    }

    public function test_el_comando_activar_registra_la_clave_y_activa(): void
    {
        Http::fake([self::URL.'/api/v1/licencias/activar' => Http::response($this->respuestaOk($this->token()))]);

        $this->artisan('licencia:activar', ['clave' => self::CLAVE])
            ->expectsOutputToContain('Licencia activada')
            ->assertSuccessful();

        $this->assertSame(self::CLAVE, $this->archivo()['clave']);
        $this->assertNotEmpty($this->archivo()['token']);

        $this->artisan('licencia:activar', ['clave' => 'CTL-MALA'])->assertFailed();
    }

    public function test_guardar_una_clave_con_formato_invalido_falla(): void
    {
        Http::fake();

        $this->actingAs($this->admin)
            ->from('/licencia')
            ->post('/licencia/clave', ['clave' => 'ABC-1234'])
            ->assertRedirect('/licencia')
            ->assertSessionHasErrors('clave');

        $this->assertFileDoesNotExist($this->archivo);
        Http::assertNothingSent();
    }

    public function test_en_el_primer_arranque_cualquier_usuario_registra_la_clave_pero_luego_hace_falta_permiso(): void
    {
        Http::fake([self::URL.'/api/v1/licencias/activar' => Http::response($this->respuestaOk($this->token()))]);
        $doctor = $this->usuarioSinPermisos();

        $this->actingAs($doctor)
            ->post('/licencia/clave', ['clave' => self::CLAVE])
            ->assertRedirect(route('licencia.mostrar'))
            ->assertSessionHas('exito');

        $this->actingAs($doctor)->post('/licencia/reactivar')->assertForbidden();
        $this->actingAs($doctor)->post('/licencia/clave', ['clave' => 'CTL-0000-0000-0000-0000'])->assertForbidden();
        $this->actingAs($doctor)->get('/licencia')->assertOk()->assertSee('Solo un administrador');

        $this->actingAs($this->admin)->post('/licencia/reactivar')->assertRedirect()->assertSessionHas('exito');
    }

    public function test_las_rutas_libres_siguen_disponibles_sin_licencia(): void
    {
        Http::fake();

        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/olvide-password')->assertOk();
        $this->get('/licencia')->assertOk()->assertSee('Iniciar sesión para gestionar la licencia');
    }

    public function test_la_pantalla_de_ajustes_muestra_la_tarjeta_de_licencia(): void
    {
        $this->guardarArchivo(['clave' => self::CLAVE, 'token' => $this->token()]);
        Http::fake();

        $this->actingAs($this->admin)
            ->get('/admin/ajustes')
            ->assertOk()
            ->assertSee('Licencia del sistema')
            ->assertSee(route('licencia.mostrar'));
    }
}
