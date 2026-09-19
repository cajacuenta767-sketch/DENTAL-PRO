<?php

namespace Tests\Feature;

use App\Models\Pago;
use App\Models\Presupuesto;
use Tests\CasoClinico;

class PresupuestoTest extends CasoClinico
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
                    'cantidad' => 2,
                    'precio_unitario' => 180,
                ],
            ],
        ], $extra);
    }

    public function test_una_linea_sin_los_campos_opcionales_se_guarda(): void
    {
        // Los campos de pieza, cara y tratamiento son «nullable», así que una
        // línea que directamente no los envía debe guardarse igual y no
        // reventar con «Undefined array key».
        $datos = $this->datos([
            'detalles' => [
                [
                    'descripcion' => 'CONSULTA DE URGENCIA',
                    'cantidad' => 1,
                    'precio_unitario' => 200,
                ],
            ],
        ]);

        $this->post('/admin/presupuestos', $datos)->assertRedirect();

        $detalle = Presupuesto::first()->detalles()->sole();

        $this->assertNull($detalle->tratamiento_id);
        $this->assertNull($detalle->pieza_dental);
        $this->assertNull($detalle->cara);
        $this->assertSame('200.00', $detalle->subtotal);
    }

    public function test_un_presupuesto_sin_seguro_cobra_el_total(): void
    {
        $this->post('/admin/presupuestos', $this->datos())->assertRedirect();

        $presupuesto = Presupuesto::first();

        $this->assertSame('360.00', $presupuesto->subtotal);
        $this->assertSame('0.00', $presupuesto->cobertura_seguro);
        $this->assertSame('360.00', $presupuesto->total);
        $this->assertStringStartsWith('PRE-'.now()->year, $presupuesto->codigo);
    }

    public function test_la_obra_social_descuenta_su_porcentaje(): void
    {
        $this->paciente->update(['aseguradora_id' => $this->aseguradora->id]);

        $this->post('/admin/presupuestos', $this->datos());

        $presupuesto = Presupuesto::first();

        $this->assertSame('180.00', $presupuesto->cobertura_seguro, 'El seguro cubre el 50%.');
        $this->assertSame('180.00', $presupuesto->total);
    }

    public function test_el_descuento_se_aplica_antes_que_la_cobertura(): void
    {
        $this->paciente->update(['aseguradora_id' => $this->aseguradora->id]);

        $this->post('/admin/presupuestos', $this->datos(['descuento' => 60]));

        $presupuesto = Presupuesto::first();

        // (360 − 60) × 50% = 150 de cobertura, quedan 150 por pagar.
        $this->assertSame('150.00', $presupuesto->cobertura_seguro);
        $this->assertSame('150.00', $presupuesto->total);
    }

    public function test_el_tope_anual_limita_la_cobertura(): void
    {
        $this->aseguradora->update(['porcentaje_cobertura' => 80, 'tope_anual' => 100]);
        $this->paciente->update(['aseguradora_id' => $this->aseguradora->id]);

        $this->post('/admin/presupuestos', $this->datos());

        $this->assertSame('100.00', Presupuesto::first()->cobertura_seguro);
    }

    public function test_ejecutar_todas_las_lineas_completa_el_presupuesto(): void
    {
        $this->post('/admin/presupuestos', $this->datos(['estado' => 'PRESENTADO']));
        $presupuesto = Presupuesto::first();

        $this->patch("/admin/presupuestos/{$presupuesto->id}/estado", ['estado' => 'APROBADO']);

        $detalle = $presupuesto->detalles()->first();
        $this->patch("/admin/presupuesto-detalles/{$detalle->id}/ejecutar", ['estado' => 'EJECUTADO']);

        $this->assertSame('COMPLETADO', $presupuesto->fresh()->estado);
        $this->assertSame(100, $presupuesto->fresh()->avance);
    }

    public function test_no_se_ejecuta_una_linea_de_un_presupuesto_sin_aprobar(): void
    {
        $this->post('/admin/presupuestos', $this->datos());
        $detalle = Presupuesto::first()->detalles()->first();

        $this->from('/admin/presupuestos')
            ->patch("/admin/presupuesto-detalles/{$detalle->id}/ejecutar", ['estado' => 'EJECUTADO']);

        $this->assertSame('PENDIENTE', $detalle->fresh()->estado);
    }

    public function test_cobrar_genera_un_recibo_solo_con_lo_ejecutado(): void
    {
        $datos = $this->datos(['estado' => 'PRESENTADO']);
        $datos['detalles'][] = [
            'tratamiento_id' => $this->tratamiento->id,
            'pieza_dental' => '26',
            'cara' => null,
            'descripcion' => 'SEGUNDA PIEZA',
            'cantidad' => 1,
            'precio_unitario' => 180,
        ];

        $this->post('/admin/presupuestos', $datos);
        $presupuesto = Presupuesto::first();

        $this->patch("/admin/presupuestos/{$presupuesto->id}/estado", ['estado' => 'APROBADO']);

        $primera = $presupuesto->detalles()->first();
        $this->patch("/admin/presupuesto-detalles/{$primera->id}/ejecutar", ['estado' => 'EJECUTADO']);

        $this->post("/admin/presupuestos/{$presupuesto->id}/facturar")->assertRedirect();

        $pago = Pago::first();

        $this->assertNotNull($pago);
        $this->assertCount(1, $pago->detalles, 'Solo se cobra la línea ejecutada.');
        $this->assertSame('360.00', $pago->monto_total);
    }

    public function test_un_presupuesto_aprobado_ya_no_se_edita(): void
    {
        $this->post('/admin/presupuestos', $this->datos(['estado' => 'PRESENTADO']));
        $presupuesto = Presupuesto::first();

        $this->patch("/admin/presupuestos/{$presupuesto->id}/estado", ['estado' => 'APROBADO']);

        $this->assertFalse($presupuesto->fresh()->es_editable);
        $this->get("/admin/presupuestos/{$presupuesto->id}/edit")->assertForbidden();
    }
}
