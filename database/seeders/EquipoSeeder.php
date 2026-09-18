<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

class EquipoSeeder extends Seeder
{
    /** [nombres, apellidos, género, especialidad, correo] */
    private const DOCTORES = [
        ['Sofía', 'Arancibia Greenhold', 'F', 'ODONTOLOGÍA GENERAL', 'sofia.arancibia@clinica.com'],
        ['Alan', 'Barton Dicki', 'M', 'ENDODONCIA', 'alan.barton@clinica.com'],
        ['Greta', 'Cole Goyette', 'F', 'PERIODONCIA', 'greta.cole@clinica.com'],
        ['Roslyn', 'Keeling Turner', 'F', 'ORTODONCIA', 'roslyn.keeling@clinica.com'],
        ['Raheem', 'Ratke O\'Connell', 'M', 'CIRUGÍA ORAL', 'raheem.ratke@clinica.com'],
        ['Elizabeth', 'Jerde Wilderman', 'F', 'ODONTOPEDIATRÍA', 'elizabeth.jerde@clinica.com'],
        ['Mauricio', 'Villarroel Paz', 'M', 'IMPLANTOLOGÍA', 'mauricio.villarroel@clinica.com'],
        ['Camila', 'Ortega Salinas', 'F', 'ESTÉTICA DENTAL', 'camila.ortega@clinica.com'],
        ['Diego', 'Mamani Quispe', 'M', 'ODONTOLOGÍA GENERAL', 'diego.mamani@clinica.com'],
        ['Valeria', 'Terceros Rojas', 'F', 'ENDODONCIA', 'valeria.terceros@clinica.com'],
    ];

    /** Plantillas de turnos que se reparten entre los doctores. */
    private const PLANTILLAS = [
        [['LUNES', 'MIERCOLES', 'VIERNES'], [['MAÑANA', '08:00', '12:00'], ['TARDE', '14:00', '18:00']]],
        [['MARTES', 'JUEVES'], [['MAÑANA', '08:30', '12:30'], ['TARDE', '15:00', '19:00']]],
        [['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES'], [['MAÑANA', '09:00', '13:00']]],
        [['MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'], [['TARDE', '14:00', '18:00']]],
    ];

    public function run(): void
    {
        $especialidades = Especialidad::pluck('id', 'nombre');

        foreach (self::DOCTORES as $i => [$nombres, $apellidos, $genero, $especialidad, $email]) {
            $usuario = Usuario::updateOrCreate(
                ['email' => $email],
                [
                    'nombre' => "{$nombres} {$apellidos}",
                    'password' => 'doctor123',
                    'estado' => 'activo',
                    'email_verified_at' => now(),
                    'debe_cambiar_password' => true,
                ]
            );
            $usuario->syncRoles(['DOCTOR']);

            $doctor = Doctor::updateOrCreate(
                ['numero_documento' => (string) (4500000 + $i * 137)],
                [
                    'usuario_id' => $usuario->id,
                    'especialidad_id' => $especialidades[$especialidad],
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'tipo_documento' => 'CI',
                    'fecha_nacimiento' => now()->subYears(rand(30, 55))->subDays(rand(0, 364))->toDateString(),
                    'genero' => $genero,
                    'telefono' => '7'.rand(1000000, 9999999),
                    'email' => $email,
                    'direccion' => 'La Paz, Bolivia',
                    'colegiatura' => 'COB-'.rand(10000, 99999),
                    'descripcion' => "Especialista en {$especialidad} con amplia experiencia clínica.",
                    'activo' => true,
                ]
            );

            $doctor->horarios()->delete();

            [$dias, $turnos] = self::PLANTILLAS[$i % count(self::PLANTILLAS)];

            foreach ($dias as $dia) {
                foreach ($turnos as [$turno, $inicio, $fin]) {
                    Horario::create([
                        'doctor_id' => $doctor->id,
                        'dia_semana' => $dia,
                        'turno' => $turno,
                        'hora_inicio' => $inicio,
                        'hora_fin' => $fin,
                        'activo' => true,
                    ]);
                }
            }
        }

        $this->command->info('Doctores: '.Doctor::count().' · Horarios: '.Horario::count());
    }
}
