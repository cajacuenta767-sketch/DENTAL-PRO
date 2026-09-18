<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Arqueo y cierre de la caja de un día (por sucursal y turno). */
class CierreCaja extends Model
{
    use Auditable, Concerns\BelongsToClinica, HasFactory;

    protected $table = 'cierres_caja';

    protected $fillable = [
        'sucursal_id', 'usuario_id', 'fecha', 'turno', 'fondo_inicial', 'efectivo_esperado', 'efectivo_contado',
        'diferencia', 'total_cobrado', 'total_egresos', 'efectivo_egresos', 'recibos', 'totales', 'observaciones',
        'estado', 'cerrado_en', 'hora_apertura', 'hora_cierre',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fondo_inicial' => 'decimal:2',
            'efectivo_esperado' => 'decimal:2',
            'efectivo_contado' => 'decimal:2',
            'diferencia' => 'decimal:2',
            'total_cobrado' => 'decimal:2',
            'total_egresos' => 'decimal:2',
            'efectivo_egresos' => 'decimal:2',
            'totales' => 'array',
            'cerrado_en' => 'datetime',
        ];
    }

    public const TURNOS = [
        'COMPLETO' => 'Jornada Completa',
        'MANANA' => 'Turno Mañana (08:00 - 14:00)',
        'TARDE' => 'Turno Tarde (14:00 - 20:00)',
        'NOCHE' => 'Turno Noche (20:00 - 08:00)',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function egresos(): HasMany
    {
        return $this->hasMany(EgresoCaja::class, 'cierre_caja_id');
    }

    public function getTurnoLegibleAttribute(): string
    {
        return self::TURNOS[$this->turno] ?? $this->turno;
    }

    /**
     * Resumen de lo cobrado y egresado en una fecha (y sucursal) a partir de los recibos
     * y egresos de caja chica vigentes.
     */
    public static function resumenDelDia(string $fecha, ?int $sucursalId = null): array
    {
        $pagos = Pago::query()
            ->vigentes()
            ->whereDate('fecha_pago', $fecha)
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->with('cajero:id,nombre')
            ->get();

        $porMetodo = collect(Pago::METODOS)
            ->reject(fn ($m) => $m === 'MIXTO')
            ->mapWithKeys(fn ($m) => [$m => 0.0])
            ->all();

        $porCajero = [];

        foreach ($pagos as $pago) {
            if ($pago->metodo_pago === 'MIXTO' && is_array($pago->desglose_metodos)) {
                foreach ($pago->desglose_metodos as $metodo => $monto) {
                    $m = strtoupper(trim((string) $metodo));
                    if (array_key_exists($m, $porMetodo)) {
                        $porMetodo[$m] = round($porMetodo[$m] + (float) $monto, 2);
                    }
                }
            } else {
                $m = $pago->metodo_pago;
                $porMetodo[$m] = round(($porMetodo[$m] ?? 0) + (float) $pago->monto_pagado, 2);
            }

            $nombre = $pago->cajero?->nombre ?? 'Sin cajero';
            $porCajero[$nombre] = round(($porCajero[$nombre] ?? 0) + (float) $pago->monto_pagado, 2);
        }

        // Egresos de caja chica registrados para la misma fecha y sucursal
        $egresos = EgresoCaja::query()
            ->vigentes()
            ->whereDate('fecha', $fecha)
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->with('usuario:id,nombre')
            ->get();

        $totalEgresos = round((float) $egresos->sum('monto'), 2);
        $efectivoEgresos = round((float) $egresos->where('metodo_pago', 'EFECTIVO')->sum('monto'), 2);

        $egresosPorCategoria = [];
        foreach ($egresos as $egreso) {
            $cat = $egreso->categoria;
            $egresosPorCategoria[$cat] = round(($egresosPorCategoria[$cat] ?? 0) + (float) $egreso->monto, 2);
        }

        $efectivoCobrado = $porMetodo['EFECTIVO'] ?? 0.0;
        $efectivoNeto = round($efectivoCobrado - $efectivoEgresos, 2);

        return [
            'recibos' => $pagos->count(),
            'total' => round((float) $pagos->sum('monto_pagado'), 2),
            'efectivo' => $efectivoCobrado,
            'por_metodo' => $porMetodo,
            'por_cajero' => $porCajero,
            'egresos_total' => $totalEgresos,
            'egresos_efectivo' => $efectivoEgresos,
            'egresos_conteo' => $egresos->count(),
            'egresos_por_categoria' => $egresosPorCategoria,
            'efectivo_neto' => $efectivoNeto,
            'anulados' => Pago::where('estado', 'ANULADO')->whereDate('fecha_pago', $fecha)
                ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))->count(),
        ];
    }
}
