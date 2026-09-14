<?php

namespace Tests\Feature;

use App\Models\Cita;
use Tests\CasoClinico;

class ConfirmacionCitaTest extends CasoClinico
{
    private function crearCita(array $extra = []): Cita
    {
        return Cita::create(array_merge([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => $this->proximaFecha(),
            'hora' => '09:00',
            'estado' => 'PENDIENTE',
            'origen' => 'RECEPCION',
        ], $extra));
    }

    public function test_el_enlace_firmado_confirma_la_cita_sin_sesion(): void
    {
        $cita = $this->crearCita();

        $this->get($cita->url_confirmacion)
            ->assertOk()
            ->assertSee('confirmada')
            ->assertSee($cita->token)
            ->assertSee($this->doctor->nombre_profesional);

        $cita->refresh();

        $this->assertSame('CONFIRMADA', $cita->estado);
        $this->assertNotNull($cita->confirmada_en);
        $this->assertSame('paciente', $cita->confirmada_por);
        $this->assertDatabaseHas('auditorias', [
            'modelo' => 'Cita',
            'modelo_id' => $cita->id,
            'descripcion' => 'El paciente confirmó por enlace',
        ]);
    }

    public function test_confirmar_dos_veces_es_idempotente(): void
    {
        $cita = $this->crearCita();

        $this->get($cita->url_confirmacion)->assertOk();
        $primera = $cita->fresh()->confirmada_en;

        $this->travel(5)->minutes();
        $this->get($cita->url_confirmacion)->assertOk();

        $this->assertTrue($primera->equalTo($cita->fresh()->confirmada_en));
    }

    public function test_sin_firma_el_enlace_se_rechaza(): void
    {
        $cita = $this->crearCita();

        $this->get(route('citas.confirmar-publica', ['token' => $cita->confirmacion_token]))
            ->assertForbidden();

        $this->assertSame('PENDIENTE', $cita->fresh()->estado);
    }

    public function test_una_cita_cancelada_no_se_confirma(): void
    {
        $cita = $this->crearCita(['estado' => 'CANCELADA']);

        $this->get($cita->url_confirmacion)
            ->assertOk()
            ->assertSee('cancelada');

        $this->assertSame('CANCELADA', $cita->fresh()->estado);
        $this->assertNull($cita->fresh()->confirmada_en);
    }

    public function test_una_cita_pasada_no_se_confirma(): void
    {
        $cita = $this->crearCita(['fecha' => now()->subDays(2)->toDateString()]);

        $this->get($cita->url_confirmacion)
            ->assertOk()
            ->assertSee('ya pasó');

        $this->assertSame('PENDIENTE', $cita->fresh()->estado);
    }
}
