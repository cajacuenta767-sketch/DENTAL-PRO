<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicamentoVademecum extends Model
{
    use HasFactory;

    protected $table = 'medicamentos_vademecum';

    protected $fillable = [
        'principio_activo',
        'nombre_comercial',
        'presentacion',
        'concentracion',
        'familia',
        'posologia_adulto',
        'posologia_pediatrica',
        'contraindicaciones',
        'advertencias',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public const FAMILIAS = [
        'PENICILINA' => 'Penicilinas y Betalactámicos',
        'MACROLIDO' => 'Macrólidos',
        'LINCOSAMIDA' => 'Lincosamidas',
        'AINE' => 'Antiinflamatorios no esteroideos (AINEs)',
        'ANALGESICO' => 'Analgésicos y Antipiréticos',
        'OPIOIDE' => 'Analgésicos opioides',
        'CORTICOIDE' => 'Corticosteroides',
        'ANTISEPTICO' => 'Antisépticos bucofaríngeos',
        'ANESTESICO' => 'Anestésicos locales',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function getNombreCompletoAttribute(): string
    {
        $comercial = $this->nombre_comercial ? " ({$this->nombre_comercial})" : '';
        return "{$this->principio_activo}{$comercial} {$this->concentracion} - {$this->presentacion}";
    }

    public function getFamiliaLegibleAttribute(): string
    {
        return self::FAMILIAS[$this->familia] ?? $this->familia;
    }

    public function getColorFamiliaAttribute(): string
    {
        return match ($this->familia) {
            'PENICILINA', 'MACROLIDO', 'LINCOSAMIDA' => 'blue',
            'AINE', 'ANALGESICO', 'OPIOIDE' => 'green',
            'CORTICOIDE' => 'orange',
            'ANTISEPTICO' => 'cyan',
            'ANESTESICO' => 'purple',
            default => 'secondary',
        };
    }
}
