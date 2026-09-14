<?php

namespace Tests\Feature;

use App\Models\CierreCaja;
use App\Models\Pago;
use Illuminate\Testing\TestResponse;
use Tests\CasoClinico;

class CajaTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin);

        $this->cobrar('EFECTIVO', 100);
        $this->cobrar('TARJETA', 50);
    }

    private function cobrar(string $metodo, float $monto): void
    {
        $this->post('/admin/pagos', [
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'metodo_pago' => $metodo,
            'monto_pagado' => $monto,
            'fecha_pago' => now()->format('Y-m-d\TH:i'),
            'detalles' => [[
                'tratamiento_id' => $this->tratamiento->id,
                'descripcion' => 'PROFILAXIS DENTAL',
                'cantidad' => 1,
                'precio_unitario' => $monto,
            ]],
        ])->assertRedirect();
    }

    private function cerrar(float $contado = 90, float $fondo = 0): TestResponse
    {
        return $this->post('/admin/caja/cerrar', [
            'fecha' => now()->toDateString(),
            'fondo_inicial' => $fondo,
            'efectivo_contado' => $contado,
            'observaciones' => 'Arqueo de prueba',
        ]);
    }

    public function test_el_arqueo_muestra_el_efectivo_del_dia(): void
    {
        $this->assertSame(2, Pago::vigentes()->count());

        $this->get('/admin/caja/arqueo')
            ->assertOk()
            ->assertSee('Arqueo de caja')
            ->assertSee('data-metodo="EFECTIVO">100.00', false)
            ->assertSee('data-metodo="TARJETA">50.00', false)
            ->assertSee('150.00');
    }

    public function test_cerrar_crea_el_cierre_con_la_diferencia(): void
    {
        $this->cerrar(contado: 90)->assertRedirect();

        $cierre = CierreCaja::firstOrFail();

        $this->assertSame('0.00', $cierre->fondo_inicial);
        $this->assertSame('100.00', $cierre->efectivo_esperado);
        $this->assertSame('90.00', $cierre->efectivo_contado);
        $this->assertSame('-10.00', $cierre->diferencia);
        $this->assertSame('150.00', $cierre->total_cobrado);
        $this->assertSame(2, $cierre->recibos);
        $this->assertSame('CERRADO', $cierre->estado);
        $this->assertNotNull($cierre->cerrado_en);
        $this->assertSame($this->admin->id, $cierre->usuario_id);
        $this->assertSame(100.0, (float) $cierre->totales['por_metodo']['EFECTIVO']);
        $this->assertSame(50.0, (float) $cierre->totales['por_metodo']['TARJETA']);
        $this->assertSame(150.0, (float) $cierre->totales['por_cajero'][$this->admin->nombre]);

        $this->assertDatabaseHas('auditorias', ['accion' => 'CERRAR']);
    }

    public function test_el_fondo_inicial_suma_al_efectivo_esperado(): void
    {
        $this->cerrar(contado: 150, fondo: 50);

        $cierre = CierreCaja::firstOrFail();

        $this->assertSame('150.00', $cierre->efectivo_esperado);
        $this->assertSame('0.00', $cierre->diferencia);
    }

    public function test_no_se_puede_cerrar_dos_veces_el_mismo_dia(): void
    {
        $this->cerrar(contado: 100);
        $this->from('/admin/caja/arqueo')->cerrar(contado: 100)
            ->assertRedirect('/admin/caja/arqueo')
            ->assertSessionHas('error');

        $this->assertSame(1, CierreCaja::count());
    }

    public function test_el_listado_muestra_el_kpi_del_dia_y_los_cierres(): void
    {
        $this->get('/admin/caja')->assertOk()->assertSee('Arquear y cerrar hoy')->assertSee('150.00');

        $this->cerrar(contado: 90);

        $this->get('/admin/caja')->assertOk()->assertSee('Ver cierre de hoy')->assertSee('-10.00');
    }

    public function test_el_detalle_y_el_pdf_del_cierre_responden(): void
    {
        $this->cerrar(contado: 90);
        $cierre = CierreCaja::firstOrFail();

        $this->get("/admin/caja/{$cierre->id}")
            ->assertOk()
            ->assertSee('Cierre de caja')
            ->assertSee('-10.00');

        $respuesta = $this->get("/admin/caja/{$cierre->id}/pdf")->assertOk();
        $this->assertSame('application/pdf', $respuesta->headers->get('content-type'));
    }
}
