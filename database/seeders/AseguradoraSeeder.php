<?php

namespace Database\Seeders;

use App\Models\Aseguradora;
use App\Models\Paciente;
use Illuminate\Database\Seeder;

class AseguradoraSeeder extends Seeder
{
    /** [nombre, código, tipo, % de cobertura, tope anual] */
    private const CONVENIOS = [
        ['SEGURO UNIVERSAL DE SALUD', 'SUS', 'PUBLICA', 70, null],
        ['LA BOLIVIANA CIACRUZ SEGUROS', 'LBC', 'PRIVADA', 60, 8000],
        ['ALIANZA SALUD', 'ALZ', 'PREPAGA', 50, 6000],
        ['SEGUROS ILLIMANI', 'ILL', 'PRIVADA', 45, 5000],
        ['CONVENIO EMPRESARIAL MINERA', 'CEM', 'CONVENIO', 80, 12000],
        ['CAJA NACIONAL DE SALUD', 'CNS', 'PUBLICA', 75, null],
    ];

    public function run(): void
    {
        foreach (self::CONVENIOS as [$nombre, $codigo, $tipo, $cobertura, $tope]) {
            Aseguradora::updateOrCreate(['nombre' => $nombre], [
                'codigo' => $codigo,
                'tipo' => $tipo,
                'porcentaje_cobertura' => $cobertura,
                'tope_anual' => $tope,
                'telefono' => '2'.rand(200000, 299999),
                'email' => mb_strtolower($codigo).'@convenios.bo',
                'contacto' => 'Departamento de convenios',
                'activo' => true,
            ]);
        }

        // Poco más de la mitad del padrón llega con obra social.
        $aseguradoras = Aseguradora::pluck('id');

        Paciente::query()->inRandomOrder()->limit((int) (Paciente::count() * 0.55))->get()
            ->each(function (Paciente $paciente) use ($aseguradoras) {
                $paciente->update([
                    'aseguradora_id' => $aseguradoras->random(),
                    'numero_afiliado' => 'AF-'.rand(100000, 999999),
                ]);
            });

        $this->command->info(
            'Aseguradoras: '.count(self::CONVENIOS).
            ' · Pacientes con cobertura: '.Paciente::whereNotNull('aseguradora_id')->count()
        );
    }
}
