<?php

namespace Database\Seeders;

use App\Models\Cita;
use App\Models\Doctor;
use App\Models\HistorialClinico;
use App\Models\Odontograma;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\Tratamiento;
use App\Models\Usuario;
use App\Services\AgendaService;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Genera un histórico verosímil de seis meses: pacientes, citas respetando
 * la disponibilidad real de cada doctor, historias clínicas, odontogramas
 * y los cobros correspondientes.
 */
class DemoClinicaSeeder extends Seeder
{
    private const PACIENTES = 50;

    private const CITAS = 200;

    public function __construct(private readonly AgendaService $agenda) {}

    public function run(): void
    {
        $pacientes = $this->crearPacientes();
        $citas = $this->crearCitas($pacientes);

        $this->crearHistoriales($citas);
        $this->crearOdontogramas($citas);
        $this->crearPagos($citas);

        $this->command->info(
            'Pacientes: '.Paciente::count().
            ' · Citas: '.Cita::count().
            ' · Historias: '.HistorialClinico::count().
            ' · Odontogramas: '.Odontograma::count().
            ' · Recibos: '.Pago::count()
        );
    }

    private function crearPacientes()
    {
        $faker = Factory::create('es_ES');
        $grupos = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        $alergias = ['Penicilina', 'Látex', 'Lidocaína', 'Ibuprofeno', 'Ninguna conocida', 'Aspirina'];
        $enfermedades = ['Hipertensión', 'Diabetes tipo 2', 'Ninguna', 'Asma', 'Hipotiroidismo'];
        $habitos = ['Bruxismo nocturno', 'Fumador ocasional', 'Buena higiene bucal', 'Consumo frecuente de azúcar'];

        for ($i = 0; $i < self::PACIENTES; $i++) {
            $genero = $faker->randomElement(['M', 'F']);

            Paciente::updateOrCreate(
                ['numero_documento' => (string) (6000000 + $i * 91)],
                [
                    'nombres' => $genero === 'M' ? $faker->firstNameMale() : $faker->firstNameFemale(),
                    'apellidos' => $faker->lastName().' '.$faker->lastName(),
                    'tipo_documento' => 'CI',
                    'fecha_nacimiento' => $faker->dateTimeBetween('-72 years', '-5 years')->format('Y-m-d'),
                    'genero' => $genero,
                    'direccion' => $faker->streetAddress().', La Paz',
                    'telefono' => '7'.$faker->numberBetween(1000000, 9999999),
                    'email' => $faker->unique()->safeEmail(),
                    'grupo_sanguineo' => $faker->randomElement($grupos),
                    'alergias' => $faker->randomElement($alergias),
                    'enfermedades' => $faker->randomElement($enfermedades),
                    'medicamentos' => $faker->boolean(30) ? $faker->words(2, true) : null,
                    'habitos' => $faker->randomElement($habitos),
                    'contacto_emergencia' => $faker->name(),
                    'telefono_emergencia' => '7'.$faker->numberBetween(1000000, 9999999),
                    'activo' => $faker->boolean(92),
                    'created_at' => $faker->dateTimeBetween('-8 months', 'now'),
                ]
            );
        }

        return Paciente::all();
    }

    private function crearCitas($pacientes)
    {
        $doctores = Doctor::with('horarios')->activos()->get();
        $tratamientos = Tratamiento::activos()->get()->groupBy('especialidad_id');

        $estadosPasados = ['COMPLETADA', 'COMPLETADA', 'COMPLETADA', 'CANCELADA'];
        $estadosFuturos = ['PENDIENTE', 'CONFIRMADA', 'CONFIRMADA'];

        // Evita chocar con la restricción única (doctor, fecha, hora).
        // Segunda siembra: no duplica la agenda ya generada.
        if (Cita::count() >= self::CITAS) {
            return Cita::with(['paciente', 'doctor', 'tratamiento'])->get();
        }

        $ocupados = [];
        $creadas = 0;
        $intentos = 0;

        while ($creadas < self::CITAS && $intentos < self::CITAS * 12) {
            $intentos++;

            $doctor = $doctores->random();
            $delTratamiento = $tratamientos->get($doctor->especialidad_id);

            if (! $delTratamiento || $delTratamiento->isEmpty()) {
                continue;
            }

            $fecha = Carbon::today()->subDays(random_int(-25, 165));
            $libres = $this->agenda->horasLibres($doctor, $fecha->toDateString());

            if (! $libres) {
                continue;
            }

            $hora = $libres[array_rand($libres)];
            $clave = "{$doctor->id}|{$fecha->toDateString()}|{$hora}";

            if (isset($ocupados[$clave])) {
                continue;
            }

            $esPasada = $fecha->isBefore(Carbon::today());

            Cita::create([
                'paciente_id' => $pacientes->random()->id,
                'doctor_id' => $doctor->id,
                'tratamiento_id' => $delTratamiento->random()->id,
                'fecha' => $fecha->toDateString(),
                'hora' => $hora,
                'estado' => $esPasada
                    ? $estadosPasados[array_rand($estadosPasados)]
                    : $estadosFuturos[array_rand($estadosFuturos)],
                'origen' => random_int(1, 4) === 1 ? 'ONLINE' : 'RECEPCION',
                'motivo' => 'Atención odontológica programada.',
                'recordatorio_enviado_en' => $esPasada && random_int(0, 1) ? $fecha->copy()->subDay() : null,
            ]);

            $ocupados[$clave] = true;
            $creadas++;
        }

        return Cita::with(['paciente', 'doctor', 'tratamiento'])->get();
    }

    private function crearHistoriales($citas): void
    {
        $diagnosticos = [
            'Caries dental en pieza tratada, sin compromiso pulpar.',
            'Gingivitis marginal generalizada por placa bacteriana.',
            'Pulpitis irreversible sintomática.',
            'Periodontitis crónica localizada.',
            'Maloclusión clase I con apiñamiento leve.',
            'Pieza con indicación de extracción por fractura radicular.',
        ];

        foreach ($citas->where('estado', 'COMPLETADA') as $cita) {
            if (random_int(1, 10) > 7) {
                continue;
            }

            HistorialClinico::firstOrCreate(
                ['cita_id' => $cita->id],
                [
                    'paciente_id' => $cita->paciente_id,
                    'doctor_id' => $cita->doctor_id,
                    'fecha' => $cita->fecha,
                    'motivo_consulta' => 'Paciente acude por '.mb_strtolower($cita->tratamiento->nombre).'.',
                    'sintomas' => 'Refiere molestia localizada al masticar y sensibilidad al frío.',
                    'diagnostico' => $diagnosticos[array_rand($diagnosticos)],
                    'tratamiento_realizado' => $cita->tratamiento->nombre.' realizado sin complicaciones.',
                    'prescripcion_receta' => 'Ibuprofeno 400 mg cada 8 horas por 3 días. Enjuague con clorhexidina 0.12%.',
                    'observaciones' => 'Se indica control en 15 días y refuerzo de técnica de cepillado.',
                ]
            );
        }
    }

    private function crearOdontogramas($citas): void
    {
        $hallazgos = ['caries', 'obturado', 'corona', 'endodoncia', 'ausente', 'sellante', 'fractura'];

        foreach ($citas->where('estado', 'COMPLETADA')->take(40) as $cita) {
            if (Odontograma::where('cita_id', $cita->id)->exists()) {
                continue;
            }

            $tipo = ($cita->paciente->edad ?? 30) < 12 ? 'INFANTIL' : 'ADULTO';
            $numeros = collect(Odontograma::cuadrantes($tipo))->flatten();

            $piezas = $numeros->mapWithKeys(fn ($n) => [(string) $n => [
                'estado' => 'sano',
                'caras' => array_fill_keys(Odontograma::CARAS, 'sano'),
                'nota' => null,
            ]])->all();

            foreach ($numeros->random(min(6, $numeros->count())) as $numero) {
                $hallazgo = $hallazgos[array_rand($hallazgos)];

                if ($hallazgo === 'ausente') {
                    $piezas[(string) $numero]['estado'] = 'ausente';

                    continue;
                }

                $cara = Odontograma::CARAS[array_rand(Odontograma::CARAS)];
                $piezas[(string) $numero]['caras'][$cara] = $hallazgo;
            }

            Odontograma::create([
                'paciente_id' => $cita->paciente_id,
                'doctor_id' => $cita->doctor_id,
                'cita_id' => $cita->id,
                'tipo' => $tipo,
                'piezas' => $piezas,
                'observaciones' => 'Registro tomado durante la atención de '.mb_strtolower($cita->tratamiento->nombre).'.',
                'fecha' => $cita->fecha,
            ]);
        }
    }

    private function crearPagos($citas): void
    {
        $cajeros = Usuario::role(['RECEPCION', 'ADMINISTRADOR', 'SUPER ADMINISTRADOR'])->pluck('id');
        $metodos = ['EFECTIVO', 'EFECTIVO', 'TARJETA', 'QR', 'TRANSFERENCIA'];

        foreach ($citas->where('estado', 'COMPLETADA') as $cita) {
            if (Pago::where('cita_id', $cita->id)->exists()) {
                continue;
            }

            $cantidad = random_int(1, 2);
            $precio = (float) $cita->tratamiento->precio;
            $total = round($precio * $cantidad, 2);

            // Un tercio de los recibos queda con saldo para poblar la cartera.
            $pagado = random_int(1, 3) === 1
                ? round($total * (random_int(30, 80) / 100), 2)
                : $total;

            $pago = Pago::create([
                'codigo_recibo' => Pago::siguienteCodigo(),
                'paciente_id' => $cita->paciente_id,
                'doctor_id' => $cita->doctor_id,
                'cita_id' => $cita->id,
                'usuario_id' => $cajeros->random(),
                'monto_pagado' => $pagado,
                'metodo_pago' => $metodos[array_rand($metodos)],
                'fecha_pago' => $cita->fecha->copy()->setTime((int) substr($cita->hora, 0, 2), (int) substr($cita->hora, 3, 2)),
                'notas' => null,
            ]);

            $pago->detalles()->create([
                'tratamiento_id' => $cita->tratamiento_id,
                'descripcion' => $cita->tratamiento->nombre,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'subtotal' => $total,
            ]);

            $pago->recalcular();

            // Algunos recibos anulados dan realismo a la auditoría de caja.
            if (random_int(1, 25) === 1) {
                $pago->update([
                    'estado' => 'ANULADO',
                    'notas' => 'ANULADO por error de digitación en el monto.',
                ]);
            }
        }
    }
}
