<?php

namespace App\Console\Commands;

use App\Models\LicenciaEmitida;
use Illuminate\Console\Command;

class LicenciaListar extends Command
{
    protected $signature = 'licencia:listar';

    protected $description = 'Lista los clientes con licencia emitida';

    public function handle(): int
    {
        $filas = LicenciaEmitida::orderBy('id')->get()->map(fn ($e) => [
            $e->id, $e->cliente, $e->tipo, $e->es_vitalicia ? 'nunca' : $e->vence_en->format('d/m/Y'),
            $e->codigo_instalacion ?? '—', $e->contacto ?? '—',
        ]);

        if ($filas->isEmpty()) {
            $this->line('Todavía no has emitido ninguna licencia.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Cliente', 'Tipo', 'Vence', 'Instalación', 'Contacto'], $filas);

        return self::SUCCESS;
    }
}
