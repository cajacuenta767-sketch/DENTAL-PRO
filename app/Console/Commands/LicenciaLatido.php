<?php

namespace App\Console\Commands;

use App\Services\Control\Licencia;
use Illuminate\Console\Command;

class LicenciaLatido extends Command
{
    protected $signature = 'licencia:latido';

    protected $description = 'Envía el latido diario a CONTROL y renueva el token de licencia';

    public function handle(Licencia $licencia): int
    {
        if (! $licencia->activo()) {
            $this->line('CONTROL desactivado (CONTROL_ACTIVO=false): no se exige licencia.');

            return self::SUCCESS;
        }

        if (! $licencia->tieneClave()) {
            $this->error('No hay clave de licencia registrada. Usa `php artisan licencia:activar CTL-XXXX-XXXX-XXXX-XXXX` o la pantalla /licencia.');

            return self::FAILURE;
        }

        $estado = $licencia->latido();
        $resumen = $licencia->resumen();

        $this->mostrar($resumen);

        if (! $estado['valido']) {
            $this->error('Licencia no válida: '.$estado['motivo']);

            return self::FAILURE;
        }

        $this->info($resumen['estado'] === 'mora' ? 'Licencia en periodo de gracia: renueva pronto.' : 'Licencia vigente.');

        return self::SUCCESS;
    }

    private function mostrar(array $r): void
    {
        $this->table(['Campo', 'Valor'], [
            ['Clave', $r['clave'] ?? '—'],
            ['Estado', $r['estado']],
            ['Plan', trim(($r['plan'] ?? '').' '.($r['etiqueta'] ?? '')) ?: '—'],
            ['Vence', $r['vence_en'] ?? 'Nunca'],
            ['Soporte hasta', $r['soporte_hasta'] ?? '—'],
            ['Funciona sin internet hasta', $r['sin_conexion_hasta'] ?? '—'],
            ['Equipo (huella)', $r['huella']],
            ['Versión', ($r['version'] ?? '—').($r['desactualizada'] ? ' (hay versión nueva: '.$r['version_actual'].')' : '')],
        ]);
    }
}
