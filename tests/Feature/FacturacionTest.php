<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\DocumentoFiscal;
use App\Models\Pago;
use Tests\CasoClinico;

class FacturacionTest extends CasoClinico
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

    private function emitir(array $extra = []): void
    {
        $this->post('/admin/facturacion', array_merge([
            'pago_id' => $this->pago->id,
            'tipo' => 'FACTURA',
            'receptor_nombre' => $this->paciente->nombre_completo,
            'receptor_documento' => $this->paciente->numero_documento,
        ], $extra));
    }

    public function test_emitir_desglosa_el_iva_del_total_cobrado(): void
    {
        $this->emitir();

        $documento = DocumentoFiscal::first();

        // 226 con IVA del 13% → base 200, impuesto 26.
        $this->assertSame('200.00', $documento->subtotal);
        $this->assertSame('26.00', $documento->iva);
        $this->assertSame('226.00', $documento->total);
    }

    public function test_el_numero_de_control_sigue_el_formato_del_dte(): void
    {
        $this->emitir();

        $documento = DocumentoFiscal::first();

        $this->assertMatchesRegularExpression('/^DTE-01-000A-\d{15}$/', $documento->numero_control);
        $this->assertSame(1, $documento->correlativo);
        $this->assertNotEmpty($documento->codigo_generacion);
        $this->assertNotEmpty($documento->sello_recepcion);
    }

    public function test_los_correlativos_avanzan_por_tipo_y_serie(): void
    {
        $this->emitir();

        $segundo = Pago::create([
            'codigo_recibo' => Pago::siguienteCodigo(),
            'paciente_id' => $this->paciente->id,
            'monto_total' => 100,
            'monto_pagado' => 100,
            'monto_saldo' => 0,
            'estado' => 'COMPLETADO',
            'metodo_pago' => 'EFECTIVO',
            'fecha_pago' => now(),
        ]);

        $this->emitir(['pago_id' => $segundo->id]);

        $this->assertSame([1, 2], DocumentoFiscal::orderBy('id')->pluck('correlativo')->all());
    }

    public function test_un_recibo_no_admite_dos_documentos_vigentes(): void
    {
        $this->emitir();
        $this->from('/admin/facturacion')->emitir();

        $this->assertSame(1, DocumentoFiscal::count());
    }

    public function test_el_documento_guarda_una_copia_de_las_lineas(): void
    {
        $this->emitir();

        $lineas = DocumentoFiscal::first()->contenido['lineas'];

        $this->assertCount(1, $lineas);
        $this->assertSame('PROFILAXIS DENTAL', $lineas[0]['descripcion']);
    }

    public function test_anular_saca_el_documento_de_los_totales(): void
    {
        $this->emitir();
        $documento = DocumentoFiscal::first();

        $this->patch("/admin/facturacion/{$documento->id}/anular", ['motivo' => 'Datos del receptor incorrectos']);

        $this->assertSame('ANULADO', $documento->fresh()->estado);
        $this->assertStringContainsString('Datos del receptor', $documento->fresh()->motivo_anulacion);
        $this->assertSame(0, DocumentoFiscal::where('estado', 'EMITIDO')->count());
    }

    public function test_tras_anular_se_puede_volver_a_facturar_el_recibo(): void
    {
        $this->emitir();
        $this->patch('/admin/facturacion/'.DocumentoFiscal::first()->id.'/anular', ['motivo' => 'Error']);

        $this->emitir(['tipo' => 'CREDITO_FISCAL']);

        $this->assertSame(2, DocumentoFiscal::count());
        $this->assertSame(1, DocumentoFiscal::where('estado', 'EMITIDO')->count());
    }
}
