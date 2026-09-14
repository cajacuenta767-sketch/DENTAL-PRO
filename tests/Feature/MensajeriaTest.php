<?php

namespace Tests\Feature;

use App\Mail\CitaConfirmacionMail;
use App\Models\Ajuste;
use App\Models\Cita;
use App\Services\Mensajeria\LogDriver;
use App\Services\Mensajeria\MetaWhatsappDriver;
use App\Services\Mensajeria\ProveedorMensajeria;
use App\Services\Mensajeria\ResultadoEnvio;
use App\Services\Mensajeria\TwilioDriver;
use App\Services\MensajeriaService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\CasoClinico;

class MensajeriaTest extends CasoClinico
{
    private function crearCita(array $extra = [], int $horas = 3): Cita
    {
        $momento = Carbon::now()->addHours($horas);

        return Cita::create(array_merge([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => $momento->toDateString(),
            'hora' => $momento->format('H:i'),
            'estado' => 'PENDIENTE',
            'origen' => 'RECEPCION',
        ], $extra));
    }

    private function configurarTwilio(): void
    {
        config([
            'services.mensajeria.proveedor' => 'twilio',
            'services.mensajeria.twilio.sid' => 'ACxxx',
            'services.mensajeria.twilio.token' => 'secreto',
            'services.mensajeria.twilio.desde_sms' => '+15550001111',
            'services.mensajeria.twilio.desde_whatsapp' => '+14155238886',
        ]);
    }

    public function test_el_proveedor_se_elige_desde_la_configuracion(): void
    {
        config(['services.mensajeria.proveedor' => 'log']);
        $this->assertInstanceOf(LogDriver::class, MensajeriaService::crearProveedor());

        config(['services.mensajeria.proveedor' => 'twilio']);
        $this->assertInstanceOf(TwilioDriver::class, MensajeriaService::crearProveedor());

        config(['services.mensajeria.proveedor' => 'meta']);
        $this->assertInstanceOf(MetaWhatsappDriver::class, MensajeriaService::crearProveedor());

        config(['services.mensajeria.proveedor' => 'inexistente']);
        $this->assertInstanceOf(LogDriver::class, MensajeriaService::crearProveedor());
    }

    public function test_los_numeros_se_normalizan_a_formato_internacional(): void
    {
        config(['services.mensajeria.prefijo_pais' => '591']);

        $this->assertSame('+59170012345', MensajeriaService::normalizarNumero('70012345'));
        $this->assertSame('+59170012345', MensajeriaService::normalizarNumero('700-12345'));
        $this->assertSame('+59170012345', MensajeriaService::normalizarNumero('+591 700 12345'));
        $this->assertSame('+5215512345678', MensajeriaService::normalizarNumero('52 155 1234 5678'));
        $this->assertNull(MensajeriaService::normalizarNumero('1234'));
        $this->assertNull(MensajeriaService::normalizarNumero(null));
        $this->assertNull(MensajeriaService::normalizarNumero('sin número'));
    }

    public function test_con_proveedor_log_el_whatsapp_se_envia_sin_llamadas_http(): void
    {
        Http::fake();
        Mail::fake();
        config(['services.mensajeria.proveedor' => 'log']);
        Ajuste::actual()->update(['recordatorio_canal' => 'whatsapp']);
        $this->paciente->update(['telefono' => '70012345']);

        $cita = $this->crearCita();
        $resultados = app(MensajeriaService::class)->recordatorioCita($cita, esRecordatorio: true);

        $this->assertArrayHasKey('whatsapp', $resultados);
        $this->assertTrue($resultados['whatsapp']->exito);
        $this->assertNotNull($resultados['whatsapp']->idExterno);
        Http::assertNothingSent();
        Mail::assertNothingQueued();
        $this->assertSame('whatsapp', $cita->fresh()->recordatorio_canal);
    }

    public function test_con_twilio_se_envia_el_whatsapp_con_el_prefijo_y_el_numero_normalizado(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
        Mail::fake();
        $this->configurarTwilio();
        Ajuste::actual()->update(['recordatorio_canal' => 'whatsapp']);
        $this->paciente->update(['telefono' => '700-12345']);

        $cita = $this->crearCita();
        $resultados = app(MensajeriaService::class)->recordatorioCita($cita, esRecordatorio: true);

        $this->assertTrue($resultados['whatsapp']->exito);
        $this->assertSame('SM1', $resultados['whatsapp']->idExterno);

        Http::assertSent(function (Request $request) use ($cita) {
            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/ACxxx/Messages.json'
                && $request['To'] === 'whatsapp:+59170012345'
                && $request['From'] === 'whatsapp:+14155238886'
                && str_contains($request['Body'], 'Juan')
                && str_contains($request['Body'], $cita->url_confirmacion)
                && $request->hasHeader('Authorization');
        });
        $this->assertSame('whatsapp', $cita->fresh()->recordatorio_canal);
    }

    public function test_con_twilio_el_sms_usa_el_numero_sin_prefijo_whatsapp(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM2'], 201)]);
        $this->configurarTwilio();

        $resultado = app(MensajeriaService::class)->enviarSms('70012345', 'Prueba');

        $this->assertTrue($resultado->exito);
        Http::assertSent(fn (Request $r) => $r['To'] === '+59170012345' && $r['From'] === '+15550001111' && $r['Body'] === 'Prueba');
    }

    public function test_un_error_de_twilio_se_devuelve_sin_lanzar_excepcion(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['message' => 'Número no verificado'], 400)]);
        $this->configurarTwilio();

        $resultado = app(MensajeriaService::class)->enviarWhatsapp('70012345', 'Prueba');

        $this->assertFalse($resultado->exito);
        $this->assertStringContainsString('Número no verificado', $resultado->error);
    }

    public function test_un_numero_invalido_devuelve_error_sin_excepcion_ni_llamadas(): void
    {
        Http::fake();
        $this->configurarTwilio();

        $resultado = app(MensajeriaService::class)->enviarWhatsapp('1234', 'Prueba');

        $this->assertFalse($resultado->exito);
        $this->assertNotEmpty($resultado->error);
        Http::assertNothingSent();
    }

    public function test_una_excepcion_del_proveedor_se_captura_y_devuelve_error(): void
    {
        $proveedor = new class implements ProveedorMensajeria
        {
            public function enviarWhatsapp(string $numero, string $texto): ResultadoEnvio
            {
                throw new \RuntimeException('Proveedor caído');
            }

            public function enviarSms(string $numero, string $texto): ResultadoEnvio
            {
                throw new \RuntimeException('Proveedor caído');
            }
        };

        $resultado = (new MensajeriaService($proveedor))->enviarWhatsapp('70012345', 'Prueba');

        $this->assertFalse($resultado->exito);
        $this->assertSame('Proveedor caído', $resultado->error);
    }

    public function test_meta_envia_whatsapp_por_la_cloud_api_y_no_soporta_sms(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);
        config([
            'services.mensajeria.proveedor' => 'meta',
            'services.mensajeria.meta.token' => 'token-meta',
            'services.mensajeria.meta.phone_id' => '123456',
        ]);

        $servicio = app(MensajeriaService::class);
        $whatsapp = $servicio->enviarWhatsapp('70012345', 'Hola');
        $sms = $servicio->enviarSms('70012345', 'Hola');

        $this->assertTrue($whatsapp->exito);
        $this->assertSame('wamid.1', $whatsapp->idExterno);
        Http::assertSent(function (Request $r) {
            return $r->url() === 'https://graph.facebook.com/v20.0/123456/messages'
                && $r->hasHeader('Authorization', 'Bearer token-meta')
                && $r['to'] === '59170012345'
                && $r['type'] === 'text'
                && $r['text']['body'] === 'Hola';
        });

        $this->assertFalse($sms->exito);
        $this->assertStringContainsString('no envía SMS', $sms->error);
    }

    public function test_el_canal_correo_whatsapp_encola_el_correo_y_envia_el_whatsapp(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM3'], 201)]);
        Mail::fake();
        $this->configurarTwilio();
        Ajuste::actual()->update(['recordatorio_canal' => 'correo_whatsapp']);
        $this->paciente->update(['telefono' => '70012345']);

        $cita = $this->crearCita();
        $resultados = app(MensajeriaService::class)->recordatorioCita($cita, esRecordatorio: true);

        $this->assertSame(['correo', 'whatsapp'], array_keys($resultados));
        $this->assertTrue($resultados['correo']->exito);
        $this->assertTrue($resultados['whatsapp']->exito);
        Mail::assertQueued(CitaConfirmacionMail::class, fn (CitaConfirmacionMail $m) => $m->esRecordatorio && $m->cita->is($cita));
        Http::assertSentCount(1);
        $this->assertSame('correo,whatsapp', $cita->fresh()->recordatorio_canal);
    }

    public function test_sin_telefono_el_canal_whatsapp_falla_y_no_marca_la_cita(): void
    {
        Http::fake();
        Ajuste::actual()->update(['recordatorio_canal' => 'whatsapp']);

        $cita = $this->crearCita();
        $resultados = app(MensajeriaService::class)->recordatorioCita($cita);

        $this->assertFalse($resultados['whatsapp']->exito);
        Http::assertNothingSent();
        $this->assertNull($cita->fresh()->recordatorio_canal);
    }

    public function test_el_correo_incluye_el_boton_de_confirmacion_solo_si_la_cita_no_esta_confirmada(): void
    {
        $pendiente = $this->crearCita(['estado' => 'PENDIENTE']);
        $html = (new CitaConfirmacionMail($pendiente, esRecordatorio: true))->render();
        $this->assertStringContainsString('Confirmar asistencia', $html);
        $this->assertStringContainsString('citas/confirmar/', $html);

        $confirmada = $this->crearCita(['estado' => 'CONFIRMADA'], horas: 5);
        $html = (new CitaConfirmacionMail($confirmada, esRecordatorio: true))->render();
        $this->assertStringNotContainsString('Confirmar asistencia', $html);
    }

    public function test_el_administrador_puede_guardar_el_canal_y_los_interruptores_del_portal(): void
    {
        $ajuste = Ajuste::actual();

        $this->actingAs($this->admin)
            ->put(route('admin.ajustes.update'), [
                'nombre' => $ajuste->nombre,
                'divisa' => $ajuste->divisa,
                'simbolo_divisa' => $ajuste->simbolo_divisa,
                'minutos_intervalo_cita' => 30,
                'horas_recordatorio' => 24,
                'recordatorio_canal' => 'whatsapp',
                'pagos_online_activos' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $ajuste->refresh();
        $this->assertSame('whatsapp', $ajuste->recordatorio_canal);
        $this->assertTrue($ajuste->pagos_online_activos);
        $this->assertFalse($ajuste->portal_reservas_activas);

        $this->actingAs($this->admin)
            ->from(route('admin.ajustes.edit'))
            ->put(route('admin.ajustes.update'), [
                'nombre' => $ajuste->nombre,
                'divisa' => $ajuste->divisa,
                'simbolo_divisa' => $ajuste->simbolo_divisa,
                'minutos_intervalo_cita' => 30,
                'horas_recordatorio' => 24,
                'recordatorio_canal' => 'paloma',
            ])
            ->assertSessionHasErrors('recordatorio_canal');
    }

    public function test_la_pantalla_de_ajustes_muestra_el_canal_y_el_proveedor(): void
    {
        config(['services.mensajeria.proveedor' => 'twilio']);

        $this->actingAs($this->admin)
            ->get(route('admin.ajustes.edit'))
            ->assertOk()
            ->assertSee('Canal del recordatorio')
            ->assertSee('MENSAJERIA_PROVEEDOR')
            ->assertSee('twilio')
            ->assertSee('Pagos en línea desde el portal')
            ->assertSee('Reservas desde el portal');
    }
}
