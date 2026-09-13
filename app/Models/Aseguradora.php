<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aseguradora extends Model
{
    use HasFactory;

    protected $table = 'aseguradoras';

    protected $fillable = [
        'nombre', 'codigo', 'tipo', 'porcentaje_cobertura', 'tope_anual',
        'telefono', 'email', 'contacto', 'observaciones', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje_cobertura' => 'decimal:2',
            'tope_anual' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public const TIPOS = ['PRIVADA', 'PUBLICA', 'PREPAGA', 'CONVENIO'];

    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class, 'aseguradora_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /** Monto que cubre el seguro sobre un importe, respetando el tope anual. */
    public function cobertura(float $importe): float
    {
        $cubierto = round($importe * ((float) $this->porcentaje_cobertura / 100), 2);

        if ($this->tope_anual !== null) {
            $cubierto = min($cubierto, (float) $this->tope_anual);
        }

        return $cubierto;
    }
}
