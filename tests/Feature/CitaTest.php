<?php

namespace Tests\Feature;

use App\Mail\CitaConfirmacionMail;
use App\Models\Cita;
use Illuminate\Support\Facades\Mail;
use Tests\CasoClinico;

class CitaTest extends CasoClinico
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
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => $this->proximaFecha(),
            'hora' => '09:00',
            'estado' => 'CONFIRMADA',
            'origen' => 'RECEPCION',
        ], $extra);
    }

    public function test_agendar_una_cita_genera_token_y_avisa_al_paciente(): void
    {
        Mail::fake();

        $this->post('/admin/citas', $this->datos())->assertRedirect();

        $cita = Cita::first();

        $this->assertNotNull($cita);
        $this->assertSame(12, strlen($cita->token));
        Mail::assertSent(CitaConfirmacionMail::class);
    }

    public function test_no_se_puede_ocupar_dos_veces_el_mismo_cupo(): void
    {
        Mail::fake();

        $this->post('/admin/citas', $this->datos());

        $this->from('/admin/citas/create')
            ->post('/admin/citas', $this->datos())
            ->assertSessionHasErrors('hora');

        $this->assertSame(1, Cita::count());
    }

    public function test_una_hora_fuera_del_turno_del_doctor_se_rechaza(): void
    {
        $this->from('/admin/citas/create')
            ->post('/admin/citas', $this->datos(['hora' => '21:00']))
            ->assertSessionHasErrors('hora');

        $this->assertSame(0, Cita::count());
    }

    public function test_el_cupo_liberado_por_una_cancelacion_vuelve_a_ofrecerse(): void
    {
        Mail::fake();

        $this->post('/admin/citas', $this->datos());
        $cita = Cita::first();

        $this->patch("/admin/citas/{$cita->id}/estado", ['estado' => 'CANCELADA']);

        $this->getJson('/admin/citas/horas-disponibles/consultar?'.http_build_query([
            'doctor_id' => $this->doctor->id,
            'fecha' => $this->proximaFecha(),
        ]))->assertOk()->assertJsonFragment(['09:00']);
    }

    public function test_las_horas_disponibles_respetan_el_horario_configurado(): void
    {
        $respuesta = $this->getJson('/admin/citas/horas-disponibles/consultar?'.http_build_query([
            'doctor_id' => $this->doctor->id,
            'fecha' => $this->proximaFecha(),
        ]))->assertOk();

        $horas = $respuesta->json('horas');

        $this->assertContains('08:00', $horas);
        $this->assertContains('11:30', $horas);
        $this->assertNotContains('12:00', $horas, 'El cupo de cierre no debe ofrecerse.');
        $this->assertNotContains('07:30', $horas);
    }
}
