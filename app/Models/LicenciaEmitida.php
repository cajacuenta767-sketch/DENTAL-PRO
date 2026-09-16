<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro del proveedor: una fila por clínica cliente con la semilla de su
 * cadena de renovación. Solo tiene sentido en la instalación que posee la
 * clave privada.
 */
class LicenciaEmitida extends Model
{
    protected $table = 'licencias_emitidas';

    protected $fillable = [
        'cliente', 'contacto', 'semilla', 'ancla', 'tipo', 'dia_fin',
        'codigo_instalacion', 'ultimo_codigo', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'semilla' => 'encrypted',
            'dia_fin' => 'integer',
        ];
    }

    public function getVenceEnAttribute(): ?\Carbon\CarbonImmutable
    {
        return $this->dia_fin ? app(\App\Services\LicenciaService::class)->fechaDeDia($this->dia_fin) : null;
    }

    public function getEsVitaliciaAttribute(): bool
    {
        return $this->dia_fin !== null && app(\App\Services\LicenciaService::class)->esVitalicio($this->dia_fin);
    }
}
