<?php

namespace Tests\Feature;

use App\Models\Conductometria;
use App\Models\Odontograma;
use App\Models\TrazadoCefalometrico;
use Tests\CasoClinico;

class EspecialidadesClinicasTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin);
    }

    public function test_puede_registrar_conductometria_con_matriz_de_conductos(): void
    {
        $response = $this->post(route('admin.conductometrias.store', $this->paciente), [
            'diente' => 16,
            'fecha' => now()->toDateString(),
            'doctor_id' => $this->doctor->id,
            'diagnostico_pulpar' => 'Pulpitis irreversible sintomática',
            'diagnostico_periapical' => 'Periodontitis apical sintomática',
            'solucion_irrigante' => 'Hipoclorito de Sodio 5.25% + EDTA 17%',
            'medicacion_intraconducto' => 'Hidróxido de Calcio',
            'cemento_sellador' => 'AH Plus',
            'tecnica_obturacion' => 'Cono único biocerámico',
            'estado' => 'EN_TRATAMIENTO',
            'observaciones' => 'Conducto MV2 muy estrecho pero permeable.',
            'conductos' => [
                [
                    'nombre' => 'MV1',
                    'referencia' => 'Cúspide MV',
                    'longitud_aparente' => 21.0,
                    'longitud_trabajo' => 20.5,
                    'lima_apical' => '25.04',
                    'tecnica' => 'Rotatoria',
                    'observaciones' => 'Permeable',
                ],
                [
                    'nombre' => 'MV2',
                    'referencia' => 'Cúspide MV',
                    'longitud_aparente' => 20.5,
                    'longitud_trabajo' => 20.0,
                    'lima_apical' => '20.04',
                    'tecnica' => 'Rotatoria',
                    'observaciones' => 'Curvatura moderada',
                ],
                [
                    'nombre' => 'Palatino',
                    'referencia' => 'Cúspide Palatina',
                    'longitud_aparente' => 22.5,
                    'longitud_trabajo' => 22.0,
                    'lima_apical' => '35.04',
                    'tecnica' => 'Rotatoria',
                    'observaciones' => 'Amplio',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.conductometrias.index', $this->paciente));
        $this->assertDatabaseHas('conductometrias', [
            'paciente_id' => $this->paciente->id,
            'diente' => 16,
            'estado' => 'EN_TRATAMIENTO',
            'solucion_irrigante' => 'Hipoclorito de Sodio 5.25% + EDTA 17%',
        ]);
    }

    public function test_puede_actualizar_estado_de_conductometria_a_obturado(): void
    {
        $conductometria = Conductometria::create([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'usuario_id' => $this->admin->id,
            'diente' => 46,
            'fecha' => now()->toDateString(),
            'estado' => 'EN_TRATAMIENTO',
            'conductos' => [
                ['nombre' => 'MV', 'longitud_trabajo' => 21.0, 'lima_apical' => '25.04'],
                ['nombre' => 'ML', 'longitud_trabajo' => 21.0, 'lima_apical' => '25.04'],
                ['nombre' => 'Distal', 'longitud_trabajo' => 22.0, 'lima_apical' => '35.04'],
            ],
        ]);

        $response = $this->put(route('admin.conductometrias.update', $conductometria), [
            'diente' => 46,
            'fecha' => now()->toDateString(),
            'doctor_id' => $this->doctor->id,
            'estado' => 'OBTURADO',
            'tecnica_obturacion' => 'Cono único biocerámico',
            'cemento_sellador' => 'Bio-C Sealer',
            'conductos' => $conductometria->conductos,
        ]);

        $response->assertRedirect(route('admin.conductometrias.index', $this->paciente));
        $this->assertDatabaseHas('conductometrias', [
            'id' => $conductometria->id,
            'estado' => 'OBTURADO',
            'tecnica_obturacion' => 'Cono único biocerámico',
        ]);
    }

    public function test_puede_generar_pdf_de_conductometria(): void
    {
        $conductometria = Conductometria::create([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'usuario_id' => $this->admin->id,
            'diente' => 21,
            'fecha' => now()->toDateString(),
            'estado' => 'FINALIZADO',
            'diagnostico_pulpar' => 'Necrosis Pulpar',
            'conductos' => [
                ['nombre' => 'Conducto Principal', 'longitud_trabajo' => 23.0, 'lima_apical' => '40'],
            ],
        ]);

        $response = $this->get(route('admin.conductometrias.pdf', $conductometria));
        $response->assertOk();
    }

    public function test_computo_cefalometrico_steiner_y_clasificacion(): void
    {
        // 1. Puntos que definen Clase II (SNA mayor, SNB menor => ANB > 4)
        // Vértice en Nasion (N: 100, 100). Sella (S: 50, 100). Punto A (A: 110, 150). Punto B (B: 95, 180).
        $analisis = TrazadoCefalometrico::computarAnalisis([
            'S' => ['x' => 50, 'y' => 100],
            'N' => ['x' => 150, 'y' => 100],
            'A' => ['x' => 140, 'y' => 180],
            'B' => ['x' => 110, 'y' => 190],
            'Go' => ['x' => 60, 'y' => 220],
            'Gn' => ['x' => 130, 'y' => 230],
        ]);

        $this->assertArrayHasKey('SNA', $analisis['medidas']);
        $this->assertArrayHasKey('SNB', $analisis['medidas']);
        $this->assertArrayHasKey('ANB', $analisis['medidas']);
        $this->assertNotEmpty($analisis['diagnostico']);
        $this->assertNotEmpty($analisis['patron']);
    }

    public function test_puede_guardar_trazado_cefalometrico_con_medidas(): void
    {
        $response = $this->post(route('admin.cefalometrias.store', $this->paciente), [
            'fecha' => now()->toDateString(),
            'doctor_id' => $this->doctor->id,
            'tipo_analisis' => 'STEINER',
            'diagnostico_esqueletico' => 'CLASE_II',
            'patron_crecimiento' => 'DOLICOFACIAL',
            'medidas' => [
                'SNA' => 84.5,
                'SNB' => 78.0,
                'ANB' => 6.5,
                'GoGn_SN' => 38.0,
                'UI_NA_deg' => 26.0,
                'UI_NA_mm' => 6.0,
            ],
            'interpretacion' => 'Clase II esquelética por retrognatismo mandibular severo y crecimiento vertical.',
            'plan_tratamiento' => 'Propulsor mandibular / Aparatología funcional Twin Block.',
        ]);

        $response->assertRedirect(route('admin.cefalometrias.index', $this->paciente));
        $this->assertDatabaseHas('trazados_cefalometricos', [
            'paciente_id' => $this->paciente->id,
            'diagnostico_esqueletico' => 'CLASE_II',
            'patron_crecimiento' => 'DOLICOFACIAL',
        ]);
    }

    public function test_odontograma_soporta_denticion_mixta_y_escala_frankl(): void
    {
        $piezas = [];
        foreach (Odontograma::cuadrantes('MIXTO') as $cuadrante => $numeros) {
            foreach ($numeros as $num) {
                $piezas[(string) $num] = [
                    'estado' => 'sano',
                    'caras' => ['vestibular' => 'sano', 'lingual' => 'sano', 'mesial' => 'sano', 'distal' => 'sano', 'oclusal' => 'sano'],
                ];
            }
        }
        // Marcar caries en pieza temporal 54
        $piezas['54']['estado'] = 'caries';

        $response = $this->post(route('admin.odontogramas.store', $this->paciente), [
            'fecha' => now()->toDateString(),
            'doctor_id' => $this->doctor->id,
            'tipo' => 'MIXTO',
            'escala_frankl' => 4, // Definitivamente positivo
            'piezas' => json_encode($piezas),
            'observaciones' => 'Paciente colaborador, dentición mixta temprana.',
        ]);

        $response->assertRedirect(route('admin.odontogramas.index', $this->paciente));
        $this->assertDatabaseHas('odontogramas', [
            'paciente_id' => $this->paciente->id,
            'tipo' => 'MIXTO',
            'escala_frankl' => 4,
        ]);
    }
}
