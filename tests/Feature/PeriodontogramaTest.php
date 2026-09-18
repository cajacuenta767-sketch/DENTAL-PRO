<?php

namespace Tests\Feature;

use App\Models\Periodontograma;
use App\Models\Usuario;
use Tests\CasoClinico;

class PeriodontogramaTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin);
    }

    private function ruta(string $sufijo = ''): string
    {
        return "/admin/pacientes/{$this->paciente->id}/periodontograma{$sufijo}";
    }

    public function test_guarda_el_sondaje_saneado_y_calcula_los_indices(): void
    {
        $enviado = [
            '16' => [
                'sondaje' => ['dv' => 5, 'v' => 3, 'mv' => 7, 'dl' => 2, 'l' => 2, 'ml' => 3],
                'sangrado' => ['dv' => true, 'v' => false, 'mv' => 1],
                'placa' => ['dv' => 'true'],
                'movilidad' => 2,
                'furca' => 1,
                'nota' => '  Furca vestibular  ',
            ],
            '48' => ['ausente' => true, 'sondaje' => ['dv' => 9]],
            '99' => ['sondaje' => ['dv' => 3]],
        ];

        $this->post($this->ruta(), [
            'fecha' => now()->toDateString(),
            'doctor_id' => $this->doctor->id,
            'piezas' => json_encode($enviado),
            'observaciones' => 'Primer sondaje.',
        ])->assertRedirect($this->ruta());

        $periodontograma = Periodontograma::first();
        $piezas = $periodontograma->piezas;

        $this->assertCount(32, $piezas, 'Se registran las 32 piezas de la dentición permanente.');
        $this->assertArrayNotHasKey('99', $piezas, 'Una pieza fuera de la numeración FDI se descarta.');
        $this->assertSame($this->admin->id, $periodontograma->usuario_id);

        $this->assertSame(5, $piezas['16']['sondaje']['dv']);
        $this->assertSame(7, $piezas['16']['sondaje']['mv']);
        $this->assertTrue($piezas['16']['sangrado']['dv']);
        $this->assertTrue($piezas['16']['sangrado']['mv']);
        $this->assertFalse($piezas['16']['sangrado']['v']);
        $this->assertTrue($piezas['16']['placa']['dv']);
        $this->assertFalse($piezas['16']['placa']['l']);
        $this->assertSame(2, $piezas['16']['movilidad']);
        $this->assertSame(1, $piezas['16']['furca']);
        $this->assertSame('Furca vestibular', $piezas['16']['nota']);
        $this->assertTrue($piezas['48']['ausente']);
        $this->assertNull($piezas['11']['sondaje']['dv']);

        $indices = $periodontograma->indices();

        // 31 piezas presentes × 6 sitios; la pieza ausente no cuenta.
        $this->assertSame(186, $indices['sitios']);
        $this->assertSame(1, $indices['ausentes']);
        $this->assertSame(2, $indices['bolsas'], 'Los sitios de 5 y 7 mm son bolsas (≥ 4 mm).');
        $this->assertSame(1, $indices['bolsas_profundas'], 'Solo el sitio de 7 mm es bolsa profunda (≥ 6 mm).');
        $this->assertSame(1, $indices['movilidad']);
        $this->assertSame(round(22 / 6, 2), $indices['profundidad_media']);
        $this->assertSame(round(2 / 186 * 100, 1), $indices['sangrado']);
        $this->assertStringContainsString('periodontitis avanzada', $periodontograma->diagnosticoOrientativo());
    }

    public function test_los_valores_fuera_de_rango_se_descartan(): void
    {
        $this->post($this->ruta(), [
            'fecha' => now()->toDateString(),
            'piezas' => json_encode([
                '11' => [
                    'sondaje' => ['dv' => 20, 'v' => -1, 'mv' => 'abc', 'dl' => 15, 'l' => 2.5, 'ml' => ''],
                    'recesion' => ['dv' => 11, 'v' => 10, 'mv' => 0],
                    'movilidad' => 7,
                    'furca' => -2,
                    'nota' => str_repeat('x', 300),
                ],
            ]),
        ])->assertRedirect();

        $pieza = Periodontograma::first()->piezas['11'];

        $this->assertNull($pieza['sondaje']['dv'], 'Un sondaje de 20 mm excede el máximo y se descarta.');
        $this->assertNull($pieza['sondaje']['v']);
        $this->assertNull($pieza['sondaje']['mv']);
        $this->assertSame(15, $pieza['sondaje']['dl']);
        $this->assertNull($pieza['sondaje']['l'], 'Solo se aceptan enteros.');
        $this->assertNull($pieza['sondaje']['ml']);
        $this->assertNull($pieza['recesion']['dv']);
        $this->assertSame(10, $pieza['recesion']['v']);
        $this->assertSame(0, $pieza['recesion']['mv']);
        $this->assertSame(0, $pieza['movilidad'], 'Un grado inexistente se normaliza a cero.');
        $this->assertSame(0, $pieza['furca']);
        $this->assertSame(255, mb_strlen($pieza['nota']));
        $this->assertFalse($pieza['ausente']);
    }

    public function test_rechaza_una_fecha_futura_y_un_json_invalido_no_rompe(): void
    {
        $this->from($this->ruta('/nuevo'))
            ->post($this->ruta(), ['fecha' => now()->addDay()->toDateString(), 'piezas' => '{}'])
            ->assertRedirect($this->ruta('/nuevo'))
            ->assertSessionHasErrors('fecha');

        $this->post($this->ruta(), ['fecha' => now()->toDateString(), 'piezas' => 'esto no es json'])
            ->assertRedirect($this->ruta());

        $this->assertCount(32, Periodontograma::first()->piezas);
    }

    public function test_el_pdf_se_genera(): void
    {
        $periodontograma = Periodontograma::create([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'usuario_id' => $this->admin->id,
            'fecha' => now()->toDateString(),
            'piezas' => array_replace(Periodontograma::piezasEnBlanco(), [
                '21' => array_replace(Periodontograma::piezaEnBlanco(), [
                    'sondaje' => ['dv' => 4, 'v' => 2, 'mv' => 6, 'dl' => 3, 'l' => 3, 'ml' => 3],
                    'sangrado' => ['dv' => true, 'v' => false, 'mv' => true, 'dl' => false, 'l' => false, 'ml' => false],
                ]),
            ]),
        ]);

        $this->get("/admin/periodontogramas/{$periodontograma->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_un_control_puede_partir_del_ultimo_periodontograma(): void
    {
        $anterior = Periodontograma::create([
            'paciente_id' => $this->paciente->id,
            'usuario_id' => $this->admin->id,
            'fecha' => now()->subMonths(3)->toDateString(),
            'piezas' => ['36' => ['sondaje' => ['dv' => 5, 'v' => 4], 'sangrado' => ['dv' => true], 'movilidad' => 1]],
        ]);

        $this->get($this->ruta('/nuevo'))
            ->assertOk()
            ->assertViewHas('periodontograma', fn ($p) => $p->piezas['36']['sondaje']['dv'] === null)
            ->assertViewHas('anterior', fn ($a) => $a->is($anterior));

        $this->get($this->ruta('/nuevo?desde=ultimo'))
            ->assertOk()
            ->assertViewHas('periodontograma', function ($p) {
                return $p->piezas['36']['sondaje']['dv'] === 5
                    && $p->piezas['36']['sondaje']['v'] === 4
                    && $p->piezas['36']['sangrado']['dv'] === true
                    && $p->piezas['36']['movilidad'] === 1
                    && count($p->piezas) === 32;
            });
    }

    public function test_el_listado_muestra_indices_y_diagnostico(): void
    {
        Periodontograma::create([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'usuario_id' => $this->admin->id,
            'fecha' => now()->toDateString(),
            'piezas' => ['11' => ['sondaje' => ['dv' => 5, 'v' => 2, 'mv' => 2]]],
        ]);

        $this->get($this->ruta())
            ->assertOk()
            ->assertSee('periodontitis moderada')
            ->assertSee('Control desde el último');
    }

    public function test_actualiza_y_elimina(): void
    {
        $periodontograma = Periodontograma::create([
            'paciente_id' => $this->paciente->id,
            'usuario_id' => $this->admin->id,
            'fecha' => now()->toDateString(),
            'piezas' => Periodontograma::piezasEnBlanco(),
        ]);

        $this->get("/admin/periodontogramas/{$periodontograma->id}/editar")->assertOk();

        $this->put("/admin/periodontogramas/{$periodontograma->id}", [
            'fecha' => now()->toDateString(),
            'piezas' => json_encode(['26' => ['sondaje' => ['l' => 3], 'placa' => ['l' => true]]]),
            'observaciones' => 'Control.',
        ])->assertRedirect($this->ruta());

        $this->assertSame(3, $periodontograma->fresh()->piezas['26']['sondaje']['l']);
        $this->assertSame('Control.', $periodontograma->fresh()->observaciones);

        $this->delete("/admin/periodontogramas/{$periodontograma->id}")->assertRedirect($this->ruta());
        $this->assertSoftDeleted('periodontogramas', ['id' => $periodontograma->id]);
    }

    public function test_recepcion_puede_ver_pero_no_registrar(): void
    {
        $recepcion = Usuario::create([
            'nombre' => 'Recepción',
            'email' => 'recepcion@pruebas.test',
            'password' => 'secreto123',
            'estado' => 'activo',
        ]);
        $recepcion->assignRole('RECEPCION');

        $this->actingAs($recepcion);

        $this->get($this->ruta())->assertOk();

        $this->post($this->ruta(), [
            'fecha' => now()->toDateString(),
            'piezas' => '{}',
        ])->assertForbidden();

        $this->assertSame(0, Periodontograma::count());
    }
}
