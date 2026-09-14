<?php

namespace App\Http\Controllers;

use App\Models\PagoOnline;
use App\Services\Pasarela\WebhookInvalido;
use App\Services\PasarelaPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Entradas públicas de la pasarela: el retorno firmado del navegador y el
 * webhook del proveedor. Ninguna requiere sesión; el retorno se protege con
 * la firma de la URL y el webhook con la firma del proveedor.
 */
class PagoOnlineController extends Controller
{
    public function __construct(private readonly PasarelaPagoService $pasarela) {}

    /**
     * Vuelta del navegador. Solo con el proveedor simulado el éxito marca el
     * intento como pagado; con una pasarela real se espera al webhook.
     */
    public function retorno(Request $request, PagoOnline $pagoOnline, string $resultado): RedirectResponse
    {
        abort_unless(in_array($resultado, ['exito', 'cancelado'], true), 404);

        if ($resultado === 'exito' && $pagoOnline->proveedor === 'simulado') {
            $this->aplicar($pagoOnline, 'PAGADO', ['origen' => 'retorno-simulado', 'ip' => $request->ip()]);
        } elseif ($resultado === 'cancelado') {
            $this->aplicar($pagoOnline, 'CANCELADO', ['origen' => 'retorno', 'ip' => $request->ip()]);
        }

        return redirect()->route('portal.pagos.online', $pagoOnline);
    }

    /** Notificación servidor a servidor del proveedor indicado en la URL. */
    public function webhook(Request $request, string $proveedor): JsonResponse
    {
        try {
            $driver = $this->pasarela->proveedor($proveedor);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        try {
            $resultado = $driver->verificarWebhook($request);
        } catch (WebhookInvalido $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        if ($resultado === null) {
            return response()->json(['recibido' => true, 'aplicado' => false]);
        }

        $intento = PagoOnline::query()
            ->where('proveedor', $driver->nombre())
            ->when(
                filled($resultado['referencia'] ?? null),
                fn ($q) => $q->where('referencia', $resultado['referencia']),
                fn ($q) => $q->whereKey($resultado['pago_online_id'] ?? 0),
            )
            ->first();

        if (! $intento && filled($resultado['pago_online_id'] ?? null)) {
            $intento = PagoOnline::query()->where('proveedor', $driver->nombre())->find($resultado['pago_online_id']);
        }

        if (! $intento) {
            return response()->json(['error' => 'Intento de pago no encontrado.'], 404);
        }

        $this->aplicar($intento, $resultado['estado'], $resultado['cruda'] ?? []);

        return response()->json(['recibido' => true, 'aplicado' => true, 'estado' => $intento->fresh()->estado]);
    }

    /** Aplica el resultado de forma idempotente (bloqueo del recibo y del intento dentro de la transacción). */
    private function aplicar(PagoOnline $intento, string $estado, array $cruda = []): void
    {
        $this->pasarela->aplicar($intento, $estado, $cruda);
    }
}
