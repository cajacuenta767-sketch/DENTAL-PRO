<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\DocumentoFiscal;
use App\Models\Pago;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\CasoClinico;

class TransmisionFiscalTest extends CasoClinico
{
    private Pago $pago;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin);

        Ajuste::actual()->update(['facturacion_activa' => true, 'facturacion_serie' => 'A', 'facturacion_tasa_iva' => 13]);

        $this->post('/admin/pagos', [
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'metodo_pago' => 'EFECTIVO',
            'monto_pagado' => 226,
            'fecha_pago' => now()->format('Y-m-d\TH:i'),
            'detalles' => [[
                'tratamiento_id' => $this->tratamiento->id,
                'descripcion' => 'PROFILAXIS DENTAL',
                'cantidad' => 1,
                'precio_unitario' => 226,
            ]],
        ]);

        $this->pago = Pago::first();
    }

    private function emitir(): DocumentoFiscal
    {
        $this->post('/admin/facturacion', [
            'pago_id' => $this->pago->id,
            'tipo' => 'FACTURA',
            'receptor_nombre' => $this->paciente->nombre_completo,
            'receptor_documento' => $this->paciente->numero_documento,
        ])->assertRedirect();

        return DocumentoFiscal::firstOrFail();
    }

    public function test_con_el_proveedor_simulado_el_documento_queda_aceptado_con_sello(): void
    {
        config(['services.facturacion_electronica.proveedor' => 'simulado']);

        $documento = $this->emitir();

        $this->assertSame('simulado', $documento->proveedor);
        $this->assertSame('ACEPTADO', $documento->estado_transmision);
        $this->assertNotEmpty($documento->sello_recepcion);
        $this->assertSame($documento->respuesta_proveedor['sello'], $documento->sello_recepcion);
        $this->assertNotNull($documento->transmitido_en);

        $this->get("/admin/facturacion/{$documento->id}")
            ->assertOk()
            ->assertSee('Aceptado')
            ->assertDontSee('Reintentar transmisión');
    }

    public function test_sin_proveedor_el_documento_queda_como_no_aplica(): void
    {
        config(['services.facturacion_electronica.proveedor' => 'ninguno']);

        $documento = $this->emitir();

        $this->assertSame('NO_APLICA', $documento->estado_transmision);
        $this->assertNull($documento->transmitido_en);

        $this->get("/admin/facturacion/{$documento->id}")->assertOk()->assertDontSee('Reintentar transmisión');
    }

    public function test_el_proveedor_http_rechaza_y_el_reintento_acepta(): void
    {
        config([
            'services.facturacion_electronica.proveedor' => 'http',
            'services.facturacion_electronica.endpoint' => 'https://fiscal.test/api/dte',
            'services.facturacion_electronica.token' => 'secreto',
        ]);

        // Primer intento: el proveedor falla; reintento: acepta con sello.
        Http::fakeSequence('fiscal.test/*')
            ->push(['mensaje' => 'Servicio no disponible'], 500)
            ->push(['aceptado' => true, 'sello' => 'SELLO-OK-123', 'codigo' => '001', 'mensaje' => 'Recibido'], 200);

        $documento = $this->emitir();

        $this->assertSame('http', $documento->proveedor);
        $this->assertSame('RECHAZADO', $documento->estado_transmision);
        $this->assertSame('Servicio no disponible', $documento->respuesta_proveedor['mensaje']);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secreto')
            && $request['numero_control'] === $documento->numero_control);

        $this->get("/admin/facturacion/{$documento->id}")
            ->assertOk()
            ->assertSee('Rechazado')
            ->assertSee('Servicio no disponible')
            ->assertSee('Reintentar transmisión');

        $this->post("/admin/facturacion/{$documento->id}/transmitir")->assertRedirect();

        $documento->refresh();

        $this->assertSame('ACEPTADO', $documento->estado_transmision);
        $this->assertSame('SELLO-OK-123', $documento->sello_recepcion);
        $this->assertSame('Recibido', $documento->respuesta_proveedor['mensaje']);
    }

    public function test_un_error_de_conexion_deja_el_documento_rechazado(): void
    {
        config([
            'services.facturacion_electronica.proveedor' => 'http',
            'services.facturacion_electronica.endpoint' => 'https://fiscal.test/api/dte',
        ]);

        Http::fake(fn () => throw new ConnectionException('timeout'));

        $documento = $this->emitir();

        $this->assertSame('RECHAZADO', $documento->estado_transmision);
        $this->assertStringContainsString('timeout', $documento->respuesta_proveedor['mensaje']);
    }
}
