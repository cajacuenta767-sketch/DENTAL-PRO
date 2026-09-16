<?php

namespace App\Console\Commands;

use App\Services\LicenciaService;
use Illuminate\Console\Command;

class LicenciaClaves extends Command
{
    protected $signature = 'licencia:claves';

    protected $description = 'Genera un par de claves nuevo para firmar códigos de activación';

    public function handle(LicenciaService $licencias): int
    {
        $claves = $licencias->generarClaves();

        $this->info('Par de claves generado. Guarda la privada en un lugar seguro: sin ella no podrás emitir códigos.');
        $this->newLine();
        $this->line('En el .env de TU instalación (la del proveedor):');
        $this->line("LICENCIA_CLAVE_PRIVADA={$claves['privada']}");
        $this->newLine();
        $this->line('En config/licencia.php (o en el .env de cada copia entregada):');
        $this->line("LICENCIA_CLAVE_PUBLICA={$claves['publica']}");

        return self::SUCCESS;
    }
}
