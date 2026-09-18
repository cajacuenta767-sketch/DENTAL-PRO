<?php

namespace App\Console\Commands;

use App\Services\RespaldoService;
use Illuminate\Console\Command;
use Illuminate\Support\Number;
use Throwable;

class RespaldarSistema extends Command
{
    protected $signature = 'sistema:respaldar';

    protected $description = 'Crea una copia de seguridad completa (base de datos + archivos privados) y aplica la retención';

    public function handle(RespaldoService $respaldos): int
    {
        $this->info('Generando la copia de seguridad...');

        try {
            $nombre = $respaldos->crear();
        } catch (Throwable $e) {
            $this->error('No se pudo crear el respaldo: '.$e->getMessage());

            return self::FAILURE;
        }

        $tamano = Number::fileSize($respaldos->disco()->size($nombre), precision: 1);
        $this->info("Respaldo creado: {$nombre} ({$tamano}).");
        $this->line('Se conservan los '.config('filesystems.disks.respaldos.conservar', 14).' respaldos más recientes.');

        return self::SUCCESS;
    }
}
