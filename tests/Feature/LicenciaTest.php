<?php

namespace Tests\Feature;

use App\Models\Licencia;
use App\Models\LicenciaEmitida;
use App\Services\EmisorLicencias;
use App\Services\LicenciaService;
use Tests\CasoClinico;

class LicenciaTest extends CasoClinico
{
    private LicenciaService $licencias;

    private string $privada;

    protected function setUp(): void
    {
        parent::setUp();

        $this->licencias = app(LicenciaService::class);
        $claves = $this->licencias->generarClaves();
        $this->privada = $claves['privada'];

        // Instalación de un cliente: verificación encendida y sin clave privada.
        config([
            'licencia.activa' => true,
            'licencia.clave_publica' => $claves['publica'],
            'licencia.clave_privada' => null,
        ]);
    }

    /** Emite como proveedor y vuelve al modo cliente. */
    private function emitir(string $cliente, int $dias = 7, int $tipo = LicenciaService::TIPO_PRUEBA): LicenciaEmitida
    {
        config(['licencia.clave_privada' => $this->privada]);
        $emisor = app(EmisorLicencias::class);
        $emitida = $emisor->emitir($cliente, $tipo, $emisor->diaFinDesde(dias: $dias));
        config(['licencia.clave_privada' => null]);

        return $emitida;
    }

    private function activar(string $codigo)
    {
        return $this->actingAs($this->admin)->post('/licencia/activar', ['codigo' => $codigo]);
    }

    public function test_sin_licencia_el_panel_redirige_a_la_pantalla_de_licencia(): void
    {
        $this->actingAs($this->admin)->get('/admin/home')
            ->assertRedirect('/licencia');

        $this->actingAs($this->admin)->get('/licencia')
            ->assertOk()
            ->assertSee(Licencia::actual()->codigo_instalacion);
    }

    public function test_un_codigo_de_activacion_valido_abre_el_sistema_por_siete_dias(): void
    {
        $emitida = $this->emitir('Clínica Sonrisa');

        $this->activar($emitida->ultimo_codigo)->assertRedirect('/admin/home');

        $licencia = Licencia::actual()->fresh();
        $this->assertTrue($licencia->estaVigente());
        $this->assertSame('PRUEBA', $licencia->tipo);
        $this->assertSame(now()->addDays(7)->toDateString(), $licencia->vence_en->toDateString());

        $this->actingAs($this->admin)->get('/admin/home')->assertOk();
    }

    public function test_el_codigo_no_importa_como_se_escriba(): void
    {
        $emitida = $this->emitir('Clínica Sonrisa');
        $desordenado = strtolower(str_replace('-', ' ', $emitida->ultimo_codigo));

        $this->activar("  {$desordenado}  ")->assertRedirect('/admin/home');
        $this->assertTrue(Licencia::actual()->fresh()->estaVigente());
    }

    public function test_un_codigo_alterado_se_rechaza(): void
    {
        $emitida = $this->emitir('Clínica Sonrisa');
        $codigo = $emitida->ultimo_codigo;
        $alterado = substr($codigo, 0, 10).($codigo[10] === 'A' ? 'B' : 'A').substr($codigo, 11);

        $this->activar($alterado)->assertSessionHas('error');
        $this->assertFalse(Licencia::actual()->fresh()->estaVigente());
    }

    public function test_un_codigo_firmado_con_otra_clave_se_rechaza(): void
    {
        $otra = $this->licencias->generarClaves();
        $ancla = $this->licencias->ancla($this->licencias->nuevaSemilla());
        $codigo = $this->licencias->codigoActivacion($ancla, LicenciaService::TIPO_PRUEBA, 300, $otra['privada']);

        $this->activar($codigo)->assertSessionHas('error');
        $this->assertFalse(Licencia::actual()->fresh()->estaVigente());
    }

    public function test_un_codigo_de_activacion_no_se_puede_usar_dos_veces(): void
    {
        $emitida = $this->emitir('Clínica Sonrisa');

        $this->activar($emitida->ultimo_codigo)->assertRedirect('/admin/home');
        $this->activar($emitida->ultimo_codigo)->assertSessionHas('error');
    }

    public function test_un_codigo_de_activacion_caduca_si_no_se_usa_a_tiempo(): void
    {
        $emitida = $this->emitir('Clínica Sonrisa');

        $this->travel(40)->days();

        $this->activar($emitida->ultimo_codigo)->assertSessionHas('error');
        $this->assertFalse(Licencia::actual()->fresh()->estaVigente());
    }

    public function test_al_vencer_el_panel_se_bloquea_y_las_reservas_publicas_tambien(): void
    {
        $emitida = $this->emitir('Clínica Sonrisa');
        $this->activar($emitida->ultimo_codigo);

        \App\Models\Ajuste::actual()->update(['reservas_online' => true, 'reservas_token' => 'token-de-pruebas-abcdefghijklmnop']);
        $this->get('/reservar/token-de-pruebas-abcdefghijklmnop')->assertOk();

        $this->travel(8)->days();

        $this->actingAs($this->admin)->get('/admin/home')->assertRedirect('/licencia');
        $this->actingAs($this->admin)->getJson('/admin/citas/horas-disponibles/consultar')->assertStatus(403);
        $this->get('/reservar/token-de-pruebas-abcdefghijklmnop')->assertStatus(503);
    }

    public function test_el_pin_de_renovacion_extiende_la_licencia_de_esa_instalacion(): void
    {
        $emitida = $this->emitir('Clínica Sonrisa');
        $this->activar($emitida->ultimo_codigo);
        $instalacion = Licencia::actual()->codigo_instalacion;

        $this->travel(10)->days();
        $this->assertFalse(Licencia::actual()->fresh()->estaVigente());

        config(['licencia.clave_privada' => $this->privada]);
        $emisor = app(EmisorLicencias::class);
        $pin = $emisor->renovar($emitida->fresh(), strtolower($instalacion), $emisor->diaFinDesde(dias: 365));
        config(['licencia.clave_privada' => null]);

        $this->assertMatchesRegularExpression('/^[0-9A-Z]{4}(-[0-9A-Z]{4}){4}$/', $pin);

        $this->activar($pin)->assertRedirect('/admin/home');

        $licencia = Licencia::actual()->fresh();
        $this->assertTrue($licencia->estaVigente());
        $this->assertSame('COMPLETA', $licencia->tipo);
        $this->assertSame(now()->addDays(365)->toDateString(), $licencia->vence_en->toDateString());
        $this->assertSame($instalacion, $emitida->fresh()->codigo_instalacion);
    }

    public function test_un_pin_emitido_para_otra_instalacion_no_sirve(): void
    {
        $emitida = $this->emitir('Clínica Sonrisa');
        $this->activar($emitida->ultimo_codigo);

        config(['licencia.clave_privada' => $this->privada]);
        $emisor = app(EmisorLicencias::class);
        $pin = $emisor->renovar($emitida->fresh(), 'AAAA-BBBB', $emisor->diaFinDesde(dias: 365));
        config(['licencia.clave_privada' => null]);

        $this->activar($pin)->assertSessionHas('error');
        $this->assertSame('PRUEBA', Licencia::actual()->fresh()->tipo);
    }

    public function test_un_pin_no_puede_acortar_ni_repetir_la_licencia(): void
    {
        $emitida = $this->emitir('Clínica Sonrisa');
        $this->activar($emitida->ultimo_codigo);
        $instalacion = Licencia::actual()->codigo_instalacion;

        config(['licencia.clave_privada' => $this->privada]);
        $emisor = app(EmisorLicencias::class);
        $pin = $emisor->renovar($emitida->fresh(), $instalacion, $emisor->diaFinDesde(dias: 30));
        config(['licencia.clave_privada' => null]);

        $this->activar($pin)->assertRedirect('/admin/home');
        $this->activar($pin)->assertSessionHas('error');
        $this->assertSame(now()->addDays(30)->toDateString(), Licencia::actual()->fresh()->vence_en->toDateString());
    }

    public function test_un_pin_sin_activacion_previa_pide_el_codigo_de_activacion(): void
    {
        $this->activar('AAAA-BBBB-CCCC-DDDD-EEEE')->assertSessionHas('error');
    }

    public function test_la_licencia_vitalicia_no_vence(): void
    {
        config(['licencia.clave_privada' => $this->privada]);
        $emisor = app(EmisorLicencias::class);
        $emitida = $emisor->emitir('Clínica Vitalicia', LicenciaService::TIPO_COMPLETA, $emisor->diaFinDesde(vitalicia: true));
        config(['licencia.clave_privada' => null]);

        $this->activar($emitida->ultimo_codigo)->assertRedirect('/admin/home');

        $this->travel(15)->years();

        $this->assertTrue(Licencia::actual()->fresh()->estaVigente());
        $this->actingAs($this->admin)->get('/admin/home')->assertOk();
    }

    public function test_cerca_del_vencimiento_aparece_el_aviso_en_el_panel(): void
    {
        $emitida = $this->emitir('Clínica Sonrisa');
        $this->activar($emitida->ultimo_codigo);

        $this->travel(5)->days();

        $this->actingAs($this->admin)->get('/admin/home')
            ->assertOk()
            ->assertSee('Tu licencia vence en 2 días');
    }

    public function test_la_instalacion_del_proveedor_nunca_se_bloquea_y_puede_emitir_desde_el_panel(): void
    {
        config(['licencia.clave_privada' => $this->privada]);

        $this->actingAs($this->admin)->get('/admin/home')->assertOk();

        $this->actingAs($this->admin)->post('/admin/licencias', [
            'cliente' => 'Clínica del Valle',
            'contacto' => '70000000',
            'tipo' => 'PRUEBA',
            'duracion' => 'dias',
            'dias' => 7,
        ])->assertRedirect('/admin/licencias')->assertSessionHas('codigo_emitido');

        $emitida = LicenciaEmitida::first();
        $this->assertSame('Clínica del Valle', $emitida->cliente);
        $this->assertNotEmpty($emitida->ultimo_codigo);

        $this->actingAs($this->admin)->get('/admin/licencias')->assertOk()->assertSee('Clínica del Valle');
    }

    public function test_sin_clave_privada_el_panel_de_emision_no_existe(): void
    {
        $emitida = $this->emitir('Clínica Sonrisa');
        $this->activar($emitida->ultimo_codigo);

        $this->actingAs($this->admin)->get('/admin/licencias')->assertNotFound();
    }

    public function test_con_la_verificacion_apagada_no_se_bloquea_nada(): void
    {
        config(['licencia.activa' => false]);

        $this->actingAs($this->admin)->get('/admin/home')->assertOk();
    }
}
