<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Concerns\ReservaCitas;
use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Doctor;
use App\Models\Paciente;
use App\Models\Tratamiento;
use App\Services\AgendaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reserva de citas desde el portal: el paciente ya está identificado por su
 * cuenta, así que solo elige especialidad, profesional, tratamiento y cupo.
 * Disponible únicamente cuando la clínica activa las reservas del portal.
 */
class PortalReservaController extends Controller
{
    use ReservaCitas;

    public function __construct(private readonly AgendaService $agenda) {}

    public function formulario(Request $request): View|RedirectResponse
    {
        $paciente = $this->paciente($request);

        if (! $paciente) {
            return redirect()->route('portal.vincular');
        }

        $ajustes = Ajuste::actual();

        if (! $ajustes->portal_reservas_activas) {
            return $this->desactivadas();
        }

        $confirmada = $request->filled('confirmada')
            ? $paciente->citas()->with(['doctor.especialidad', 'tratamiento'])->where('token', $request->query('confirmada'))->first()
            : null;

        return view('portal.reservar', [
            'paciente' => $paciente,
            'especialidades' => $this->especialidadesReservables(),
            'confirmada' => $confirmada,
        ] + $this->ventanaReservas($ajustes));
    }

    /** Doctores y tratamientos de una especialidad, para los selectores. */
    public function opciones(Request $request): JsonResponse
    {
        abort_unless($this->paciente($request) && Ajuste::actual()->portal_reservas_activas, 403);

        $especialidad = $request->validate([
            'especialidad_id' => ['required', 'exists:especialidades,id'],
        ])['especialidad_id'];

        return response()->json($this->opcionesDeEspecialidad((int) $especialidad));
    }

    public function horas(Request $request): JsonResponse
    {
        $ajustes = Ajuste::actual();
        abort_unless($this->paciente($request) && $ajustes->portal_reservas_activas, 403);

        $datos = $request->validate([
            'doctor_id' => ['required', 'exists:doctores,id'],
            'fecha' => ['required', 'date'],
        ]);

        $doctor = Doctor::activos()->findOrFail($datos['doctor_id']);
        $duracion = $request->filled('tratamiento_id') ? Tratamiento::find($request->tratamiento_id)?->duracion : null;

        return response()->json([
            'dia' => $this->agenda->nombreDia($datos['fecha']),
            'horas' => $this->cuposReservables($this->agenda, $doctor, $datos['fecha'], $duracion, $ajustes),
        ]);
    }

    public function reservar(Request $request): RedirectResponse
    {
        $paciente = $this->paciente($request);

        if (! $paciente) {
            return redirect()->route('portal.vincular');
        }

        $ajustes = Ajuste::actual();

        if (! $ajustes->portal_reservas_activas) {
            return $this->desactivadas();
        }

        $datos = $request->validate([
            'doctor_id' => ['required', 'exists:doctores,id'],
            'tratamiento_id' => ['required', 'exists:tratamientos,id'],
            'fecha' => ['required', 'date'],
            'hora' => ['required', 'date_format:H:i'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ], [], [
            'doctor_id' => 'profesional',
            'tratamiento_id' => 'motivo de consulta',
        ]);

        $cita = $this->crearCitaOnline($this->agenda, $paciente, $datos, $ajustes);

        $this->notificarReserva($cita);

        return redirect()->route('portal.reservar', ['confirmada' => $cita->token])
            ->with('exito', "Tu cita del {$cita->fecha_hora} quedó reservada y pendiente de confirmación.");
    }

    private function desactivadas(): RedirectResponse
    {
        return redirect()->route('portal.citas')
            ->with('error', 'La reserva de citas desde el portal no está disponible por ahora. Comunícate con la clínica.');
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
