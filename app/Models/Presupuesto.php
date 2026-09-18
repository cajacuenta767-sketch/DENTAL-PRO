<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Presupuesto extends Model
{
    use Auditable, Concerns\BelongsToClinica, HasFactory, SoftDeletes;

    protected $table = 'presupuestos';

    protected $fillable = [
        'codigo', 'paciente_id', 'doctor_id', 'usuario_id', 'odontograma_id',
        'aseguradora_id', 'porcentaje_cobertura', 'estado', 'subtotal', 'descuento', 'cobertura_seguro', 'total',
        'validez_dias', 'fecha', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'cobertura_seguro' => 'decimal:2',
            'porcentaje_cobertura' => 'decimal:2',
            'total' => 'decimal:2',
            'validez_dias' => 'integer',
            'fecha' => 'date',
        ];
    }

    public const ESTADOS = [
        'BORRADOR' => 'Borrador',
        'PRESENTADO' => 'Presentado al paciente',
        'APROBADO' => 'Aprobado',
        'EN_EJECUCION' => 'En ejecución',
        'COMPLETADO' => 'Completado',
        'RECHAZADO' => 'Rechazado',
    ];

    /** Cambios manuales permitidos; ejecución y cierre los maneja sincronizarEstado(). */
    public const TRANSICIONES = [
        'BORRADOR' => ['PRESENTADO', 'APROBADO', 'RECHAZADO'],
        'PRESENTADO' => ['BORRADOR', 'APROBADO', 'RECHAZADO'],
        'APROBADO' => ['RECHAZADO'],
        'EN_EJECUCION' => [],
        'COMPLETADO' => [],
        'RECHAZADO' => ['PRESENTADO'],
    ];

    public const COLORES = [
        'BORRADOR' => 'secondary',
        'PRESENTADO' => 'azure',
        'APROBADO' => 'primary',
        'EN_EJECUCION' => 'warning',
        'COMPLETADO' => 'success',
        'RECHAZADO' => 'danger',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function odontograma(): BelongsTo
    {
        return $this->belongsTo(Odontograma::class, 'odontograma_id');
    }

    /** Aseguradora congelada en el presupuesto (no la actual del paciente). */
    public function aseguradora(): BelongsTo
    {
        return $this->belongsTo(Aseguradora::class, 'aseguradora_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'presupuesto_id');
    }

    public function puedeTransitarA(string $estado): bool
    {
        return in_array($estado, self::TRANSICIONES[$this->estado] ?? [], true);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PresupuestoDetalle::class, 'presupuesto_id')->orderBy('orden')->orderBy('id');
    }

    public static function siguienteCodigo(): string
    {
        $anio = now()->year;

        $correlativo = Secuencia::siguiente("presupuesto-{$anio}", function () use ($anio) {
            $ultimo = static::withTrashed()
                ->where('codigo', 'like', "PRE-{$anio}-%")
                ->orderByDesc('codigo')
                ->value('codigo');

            return $ultimo ? (int) substr($ultimo, -5) : 0;
        });

        return sprintf('PRE-%d-%05d', $anio, $correlativo);
    }

    /**
     * Recalcula importes desde las líneas y aplica la cobertura de la
     * aseguradora sobre el neto ya descontado. Mientras el presupuesto es
     * editable toma la aseguradora actual del paciente y la deja congelada;
     * una vez aprobado respeta la que quedó guardada, aunque el paciente
     * cambie de seguro después. El tope anual se aplica sobre lo que la
     * aseguradora ya cubrió al paciente en el año.
     */
    public function recalcular(): void
    {
        $this->subtotal = (float) $this->detalles()->where('estado', '!=', 'ANULADO')->sum('subtotal');

        $neto = max(0, round((float) $this->subtotal - (float) $this->descuento, 2));

        if ($this->es_editable || $this->aseguradora_id === null) {
            $actual = $this->paciente?->aseguradora;
            $this->aseguradora_id = $actual?->id;
            $this->porcentaje_cobertura = $actual?->porcentaje_cobertura;
        }

        $aseguradora = $this->aseguradora_id ? $this->aseguradora()->first() : null;

        $this->cobertura_seguro = $aseguradora
            ? $aseguradora->cobertura(
                $neto,
                $this->coberturaYaUsadaEnElAnio($aseguradora),
                $this->porcentaje_cobertura !== null ? (float) $this->porcentaje_cobertura : null,
            )
            : 0;

        $this->total = max(0, round($neto - (float) $this->cobertura_seguro, 2));

        $this->save();
    }

    /** Suma de lo que la aseguradora ya cubrió al paciente en otros presupuestos vigentes del año. */
    private function coberturaYaUsadaEnElAnio(Aseguradora $aseguradora): float
    {
        $anio = ($this->fecha ?? now())->year;

        return (float) static::query()
            ->where('paciente_id', $this->paciente_id)
            ->where('aseguradora_id', $aseguradora->id)
            ->whereIn('estado', ['APROBADO', 'EN_EJECUCION', 'COMPLETADO'])
            ->whereYear('fecha', $anio)
            ->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id))
            ->sum('cobertura_seguro');
    }

    /** Avanza el estado según cuántas líneas se han ejecutado. */
    public function sincronizarEstado(): void
    {
        if (in_array($this->estado, ['BORRADOR', 'PRESENTADO', 'RECHAZADO'], true)) {
            return;
        }

        $vigentes = $this->detalles()->where('estado', '!=', 'ANULADO');
        $total = (clone $vigentes)->count();
        $ejecutados = (clone $vigentes)->where('estado', 'EJECUTADO')->count();

        $this->estado = match (true) {
            $total === 0 => $this->estado,
            $ejecutados === $total => 'COMPLETADO',
            $ejecutados > 0 => 'EN_EJECUCION',
            default => 'APROBADO',
        };

        $this->save();
    }

    public function getColorEstadoAttribute(): string
    {
        return self::COLORES[$this->estado] ?? 'secondary';
    }

    public function getEstadoLegibleAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function getVenceElAttribute(): Carbon
    {
        return $this->fecha->copy()->addDays((int) $this->validez_dias);
    }

    public function getAvanceAttribute(): int
    {
        $vigentes = $this->detalles->where('estado', '!=', 'ANULADO');

        if ($vigentes->isEmpty()) {
            return 0;
        }

        return (int) round($vigentes->where('estado', 'EJECUTADO')->count() / $vigentes->count() * 100);
    }

    /** Un presupuesto aprobado ya no admite cambios en sus líneas. */
    public function getEsEditableAttribute(): bool
    {
        return in_array($this->estado, ['BORRADOR', 'PRESENTADO'], true);
    }
}
