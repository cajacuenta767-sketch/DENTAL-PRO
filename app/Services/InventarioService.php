<?php

namespace App\Services;

use App\Models\Insumo;
use App\Models\MovimientoInventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Único punto por donde cambian las existencias: registra el movimiento y
 * deja el stock del insumo consistente con su kardex.
 */
class InventarioService
{
    public function registrar(Insumo $insumo, array $datos): MovimientoInventario
    {
        return DB::transaction(function () use ($insumo, $datos) {
            // Bloquea la fila para que dos cajeros no calculen el mismo saldo.
            $insumo = Insumo::lockForUpdate()->findOrFail($insumo->id);

            $cantidad = round((float) $datos['cantidad'], 2);
            $tipo = $datos['tipo'];

            $resultante = match ($tipo) {
                'ENTRADA' => (float) $insumo->stock_actual + $cantidad,
                'SALIDA', 'MERMA' => (float) $insumo->stock_actual - $cantidad,
                'AJUSTE' => $cantidad,
            };

            // En un ajuste el kardex guarda la diferencia (con signo) y no el
            // saldo contado, para que la suma de movimientos cuadre con el stock.
            if ($tipo === 'AJUSTE') {
                $cantidad = round($resultante - (float) $insumo->stock_actual, 2);
            }

            if ($resultante < 0) {
                throw ValidationException::withMessages([
                    'cantidad' => "No hay existencias suficientes: quedan {$insumo->stock_actual} {$insumo->unidad_medida}.",
                ]);
            }

            $resultante = round($resultante, 2);

            $movimiento = MovimientoInventario::create([
                'insumo_id' => $insumo->id,
                'usuario_id' => $datos['usuario_id'] ?? null,
                'cita_id' => $datos['cita_id'] ?? null,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'stock_resultante' => $resultante,
                'costo_unitario' => $datos['costo_unitario'] ?? $insumo->costo_unitario,
                'motivo' => $datos['motivo'] ?? null,
                'referencia' => $datos['referencia'] ?? null,
                'fecha' => $datos['fecha'] ?? now(),
            ]);

            $insumo->stock_actual = $resultante;

            // Una compra actualiza el costo de referencia del insumo.
            if ($tipo === 'ENTRADA' && filled($datos['costo_unitario'] ?? null)) {
                $insumo->costo_unitario = $datos['costo_unitario'];
            }

            $insumo->save();

            return $movimiento;
        });
    }

    /** Indicadores de la cabecera del módulo. */
    public function resumen(): array
    {
        return [
            'articulos' => Insumo::activos()->count(),
            'valorizado' => (float) Insumo::activos()->sum(DB::raw('stock_actual * costo_unitario')),
            'bajoMinimo' => Insumo::activos()->bajoMinimo()->count(),
            'porVencer' => Insumo::activos()->porVencer(90)->count(),
        ];
    }
}
