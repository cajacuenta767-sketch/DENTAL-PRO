<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Tratamiento;
use Illuminate\Support\Facades\Mail;
use Tests\CasoClinico;

class AgendaDuracionTest extends CasoClinico
{
    private Tratamiento $endodoncia;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->actingAs($this->admin);

        $this->endodoncia = Tratamiento::create([
            'especialidad_id' => $this->tratamiento->especialidad_id,
            'nombre' => 'ENDODONCIA MULTIRRADICULAR',
            'precio' => 900,
            'duracion' => 90,
            'activo' => true,
        ]);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => $this->proximaFecha(),
            'hora' => '09:00',
            'estado' => 'CONFIRMADA',
            'origen' => 'RECEPCION',
        ], $extra);
    }

    private function horasLibres(array $parametros = []): array
    {
        return $this->getJson('/admin/citas/horas-disponibles/consultar?'.http_build_query(array_merge([
            'doctor_id' => $this->doctor->id,
            'fecha' => $this->proximaFecha(),
        ], $parametros)))->assertOk()->json('horas');
    }

    public function test_un_tratamiento_largo_bloquea_todos_sus_cupos(): void
    {
        $this->post('/admin/citas', $this->datos(['tratamiento_id' => $this->endodoncia->id]))->assertRedirect();

        $horas = $this->horasLibres();

        $this->assertNotContains('09:00', $horas);
        $this->assertNotContains('09:30', $horas);
        $this->assertNotContains('10:00', $horas);
        $this->assertContains('08:00', $horas);
        $this->assertContains('10:30', $horas);
    }

    public function test_no_se_agenda_dentro_de_una_cita_larga_en_curso(): void
    {
        $this->post('/admin/citas', $this->datos(['tratamiento_id' => $this->endodoncia->id]));

        $this->from('/admin/citas/create')
            ->post('/admin/citas', $this->datos(['hora' => '09:30']))
            ->assertSessionHasErrors('hora');

        $this->assertSame(1, Cita::count());
    }

    public function test_un_tratamiento_que_no_cabe_antes_del_cierre_se_rechaza(): void
    {
        $this->from('/admin/citas/create')
            ->post('/admin/citas', $this->datos(['tratamiento_id' => $this->endodoncia->id, 'hora' => '11:00']))
            ->assertSessionHasErrors('hora');

        $this->assertSame(0, Cita::count());
    }

    public function test_las_horas_disponibles_descartan_los_cupos_donde_no_cabe_la_duracion(): void
    {
        $horas = $this->horasLibres(['tratamiento_id' => $this->endodoncia->id]);

        $this->assertContains('10:30', $horas);
        $this->assertNotContains('11:00', $horas);
        $this->assertNotContains('11:30', $horas);
    }

    public function test_un_cupo_cancelado_se_vuelve_a_ocupar_sin_error(): void
    {
        $this->post('/admin/citas', $this->datos(['hora' => '10:00']));
        $cancelada = Cita::first();

        $this->patch("/admin/citas/{$cancelada->id}/estado", ['estado' => 'CANCELADA']);
        $this->assertSame('CANCELADA', $cancelada->fresh()->estado);

        $this->post('/admin/citas', $this->datos(['hora' => '10:00']))->assertRedirect();

        $this->assertSame(2, Cita::count());
        $this->assertSame(1, Cita::vigentes()->where('hora', 'like', '10:00%')->count());
    }

    public function test_no_se_reactiva_una_cancelada_si_el_cupo_ya_fue_tomado(): void
    {
        $this->post('/admin/citas', $this->datos(['hora' => '10:00']));
        $cancelada = Cita::first();
        $this->patch("/admin/citas/{$cancelada->id}/estado", ['estado' => 'CANCELADA']);

        $this->post('/admin/citas', $this->datos(['hora' => '10:00']))->assertRedirect();

        $this->patch("/admin/citas/{$cancelada->id}/estado", ['estado' => 'CONFIRMADA'])
            ->assertSessionHas('error');

        $this->assertSame('CANCELADA', $cancelada->fresh()->estado);
    }
}
