<?php

namespace App\Console\Commands;

use App\Models\LicenciaEmitida;
use App\Services\EmisorLicencias;
use Illuminate\Console\Command;
use InvalidArgumentException;

class LicenciaRenovar extends Command
{
    protected $signature = 'licencia:renovar {cliente : ID o nombre del cliente registrado}
                            {instalacion : Código de instalación que muestra su pantalla de licencia}
                            {--dias= : Días de vigencia desde hoy}
                            {--hasta= : Fecha límite (YYYY-MM-DD)}
                            {--vitalicia : Sin vencimiento}';

    protected $description = 'Genera el PIN de renovación para una instalación concreta';

    public function handle(EmisorLicencias $emisor): int
    {
        if (! $emisor->disponible()) {
            $this->error('Configura LICENCIA_CLAVE_PRIVADA en el .env para poder emitir.');

            return self::FAILURE;
        }

        $clave = $this->argument('cliente');
        $emitida = is_numeric($clave)
            ? LicenciaEmitida::find($clave)
            : LicenciaEmitida::where('cliente', 'ilike', $clave)->first();

        if (! $emitida) {
            $this->error("No hay ningún cliente registrado como \"{$clave}\". Revisa con licencia:listar.");

            return self::FAILURE;
        }

        try {
            $pin = $emisor->renovar($emitida, $this->argument('instalacion'), $emisor->diaFinDesde(
                dias: $this->option('dias') !== null ? (int) $this->option('dias') : null,
                hasta: $this->option('hasta'),
                vitalicia: (bool) $this->option('vitalicia'),
            ));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$emitida->cliente} · instalación {$emitida->codigo_instalacion} · vence ".($emitida->es_vitalicia ? 'nunca' : $emitida->vence_en->format('d/m/Y')));
        $this->newLine();
        $this->line('PIN de renovación:');
        $this->line($pin);

        return self::SUCCESS;
    }
}
