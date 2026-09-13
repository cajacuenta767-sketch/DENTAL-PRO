<?php

namespace Tests\Feature;

use App\Models\Odontograma;
use Tests\CasoClinico;

class OdontogramaTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin);
    }

    public function test_guarda_los_hallazgos_por_cara_y_descarta_valores_invalidos(): void
    {
        $enviado = [
            '11' => ['estado' => 'sano', 'caras' => ['oclusal' => 'caries', 'mesial' => 'inventado']],
            '46' => ['estado' => 'ausente', 'caras' => []],
        ];

        $this->post("/admin/pacientes/{$this->paciente->id}/odontograma", [
            'tipo' => 'ADULTO',
            'fecha' => now()->toDateString(),
            'doctor_id' => $this->doctor->id,
            'piezas' => json_encode($enviado),
        ])->assertRedirect();

        $piezas = Odontograma::first()->piezas;

        $this->assertCount(32, $piezas, 'Un odontograma adulto registra las 32 piezas.');
        $this->assertSame('caries', $piezas['11']['caras']['oclusal']);
        $this->assertSame('sano', $piezas['11']['caras']['mesial'], 'Un hallazgo desconocido se normaliza a sano.');
        $this->assertSame('ausente', $piezas['46']['estado']);
        $this->assertSame('sano', $piezas['27']['estado']);
    }

    public function test_el_odontograma_infantil_usa_la_denticion_temporal(): void
    {
        $this->post("/admin/pacientes/{$this->paciente->id}/odontograma", [
            'tipo' => 'INFANTIL',
            'fecha' => now()->toDateString(),
            'piezas' => json_encode([]),
        ])->assertRedirect();

        $piezas = Odontograma::first()->piezas;

        $this->assertCount(20, $piezas);
        $this->assertArrayHasKey('51', $piezas);
        $this->assertArrayNotHasKey('11', $piezas);
    }

    public function test_cuenta_las_piezas_con_hallazgo(): void
    {
        $this->post("/admin/pacientes/{$this->paciente->id}/odontograma", [
            'tipo' => 'ADULTO',
            'fecha' => now()->toDateString(),
            'piezas' => json_encode([
                '11' => ['caras' => ['oclusal' => 'caries']],
                '12' => ['estado' => 'corona'],
                '13' => ['caras' => ['distal' => 'obturado']],
            ]),
        ]);

        $this->assertSame(3, Odontograma::first()->piezas_afectadas);
    }
}
