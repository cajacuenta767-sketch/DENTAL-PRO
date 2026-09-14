<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Arqueo y cierre de la caja de un día (por sucursal). */
class CierreCaja extends Model
{
    use Auditable, HasFactory;

    protected $table = 'cierres_caja';

    protected $fillable = [
        'sucursal_id', 'usuario_id', 'fecha', 'fondo_inicial', 'efectivo_esperado', 'efectivo_contado',
        'diferencia', 'total_cobrado', 'recibos', 'totales', 'observaciones', 'estado', 'cerrado_en',
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
            'totales' => 'array',
            'cerrado_en' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * Resumen de lo cobrado en una fecha (y sucursal) a partir de los recibos
     * vigentes: totales por método y por cajero.
     */
    public static function resumenDelDia(string $fecha, ?int $sucursalId = null): array
    {
        $pagos = Pago::query()
            ->vigentes()
            ->whereDate('fecha_pago', $fecha)
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->with('cajero:id,nombre')
            ->get();

        $porMetodo = collect(Pago::METODOS)->mapWithKeys(fn ($m) => [$m => 0.0])->all();
        $porCajero = [];

        foreach ($pagos as $pago) {
            $porMetodo[$pago->metodo_pago] = round(($porMetodo[$pago->metodo_pago] ?? 0) + (float) $pago->monto_pagado, 2);
            $nombre = $pago->cajero?->nombre ?? 'Sin cajero';
            $porCajero[$nombre] = round(($porCajero[$nombre] ?? 0) + (float) $pago->monto_pagado, 2);
        }

        return [
            'recibos' => $pagos->count(),
            'total' => round((float) $pagos->sum('monto_pagado'), 2),
            'efectivo' => $porMetodo['EFECTIVO'] ?? 0.0,
            'por_metodo' => $porMetodo,
            'por_cajero' => $porCajero,
            'anulados' => Pago::where('estado', 'ANULADO')->whereDate('fecha_pago', $fecha)
                ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))->count(),
        ];
    }
}
