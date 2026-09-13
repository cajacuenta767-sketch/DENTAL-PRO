<?php

namespace Tests\Feature;

use App\Models\DocumentoClinico;
use Tests\CasoClinico;

class DocumentoClinicoTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tipo' => 'RECETA',
            'titulo' => 'Receta médica',
            'contenido' => 'Amoxicilina 500 mg cada 8 horas por 7 días.',
            'fecha_emision' => now()->toDateString(),
            'vigencia_dias' => 30,
        ], $extra);
    }

    public function test_emitir_genera_un_folio_correlativo_por_tipo(): void
    {
        $this->post('/admin/documentos', $this->datos());
        $this->post('/admin/documentos', $this->datos());
        $this->post('/admin/documentos', $this->datos(['tipo' => 'CERTIFICADO', 'titulo' => 'Certificado']));

        $anio = now()->year;

        $this->assertSame("REC-{$anio}-00001", DocumentoClinico::find(1)->folio);
        $this->assertSame("REC-{$anio}-00002", DocumentoClinico::find(2)->folio);
        $this->assertSame("CER-{$anio}-00001", DocumentoClinico::find(3)->folio);
    }

    public function test_la_vigencia_se_calcula_desde_la_emision(): void
    {
        $this->post('/admin/documentos', $this->datos(['vigencia_dias' => 10]));

        $documento = DocumentoClinico::first();

        $this->assertSame(now()->addDays(10)->toDateString(), $documento->vence_el->toDateString());
        $this->assertTrue($documento->esta_vigente);
    }

    public function test_un_documento_sin_vigencia_nunca_caduca(): void
    {
        $this->post('/admin/documentos', $this->datos(['vigencia_dias' => null]));

        $documento = DocumentoClinico::first();

        $this->assertNull($documento->vence_el);
        $this->assertTrue($documento->esta_vigente);
    }

    public function test_anular_invalida_el_documento_pero_lo_conserva(): void
    {
        $this->post('/admin/documentos', $this->datos());
        $documento = DocumentoClinico::first();

        $this->patch("/admin/documentos/{$documento->id}/anular");

        $this->assertSame('ANULADO', $documento->fresh()->estado);
        $this->assertFalse($documento->fresh()->esta_vigente);
        $this->assertSame(1, DocumentoClinico::count());
    }

    public function test_un_documento_anulado_ya_no_se_edita(): void
    {
        $this->post('/admin/documentos', $this->datos());
        $documento = DocumentoClinico::first();
        $this->patch("/admin/documentos/{$documento->id}/anular");

        $this->put("/admin/documentos/{$documento->id}", $this->datos(['titulo' => 'Cambiado']));

        $this->assertSame('Receta médica', $documento->fresh()->titulo);
    }

    public function test_el_pdf_se_genera(): void
    {
        $this->post('/admin/documentos', $this->datos());

        $respuesta = $this->get('/admin/documentos/'.DocumentoClinico::first()->id.'/pdf')->assertOk();

        $this->assertSame('application/pdf', $respuesta->headers->get('content-type'));
    }
}
