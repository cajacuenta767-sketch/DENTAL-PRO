<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\Cita;
use App\Models\DocumentoClinico;
use App\Models\Pago;
use App\Models\Paciente;
use App\Models\Presupuesto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portal del paciente: solo ve lo suyo. La ficha de paciente se vincula a
 * la cuenta por correo verificado o completando sus datos la primera vez.
 */
class PortalController extends Controller
{
    public function inicio(Request $request): View|RedirectResponse
    {
        $paciente = $this->paciente($request);

        if (! $paciente) {
            return redirect()->route('portal.vincular');
        }

        $proximas = $paciente->citas()
            ->with(['doctor.especialidad', 'tratamiento'])
            ->vigentes()
            ->whereDate('fecha', '>=', now()->toDateString())
            ->orderBy('fecha')->orderBy('hora')
            ->limit(5)
            ->get();

        return view('portal.inicio', [
            'paciente' => $paciente,
            'proximas' => $proximas,
            'saldo' => $paciente->saldo_pendiente,
            'presupuestosAbiertos' => $paciente->presupuestos()->whereIn('estado', ['PRESENTADO', 'APROBADO', 'EN_EJECUCION'])->count(),
            'documentos' => $paciente->documentos()->where('estado', 'EMITIDO')->count(),
            'ajustes' => Ajuste::actual(),
        ]);
    }

    public function citas(Request $request): View|RedirectResponse
    {
        $paciente = $this->paciente($request);

        if (! $paciente) {
            return redirect()->route('portal.vincular');
        }

        return view('portal.citas', [
            'paciente' => $paciente,
            'citas' => $paciente->citas()
                ->with(['doctor.especialidad', 'tratamiento'])
                ->orderByDesc('fecha')->orderByDesc('hora')
                ->paginate(10),
        ]);
    }

    public function cancelarCita(Request $request, Cita $cita): RedirectResponse
    {
        $paciente = $this->paciente($request);
        abort_unless($paciente && $cita->paciente_id === $paciente->id, 404);

        if (! $cita->cancelable_por_paciente) {
            return back()->with('error', 'Esa cita ya no puede cancelarse desde el portal. Comunícate con la clínica.');
        }

        $cita->update([
            'estado' => 'CANCELADA',
            'observacion' => trim(($cita->observacion ?? '')."\nCancelada por el paciente desde el portal el ".now()->format('d/m/Y H:i').'.'),
        ]);

        Auditoria::registrar('ANULAR', $cita, 'El paciente canceló la cita desde el portal');

        return back()->with('exito', 'Tu cita fue cancelada.');
    }

    public function documentos(Request $request): View|RedirectResponse
    {
        $paciente = $this->paciente($request);

        if (! $paciente) {
            return redirect()->route('portal.vincular');
        }

        return view('portal.documentos', [
            'paciente' => $paciente,
            'documentos' => $paciente->documentos()->with('doctor')->where('estado', 'EMITIDO')
                ->orderByDesc('fecha_emision')->orderByDesc('id')->paginate(10),
        ]);
    }

    public function documentoPdf(Request $request, DocumentoClinico $documento): Response
    {
        $paciente = $this->paciente($request);
        abort_unless($paciente && $documento->paciente_id === $paciente->id && $documento->estado === 'EMITIDO', 404);

        $documento->load(['paciente.aseguradora', 'doctor.especialidad', 'cita.tratamiento']);

        return Pdf::loadView('pdf.documento-clinico', [
            'documento' => $documento,
            'clinica' => Ajuste::actual(),
        ])->setPaper('letter')->stream($documento->folio.'.pdf');
    }

    public function presupuestos(Request $request): View|RedirectResponse
    {
        $paciente = $this->paciente($request);

        if (! $paciente) {
            return redirect()->route('portal.vincular');
        }

        return view('portal.presupuestos', [
            'paciente' => $paciente,
            'presupuestos' => $paciente->presupuestos()->with('doctor')->where('estado', '!=', 'BORRADOR')
                ->withCount('detalles')->orderByDesc('fecha')->orderByDesc('id')->paginate(10),
        ]);
    }

    public function presupuestoPdf(Request $request, Presupuesto $presupuesto): Response
    {
        $paciente = $this->paciente($request);
        abort_unless($paciente && $presupuesto->paciente_id === $paciente->id && $presupuesto->estado !== 'BORRADOR', 404);

        $presupuesto->load(['paciente.aseguradora', 'doctor.especialidad', 'detalles.tratamiento']);

        return Pdf::loadView('pdf.presupuesto', [
            'presupuesto' => $presupuesto,
            'clinica' => Ajuste::actual(),
        ])->setPaper('letter')->stream($presupuesto->codigo.'.pdf');
    }

    public function pagos(Request $request): View|RedirectResponse
    {
        $paciente = $this->paciente($request);

        if (! $paciente) {
            return redirect()->route('portal.vincular');
        }

        return view('portal.pagos', [
            'paciente' => $paciente,
            'pagos' => $paciente->pagos()->with('doctor')->vigentes()
                ->orderByDesc('fecha_pago')->paginate(10),
            'saldo' => $paciente->saldo_pendiente,
        ]);
    }

    public function reciboPdf(Request $request, Pago $pago): Response
    {
        $paciente = $this->paciente($request);
        abort_unless($paciente && $pago->paciente_id === $paciente->id && $pago->estado !== 'ANULADO', 404);

        $pago->load(['paciente', 'doctor', 'cajero', 'detalles']);

        return Pdf::loadView('pdf.recibo', [
            'pago' => $pago,
            'clinica' => Ajuste::actual(),
        ])->setPaper('letter')->stream($pago->codigo_recibo.'.pdf');
    }

    /** Primera vez: la cuenta todavía no está unida a una ficha de paciente. */
    public function vincular(Request $request): View|RedirectResponse
    {
        if ($this->paciente($request)) {
            return redirect()->route('portal.inicio');
        }

        return view('portal.vincular', ['usuario' => $request->user()]);
    }

    public function guardarVinculo(Request $request): RedirectResponse
    {
        if ($this->paciente($request)) {
            return redirect()->route('portal.inicio');
        }

        $datos = $request->validate([
            'nombres' => ['required', 'string', 'max:150'],
            'apellidos' => ['required', 'string', 'max:150'],
            'tipo_documento' => ['required', 'in:CI,DNI,PASAPORTE,CE'],
            'numero_documento' => ['required', 'string', 'max:20'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'genero' => ['required', 'in:M,F,O'],
            'telefono' => ['nullable', 'string', 'max:50'],
        ], [], ['numero_documento' => 'número de documento', 'fecha_nacimiento' => 'fecha de nacimiento']);

        $usuario = $request->user();
        $existente = Paciente::where('numero_documento', $datos['numero_documento'])->first();

        if ($existente) {
            // La ficha ya existe: solo se vincula si el correo verificado coincide.
            $mismoCorreo = filled($existente->email) && mb_strtolower($existente->email) === mb_strtolower($usuario->email);

            if ($existente->usuario_id || ! $mismoCorreo) {
                throw ValidationException::withMessages([
                    'numero_documento' => 'Ya existe una ficha con ese documento asociada a otro correo. Pide en recepción que la vinculen a tu cuenta.',
                ]);
            }

            $existente->update(['usuario_id' => $usuario->id, 'telefono' => $existente->telefono ?: $datos['telefono']]);
        } else {
            Paciente::create($datos + [
                'usuario_id' => $usuario->id,
                'email' => $usuario->email,
                'activo' => true,
            ]);
        }

        return redirect()->route('portal.inicio')->with('exito', 'Tu ficha quedó vinculada a la cuenta.');
    }

    /** Ficha del paciente de la cuenta; si no está unida, la busca por correo verificado. */
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
