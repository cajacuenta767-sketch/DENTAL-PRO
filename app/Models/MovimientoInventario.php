<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventario extends Model
{
    use HasFactory;

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'insumo_id', 'usuario_id', 'cita_id', 'tipo', 'cantidad',
        'stock_resultante', 'costo_unitario', 'motivo', 'referencia', 'fecha',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'stock_resultante' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'fecha' => 'datetime',
        ];
    }

    public const TIPOS = ['ENTRADA', 'SALIDA', 'AJUSTE', 'MERMA'];

    public const COLORES = [
        'ENTRADA' => 'success',
        'SALIDA' => 'azure',
        'AJUSTE' => 'warning',
        'MERMA' => 'danger',
    ];

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function getColorAttribute(): string
    {
        return self::COLORES[$this->tipo] ?? 'secondary';
    }

    /** Signo con el que el movimiento afecta las existencias. */
    public function getSignoAttribute(): int
    {
        return in_array($this->tipo, ['SALIDA', 'MERMA'], true) ? -1 : 1;
    }
}
