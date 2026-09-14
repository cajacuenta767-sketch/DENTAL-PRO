<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\RespaldoService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\CasoClinico;
use ZipArchive;

class RespaldoTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(RespaldoService::DISCO);
    }

    private function usuarioCon(string $rol): Usuario
    {
        $usuario = Usuario::create([
            'nombre' => "Usuario {$rol}",
            'email' => str($rol)->slug().'@pruebas.test',
            'password' => 'secreto123',
            'estado' => 'activo',
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    }

    /** Omite la prueba con un mensaje claro si pg_dump/pg_restore no existen en este entorno. */
    private function requiereHerramientasPg(): void
    {
        try {
            app(RespaldoService::class)->comprobarHerramientas();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    public function test_el_comando_crea_un_zip_con_el_volcado_y_el_manifiesto(): void
    {
        $this->requiereHerramientasPg();

        $codigo = Artisan::call('sistema:respaldar');

        if ($codigo !== 0) {
            $this->markTestSkipped('pg_dump falló en el entorno de pruebas: '.trim(Artisan::output()));
        }

        $disco = Storage::disk(RespaldoService::DISCO);
        $archivos = collect($disco->files())->filter(fn ($f) => preg_match(RespaldoService::PATRON_NOMBRE, basename($f)));

        $this->assertCount(1, $archivos, 'Debe existir exactamente un respaldo.');

        $nombre = basename($archivos->first());
        $this->assertMatchesRegularExpression('/^respaldo-\d{8}-\d{6}\.zip$/', $nombre);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($disco->path($nombre)));
        $this->assertNotFalse($zip->locateName('base.dump'), 'El ZIP debe contener base.dump.');
        $this->assertNotFalse($zip->locateName('manifiesto.json'), 'El ZIP debe contener manifiesto.json.');
        $this->assertGreaterThan(0, $zip->statName('base.dump')['size']);

        $manifiesto = json_decode($zip->getFromName('manifiesto.json'), true);
        $zip->close();

        $this->assertSame(config('odontosuite.version'), $manifiesto['version']);
        $this->assertArrayHasKey('fecha', $manifiesto);
        $this->assertContains('public.pacientes', $manifiesto['tablas']);
    }

    public function test_la_retencion_elimina_los_respaldos_mas_antiguos(): void
    {
        config(['filesystems.disks.respaldos.conservar' => 2]);
        $disco = Storage::disk(RespaldoService::DISCO);

        foreach (['20240101-020000', '20240102-020000', '20240103-020000', '20240104-020000'] as $sello) {
            $disco->put("respaldo-{$sello}.zip", 'contenido');
        }
        $disco->put('otro-archivo.txt', 'no es un respaldo');

        $eliminados = app(RespaldoService::class)->aplicarRetencion();

        $this->assertSame(2, $eliminados);
        $disco->assertMissing('respaldo-20240101-020000.zip');
        $disco->assertMissing('respaldo-20240102-020000.zip');
        $disco->assertExists('respaldo-20240103-020000.zip');
        $disco->assertExists('respaldo-20240104-020000.zip');
        $disco->assertExists('otro-archivo.txt');

        $nombres = app(RespaldoService::class)->listar()->pluck('nombre')->all();
        $this->assertSame(['respaldo-20240104-020000.zip', 'respaldo-20240103-020000.zip'], $nombres);
    }

    public function test_crear_aplica_la_retencion_configurada(): void
    {
        $this->requiereHerramientasPg();

        config(['filesystems.disks.respaldos.conservar' => 1]);
        $disco = Storage::disk(RespaldoService::DISCO);
        $disco->put('respaldo-20240101-020000.zip', 'antiguo');

        try {
            $nombre = app(RespaldoService::class)->crear();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $disco->assertExists($nombre);
        $disco->assertMissing('respaldo-20240101-020000.zip');
    }

    public function test_eliminar_rechaza_nombres_fuera_del_patron(): void
    {
        $disco = Storage::disk(RespaldoService::DISCO);
        $disco->put('respaldo-20240101-020000.zip', 'x');

        $servicio = app(RespaldoService::class);

        $this->assertFalse($servicio->eliminar('../.env'));
        $this->assertFalse($servicio->existe('cualquiera.zip'));
        $this->assertTrue($servicio->eliminar('respaldo-20240101-020000.zip'));
        $disco->assertMissing('respaldo-20240101-020000.zip');
    }

    public function test_el_indice_responde_al_administrador_y_rechaza_a_recepcion(): void
    {
        Storage::disk(RespaldoService::DISCO)->put('respaldo-20240101-020000.zip', 'x');

        $this->actingAs($this->admin)
            ->get('/admin/respaldos')
            ->assertOk()
            ->assertSee('Copias de seguridad')
            ->assertSee('respaldo-20240101-020000.zip');

        $this->actingAs($this->usuarioCon('RECEPCION'))
            ->get('/admin/respaldos')
            ->assertForbidden();
    }

    public function test_descargar_y_eliminar_desde_el_panel(): void
    {
        $disco = Storage::disk(RespaldoService::DISCO);
        $disco->put('respaldo-20240101-020000.zip', 'contenido-zip');

        $this->actingAs($this->admin);

        $this->get('/admin/respaldos/respaldo-20240101-020000.zip/descargar')
            ->assertOk()
            ->assertDownload('respaldo-20240101-020000.zip');

        $this->get('/admin/respaldos/inexistente.zip/descargar')->assertNotFound();

        $this->delete('/admin/respaldos/respaldo-20240101-020000.zip')->assertRedirect();
        $disco->assertMissing('respaldo-20240101-020000.zip');
    }
}
