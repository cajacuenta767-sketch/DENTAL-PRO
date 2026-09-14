<?php

namespace Tests\Feature;

use Tests\CasoClinico;

class ComisionesTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin);

        $this->doctor->update(['porcentaje_comision' => 10]);

        $this->post('/admin/pagos', [
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'metodo_pago' => 'EFECTIVO',
            'monto_pagado' => 200,
            'fecha_pago' => now()->format('Y-m-d\TH:i'),
            'detalles' => [[
                'tratamiento_id' => $this->tratamiento->id,
                'descripcion' => 'PROFILAXIS DENTAL',
                'cantidad' => 1,
                'precio_unitario' => 200,
            ]],
        ])->assertRedirect();
    }

    public function test_la_seccion_de_comisiones_calcula_la_comision_del_doctor(): void
    {
        $this->get('/admin/reportes')
            ->assertOk()
            ->assertSee('Comisiones por Doctor')
            ->assertSee($this->doctor->nombre_profesional)
            ->assertSee('10.00 %')
            ->assertSee('20.00');
    }

    public function test_la_comision_se_exporta_a_csv(): void
    {
        $respuesta = $this->get('/admin/reportes/exportar/comisiones/csv')->assertOk();
        $contenido = $respuesta->streamedContent();

        $this->assertStringContainsString('Doctor;Especialidad;Recibos;Cobrado;"% comisión";Comisión', $contenido);
        $this->assertStringContainsString('"Dra. Sofía Arancibia";"ODONTOLOGÍA GENERAL";1;200.00;10.00;20.00', $contenido);
    }

    public function test_la_comision_se_exporta_a_pdf(): void
    {
        $respuesta = $this->get('/admin/reportes/exportar/comisiones')->assertOk();

        $this->assertSame('application/pdf', $respuesta->headers->get('content-type'));
    }

    public function test_el_porcentaje_se_valida_entre_0_y_100(): void
    {
        $this->from("/admin/doctores/{$this->doctor->id}/edit")
            ->put("/admin/doctores/{$this->doctor->id}", array_merge($this->doctor->only([
                'especialidad_id', 'nombres', 'apellidos', 'tipo_documento', 'numero_documento', 'genero',
            ]), ['porcentaje_comision' => 150, 'activo' => 1]))
            ->assertSessionHasErrors('porcentaje_comision');

        $this->put("/admin/doctores/{$this->doctor->id}", array_merge($this->doctor->only([
            'especialidad_id', 'nombres', 'apellidos', 'tipo_documento', 'numero_documento', 'genero',
        ]), ['porcentaje_comision' => 12.5, 'activo' => 1]))->assertRedirect();

        $this->assertSame('12.50', $this->doctor->fresh()->porcentaje_comision);

        $this->get("/admin/doctores/{$this->doctor->id}")->assertOk()->assertSee('12.50 %');
    }
}
