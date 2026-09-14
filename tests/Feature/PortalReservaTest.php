<?php

namespace Tests\Feature;

use App\Mail\CitaConfirmacionMail;
use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Usuario;
use Illuminate\Support\Facades\Mail;
use Tests\CasoClinico;

class PortalReservaTest extends CasoClinico
{
    private Usuario $cuentaPaciente;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        Ajuste::actual()->update([
            'portal_reservas_activas' => true,
            'reservas_anticipacion_dias' => 30,
            'reservas_minimo_horas' => 4,
        ]);

        $this->cuentaPaciente = Usuario::create([
            'nombre' => 'Cuenta PACIENTE',
            'email' => 'paciente@pruebas.test',
            'password' => 'secreto123',
            'estado' => 'activo',
        ]);
        $this->cuentaPaciente->assignRole('PACIENTE');
        $this->cuentaPaciente->forceFill(['email_verified_at' => now()])->save();

        $this->paciente->update(['usuario_id' => $this->cuentaPaciente->id]);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => $this->proximaFecha(),
            'hora' => '09:00',
            'motivo' => 'Limpieza de rutina',
        ], $extra);
    }

    public function test_el_formulario_del_portal_responde_con_las_reservas_activas(): void
    {
        $this->actingAs($this->cuentaPaciente)
            ->get(route('portal.reservar'))
            ->assertOk()
            ->assertSee('Confirmar reserva', false);
    }

    public function test_los_selectores_y_los_cupos_responden_en_json(): void
    {
        $this->actingAs($this->cuentaPaciente);

        $especialidad = $this->tratamiento->especialidad_id;

        $this->getJson(route('portal.reservar.opciones', ['especialidad_id' => $especialidad]))
            ->assertOk()
            ->assertJsonPath('doctores.0.id', $this->doctor->id)
            ->assertJsonPath('tratamientos.0.id', $this->tratamiento->id);

        $this->getJson(route('portal.reservar.horas', ['doctor_id' => $this->doctor->id, 'fecha' => $this->proximaFecha(), 'tratamiento_id' => $this->tratamiento->id]))
            ->assertOk()
            ->assertJsonFragment(['horas' => ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30']]);
    }

    public function test_reservar_crea_una_cita_online_pendiente_del_paciente_y_avisa_por_correo(): void
    {
        $respuesta = $this->actingAs($this->cuentaPaciente)
            ->post(route('portal.reservar.guardar'), $this->datos());

        $cita = Cita::first();

        $this->assertNotNull($cita);
        $respuesta->assertRedirect(route('portal.reservar', ['confirmada' => $cita->token]));

        $this->assertSame($this->paciente->id, $cita->paciente_id);
        $this->assertSame('ONLINE', $cita->origen);
        $this->assertSame('PENDIENTE', $cita->estado);
        $this->assertSame('Limpieza de rutina', $cita->motivo);

        Mail::assertQueued(CitaConfirmacionMail::class, fn ($mail) => $mail->hasTo($this->paciente->email));

        // La confirmación se muestra dentro del portal con el enlace a las citas.
        $this->get(route('portal.reservar', ['confirmada' => $cita->token]))
            ->assertOk()
            ->assertSee('Tu cita quedó reservada', false)
            ->assertSee(route('portal.citas'), false);
    }

    public function test_un_horario_ocupado_no_se_reserva_dos_veces(): void
    {
        $this->actingAs($this->cuentaPaciente);

        $this->post(route('portal.reservar.guardar'), $this->datos())->assertRedirect();

        $this->from(route('portal.reservar'))
            ->post(route('portal.reservar.guardar'), $this->datos())
            ->assertRedirect(route('portal.reservar'))
            ->assertSessionHasErrors('hora');

        $this->assertSame(1, Cita::count());
    }

    public function test_con_las_reservas_del_portal_apagadas_no_se_puede_reservar(): void
    {
        Ajuste::actual()->update(['portal_reservas_activas' => false]);

        $this->actingAs($this->cuentaPaciente);

        $this->get(route('portal.reservar'))
            ->assertRedirect(route('portal.citas'))
            ->assertSessionHas('error');

        $this->post(route('portal.reservar.guardar'), $this->datos())
            ->assertRedirect(route('portal.citas'))
            ->assertSessionHas('error');

        $this->getJson(route('portal.reservar.horas', ['doctor_id' => $this->doctor->id, 'fecha' => $this->proximaFecha()]))
            ->assertForbidden();

        $this->assertSame(0, Cita::count());
        $this->get(route('portal.citas'))->assertOk()->assertDontSee(route('portal.reservar'), false);
    }

    public function test_el_boton_de_reservar_aparece_en_el_portal_solo_si_esta_activo(): void
    {
        $this->actingAs($this->cuentaPaciente);

        $this->get(route('portal.inicio'))->assertOk()->assertSee(route('portal.reservar'), false);

        Ajuste::actual()->update(['portal_reservas_activas' => false]);

        $this->get(route('portal.inicio'))->assertOk()->assertDontSee(route('portal.reservar'), false);
    }
}
