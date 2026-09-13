<?php

namespace Database\Seeders;

use App\Models\Especialidad;
use App\Models\Tratamiento;
use Illuminate\Database\Seeder;

class CatalogoSeeder extends Seeder
{
    /** Especialidades odontológicas con su color de identificación. */
    private const ESPECIALIDADES = [
        ['ODONTOLOGÍA GENERAL', 'Diagnóstico, prevención y atención odontológica integral.', '#0d9488'],
        ['ENDODONCIA', 'Tratamiento de conductos y patología pulpar.', '#7048e8'],
        ['PERIODONCIA', 'Salud de encías y tejidos de soporte dental.', '#e8590c'],
        ['ORTODONCIA', 'Corrección de la posición dentaria y maloclusiones.', '#1c7ed6'],
        ['CIRUGÍA ORAL', 'Exodoncias y procedimientos quirúrgicos bucales.', '#c92a2a'],
        ['ODONTOPEDIATRÍA', 'Atención odontológica de niñas y niños.', '#e64980'],
        ['IMPLANTOLOGÍA', 'Rehabilitación con implantes dentales.', '#2b8a3e'],
        ['ESTÉTICA DENTAL', 'Blanqueamiento, carillas y armonía de la sonrisa.', '#f59f00'],
    ];

    /** [especialidad, nombre, precio, duración en minutos] */
    private const TRATAMIENTOS = [
        ['ODONTOLOGÍA GENERAL', 'CONSULTA Y DIAGNÓSTICO', 80, 30],
        ['ODONTOLOGÍA GENERAL', 'PROFILAXIS DENTAL', 180, 45],
        ['ODONTOLOGÍA GENERAL', 'OBTURACIÓN SIMPLE', 200, 60],
        ['ODONTOLOGÍA GENERAL', 'OBTURACIÓN COMPUESTA', 280, 75],
        ['ODONTOLOGÍA GENERAL', 'APLICACIÓN DE FLÚOR', 120, 30],
        ['ENDODONCIA', 'ENDODONCIA UNIRRADICULAR', 450, 90],
        ['ENDODONCIA', 'ENDODONCIA BIRRADICULAR', 550, 120],
        ['ENDODONCIA', 'ENDODONCIA MULTIRRADICULAR', 700, 150],
        ['ENDODONCIA', 'RECUBRIMIENTO PULPAR', 220, 60],
        ['PERIODONCIA', 'CURETAJE GINGIVAL', 280, 60],
        ['PERIODONCIA', 'RASPADO Y ALISADO RADICULAR', 350, 90],
        ['PERIODONCIA', 'CIRUGÍA PERIODONTAL', 800, 120],
        ['PERIODONCIA', 'FÉRULA DE FERULIZACIÓN', 300, 60],
        ['ORTODONCIA', 'INSTALACIÓN DE BRACKETS METÁLICOS', 2400, 120],
        ['ORTODONCIA', 'INSTALACIÓN DE BRACKETS ESTÉTICOS', 3200, 120],
        ['ORTODONCIA', 'CONTROL DE ORTODONCIA', 180, 30],
        ['ORTODONCIA', 'RETENEDOR ORTODÓNCICO', 650, 45],
        ['CIRUGÍA ORAL', 'EXODONCIA SIMPLE', 150, 45],
        ['CIRUGÍA ORAL', 'EXODONCIA QUIRÚRGICA', 450, 90],
        ['CIRUGÍA ORAL', 'EXTRACCIÓN DE TERCER MOLAR', 700, 120],
        ['ODONTOPEDIATRÍA', 'SELLANTE DE FOSAS Y FISURAS', 140, 30],
        ['ODONTOPEDIATRÍA', 'PULPOTOMÍA INFANTIL', 320, 60],
        ['ODONTOPEDIATRÍA', 'CORONA DE ACERO PEDIÁTRICA', 400, 60],
        ['IMPLANTOLOGÍA', 'IMPLANTE DENTAL UNITARIO', 3500, 120],
        ['IMPLANTOLOGÍA', 'PRÓTESIS SOBRE IMPLANTE', 1200, 120],
        ['IMPLANTOLOGÍA', 'INJERTO ÓSEO', 1800, 150],
        ['ESTÉTICA DENTAL', 'BLANQUEAMIENTO DENTAL', 900, 90],
        ['ESTÉTICA DENTAL', 'CARILLA DE PORCELANA', 1500, 120],
        ['ESTÉTICA DENTAL', 'CORONA DE ZIRCONIO', 1900, 120],
        ['ESTÉTICA DENTAL', 'DISEÑO DE SONRISA', 2500, 150],
    ];

    public function run(): void
    {
        foreach (self::ESPECIALIDADES as [$nombre, $descripcion, $color]) {
            Especialidad::updateOrCreate(
                ['nombre' => $nombre],
                ['descripcion' => $descripcion, 'color' => $color, 'activo' => true]
            );
        }

        $especialidades = Especialidad::pluck('id', 'nombre');

        foreach (self::TRATAMIENTOS as [$especialidad, $nombre, $precio, $duracion]) {
            Tratamiento::updateOrCreate(
                ['nombre' => $nombre],
                [
                    'especialidad_id' => $especialidades[$especialidad],
                    'precio' => $precio,
                    'duracion' => $duracion,
                    'activo' => true,
                    'descripcion' => null,
                ]
            );
        }

        $this->command->info('Especialidades: '.count(self::ESPECIALIDADES).' · Tratamientos: '.count(self::TRATAMIENTOS));
    }
}
