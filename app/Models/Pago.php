<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pago extends Model
{
    use Auditable, Concerns\BelongsToClinica, HasFactory, SoftDeletes;

    protected $table = 'pagos';

    protected $fillable = [
        'codigo_recibo', 'paciente_id', 'doctor_id', 'cita_id', 'usuario_id', 'presupuesto_id', 'sucursal_id',
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

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function pagosOnline(): HasMany
    {
        return $this->hasMany(PagoOnline::class, 'pago_id');
    }

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class, 'presupuesto_id');
    }

    /** Factura o comprobante vigente del recibo (las notas de crédito no cuentan). */
    public function documentoFiscal(): HasOne
    {
        return $this->hasOne(DocumentoFiscal::class, 'pago_id')
            ->where('estado', '!=', 'ANULADO')
            ->whereIn('tipo', ['FACTURA', 'CREDITO_FISCAL']);
    }

    /** Todos los documentos fiscales ligados al recibo, anulados y notas incluidos. */
    public function documentosFiscales(): HasMany
    {
        return $this->hasMany(DocumentoFiscal::class, 'pago_id');
    }

    /**
     * Correlativo REC-AAAA-NNNNN del siguiente recibo, tomado de una
     * secuencia bloqueada para que dos cajas no repitan número.
     */
    public static function siguienteCodigo(): string
    {
        $anio = now()->year;

        $correlativo = Secuencia::siguiente("recibo-{$anio}", function () use ($anio) {
            $ultimo = static::withTrashed()
                ->where('codigo_recibo', 'like', "REC-{$anio}-%")
                ->orderByDesc('codigo_recibo')
                ->value('codigo_recibo');

            return $ultimo ? (int) substr($ultimo, -5) : 0;
        });

        return sprintf('REC-%d-%05d', $anio, $correlativo);
    }

    /** Recalcula saldo y estado a partir de los montos registrados. */
    public function recalcular(): void
    {
        $this->monto_total = (float) $this->detalles()->sum('subtotal');
        $this->monto_pagado = min((float) $this->monto_pagado, (float) $this->monto_total);
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

    /** Un recibo con documento fiscal asociado ya forma parte del registro tributario. */
    public function getTieneDocumentosFiscalesAttribute(): bool
    {
        return $this->documentosFiscales()->exists();
    }
}
