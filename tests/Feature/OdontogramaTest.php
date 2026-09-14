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

    public function test_registra_movilidad_urgencia_y_genera_el_resumen(): void
    {
        $this->post("/admin/pacientes/{$this->paciente->id}/odontograma", [
            'tipo' => 'ADULTO',
            'fecha' => now()->toDateString(),
            'piezas' => json_encode([
                '16' => ['estado' => 'sano', 'caras' => ['oclusal' => 'caries'], 'movilidad' => 2, 'urgente' => true, 'nota' => 'Dolor al frío'],
                '26' => ['estado' => 'corona', 'caras' => [], 'movilidad' => 9],
            ]),
        ])->assertRedirect();

        $odontograma = Odontograma::first();

        $this->assertSame(2, $odontograma->piezas['16']['movilidad']);
        $this->assertTrue($odontograma->piezas['16']['urgente']);
        $this->assertSame(0, $odontograma->piezas['26']['movilidad'], 'Un grado inexistente se normaliza a cero.');
        $this->assertFalse($odontograma->piezas['26']['urgente']);
        $this->assertSame(['16'], $odontograma->piezas_urgentes);

        $resumen = $odontograma->resumenTexto();
        $this->assertStringContainsString('Pieza 16: caries (oclusal), movilidad Grado II [URGENTE] — Dolor al frío.', $resumen);
        $this->assertStringContainsString('Pieza 26: corona.', $resumen);
    }

    public function test_un_control_puede_partir_del_ultimo_odontograma_y_marca_los_cambios(): void
    {
        $anterior = Odontograma::create([
            'paciente_id' => $this->paciente->id,
            'tipo' => 'ADULTO',
            'fecha' => now()->subMonth()->toDateString(),
            'piezas' => ['16' => ['estado' => 'sano', 'caras' => ['oclusal' => 'caries']]],
        ]);

        $this->get("/admin/pacientes/{$this->paciente->id}/odontograma/nuevo?desde=ultimo")
            ->assertOk()
            ->assertViewHas('odontograma', fn ($o) => $o->piezas['16']['caras']['oclusal'] === 'caries')
            ->assertViewHas('anterior', fn ($a) => $a->is($anterior));

        $nuevo = new Odontograma(['piezas' => [
            '16' => ['estado' => 'sano', 'caras' => ['oclusal' => 'obturado']],
            '21' => ['estado' => 'ausente', 'caras' => []],
        ]]);

        $cambios = $nuevo->cambiosRespectoA($anterior->piezas);

        $this->assertCount(2, $cambios);
        $this->assertSame(['pieza' => '16', 'cara' => 'oclusal', 'antes' => 'caries', 'despues' => 'obturado'], $cambios[0]);
        $this->assertSame(['pieza' => '21', 'cara' => null, 'antes' => 'sano', 'despues' => 'ausente'], $cambios[1]);
    }
}
