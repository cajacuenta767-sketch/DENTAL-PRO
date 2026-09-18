<?php

namespace Tests\Feature;

use App\Models\Aseguradora;
use App\Models\Pago;
use App\Models\Presupuesto;
use Tests\CasoClinico;

class PresupuestoCobroTest extends CasoClinico
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
            'estado' => 'BORRADOR',
            'validez_dias' => 30,
            'fecha' => now()->toDateString(),
            'descuento' => 0,
            'detalles' => [
                [
                    'tratamiento_id' => $this->tratamiento->id,
                    'pieza_dental' => '16',
                    'cara' => 'oclusal',
                    'descripcion' => 'PROFILAXIS DENTAL',
                    'cantidad' => 1,
                    'precio_unitario' => 180,
                ],
            ],
        ], $extra);
    }

    /** Crea un presupuesto y lo deja aprobado; devuelve el modelo fresco. */
    private function presupuestoAprobado(array $extra = []): Presupuesto
    {
        $this->post('/admin/presupuestos', $this->datos(array_merge(['estado' => 'PRESENTADO'], $extra)));
        $presupuesto = Presupuesto::orderByDesc('id')->first();

        $this->patch("/admin/presupuestos/{$presupuesto->id}/estado", ['estado' => 'APROBADO']);

        return $presupuesto->fresh();
    }

    public function test_facturar_dos_veces_crea_un_solo_recibo(): void
    {
        $presupuesto = $this->presupuestoAprobado();
        $detalle = $presupuesto->detalles()->first();
        $this->patch("/admin/presupuesto-detalles/{$detalle->id}/ejecutar", ['estado' => 'EJECUTADO']);

        $this->post("/admin/presupuestos/{$presupuesto->id}/facturar")->assertRedirect();

        $this->from("/admin/presupuestos/{$presupuesto->id}")
            ->post("/admin/presupuestos/{$presupuesto->id}/facturar")
            ->assertSessionHasErrors('detalles');

        $this->assertSame(1, Pago::count());
        $this->assertSame(Pago::first()->id, $detalle->fresh()->pago_id, 'La línea ejecutada queda ligada a su recibo.');
    }

    public function test_el_tope_anual_se_acumula_entre_presupuestos(): void
    {
        $this->aseguradora->update(['tope_anual' => 100, 'porcentaje_cobertura' => 50]);
        $this->paciente->update(['aseguradora_id' => $this->aseguradora->id]);

        $primero = $this->presupuestoAprobado();
        $this->assertSame('90.00', $primero->cobertura_seguro);

        $segundo = $this->presupuestoAprobado();

        $this->assertSame('10.00', $segundo->cobertura_seguro, 'Solo quedan 10 del tope anual.');
        $this->assertSame('170.00', $segundo->total);
    }

    public function test_cambiar_de_aseguradora_no_altera_un_presupuesto_aprobado(): void
    {
        $this->paciente->update(['aseguradora_id' => $this->aseguradora->id]);

        $presupuesto = $this->presupuestoAprobado();
        $this->assertSame('90.00', $presupuesto->cobertura_seguro);

        $otra = Aseguradora::create([
            'nombre' => 'SEGURO GENEROSO',
            'tipo' => 'PRIVADA',
            'porcentaje_cobertura' => 90,
            'activo' => true,
        ]);
        $this->paciente->update(['aseguradora_id' => $otra->id]);

        $presupuesto->fresh()->recalcular();

        $this->assertSame('90.00', $presupuesto->fresh()->cobertura_seguro);
        $this->assertSame('90.00', $presupuesto->fresh()->total);
        $this->assertSame($this->aseguradora->id, $presupuesto->fresh()->aseguradora_id);
    }

    public function test_la_maquina_de_estados_rechaza_saltos_no_permitidos(): void
    {
        $this->post('/admin/presupuestos', $this->datos());
        $borrador = Presupuesto::first();

        $this->patch("/admin/presupuestos/{$borrador->id}/estado", ['estado' => 'COMPLETADO'])
            ->assertSessionHas('error');
        $this->assertSame('BORRADOR', $borrador->fresh()->estado);

        $aprobado = $this->presupuestoAprobado();

        $this->patch("/admin/presupuestos/{$aprobado->id}/estado", ['estado' => 'BORRADOR'])
            ->assertSessionHas('error');
        $this->assertSame('APROBADO', $aprobado->fresh()->estado);
    }

    public function test_un_recibo_con_dinero_cobrado_no_se_elimina(): void
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

        $pago = Pago::first();

        $this->delete("/admin/pagos/{$pago->id}")->assertSessionHas('error');

        $this->assertNotNull(Pago::find($pago->id));
        $this->assertSame(1, Pago::count());
    }
}
