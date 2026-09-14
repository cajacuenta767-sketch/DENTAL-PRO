<?php

namespace Tests\Feature;

use App\Models\Pago;
use Tests\CasoClinico;

class ReporteCsvTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin);
    }

    public function test_el_reporte_financiero_se_exporta_a_csv(): void
    {
        $this->post('/admin/pagos', [
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'metodo_pago' => 'EFECTIVO',
            'monto_pagado' => 180,
            'fecha_pago' => now()->format('Y-m-d\TH:i'),
            'detalles' => [[
                'tratamiento_id' => $this->tratamiento->id,
                'descripcion' => 'PROFILAXIS DENTAL',
                'cantidad' => 1,
                'precio_unitario' => 180,
            ]],
        ])->assertRedirect();

        $pago = Pago::firstOrFail();

        $respuesta = $this->get('/admin/reportes/exportar/financiero/csv')->assertOk();

        $this->assertStringContainsString('text/csv', (string) $respuesta->headers->get('content-type'));

        $contenido = $respuesta->streamedContent();

        $this->assertStringContainsString('Recibo;Fecha', $contenido);
        $this->assertStringContainsString($pago->codigo_recibo, $contenido);
    }

    public function test_una_seccion_desconocida_no_existe(): void
    {
        $this->get('/admin/reportes/exportar/inventada/csv')->assertNotFound();
    }
}
