<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';

    protected $fillable = [
        'codigo_recibo', 'paciente_id', 'doctor_id', 'cita_id', 'usuario_id',
        'monto_total', 'monto_pagado', 'monto_saldo',
        'metodo_pago', 'estado', 'notas', 'fecha_pago',
    ];

    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:2',
            'monto_pagado' => 'decimal:2',
            'monto_saldo' => 'decimal:2',
            'fecha_pago' => 'datetime',
        ];
    }

    public const METODOS = ['EFECTIVO', 'TARJETA', 'QR', 'TRANSFERENCIA'];

    public const ESTADOS = ['PENDIENTE', 'PARCIAL', 'COMPLETADO', 'ANULADO'];

    public const COLORES_ESTADO = [
        'PENDIENTE' => 'secondary',
        'PARCIAL' => 'warning',
        'COMPLETADO' => 'success',
        'ANULADO' => 'danger',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PagoDetalle::class, 'pago_id');
    }

    /** Genera el correlativo REC-AAAA-NNNNN del siguiente recibo. */
    public static function siguienteCodigo(): string
    {
        $anio = now()->year;
        $ultimo = static::query()
            ->where('codigo_recibo', 'like', "REC-{$anio}-%")
            ->orderByDesc('id')
            ->value('codigo_recibo');

        $correlativo = $ultimo ? ((int) substr($ultimo, -5)) + 1 : 1;

        return sprintf('REC-%d-%05d', $anio, $correlativo);
    }

    /** Recalcula saldo y estado a partir de los montos registrados. */
    public function recalcular(): void
    {
        $this->monto_total = (float) $this->detalles()->sum('subtotal');
        $this->monto_saldo = max(0, round($this->monto_total - (float) $this->monto_pagado, 2));

        if ($this->estado !== 'ANULADO') {
            $this->estado = match (true) {
                (float) $this->monto_pagado <= 0 => 'PENDIENTE',
                $this->monto_saldo > 0 => 'PARCIAL',
                default => 'COMPLETADO',
            };
        }

        $this->save();
    }

    public function getColorEstadoAttribute(): string
    {
        return self::COLORES_ESTADO[$this->estado] ?? 'secondary';
    }

    public function scopeVigentes($query)
    {
        return $query->where('estado', '!=', 'ANULADO');
    }
}
