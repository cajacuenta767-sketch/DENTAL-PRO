<?php

namespace Tests\Feature;

use App\Mail\CitaConfirmacionMail;
use App\Models\Ajuste;
use App\Models\Cita;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
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

    public function test_con_canal_whatsapp_y_proveedor_log_no_hay_llamadas_http_y_la_cita_queda_marcada(): void
    {
        Mail::fake();
        Http::fake();
        config(['services.mensajeria.proveedor' => 'log']);
        Ajuste::actual()->update(['recordatorio_canal' => 'whatsapp']);
        $this->paciente->update(['telefono' => '70012345']);

        $cita = $this->crearCita(Carbon::now()->addHours(2));

        $this->artisan('citas:recordar')
            ->expectsOutputToContain('Recordatorios enviados: 1')
            ->assertSuccessful();

        Http::assertNothingSent();
        Mail::assertNotQueued(CitaConfirmacionMail::class);

        $cita->refresh();
        $this->assertNotNull($cita->recordatorio_enviado_en);
        $this->assertSame('whatsapp', $cita->recordatorio_canal);
    }

    public function test_con_proveedor_twilio_el_recordatorio_sale_por_whatsapp(): void
    {
        Mail::fake();
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
        config([
            'services.mensajeria.proveedor' => 'twilio',
            'services.mensajeria.prefijo_pais' => '591',
            'services.mensajeria.twilio.sid' => 'ACxxx',
            'services.mensajeria.twilio.token' => 'secreto',
            'services.mensajeria.twilio.desde_whatsapp' => '+14155238886',
        ]);
        Ajuste::actual()->update(['recordatorio_canal' => 'whatsapp']);
        $this->paciente->update(['telefono' => '70012345']);

        $cita = $this->crearCita(Carbon::now()->addHours(2));

        $this->artisan('citas:recordar')
            ->expectsOutputToContain('Recordatorios enviados: 1')
            ->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r['To'] === 'whatsapp:+59170012345' && str_contains($r['Body'], 'te recuerda tu cita'));
        Mail::assertNotQueued(CitaConfirmacionMail::class);
        $this->assertSame('whatsapp', $cita->fresh()->recordatorio_canal);
    }

    public function test_con_numero_invalido_el_comando_no_marca_la_cita_y_no_falla(): void
    {
        Mail::fake();
        Http::fake();
        config(['services.mensajeria.proveedor' => 'twilio', 'services.mensajeria.twilio.sid' => 'ACxxx', 'services.mensajeria.twilio.token' => 'x', 'services.mensajeria.twilio.desde_whatsapp' => '+1']);
        Ajuste::actual()->update(['recordatorio_canal' => 'whatsapp']);
        $this->paciente->update(['telefono' => '123']);

        $cita = $this->crearCita(Carbon::now()->addHours(2));

        $this->artisan('citas:recordar')
            ->expectsOutputToContain('Recordatorios enviados: 0')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertNull($cita->fresh()->recordatorio_enviado_en);
    }

    public function test_la_opcion_canal_fuerza_el_medio_de_envio(): void
    {
        Mail::fake();
        Http::fake();
        config(['services.mensajeria.proveedor' => 'log']);
        Ajuste::actual()->update(['recordatorio_canal' => 'correo']);
        $this->paciente->update(['telefono' => '70012345']);

        $cita = $this->crearCita(Carbon::now()->addHours(2));

        $this->artisan('citas:recordar', ['--canal' => 'sms'])
            ->expectsOutputToContain('Recordatorios enviados: 1')
            ->assertSuccessful();

        Mail::assertNotQueued(CitaConfirmacionMail::class);
        $this->assertSame('sms', $cita->fresh()->recordatorio_canal);

        $this->artisan('citas:recordar', ['--canal' => 'paloma'])->assertFailed();
    }

    public function test_el_canal_correo_whatsapp_encola_el_correo_y_envia_el_whatsapp(): void
    {
        Mail::fake();
        Http::fake();
        config(['services.mensajeria.proveedor' => 'log']);
        Ajuste::actual()->update(['recordatorio_canal' => 'correo_whatsapp']);
        $this->paciente->update(['telefono' => '70012345']);

        $cita = $this->crearCita(Carbon::now()->addHours(2));

        $this->artisan('citas:recordar')
            ->expectsOutputToContain('Recordatorios enviados: 1')
            ->assertSuccessful();

        $this->assertSame(1, $this->recordatoriosEncolados());
        $this->assertSame('correo,whatsapp', $cita->fresh()->recordatorio_canal);
    }
}
