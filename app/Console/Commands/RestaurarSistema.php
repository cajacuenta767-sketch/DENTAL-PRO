<?php

namespace App\Console\Commands;

use App\Services\RespaldoService;
use Illuminate\Console\Command;
use Throwable;

class RestaurarSistema extends Command
{
    protected $signature = 'sistema:restaurar
                            {archivo : Nombre del respaldo (respaldo-YYYYmmdd-HHMMSS.zip)}
                            {--forzar : No pedir confirmación}';

    protected $description = 'Restaura la base de datos y los archivos privados desde un respaldo (reemplaza los datos actuales)';

    public function handle(RespaldoService $respaldos): int
    {
        $archivo = (string) $this->argument('archivo');

        if (! $respaldos->existe($archivo)) {
            $this->error("El respaldo {$archivo} no existe. Usa el nombre exacto, por ejemplo: respaldo-20260101-020000.zip");

            return self::INVALID;
        }

        $this->warn('ATENCIÓN: se reemplazarán TODOS los datos actuales de la base de datos y los archivos privados.');

        if (! $this->option('forzar') && ! $this->confirm("¿Restaurar el sistema desde {$archivo}?", false)) {
            $this->line('Restauración cancelada.');

            return self::SUCCESS;
        }

        try {
            $respaldos->restaurar($archivo);
        } catch (Throwable $e) {
            $this->error('La restauración falló: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sistema restaurado desde {$archivo}.");
        $this->line('Recomendado: php artisan optimize:clear');

        return self::SUCCESS;
    }
}
