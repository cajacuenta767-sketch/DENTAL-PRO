<?php

namespace Tests\Feature;

use App\Models\Aseguradora;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Insumo;
use App\Models\Tratamiento;
use Tests\CasoClinico;

/**
 * Catálogos de la clínica: especialidades, tratamientos, convenios,
 * horarios e insumos. Cubre el alta, la edición y las reglas que
 * protegen la integridad de los datos al eliminar.
 */
class CatalogoTest extends CasoClinico
{
    // --- Especialidades ----------------------------------------------------

    public function test_una_especialidad_se_registra_normalizada_en_mayusculas(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/especialidades', [
                'nombre' => 'Rehabilitación oral',
                'color' => '#0d9488',
                'activo' => 1,
            ])
            ->assertRedirect('/admin/especialidades');

        $this->assertDatabaseHas('especialidades', ['nombre' => 'REHABILITACIÓN ORAL']);
    }

    public function test_una_especialidad_no_se_duplica_por_diferencia_de_mayusculas(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/especialidades', ['nombre' => 'odontología general', 'color' => '#000000'])
            ->assertSessionHasErrors('nombre');

        $this->assertSame(1, Especialidad::where('nombre', 'ODONTOLOGÍA GENERAL')->count());
    }

    public function test_no_se_elimina_una_especialidad_con_tratamientos(): void
    {
        $especialidad = $this->tratamiento->especialidad;

        $this->actingAs($this->admin)
            ->from('/admin/especialidades')
            ->delete("/admin/especialidades/{$especialidad->id}")
            ->assertRedirect('/admin/especialidades')
            ->assertSessionHas('error');

        $this->assertModelExists($especialidad);
    }

    // --- Tratamientos ------------------------------------------------------

    public function test_un_tratamiento_se_registra_con_su_precio_y_duracion(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/tratamientos', [
                'especialidad_id' => $this->tratamiento->especialidad_id,
                'nombre' => 'Blanqueamiento',
                'precio' => 450.50,
                'duracion' => 60,
                'activo' => 1,
            ])
            ->assertRedirect('/admin/tratamientos');

        $tratamiento = Tratamiento::where('nombre', 'BLANQUEAMIENTO')->sole();

        $this->assertSame('450.50', $tratamiento->precio);
        $this->assertSame(60, $tratamiento->duracion);
    }

    public function test_un_tratamiento_rechaza_una_duracion_fuera_de_rango(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/tratamientos', [
                'especialidad_id' => $this->tratamiento->especialidad_id,
                'nombre' => 'Sesión exprés',
                'precio' => 100,
                'duracion' => 1,
            ])
            ->assertSessionHasErrors('duracion');
    }

    // --- Aseguradoras ------------------------------------------------------

    public function test_una_aseguradora_no_se_duplica_por_diferencia_de_mayusculas(): void
    {
        $this->actingAs($this->admin)->post('/admin/aseguradoras', [
            'nombre' => 'Seguros del Sur',
            'tipo' => 'PRIVADA',
            'porcentaje_cobertura' => 50,
            'activo' => 1,
        ])->assertRedirect();

        $this->actingAs($this->admin)->post('/admin/aseguradoras', [
            'nombre' => 'seguros del sur',
            'tipo' => 'PRIVADA',
            'porcentaje_cobertura' => 60,
        ])->assertSessionHasErrors('nombre');

        $this->assertSame(1, Aseguradora::where('nombre', 'SEGUROS DEL SUR')->count());
    }

    public function test_la_cobertura_de_una_aseguradora_no_pasa_de_cien(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/aseguradoras', [
                'nombre' => 'Cobertura Imposible',
                'tipo' => 'PRIVADA',
                'porcentaje_cobertura' => 140,
            ])
            ->assertSessionHasErrors('porcentaje_cobertura');
    }

    public function test_no_se_elimina_una_aseguradora_con_afiliados(): void
    {
        $this->paciente->update(['aseguradora_id' => $this->aseguradora->id]);

        $this->actingAs($this->admin)
            ->from('/admin/aseguradoras')
            ->delete("/admin/aseguradoras/{$this->aseguradora->id}")
            ->assertSessionHas('error');

        $this->assertModelExists($this->aseguradora);
    }

    // --- Horarios ----------------------------------------------------------

    public function test_un_horario_que_se_cruza_con_otro_del_mismo_doctor_se_rechaza(): void
    {
        $existente = $this->doctor->horarios()->firstOrFail();

        $this->actingAs($this->admin)
            ->from('/admin/horarios/create')
            ->post('/admin/horarios', [
                'doctor_id' => $this->doctor->id,
                'dia_semana' => $existente->dia_semana,
                'turno' => 'MAÑANA',
                'hora_inicio' => substr($existente->hora_inicio, 0, 5),
                'hora_fin' => substr($existente->hora_fin, 0, 5),
                'activo' => 1,
            ])
            ->assertRedirect('/admin/horarios/create')
            ->assertSessionHas('error');
    }

    public function test_un_horario_con_hora_fin_anterior_al_inicio_se_rechaza(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/horarios', [
                'doctor_id' => $this->doctor->id,
                'dia_semana' => 'DOMINGO',
                'turno' => 'MAÑANA',
                'hora_inicio' => '12:00',
                'hora_fin' => '09:00',
            ])
            ->assertSessionHasErrors('hora_fin');
    }

    public function test_un_turno_que_no_se_cruza_con_ninguno_se_registra(): void
    {
        // El doctor de pruebas atiende todos los días de 08:00 a 12:00:
        // la tarde queda libre y debe aceptarse.
        $this->actingAs($this->admin)
            ->post('/admin/horarios', [
                'doctor_id' => $this->doctor->id,
                'dia_semana' => 'DOMINGO',
                'turno' => 'TARDE',
                'hora_inicio' => '14:00',
                'hora_fin' => '18:00',
                'activo' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/horarios');

        $this->assertTrue(
            Horario::where('doctor_id', $this->doctor->id)->where('turno', 'TARDE')->exists()
        );
    }

    // --- Insumos -----------------------------------------------------------

    public function test_el_codigo_de_insumo_no_se_duplica_por_diferencia_de_mayusculas(): void
    {
        $alta = [
            'codigo' => 'ins-001',
            'nombre' => 'Guantes de nitrilo',
            'categoria' => Insumo::CATEGORIAS[0],
            'unidad_medida' => 'CAJA',
            'stock_minimo' => 5,
            'costo_unitario' => 30,
            'activo' => 1,
        ];

        $this->actingAs($this->admin)->post('/admin/inventario', $alta)->assertRedirect();

        $this->actingAs($this->admin)
            ->post('/admin/inventario', ['codigo' => 'INS-001'] + $alta)
            ->assertSessionHasErrors('codigo');

        $this->assertSame(1, Insumo::where('codigo', 'INS-001')->count());
    }
}
