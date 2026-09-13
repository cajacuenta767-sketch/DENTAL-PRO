<?php

namespace Tests\Feature;

use Tests\CasoClinico;

class ReporteTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin);

        $this->post('/admin/pagos', [
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'metodo_pago' => 'TARJETA',
            'monto_pagado' => 100,
            'fecha_pago' => now()->format('Y-m-d\TH:i'),
            'detalles' => [[
                'tratamiento_id' => $this->tratamiento->id,
                'descripcion' => 'PROFILAXIS DENTAL',
                'cantidad' => 1,
                'precio_unitario' => 180,
            ]],
        ]);
    }

    public function test_el_centro_de_reportes_muestra_las_cuatro_secciones(): void
    {
        $this->get('/admin/reportes')
            ->assertOk()
            ->assertSee('Financiero')
            ->assertSee('Productividad')
            ->assertSee('Padrón de Pacientes')
            ->assertSee('Rentabilidad');
    }

    public function test_el_paciente_con_saldo_aparece_en_el_padron(): void
    {
        $this->get('/admin/reportes')
            ->assertOk()
            ->assertSee($this->paciente->nombre_completo);
    }

    /** @dataProvider secciones */
    public function test_cada_seccion_se_exporta_a_pdf(string $seccion): void
    {
        $respuesta = $this->get("/admin/reportes/exportar/{$seccion}")->assertOk();

        $this->assertSame('application/pdf', $respuesta->headers->get('content-type'));
    }

    public static function secciones(): array
    {
        return [['financiero'], ['citas'], ['padron'], ['tratamientos']];
    }
}
