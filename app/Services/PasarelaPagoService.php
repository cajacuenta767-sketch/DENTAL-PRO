<?php

namespace App\Services;

use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\PagoOnline;
use App\Services\Pasarela\ProveedorPago;
use App\Services\Pasarela\SimuladoDriver;
use App\Services\Pasarela\StripeDriver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * Pagos de saldos desde el portal a través de una pasarela. Elige el driver
 * según config('services.pasarela.proveedor'), abre los intentos y aplica el
 * resultado (retorno firmado o webhook) sobre el recibo de forma idempotente.
 */
class PasarelaPagoService
{
    public const PROVEEDORES = ['simulado', 'stripe'];

    /** Driver configurado, o el indicado por nombre (para la ruta del webhook). */
    public function proveedor(?string $nombre = null): ProveedorPago
    {
        $nombre = strtolower($nombre ?? (string) config('services.pasarela.proveedor', 'simulado'));

        return match ($nombre) {
            'simulado' => new SimuladoDriver,
            'stripe' => new StripeDriver(
                config('services.pasarela.stripe.secret'),
                config('services.pasarela.stripe.webhook_secret'),
            ),
            default => throw new InvalidArgumentException("Proveedor de pagos desconocido: {$nombre}"),
        };
    }

    public function activos(): bool
    {
        return (bool) Ajuste::actual()->pagos_online_activos;
    }

    /** Abre un intento PENDIENTE por el saldo del recibo y devuelve la URL de la pasarela ya guardada en él. */
    public function iniciar(Pago $pago, Paciente $paciente): PagoOnline
    {
        $driver = $this->proveedor();

        $intento = PagoOnline::create([
            'pago_id' => $pago->id,
            'paciente_id' => $paciente->id,
            'proveedor' => $driver->nombre(),
            'monto' => round((float) $pago->monto_saldo, 2),
            'moneda' => Ajuste::actual()->divisa ?: 'BOB',
            'estado' => 'PENDIENTE',
        ]);

        try {
            $url = $driver->crearSesion($intento, $this->urlRetorno($intento, 'exito'), $this->urlRetorno($intento, 'cancelado'));
        } catch (\Throwable $e) {
            $intento->update(['estado' => 'FALLIDO', 'respuesta' => ['error' => $e->getMessage()]]);

            throw $e;
        }

        $intento->url = $url;
        $intento->save();

        return $intento;
    }

    /** Ruta de retorno firmada (exito | cancelado) a la que vuelve el navegador desde la pasarela. */
    public function urlRetorno(PagoOnline $intento, string $resultado): string
    {
        return URL::signedRoute('pagos-online.retorno', ['pagoOnline' => $intento->id, 'resultado' => $resultado]);
    }

    /** Aplica el resultado que informó la pasarela. Devuelve el intento actualizado. */
    public function aplicar(PagoOnline $intento, string $estado, array $cruda = []): PagoOnline
    {
        return match ($estado) {
            'PAGADO' => $this->marcarPagado($intento, $cruda),
            'FALLIDO', 'CANCELADO' => $this->cerrar($intento, $estado, $cruda),
            default => throw new InvalidArgumentException("Estado de pago desconocido: {$estado}"),
        };
    }

    /**
     * Marca el intento como PAGADO y abona el monto al recibo. Idempotente:
     * el recibo se bloquea y un intento ya pagado no se vuelve a sumar.
     */
    public function marcarPagado(PagoOnline $intento, array $cruda = []): PagoOnline
    {
        return DB::transaction(function () use ($intento, $cruda) {
            $pago = Pago::query()->lockForUpdate()->findOrFail($intento->pago_id);
            $intento = PagoOnline::query()->lockForUpdate()->findOrFail($intento->id);

            if ($intento->estado === 'PAGADO') {
                return $intento;
            }

            $intento->fill([
                'estado' => 'PAGADO',
                'pagado_en' => now(),
                'respuesta' => array_merge($intento->respuesta ?? [], $cruda ? ['confirmacion' => $cruda] : []),
            ])->save();

            $referencia = $intento->referencia ?: "#{$intento->id}";

            if ($pago->estado !== 'ANULADO') {
                $pago->monto_pagado = round((float) $pago->monto_pagado + (float) $intento->monto, 2);
                $pago->notas = trim(($pago->notas ?? '')."\nPago en línea {$referencia} ({$intento->proveedor}) por "
                    .number_format((float) $intento->monto, 2).' '.$intento->moneda.' el '.now()->format('d/m/Y H:i').'.');
                $pago->recalcular();
            }

            Auditoria::registrar(
                'ACTUALIZAR',
                $pago,
                "Pago en línea {$referencia} acreditado al recibo {$pago->codigo_recibo} por ".number_format((float) $intento->monto, 2).' '.$intento->moneda,
                ['pago_online_id' => $intento->id, 'proveedor' => $intento->proveedor, 'monto' => (float) $intento->monto],
                $intento->paciente?->usuario_id,
            );

            return $intento;
        });
    }

    /** Cierra un intento pendiente como FALLIDO o CANCELADO; uno ya pagado no cambia. */
    public function cerrar(PagoOnline $intento, string $estado, array $cruda = []): PagoOnline
    {
        return DB::transaction(function () use ($intento, $estado, $cruda) {
            $intento = PagoOnline::query()->lockForUpdate()->findOrFail($intento->id);

            if ($intento->estado !== 'PENDIENTE') {
                return $intento;
            }

            $intento->fill([
                'estado' => $estado,
                'respuesta' => array_merge($intento->respuesta ?? [], $cruda ? ['confirmacion' => $cruda] : []),
            ])->save();

            return $intento;
        });
    }
}
