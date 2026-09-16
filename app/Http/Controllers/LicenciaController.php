<?php

namespace App\Http\Controllers;

use App\Models\Licencia;
use App\Services\LicenciaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantalla de licencia de la clínica: estado, código de instalación y
 * entrada del PIN de activación o renovación.
 */
class LicenciaController extends Controller
{
    public function __construct(private readonly LicenciaService $licencias) {}

    public function ver(): View
    {
        $licencia = Licencia::actual();
        $whatsapp = preg_replace('/\D/', '', (string) config('licencia.contacto_whatsapp'));

        $mensaje = rawurlencode(
            "Hola, quiero renovar mi licencia de OdontoSuite. Mi código de instalación es {$licencia->codigo_instalacion}."
        );

        return view('licencia.ver', [
            'licencia' => $licencia,
            'contactoNombre' => config('licencia.contacto_nombre'),
            'contactoWhatsapp' => $whatsapp ? "https://wa.me/{$whatsapp}?text={$mensaje}" : null,
            'bloqueada' => config('licencia.activa') && blank(config('licencia.clave_privada')) && ! $licencia->estaVigente(),
        ]);
    }

    public function activar(Request $request): RedirectResponse
    {
        $request->validate(['codigo' => ['required', 'string', 'max:400']], [], ['codigo' => 'PIN']);

        $codigo = (string) $request->input('codigo');
        $licencia = Licencia::actual();

        if ($this->licencias->esCodigoActivacion($codigo)) {
            return $this->activarConCodigo($licencia, $codigo);
        }

        return $this->renovarConPin($licencia, $codigo);
    }

    private function activarConCodigo(Licencia $licencia, string $codigo): RedirectResponse
    {
        $datos = $this->licencias->verificarActivacion($codigo);

        if ($datos === null) {
            return back()->with('error', 'El código de activación no es válido. Revisa que lo hayas copiado completo.');
        }

        $hoy = $this->licencias->diaDeFecha(now());

        if ($hoy > $datos['dia_emision'] + (int) config('licencia.dias_para_activar')) {
            return back()->with('error', 'Este código de activación caducó. Pide uno nuevo a '.config('licencia.contacto_nombre').'.');
        }

        if ($licencia->ancla === bin2hex($datos['ancla']) && $licencia->dia_fin >= $datos['dia_fin']) {
            return back()->with('error', 'Este código ya fue utilizado en esta instalación.');
        }

        $vence = $this->licencias->fechaDeDia($datos['dia_fin']);

        $licencia->registrar('activacion', ['dia_fin' => $datos['dia_fin']]);
        $licencia->fill([
            'ancla' => bin2hex($datos['ancla']),
            'tipo' => $datos['tipo'] === LicenciaService::TIPO_PRUEBA ? 'PRUEBA' : 'COMPLETA',
            'dia_fin' => $datos['dia_fin'],
            'vence_en' => $vence,
            'activada_en' => now(),
        ])->save();

        return redirect()->route('admin.home')->with('exito', $licencia->esVitalicia()
            ? '¡Listo! Tu licencia quedó activada sin fecha de vencimiento.'
            : '¡Listo! Tu acceso está activo hasta el '.$vence->format('d/m/Y').'.');
    }

    private function renovarConPin(Licencia $licencia, string $pin): RedirectResponse
    {
        if (! $licencia->estaActivada()) {
            return back()->with('error', 'Este PIN es de renovación. Primero ingresa el código de activación que recibiste.');
        }

        $dia = $this->licencias->verificarPin($pin, hex2bin($licencia->ancla), $licencia->codigo_instalacion);

        if ($dia === null) {
            return back()->with('error', 'El PIN no es válido para esta instalación. Verifica que hayas enviado tu código de instalación '.$licencia->codigo_instalacion.' al pedirlo.');
        }

        if ($dia <= $licencia->dia_fin) {
            return back()->with('error', 'Este PIN ya fue utilizado o no extiende tu licencia actual.');
        }

        $vence = $this->licencias->fechaDeDia($dia);

        $licencia->registrar('renovacion', ['dia_fin' => $dia]);
        $licencia->fill([
            'tipo' => 'COMPLETA',
            'dia_fin' => $dia,
            'vence_en' => $vence,
            'renovada_en' => now(),
        ])->save();

        return redirect()->route('admin.home')->with('exito', $licencia->esVitalicia()
            ? '¡Gracias! Tu licencia quedó activada sin fecha de vencimiento.'
            : '¡Gracias! Tu licencia fue renovada hasta el '.$vence->format('d/m/Y').'.');
    }
}
