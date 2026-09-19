<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Paciente;
use App\Models\Pago;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CasoClinico;

/**
 * Fichas de pacientes y doctores: alta, edición, foto y las reglas que
 * impiden borrar a alguien con historia clínica o económica detrás.
 */
class FichaTest extends CasoClinico
{
    // --- Pacientes ---------------------------------------------------------

    private function datosPaciente(array $extra = []): array
    {
        return $extra + [
            'nombres' => 'Lucía',
            'apellidos' => 'Rojas',
            'tipo_documento' => 'CI',
            'numero_documento' => '9988776',
            'genero' => 'F',
            'fecha_nacimiento' => '1990-05-12',
            'email' => 'lucia.rojas@pruebas.test',
            'activo' => 1,
        ];
    }

    public function test_un_paciente_nuevo_llega_a_su_ficha(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->post('/admin/pacientes', $this->datosPaciente());

        $paciente = Paciente::where('numero_documento', '9988776')->sole();

        $respuesta->assertRedirect("/admin/pacientes/{$paciente->id}");
        $this->actingAs($this->admin)->get("/admin/pacientes/{$paciente->id}")->assertOk();
    }

    public function test_el_documento_de_un_paciente_no_se_repite(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/pacientes', $this->datosPaciente(['numero_documento' => $this->paciente->numero_documento]))
            ->assertSessionHasErrors('numero_documento');
    }

    public function test_una_fecha_de_nacimiento_futura_se_rechaza(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/pacientes', $this->datosPaciente([
                'fecha_nacimiento' => now()->addYear()->toDateString(),
            ]))
            ->assertSessionHasErrors('fecha_nacimiento');
    }

    public function test_la_foto_del_paciente_se_guarda_y_se_reemplaza(): void
    {
        Storage::fake(Paciente::DISCO_FOTOS);

        $this->actingAs($this->admin)->post('/admin/pacientes', $this->datosPaciente([
            'foto' => UploadedFile::fake()->image('paciente.jpg'),
        ]));

        $paciente = Paciente::where('numero_documento', '9988776')->sole();
        $primera = $paciente->fotografia;

        $this->assertNotNull($primera);
        Storage::disk(Paciente::DISCO_FOTOS)->assertExists($primera);

        $this->actingAs($this->admin)->put("/admin/pacientes/{$paciente->id}", $this->datosPaciente([
            'foto' => UploadedFile::fake()->image('otra.jpg'),
        ]));

        $paciente->refresh();

        $this->assertNotSame($primera, $paciente->fotografia);
        Storage::disk(Paciente::DISCO_FOTOS)->assertMissing($primera);
        Storage::disk(Paciente::DISCO_FOTOS)->assertExists($paciente->fotografia);
    }

    public function test_no_se_elimina_un_paciente_con_recibos(): void
    {
        Pago::create([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'usuario_id' => $this->admin->id,
            'codigo_recibo' => Pago::siguienteCodigo(),
            'fecha_pago' => now(),
            'metodo_pago' => 'EFECTIVO',
            'monto_total' => 100,
            'monto_pagado' => 100,
            'monto_saldo' => 0,
            'estado' => 'COMPLETADO',
        ]);

        $this->actingAs($this->admin)
            ->from('/admin/pacientes')
            ->delete("/admin/pacientes/{$this->paciente->id}")
            ->assertSessionHas('error');

        $this->assertModelExists($this->paciente);
    }

    public function test_un_paciente_sin_movimientos_si_se_elimina(): void
    {
        $paciente = Paciente::create([
            'nombres' => 'Sin',
            'apellidos' => 'Movimientos',
            'tipo_documento' => 'CI',
            'numero_documento' => '5550001',
            'genero' => 'O',
            'activo' => true,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/pacientes/{$paciente->id}")
            ->assertRedirect('/admin/pacientes');

        $this->assertSoftDeleted($paciente);
    }

    // --- Doctores ----------------------------------------------------------

    public function test_un_doctor_nuevo_llega_a_su_ficha(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/admin/doctores', [
            'especialidad_id' => $this->tratamiento->especialidad_id,
            'nombres' => 'Marco',
            'apellidos' => 'Salinas',
            'tipo_documento' => 'CI',
            'numero_documento' => '4443332',
            'genero' => 'M',
            'activo' => 1,
        ]);

        $doctor = Doctor::where('numero_documento', '4443332')->sole();

        $respuesta->assertRedirect("/admin/doctores/{$doctor->id}");
        $this->actingAs($this->admin)->get("/admin/doctores/{$doctor->id}")->assertOk();
    }

    public function test_no_se_elimina_un_doctor_con_citas(): void
    {
        $this->actingAs($this->admin)->post('/admin/citas', [
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => $this->proximaFecha(),
            'hora' => '09:00',
            'estado' => 'CONFIRMADA',
            'origen' => 'RECEPCION',
        ]);

        $this->assertTrue($this->doctor->citas()->exists());

        $this->actingAs($this->admin)
            ->from('/admin/doctores')
            ->delete("/admin/doctores/{$this->doctor->id}")
            ->assertSessionHas('error');

        $this->assertModelExists($this->doctor);
    }
}
