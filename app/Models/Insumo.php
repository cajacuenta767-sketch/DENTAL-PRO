<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Insumo extends Model
{
    use HasFactory;

    protected $table = 'insumos';

    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'categoria', 'unidad_medida',
        'stock_actual', 'stock_minimo', 'costo_unitario',
        'proveedor', 'ubicacion', 'fecha_vencimiento', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'stock_actual' => 'decimal:2',
            'stock_minimo' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'fecha_vencimiento' => 'date',
            'activo' => 'boolean',
        ];
    }

    public const CATEGORIAS = [
        'ANESTESIA', 'RESTAURACION', 'ENDODONCIA', 'ORTODONCIA', 'CIRUGIA',
        'PROFILAXIS', 'DESECHABLE', 'BIOSEGURIDAD', 'INSTRUMENTAL', 'OTRO',
    ];

    public const UNIDADES = ['UNIDAD', 'CAJA', 'FRASCO', 'TUBO', 'PAQUETE', 'PAR', 'ML', 'GR', 'ROLLO'];

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'insumo_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeBajoMinimo($query)
    {
        return $query->whereColumn('stock_actual', '<=', 'stock_minimo');
    }

    public function scopePorVencer($query, int $dias = 90)
    {
        return $query->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<=', now()->addDays($dias));
    }

    /** Semáforo de existencias usado en listados y alertas. */
    public function getNivelStockAttribute(): string
    {
        return match (true) {
            (float) $this->stock_actual <= 0 => 'agotado',
            (float) $this->stock_actual <= (float) $this->stock_minimo => 'critico',
            (float) $this->stock_actual <= (float) $this->stock_minimo * 1.5 => 'bajo',
            default => 'normal',
        };
    }

    public function getColorStockAttribute(): string
    {
        return match ($this->nivel_stock) {
            'agotado' => 'danger',
            'critico' => 'warning',
            'bajo' => 'azure',
            default => 'success',
        };
    }

    public function getValorizadoAttribute(): float
    {
        return round((float) $this->stock_actual * (float) $this->costo_unitario, 2);
    }

    public function getEstaVencidoAttribute(): bool
    {
        return $this->fecha_vencimiento !== null && $this->fecha_vencimiento->isPast();
    }
}
