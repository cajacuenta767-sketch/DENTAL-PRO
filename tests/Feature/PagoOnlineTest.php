<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\PagoOnline;
use App\Models\Usuario;
use App\Services\Pasarela\StripeDriver;
use Illuminate\Support\Facades\URL;
use Tests\CasoClinico;

class PagoOnlineTest extends CasoClinico
{
    private Usuario $cuentaPaciente;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.pasarela.proveedor' => 'simulado']);
        Ajuste::actual()->update(['pagos_online_activos' => true]);

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

    /** Recibo de 360 con 200 pagados: queda PARCIAL con saldo 160. */
    private function reciboConSaldo(?Paciente $paciente = null, float $pagado = 200): Pago
    {
        $pago = Pago::create([
            'codigo_recibo' => Pago::siguienteCodigo(),
            'paciente_id' => ($paciente ?? $this->paciente)->id,
            'doctor_id' => $this->doctor->id,
            'usuario_id' => $this->admin->id,
            'metodo_pago' => 'EFECTIVO',
            'monto_total' => 360,
            'monto_pagado' => $pagado,
            'monto_saldo' => 360 - $pagado,
            'estado' => 'PARCIAL',
            'fecha_pago' => now(),
        ]);

        $pago->detalles()->create([
            'tratamiento_id' => $this->tratamiento->id,
            'descripcion' => 'PROFILAXIS DENTAL',
            'cantidad' => 2,
            'precio_unitario' => 180,
            'subtotal' => 360,
        ]);

        $pago->recalcular();

        return $pago->fresh();
    }

    private function retorno(PagoOnline $intento, string $resultado): string
    {
        return URL::signedRoute('pagos-online.retorno', ['pagoOnline' => $intento->id, 'resultado' => $resultado]);
    }

    public function test_iniciar_crea_un_intento_pendiente_por_el_saldo_y_redirige_a_la_pasarela(): void
    {
        $pago = $this->reciboConSaldo();

        $this->actingAs($this->cuentaPaciente)
            ->post(route('portal.pagos.pagar', $pago))
            ->assertRedirect();

        $intento = PagoOnline::first();

        $this->assertNotNull($intento);
        $this->assertSame('PENDIENTE', $intento->estado);
        $this->assertSame('simulado', $intento->proveedor);
        $this->assertSame('160.00', $intento->monto);
        $this->assertSame($pago->id, $intento->pago_id);
        $this->assertSame($this->paciente->id, $intento->paciente_id);
        $this->assertSame(route('portal.pagos.online', $intento), $intento->url);

        // La "pasarela" simulada muestra los botones con las rutas de retorno firmadas.
        $this->get($intento->url)
            ->assertOk()
            ->assertSee('Pasarela simulada', false)
            ->assertSee($intento->respuesta['url_exito'], false)
            ->assertSee($intento->respuesta['url_cancelacion'], false);
    }

    public function test_el_retorno_firmado_con_exito_acredita_el_pago_y_completa_el_recibo(): void
    {
        $pago = $this->reciboConSaldo();

        $this->actingAs($this->cuentaPaciente)->post(route('portal.pagos.pagar', $pago));
        $intento = PagoOnline::first();

        $this->get($this->retorno($intento, 'exito'))
            ->assertRedirect(route('portal.pagos.online', $intento));

        $intento->refresh();
        $pago->refresh();

        $this->assertSame('PAGADO', $intento->estado);
        $this->assertNotNull($intento->pagado_en);
        $this->assertSame('COMPLETADO', $pago->estado);
        $this->assertSame('0.00', $pago->monto_saldo);
        $this->assertSame('360.00', $pago->monto_pagado);
        $this->assertStringContainsString("Pago en línea {$intento->referencia}", (string) $pago->notas);

        $this->assertTrue(
            Auditoria::where('modelo', 'Pago')->where('modelo_id', $pago->id)->where('accion', 'ACTUALIZAR')
                ->where('descripcion', 'like', 'Pago en línea%')->exists()
        );

        $this->actingAs($this->cuentaPaciente)
            ->get(route('portal.pagos.online', $intento))
            ->assertOk()
            ->assertSee('Pago recibido', false);
    }

    public function test_un_segundo_retorno_no_duplica_el_abono(): void
    {
        $pago = $this->reciboConSaldo();

        $this->actingAs($this->cuentaPaciente)->post(route('portal.pagos.pagar', $pago));
        $intento = PagoOnline::first();

        $this->get($this->retorno($intento, 'exito'))->assertRedirect();
        $this->get($this->retorno($intento, 'exito'))->assertRedirect();
        $this->get($this->retorno($intento, 'cancelado'))->assertRedirect();

        $pago->refresh();

        $this->assertSame('PAGADO', $intento->fresh()->estado);
        $this->assertSame('360.00', $pago->monto_pagado);
        $this->assertSame('0.00', $pago->monto_saldo);
        $this->assertSame('COMPLETADO', $pago->estado);
        $this->assertSame(1, substr_count((string) $pago->notas, 'Pago en línea'));
    }

    public function test_el_retorno_cancelado_cierra_el_intento_sin_tocar_el_recibo(): void
    {
        $pago = $this->reciboConSaldo();

        $this->actingAs($this->cuentaPaciente)->post(route('portal.pagos.pagar', $pago));
        $intento = PagoOnline::first();

        $this->get($this->retorno($intento, 'cancelado'))->assertRedirect();

        $this->assertSame('CANCELADO', $intento->fresh()->estado);
        $this->assertSame('160.00', $pago->fresh()->monto_saldo);
        $this->assertSame('PARCIAL', $pago->fresh()->estado);
    }

    public function test_un_retorno_sin_firma_valida_no_existe(): void
    {
        $pago = $this->reciboConSaldo();

        $this->actingAs($this->cuentaPaciente)->post(route('portal.pagos.pagar', $pago));
        $intento = PagoOnline::first();

        $this->get(route('pagos-online.retorno', ['pagoOnline' => $intento->id, 'resultado' => 'exito']))
            ->assertForbidden();

        $this->assertSame('PENDIENTE', $intento->fresh()->estado);
    }

    public function test_un_recibo_ajeno_no_existe_para_el_portal(): void
    {
        $otro = Paciente::create([
            'nombres' => 'Otra',
            'apellidos' => 'Persona',
            'tipo_documento' => 'CI',
            'numero_documento' => '99998888',
            'genero' => 'F',
            'activo' => true,
        ]);
        $pago = $this->reciboConSaldo($otro);

        $this->actingAs($this->cuentaPaciente)
            ->post(route('portal.pagos.pagar', $pago))
            ->assertNotFound();

        $this->assertSame(0, PagoOnline::count());
    }

    public function test_con_los_pagos_en_linea_apagados_no_se_abre_ningun_intento(): void
    {
        Ajuste::actual()->update(['pagos_online_activos' => false]);
        $pago = $this->reciboConSaldo();

        $this->actingAs($this->cuentaPaciente)
            ->post(route('portal.pagos.pagar', $pago))
            ->assertRedirect(route('portal.pagos'))
            ->assertSessionHas('error');

        $this->assertSame(0, PagoOnline::count());
        $this->get(route('portal.pagos'))->assertOk()->assertDontSee('Pagar en línea', false);
    }

    public function test_un_recibo_sin_saldo_no_se_puede_pagar(): void
    {
        $pago = $this->reciboConSaldo(pagado: 360);

        $this->actingAs($this->cuentaPaciente)
            ->post(route('portal.pagos.pagar', $pago))
            ->assertRedirect(route('portal.pagos'))
            ->assertSessionHas('error');

        $this->assertSame(0, PagoOnline::count());
    }

    public function test_el_boton_de_pagar_aparece_en_los_recibos_con_saldo(): void
    {
        $pago = $this->reciboConSaldo();

        $this->actingAs($this->cuentaPaciente)
            ->get(route('portal.pagos'))
            ->assertOk()
            ->assertSee(route('portal.pagos.pagar', $pago), false)
            ->assertSee('Pagar en línea', false);
    }

    public function test_el_webhook_de_stripe_firmado_acredita_el_pago_y_uno_sin_firma_se_rechaza(): void
    {
        config(['services.pasarela.stripe.webhook_secret' => 'whsec_pruebas']);
        $pago = $this->reciboConSaldo();

        $intento = PagoOnline::create([
            'pago_id' => $pago->id,
            'paciente_id' => $this->paciente->id,
            'proveedor' => 'stripe',
            'referencia' => 'cs_test_123',
            'monto' => 160,
            'moneda' => 'BOB',
            'estado' => 'PENDIENTE',
        ]);

        $evento = json_encode([
            'id' => 'evt_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_123',
                'payment_status' => 'paid',
                'metadata' => ['pago_online_id' => (string) $intento->id],
            ]],
        ]);
        $marca = time();
        $firma = hash_hmac('sha256', "{$marca}.{$evento}", 'whsec_pruebas');

        // Sin firma: rechazado y nada cambia.
        $this->call('POST', route('pagos-online.webhook', 'stripe'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $evento)
            ->assertStatus(400);
        $this->assertSame('PENDIENTE', $intento->fresh()->estado);

        // Con la firma correcta: acreditado.
        $this->call('POST', route('pagos-online.webhook', 'stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$marca},v1={$firma}",
        ], $evento)->assertOk()->assertJsonPath('estado', 'PAGADO');

        $this->assertSame('PAGADO', $intento->fresh()->estado);
        $this->assertSame('COMPLETADO', $pago->fresh()->estado);
        $this->assertSame('0.00', $pago->fresh()->monto_saldo);

        // Una marca de tiempo fuera de la tolerancia tampoco vale.
        $driver = new StripeDriver('sk_test', 'whsec_pruebas');
        $this->assertFalse($driver->firmaValida("t={$marca},v1={$firma}", $evento, $marca + StripeDriver::TOLERANCIA_SEGUNDOS + 1));
        $this->assertTrue($driver->firmaValida("t={$marca},v1={$firma}", $evento, $marca + 10));
    }

    public function test_con_stripe_el_retorno_del_navegador_no_marca_nada_como_pagado(): void
    {
        $pago = $this->reciboConSaldo();

        $intento = PagoOnline::create([
            'pago_id' => $pago->id,
            'paciente_id' => $this->paciente->id,
            'proveedor' => 'stripe',
            'referencia' => 'cs_test_456',
            'monto' => 160,
            'moneda' => 'BOB',
            'estado' => 'PENDIENTE',
        ]);

        $this->get($this->retorno($intento, 'exito'))->assertRedirect(route('portal.pagos.online', $intento));

        $this->assertSame('PENDIENTE', $intento->fresh()->estado);
        $this->assertSame('160.00', $pago->fresh()->monto_saldo);
    }
}
