<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Tratamiento;
use Illuminate\Support\Facades\Mail;
use Tests\CasoClinico;

class ReservaPublicaProteccionTest extends CasoClinico
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $ajustes = Ajuste::actual();
        $ajustes->update([
            'reservas_online' => true,
            'reservas_token' => 'token-de-pruebas-abcdefghijklmnop',
            'reservas_anticipacion_dias' => 30,
            'reservas_minimo_horas' => 4,
        ]);

        $this->token = $ajustes->reservas_token;

        $this->paciente->update(['telefono' => '70011223', 'email' => 'juan.perez@pruebas.test']);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'tipo_documento' => 'CI',
            'numero_documento' => $this->paciente->numero_documento,
            'telefono' => '79990000',
            'email' => 'otro.correo@intruso.test',
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => $this->proximaFecha(),
            'hora' => '09:00',
        ], $extra);
    }

    public function test_una_reserva_anonima_no_pisa_los_datos_de_contacto_de_una_ficha_existente(): void
    {
        $this->post("/reservar/{$this->token}", $this->datos())->assertRedirect();

        $paciente = $this->paciente->fresh();

        $this->assertSame('juan.perez@pruebas.test', $paciente->email, 'El correo registrado no se sobrescribe.');
        $this->assertSame('70011223', $paciente->telefono, 'El teléfono registrado no se sobrescribe.');

        $cita = Cita::firstOrFail();
        $this->assertSame($paciente->id, $cita->paciente_id);
        $this->assertSame('ONLINE', $cita->origen);
    }

    public function test_el_tratamiento_debe_ser_de_la_especialidad_del_doctor(): void
    {
        $ortodoncia = Especialidad::create(['nombre' => 'ORTODONCIA', 'activo' => true]);
        $ajeno = Tratamiento::create([
            'especialidad_id' => $ortodoncia->id,
            'nombre' => 'BRACKETS METÁLICOS',
            'precio' => 2500,
            'duracion' => 60,
            'activo' => true,
        ]);

        $this->from("/reservar/{$this->token}")
            ->post("/reservar/{$this->token}", $this->datos(['tratamiento_id' => $ajeno->id]))
            ->assertSessionHasErrors('tratamiento_id');

        $this->assertSame(0, Cita::count());
    }
}
