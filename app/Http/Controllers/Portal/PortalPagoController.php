<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\PagoOnline;
use App\Services\PasarelaPagoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pago en línea de los saldos pendientes del paciente desde el portal. */
class PortalPagoController extends Controller
{
    public function __construct(private readonly PasarelaPagoService $pasarela) {}

    /** Abre un intento de pago por el saldo del recibo y manda al paciente a la pasarela. */
    public function iniciar(Request $request, Pago $pago): RedirectResponse
    {
        $paciente = $this->paciente($request);
        abort_unless($paciente && $pago->paciente_id === $paciente->id, 404);

        if (! $this->pasarela->activos()) {
            return redirect()->route('portal.pagos')->with('error', 'El pago en línea no está habilitado por ahora. Puedes pagar en recepción.');
        }

        if ($pago->estado === 'ANULADO' || (float) $pago->monto_saldo <= 0) {
            return redirect()->route('portal.pagos')->with('error', 'Ese recibo no tiene saldo pendiente por pagar.');
        }

        try {
            $intento = $this->pasarela->iniciar($pago, $paciente);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('portal.pagos')->with('error', 'No pudimos conectar con la pasarela de pago. Intenta de nuevo en unos minutos.');
        }

        return redirect()->away($intento->url);
    }

    /** Estado de un intento; con el proveedor simulado también muestra la "pasarela" local. */
    public function estado(Request $request, PagoOnline $pagoOnline): View
    {
        $paciente = $this->paciente($request);
        abort_unless($paciente && $pagoOnline->paciente_id === $paciente->id, 404);

        $pagoOnline->load('pago.doctor');

        return view('portal.pago-online', [
            'paciente' => $paciente,
            'intento' => $pagoOnline,
            'pago' => $pagoOnline->pago,
            'simulador' => $pagoOnline->proveedor === 'simulado' && $pagoOnline->estado === 'PENDIENTE'
                ? [
                    'exito' => $pagoOnline->respuesta['url_exito'] ?? $this->pasarela->urlRetorno($pagoOnline, 'exito'),
                    'cancelacion' => $pagoOnline->respuesta['url_cancelacion'] ?? $this->pasarela->urlRetorno($pagoOnline, 'cancelado'),
                ]
                : null,
        ]);
    }

    /** Ficha del paciente de la cuenta; si no está unida, la busca por correo verificado (igual que PortalController). */
    private function paciente(Request $request): ?Paciente
    {
        $usuario = $request->user();

        if ($usuario->paciente) {
            return $usuario->paciente;
        }

        if (! $usuario->hasVerifiedEmail()) {
            return null;
        }

        $porCorreo = Paciente::whereNull('usuario_id')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($usuario->email)])
            ->orderBy('id')
            ->first();

        if ($porCorreo) {
            $porCorreo->update(['usuario_id' => $usuario->id]);
            $usuario->setRelation('paciente', $porCorreo);
        }

        return $porCorreo;
    }
}
