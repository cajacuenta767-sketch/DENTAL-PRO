<?php

namespace Database\Seeders;

use App\Models\CicloEsterilizacion;
use App\Models\Conductometria;
use App\Models\Doctor;
use App\Models\ImplantePaciente;
use App\Models\LaboratorioDental;
use App\Models\OrdenLaboratorio;
use App\Models\Paciente;
use App\Models\TrazadoCefalometrico;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FasesNuevasDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Usuario::where('email', 'admin@admin.com')->first() ?? Usuario::first();
        $paciente = Paciente::first();
        $doctor = Doctor::first();

        // 1. Ciclos de esterilización
        CicloEsterilizacion::updateOrCreate(
            ['numero_ciclo' => 1, 'fecha' => now()->toDateString()],
            [
                'autoclave_nombre' => 'Autoclave Clase B - Sala Quirúrgica',
                'hora_inicio' => '08:30',
                'hora_fin' => '09:15',
                'temperatura' => 134.0,
                'presion' => 2.10,
                'tiempo_esterilizacion' => 18,
                'tipo_carga' => 'INSTRUMENTAL_QUIRURGICO',
                'indicador_quimico' => 'CONFORME',
                'indicador_biologico' => 'NEGATIVO',
                'resultado' => 'APROBADO',
                'paquetes_esterilizados' => 12,
                'fecha_caducidad_paquetes' => now()->addDays(30)->toDateString(),
                'qr_token' => Str::random(32),
                'usuario_id' => $admin?->id,
                'observaciones' => 'Ciclo matutino de instrumental para cirugía de implantes y endodoncia.',
            ]
        );

        if ($paciente) {
            // 2. Implante dental con pasaporte
            ImplantePaciente::updateOrCreate(
                ['paciente_id' => $paciente->id, 'posicion_fdi' => 16],
                [
                    'marca' => 'Straumann',
                    'modelo' => 'BLX Roxolid SLActive',
                    'numero_lote' => 'LOT-ST998822',
                    'numero_serie' => 'SN-882211',
                    'diametro_mm' => 4.10,
                    'longitud_mm' => 10.00,
                    'tipo_conexion' => 'CONO_MORSE',
                    'torque_insercion_ncm' => 45.0,
                    'isq_estabilidad' => 78,
                    'injerto_oseo' => 'Bio-Oss 0.5g',
                    'membrana' => 'Bio-Gide 25x25',
                    'fecha_colocacion' => now()->subMonths(2)->toDateString(),
                    'doctor_id' => $doctor?->id,
                    'estado' => 'OSEOINTEGRADO',
                    'qr_pasaporte_token' => Str::random(32),
                    'observaciones' => 'Estabilidad primaria óptima. Planificada corona de zirconio sobre implante.',
                ]
            );

            // 3. Conductometría (Endodoncia)
            Conductometria::updateOrCreate(
                ['paciente_id' => $paciente->id, 'diente' => 26],
                [
                    'fecha' => now()->subDays(5)->toDateString(),
                    'doctor_id' => $doctor?->id,
                    'diagnostico_pulpar' => 'Pulpitis irreversible sintomática',
                    'diagnostico_periapical' => 'Periodontitis apical sintomática',
                    'solucion_irrigante' => 'NaOCl 5.25% + EDTA 17%',
                    'medicacion_intraconducto' => 'Hidróxido de Calcio',
                    'cemento_sellador' => 'AH Plus Biocerámico',
                    'tecnica_obturacion' => 'Cono único biocerámico',
                    'estado' => 'OBTURADO',
                    'observaciones' => 'Conducto MV2 permeable y sellado tridimensionalmente.',
                    'conductos' => [
                        ['nombre' => 'MV1', 'referencia' => 'Cúspide MV', 'longitud_tentativa' => 21.0, 'longitud_trabajo' => 20.5, 'lima_apical' => '25.04'],
                        ['nombre' => 'MV2', 'referencia' => 'Cúspide MV', 'longitud_tentativa' => 20.5, 'longitud_trabajo' => 20.0, 'lima_apical' => '20.04'],
                        ['nombre' => 'DV', 'referencia' => 'Cúspide DV', 'longitud_tentativa' => 20.0, 'longitud_trabajo' => 19.5, 'lima_apical' => '25.04'],
                        ['nombre' => 'P', 'referencia' => 'Cúspide Palatina', 'longitud_tentativa' => 22.0, 'longitud_trabajo' => 21.5, 'lima_apical' => '35.04'],
                    ],
                ]
            );

            // 4. Cefalometría (Ortodoncia)
            TrazadoCefalometrico::updateOrCreate(
                ['paciente_id' => $paciente->id],
                [
                    'fecha' => now()->subDays(10)->toDateString(),
                    'doctor_id' => $doctor?->id,
                    'medidas' => [
                        'SNA' => 82.0,
                        'SNB' => 80.0,
                        'ANB' => 2.0,
                        'GoGn_SN' => 32.0,
                        'U1_NA_deg' => 22.0,
                        'L1_NB_deg' => 25.0,
                    ],
                    'diagnostico_esqueletico' => 'Clase I esquelética (Patrón Mesofacial armónico)',
                    'interpretacion' => 'Perfil armónico. Plan de ortodoncia correctiva con brackets de autoligado.',
                ]
            );
        }

        // 5. Laboratorio Dental y Orden
        $lab = LaboratorioDental::firstOrCreate(
            ['nombre' => 'Laboratorio Dental Élite'],
            [
                'contacto' => 'Téc. Roberto Gómez',
                'telefono' => '+51987112233',
                'email' => 'contacto@labelite.com',
                'direccion' => 'Av. Los Próceres 450',
                'activo' => true,
            ]
        );

        if ($paciente && $doctor && $lab) {
            OrdenLaboratorio::updateOrCreate(
                ['folio' => 'LAB-2026-0001'],
                [
                    'laboratorio_id' => $lab->id,
                    'paciente_id' => $paciente->id,
                    'doctor_id' => $doctor->id,
                    'tipo_trabajo' => 'Corona Monolítica de Zirconio',
                    'piezas_dentales' => '16',
                    'color_vita' => 'A2',
                    'fecha_envio' => now()->subDays(3)->toDateString(),
                    'fecha_prometida' => now()->addDays(4)->toDateString(),
                    'costo_laboratorio' => 150.00,
                    'precio_paciente' => 350.00,
                    'estado' => 'EN_PROCESO',
                    'notas_tecnicas' => 'Corona monolítica sobre implante Straumann BLX conexión Cono Morse.',
                ]
            );
        }
    }
}
