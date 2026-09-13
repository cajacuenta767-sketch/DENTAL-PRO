<?php

namespace Tests;

use App\Models\Aseguradora;
use App\Models\Doctor;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Paciente;
use App\Models\Tratamiento;
use App\Models\Usuario;
use Database\Seeders\AjusteSeeder;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Base para las pruebas del panel: deja la clínica configurada con roles,
 * permisos y un mínimo de catálogo para poder agendar y cobrar.
 */
abstract class CasoClinico extends TestCase
{
    use RefreshDatabase;

    protected Usuario $admin;

    protected Doctor $doctor;

    protected Paciente $paciente;

    protected Tratamiento $tratamiento;

    protected Aseguradora $aseguradora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AjusteSeeder::class, RolPermisoSeeder::class]);

        $this->admin = Usuario::create([
            'nombre' => 'Admin de Pruebas',
            'email' => 'admin@pruebas.test',
            'password' => 'secreto123',
            'estado' => 'activo',
        ]);
        $this->admin->assignRole('SUPER ADMINISTRADOR');

        $especialidad = Especialidad::create(['nombre' => 'ODONTOLOGÍA GENERAL', 'activo' => true]);

        $this->tratamiento = Tratamiento::create([
            'especialidad_id' => $especialidad->id,
            'nombre' => 'PROFILAXIS DENTAL',
            'precio' => 180,
            'duracion' => 30,
            'activo' => true,
        ]);

        $this->doctor = Doctor::create([
            'especialidad_id' => $especialidad->id,
            'nombres' => 'Sofía',
            'apellidos' => 'Arancibia',
            'tipo_documento' => 'CI',
            'numero_documento' => '1234567',
            'genero' => 'F',
            'activo' => true,
        ]);

        // Atiende todos los días de 08:00 a 12:00 para simplificar las pruebas.
        foreach (Horario::DIAS as $dia) {
            Horario::create([
                'doctor_id' => $this->doctor->id,
                'dia_semana' => $dia,
                'turno' => 'MAÑANA',
                'hora_inicio' => '08:00',
                'hora_fin' => '12:00',
                'activo' => true,
            ]);
        }

        $this->aseguradora = Aseguradora::create([
            'nombre' => 'SEGURO DE PRUEBAS',
            'tipo' => 'PRIVADA',
            'porcentaje_cobertura' => 50,
            'activo' => true,
        ]);

        $this->paciente = Paciente::create([
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'tipo_documento' => 'CI',
            'numero_documento' => '7654321',
            'genero' => 'M',
            'email' => 'juan.perez@pruebas.test',
            'activo' => true,
        ]);
    }

    /** Fecha futura con cupos, evitando el día de hoy. */
    protected function proximaFecha(int $dias = 3): string
    {
        return now()->addDays($dias)->toDateString();
    }
}
