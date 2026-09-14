<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresupuestoDetalle extends Model
{
    use HasFactory;

    protected $table = 'presupuesto_detalles';

    protected $fillable = [
        'presupuesto_id', 'tratamiento_id', 'cita_id', 'pago_id', 'pieza_dental', 'cara',
        'descripcion', 'cantidad', 'precio_unitario', 'subtotal',
        'estado', 'fecha_ejecucion', 'orden', 'sesion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'fecha_ejecucion' => 'date',
            'orden' => 'integer',
            'sesion' => 'integer',
        ];
    }

    public const ESTADOS = ['PENDIENTE', 'EN_PROCESO', 'EJECUTADO', 'ANULADO'];

    public const COLORES = [
        'PENDIENTE' => 'secondary',
        'EN_PROCESO' => 'warning',
        'EJECUTADO' => 'success',
        'ANULADO' => 'danger',
    ];

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class, 'presupuesto_id');
    }

    public function tratamiento(): BelongsTo
    {
        return $this->belongsTo(Tratamiento::class, 'tratamiento_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }

    public function scopeCobrables($query)
    {
        return $query->where('estado', 'EJECUTADO')->whereNull('pago_id');
    }

    public function getColorEstadoAttribute(): string
    {
        return self::COLORES[$this->estado] ?? 'secondary';
    }

    /** Etiqueta "Pieza 16 · oclusal" para listados y odontograma. */
    public function getUbicacionAttribute(): ?string
    {
        if (blank($this->pieza_dental)) {
            return null;
        }

        return 'Pieza '.$this->pieza_dental.($this->cara ? ' · '.$this->cara : '');
    }
}
