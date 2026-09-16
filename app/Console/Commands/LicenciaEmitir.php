<?php

namespace App\Console\Commands;

use App\Services\EmisorLicencias;
use App\Services\LicenciaService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class LicenciaEmitir extends Command
{
    protected $signature = 'licencia:emitir {cliente : Nombre de la clínica}
                            {--dias=7 : Días de vigencia desde hoy}
                            {--hasta= : Fecha límite (YYYY-MM-DD), reemplaza a --dias}
                            {--vitalicia : Sin vencimiento}
                            {--completa : Marcarla como licencia completa en vez de prueba}
                            {--contacto= : Teléfono o correo del cliente}';

    protected $description = 'Registra un cliente nuevo y muestra su código de activación';

    public function handle(EmisorLicencias $emisor): int
    {
        if (! $emisor->disponible()) {
            $this->error('Configura LICENCIA_CLAVE_PRIVADA en el .env para poder emitir.');

            return self::FAILURE;
        }

        try {
            $diaFin = $emisor->diaFinDesde(
                dias: $this->option('hasta') ? null : (int) $this->option('dias'),
                hasta: $this->option('hasta'),
                vitalicia: (bool) $this->option('vitalicia'),
            );
            $emitida = $emisor->emitir(
                $this->argument('cliente'),
                $this->option('completa') || $this->option('vitalicia') ? LicenciaService::TIPO_COMPLETA : LicenciaService::TIPO_PRUEBA,
                $diaFin,
                $this->option('contacto'),
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Cliente #{$emitida->id} · {$emitida->cliente} · vence ".($emitida->es_vitalicia ? 'nunca' : $emitida->vence_en->format('d/m/Y')));
        $this->newLine();
        $this->line('Código de activación (envíalo junto con el enlace de descarga):');
        $this->line($emitida->ultimo_codigo);

        return self::SUCCESS;
    }
}
