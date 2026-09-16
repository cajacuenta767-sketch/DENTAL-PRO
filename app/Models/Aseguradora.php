<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aseguradora extends Model
{
    use Auditable, Concerns\BelongsToClinica, HasFactory;

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

    /**
     * Monto que cubre el seguro sobre un importe. El tope anual se aplica
     * sobre lo que ya se cubrió al paciente en el año ($yaCubierto), y el
     * porcentaje puede venir congelado desde el presupuesto.
     */
    public function cobertura(float $importe, float $yaCubierto = 0, ?float $porcentaje = null): float
    {
        $porcentaje ??= (float) $this->porcentaje_cobertura;
        $cubierto = round($importe * ($porcentaje / 100), 2);

        if ($this->tope_anual !== null) {
            $disponible = max(0, round((float) $this->tope_anual - $yaCubierto, 2));
            $cubierto = min($cubierto, $disponible);
        }

        return max(0, $cubierto);
    }
}
