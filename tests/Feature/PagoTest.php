<?php

namespace Tests\Feature;

use App\Models\Pago;
use Tests\CasoClinico;

class PagoTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'metodo_pago' => 'EFECTIVO',
            'monto_pagado' => 360,
            'fecha_pago' => now()->format('Y-m-d\TH:i'),
            'detalles' => [[
                'tratamiento_id' => $this->tratamiento->id,
                'descripcion' => 'PROFILAXIS DENTAL',
                'cantidad' => 2,
                'precio_unitario' => 180,
            ]],
        ], $extra);
    }

    public function test_una_linea_sin_tratamiento_se_cobra_igual(): void
    {
        // «tratamiento_id» es opcional: una línea libre que no lo envía debe
        // cobrarse igual, no fallar con «Undefined array key».
        $datos = $this->datos([
            'monto_pagado' => 200,
            'detalles' => [[
                'descripcion' => 'CONSULTA DE URGENCIA',
                'cantidad' => 1,
                'precio_unitario' => 200,
            ]],
        ]);

        $this->post('/admin/pagos', $datos)->assertRedirect();

        $pago = Pago::first();

        $this->assertSame('200.00', $pago->monto_total);
        $this->assertNull($pago->detalles()->sole()->tratamiento_id);
    }

    public function test_un_cobro_completo_queda_sin_saldo(): void
    {
        $this->post('/admin/pagos', $this->datos())->assertRedirect();

        $pago = Pago::first();

        $this->assertSame('360.00', $pago->monto_total);
        $this->assertSame('0.00', $pago->monto_saldo);
        $this->assertSame('COMPLETADO', $pago->estado);
        $this->assertStringStartsWith('REC-'.now()->year, $pago->codigo_recibo);
    }

    public function test_un_cobro_parcial_deja_saldo_pendiente(): void
    {
        $this->post('/admin/pagos', $this->datos(['monto_pagado' => 200]));

        $pago = Pago::first();

        $this->assertSame('160.00', $pago->monto_saldo);
        $this->assertSame('PARCIAL', $pago->estado);
    }

    public function test_un_recibo_sin_lineas_no_se_emite(): void
    {
        $this->from('/admin/pagos/create')
            ->post('/admin/pagos', $this->datos(['detalles' => []]))
            ->assertSessionHasErrors('detalles');

        $this->assertSame(0, Pago::count());
    }

    public function test_anular_saca_el_recibo_de_los_totales_pero_lo_conserva(): void
    {
        $this->post('/admin/pagos', $this->datos());
        $pago = Pago::first();

        $this->patch("/admin/pagos/{$pago->id}/anular", ['motivo' => 'Error de digitación']);

        $this->assertSame('ANULADO', $pago->fresh()->estado);
        $this->assertSame(0, Pago::vigentes()->count());
        $this->assertSame(1, Pago::count());
    }

    public function test_los_correlativos_de_recibo_no_se_repiten(): void
    {
        $this->post('/admin/pagos', $this->datos());
        $this->post('/admin/pagos', $this->datos());

        $codigos = Pago::pluck('codigo_recibo');

        $this->assertSame(2, $codigos->unique()->count());
    }
}
