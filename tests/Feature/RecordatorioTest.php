<?php

namespace Tests\Feature;

use App\Mail\CitaConfirmacionMail;
use App\Models\Cita;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\CasoClinico;

class RecordatorioTest extends CasoClinico
{
    private function crearCita(Carbon $momento, array $extra = []): Cita
    {
        return Cita::create(array_merge([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => $momento->toDateString(),
            'hora' => $momento->format('H:i'),
            'estado' => 'CONFIRMADA',
            'origen' => 'RECEPCION',
        ], $extra));
    }

    private function recordatoriosEncolados(): int
    {
        $total = 0;
        Mail::assertQueued(CitaConfirmacionMail::class, function (CitaConfirmacionMail $mail) use (&$total) {
            if ($mail->esRecordatorio === true) {
                $total++;
            }

            return true;
        });

        return $total;
    }

    public function test_una_cita_dentro_de_la_ventana_recibe_recordatorio_y_queda_marcada(): void
    {
        Mail::fake();

        $cita = $this->crearCita(Carbon::now()->addHours(2));

        $this->artisan('citas:recordar')
            ->expectsOutputToContain('Recordatorios enviados: 1')
            ->assertSuccessful();

        Mail::assertQueued(CitaConfirmacionMail::class, function (CitaConfirmacionMail $mail) use ($cita) {
            return $mail->esRecordatorio === true
                && $mail->cita->is($cita)
                && $mail->hasTo($this->paciente->email);
        });

        $this->assertNotNull($cita->fresh()->recordatorio_enviado_en);
    }

    public function test_una_cita_fuera_de_la_ventana_no_recibe_recordatorio(): void
    {
        Mail::fake();

        $cita = $this->crearCita(Carbon::now()->addDays(5)->setTime(9, 0));

        $this->artisan('citas:recordar')
            ->expectsOutputToContain('Recordatorios enviados: 0')
            ->assertSuccessful();

        Mail::assertNotQueued(CitaConfirmacionMail::class);
        $this->assertNull($cita->fresh()->recordatorio_enviado_en);
    }

    public function test_una_cita_cancelada_no_recibe_recordatorio(): void
    {
        Mail::fake();

        $cita = $this->crearCita(Carbon::now()->addHours(2), ['estado' => 'CANCELADA']);

        $this->artisan('citas:recordar')
            ->expectsOutputToContain('Recordatorios enviados: 0')
            ->assertSuccessful();

        Mail::assertNotQueued(CitaConfirmacionMail::class);
        $this->assertNull($cita->fresh()->recordatorio_enviado_en);
    }

    public function test_el_comando_no_reenvia_a_una_cita_ya_marcada(): void
    {
        Mail::fake();

        $cita = $this->crearCita(Carbon::now()->addHours(2));

        $this->artisan('citas:recordar')->assertSuccessful();
        $marcadoEn = $cita->fresh()->recordatorio_enviado_en;
        $this->assertNotNull($marcadoEn);

        $this->artisan('citas:recordar')
            ->expectsOutputToContain('Recordatorios enviados: 0')
            ->assertSuccessful();

        $this->assertSame(1, $this->recordatoriosEncolados());
        $this->assertTrue($marcadoEn->equalTo($cita->fresh()->recordatorio_enviado_en));
    }

    public function test_la_opcion_horas_amplia_la_ventana(): void
    {
        Mail::fake();

        $this->crearCita(Carbon::now()->addHours(30));

        $this->artisan('citas:recordar')
            ->expectsOutputToContain('Recordatorios enviados: 0')
            ->assertSuccessful();

        $this->artisan('citas:recordar', ['--horas' => 48])
            ->expectsOutputToContain('Recordatorios enviados: 1')
            ->assertSuccessful();

        $this->assertSame(1, $this->recordatoriosEncolados());
    }
}
