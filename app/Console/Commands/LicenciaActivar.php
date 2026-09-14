<?php

namespace App\Console\Commands;

use App\Services\Control\Licencia;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Registra la clave de licencia y activa este equipo en CONTROL. Lo usan el
 * instalador de escritorio y los despliegues automatizados.
 */
class LicenciaActivar extends Command
{
    protected $signature = 'licencia:activar {clave : Clave CTL-XXXX-XXXX-XXXX-XXXX}';

    protected $description = 'Registra la clave de licencia CONTROL y activa este equipo';

    public function handle(Licencia $licencia): int
    {
        try {
            $clave = $licencia->guardarClave((string) $this->argument('clave'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }

        $this->line("Clave {$clave} registrada en ".config('control.archivo').'.');
        $this->line('Huella de este equipo: '.$licencia->huella());

        if (! $licencia->activo()) {
            $this->warn('CONTROL_ACTIVO es false: la clave queda guardada pero no se exige licencia.');
        }

        $estado = $licencia->verificarAhora();

        if (! $estado['valido']) {
            $this->error('No se pudo activar: '.$estado['motivo']);

            return self::FAILURE;
        }

        $r = $licencia->resumen();
        $this->info('Licencia activada ('.$r['estado'].'). Vence: '.($r['vence_en'] ?? 'nunca').'. Sin internet hasta: '.$r['sin_conexion_hasta'].'.');

        return self::SUCCESS;
    }
}
