<?php

namespace Tests\Feature;

use App\Models\Cita;
use Illuminate\Support\Facades\Mail;
use Tests\CasoClinico;

class AgendaSemanaTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->actingAs($this->admin);
    }

    private function crearCita(array $extra = []): Cita
    {
        return Cita::create(array_merge([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => $this->proximaFecha(),
            'hora' => '09:00',
            'estado' => 'CONFIRMADA',
            'origen' => 'RECEPCION',
        ], $extra));
    }

    public function test_la_agenda_semanal_muestra_las_citas_de_la_semana(): void
    {
        $cita = $this->crearCita();

        $this->get('/admin/agenda/semana?'.http_build_query(['fecha' => $this->proximaFecha(), 'doctor_id' => $this->doctor->id]))
            ->assertOk()
            ->assertSee($this->paciente->nombre_completo)
            ->assertSee($this->tratamiento->nombre)
            ->assertSee('data-id="'.$cita->id.'"', false)
            ->assertSee('draggable="true"', false);
    }

    public function test_la_agenda_mensual_responde_con_el_conteo_del_dia(): void
    {
        $this->crearCita();

        $this->get('/admin/agenda/mes?'.http_build_query(['fecha' => $this->proximaFecha()]))
            ->assertOk()
            ->assertSee($this->paciente->nombre_completo);
    }

    public function test_reprogramar_mueve_la_cita_y_deja_rastro(): void
    {
        $cita = $this->crearCita();
        $nuevaFecha = $this->proximaFecha(4);

        $this->patchJson("/admin/citas/{$cita->id}/reprogramar", ['fecha' => $nuevaFecha, 'hora' => '10:30'])
            ->assertOk()
            ->assertJson(['ok' => true, 'fecha' => $nuevaFecha, 'hora' => '10:30']);

        $cita->refresh();

        $this->assertSame($nuevaFecha, $cita->fecha->toDateString());
        $this->assertSame('10:30', substr($cita->hora, 0, 5));
        $this->assertStringContainsString('Reprogramada de', $cita->observacion);
        $this->assertStringContainsString($this->admin->nombre, $cita->observacion);
        $this->assertDatabaseHas('auditorias', ['modelo' => 'Cita', 'modelo_id' => $cita->id, 'accion' => 'ACTUALIZAR']);
    }

    public function test_reprogramar_rechaza_un_cupo_ocupado(): void
    {
        $this->crearCita(['hora' => '09:00']);
        $otra = $this->crearCita(['hora' => '11:00']);

        $this->patchJson("/admin/citas/{$otra->id}/reprogramar", ['fecha' => $this->proximaFecha(), 'hora' => '09:00'])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertSame('11:00', substr($otra->fresh()->hora, 0, 5));
    }

    public function test_reprogramar_rechaza_una_hora_fuera_del_turno(): void
    {
        $cita = $this->crearCita();

        $this->patchJson("/admin/citas/{$cita->id}/reprogramar", ['fecha' => $this->proximaFecha(), 'hora' => '15:00'])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertSame('09:00', substr($cita->fresh()->hora, 0, 5));
    }

    public function test_una_cita_completada_no_se_puede_reprogramar(): void
    {
        $cita = $this->crearCita(['estado' => 'COMPLETADA']);

        $this->patchJson("/admin/citas/{$cita->id}/reprogramar", ['fecha' => $this->proximaFecha(5), 'hora' => '10:00'])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertSame($this->proximaFecha(), $cita->fresh()->fecha->toDateString());
    }
}
