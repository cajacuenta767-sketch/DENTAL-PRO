<?php

namespace Tests\Feature;

use App\Models\Odontograma;
use Tests\CasoClinico;

class OdontogramaComparativoTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin);
    }

    private function registrar(string $fecha, array $piezas): void
    {
        $this->post("/admin/pacientes/{$this->paciente->id}/odontograma", [
            'tipo' => 'ADULTO',
            'fecha' => $fecha,
            'doctor_id' => $this->doctor->id,
            'piezas' => json_encode($piezas),
        ])->assertRedirect();
    }

    public function test_el_indice_compara_cada_odontograma_con_el_anterior(): void
    {
        $this->registrar(now()->subMonths(6)->toDateString(), [
            '16' => ['caras' => ['oclusal' => 'caries']],
            '21' => ['estado' => 'corona'],
        ]);

        $this->registrar(now()->toDateString(), [
            '16' => ['caras' => ['oclusal' => 'obturado']],
            '36' => ['caras' => ['distal' => 'caries']],
        ]);

        $this->assertSame(2, Odontograma::count());

        $respuesta = $this->get("/admin/pacientes/{$this->paciente->id}/odontograma")
            ->assertOk()
            ->assertSee('Comparar con el anterior')
            ->assertSee('Nuevo')
            ->assertSee('Resuelto')
            ->assertSee('Cambió')
            ->assertSee('Pieza 36')
            ->assertSee('Pieza 21')
            ->assertSee('Pieza 16')
            ->assertSee('data-comparacion-actual', false);

        // Las piezas cambiadas viajan al script que las resalta en la arcada.
        $html = $respuesta->getContent();
        $this->assertMatchesRegularExpression('/data-cambios=\'\[[^\]]*"16"[^\]]*\]\'/', $html);
        $this->assertMatchesRegularExpression('/data-cambios=\'\[[^\]]*"36"[^\]]*\]\'/', $html);
        $this->assertStringContainsString('.pieza-cambiada .pieza-anillo', $html);
    }

    public function test_el_primer_odontograma_no_tiene_comparacion(): void
    {
        $this->registrar(now()->toDateString(), ['11' => ['caras' => ['mesial' => 'caries']]]);

        $this->get("/admin/pacientes/{$this->paciente->id}/odontograma")
            ->assertOk()
            ->assertDontSee('Comparar con el anterior');
    }

    public function test_solo_compara_con_odontogramas_de_la_misma_denticion(): void
    {
        $this->post("/admin/pacientes/{$this->paciente->id}/odontograma", [
            'tipo' => 'INFANTIL',
            'fecha' => now()->subYear()->toDateString(),
            'piezas' => json_encode(['51' => ['estado' => 'caries']]),
        ])->assertRedirect();

        $this->registrar(now()->toDateString(), ['11' => ['estado' => 'caries']]);

        $this->get("/admin/pacientes/{$this->paciente->id}/odontograma")
            ->assertOk()
            ->assertDontSee('Comparar con el anterior');
    }
}
