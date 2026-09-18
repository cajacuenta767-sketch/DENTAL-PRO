<?php

namespace Tests\Feature;

use App\Models\Presupuesto;
use Tests\CasoClinico;

class PresupuestoSesionesTest extends CasoClinico
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
                    'sesion' => 1,
                ],
                [
                    'tratamiento_id' => null,
                    'pieza_dental' => '26',
                    'cara' => null,
                    'descripcion' => 'OBTURACIÓN SIMPLE',
                    'cantidad' => 1,
                    'precio_unitario' => 200,
                    'sesion' => 2,
                ],
                [
                    'tratamiento_id' => null,
                    'pieza_dental' => null,
                    'cara' => null,
                    'descripcion' => 'CONTROL FINAL',
                    'cantidad' => 1,
                    'precio_unitario' => 0,
                ],
            ],
        ], $extra);
    }

    public function test_guarda_las_lineas_con_sesiones_distintas(): void
    {
        $this->post('/admin/presupuestos', $this->datos())->assertRedirect();

        $sesiones = Presupuesto::first()->detalles->pluck('sesion', 'descripcion');

        $this->assertSame(1, $sesiones['PROFILAXIS DENTAL']);
        $this->assertSame(2, $sesiones['OBTURACIÓN SIMPLE']);
        $this->assertSame(1, $sesiones['CONTROL FINAL'], 'Sin sesión indicada se asume la primera.');
    }

    public function test_rechaza_una_sesion_fuera_de_rango(): void
    {
        $datos = $this->datos();
        $datos['detalles'][0]['sesion'] = 99;

        $this->from('/admin/presupuestos/nuevo')
            ->post('/admin/presupuestos', $datos)
            ->assertRedirect('/admin/presupuestos/nuevo')
            ->assertSessionHasErrors('detalles.0.sesion');

        $this->assertSame(0, Presupuesto::count());
    }

    public function test_reasigna_las_sesiones_del_plan(): void
    {
        $this->post('/admin/presupuestos', $this->datos());
        $presupuesto = Presupuesto::first();
        [$primera, $segunda, $tercera] = $presupuesto->detalles;

        $this->patch("/admin/presupuestos/{$presupuesto->id}/sesiones", [
            'sesiones' => [$segunda->id => 3, $tercera->id => 3],
        ])->assertRedirect();

        $this->assertSame(1, $primera->fresh()->sesion);
        $this->assertSame(3, $segunda->fresh()->sesion);
        $this->assertSame(3, $tercera->fresh()->sesion);

        $this->patchJson("/admin/presupuestos/{$presupuesto->id}/sesiones", [
            'sesiones' => [$primera->id => 2],
        ])->assertOk()->assertJson(['ok' => true, 'actualizadas' => 1]);

        $this->assertSame(2, $primera->fresh()->sesion);
    }

    public function test_no_toca_lineas_de_otro_presupuesto_ni_acepta_valores_invalidos(): void
    {
        $this->post('/admin/presupuestos', $this->datos());
        $this->post('/admin/presupuestos', $this->datos());
        [$uno, $dos] = Presupuesto::orderBy('id')->get();
        $ajena = $dos->detalles->first();

        $this->patchJson("/admin/presupuestos/{$uno->id}/sesiones", [
            'sesiones' => [$ajena->id => 4],
        ])->assertOk()->assertJson(['actualizadas' => 0]);

        $this->assertSame(1, $ajena->fresh()->sesion, 'Una línea de otro presupuesto no se modifica.');

        $this->patchJson("/admin/presupuestos/{$uno->id}/sesiones", [
            'sesiones' => [$uno->detalles->first()->id => 51],
        ])->assertUnprocessable();
    }

    public function test_agendar_una_sesion_lleva_a_la_agenda_con_los_datos_del_plan(): void
    {
        $this->post('/admin/presupuestos', $this->datos(['estado' => 'PRESENTADO']));
        $presupuesto = Presupuesto::first();
        $this->patch("/admin/presupuestos/{$presupuesto->id}/estado", ['estado' => 'APROBADO']);

        $respuesta = $this->get("/admin/presupuestos/{$presupuesto->id}/agendar-sesion/1")
            ->assertRedirect();

        $destino = $respuesta->headers->get('Location');
        parse_str(parse_url($destino, PHP_URL_QUERY) ?? '', $query);

        $this->assertStringStartsWith(route('admin.citas.create'), $destino);
        $this->assertSame((string) $this->paciente->id, $query['paciente_id']);
        $this->assertSame((string) $this->doctor->id, $query['doctor_id']);
        $this->assertSame((string) $this->tratamiento->id, $query['tratamiento_id']);
        $this->assertStringContainsString("Sesión 1 del presupuesto {$presupuesto->codigo}", $query['motivo']);
        $this->assertStringContainsString('PROFILAXIS DENTAL', $query['motivo']);
        $this->assertStringContainsString('CONTROL FINAL', $query['motivo']);
        $this->assertStringNotContainsString('OBTURACIÓN', $query['motivo'], 'La sesión 2 no forma parte del motivo.');
    }

    public function test_una_sesion_sin_pendientes_no_se_agenda(): void
    {
        $this->post('/admin/presupuestos', $this->datos());
        $presupuesto = Presupuesto::first();

        $this->from("/admin/presupuestos/{$presupuesto->id}")
            ->get("/admin/presupuestos/{$presupuesto->id}/agendar-sesion/7")
            ->assertRedirect("/admin/presupuestos/{$presupuesto->id}")
            ->assertSessionHas('aviso');
    }

    public function test_la_ficha_agrupa_las_lineas_por_sesion(): void
    {
        $this->post('/admin/presupuestos', $this->datos(['estado' => 'PRESENTADO']));
        $presupuesto = Presupuesto::first();
        $this->patch("/admin/presupuestos/{$presupuesto->id}/estado", ['estado' => 'APROBADO']);

        $this->get("/admin/presupuestos/{$presupuesto->id}")
            ->assertOk()
            ->assertSeeInOrder(['Sesión 1', 'PROFILAXIS DENTAL', 'Sesión 2', 'OBTURACIÓN SIMPLE'])
            ->assertSee('Agendar sesión')
            ->assertSee("/admin/presupuestos/{$presupuesto->id}/agendar-sesion/2");
    }
}
