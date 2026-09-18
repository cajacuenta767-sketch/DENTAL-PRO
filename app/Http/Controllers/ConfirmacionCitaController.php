<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\Cita;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Confirmación de asistencia por el enlace firmado que recibe el paciente
 * en el correo, WhatsApp o SMS. No exige sesión: la firma de la URL y el
 * token de la cita son la única credencial.
 */
class ConfirmacionCitaController extends Controller
{
    public function confirmar(Request $request, string $token): View
    {
        $cita = Cita::query()
            ->with(['paciente', 'doctor.especialidad', 'tratamiento'])
            ->where(fn ($q) => $q->where('confirmacion_token', $token)
                ->orWhere(fn ($s) => $s->whereNull('confirmacion_token')->where('token', $token)))
            ->firstOrFail();

        $resultado = match (true) {
            $cita->estado === 'CANCELADA' => 'cancelada',
            in_array($cita->estado, ['EN_CURSO', 'COMPLETADA'], true) => 'atendida',
            $cita->inicio->isPast() => 'pasada',
            default => 'confirmada',
        };

        // Idempotente: una cita ya confirmada por el paciente no se vuelve a tocar.
        if ($resultado === 'confirmada' && ($cita->estado !== 'CONFIRMADA' || ! $cita->confirmada_en)) {
            $cita->forceFill([
                'estado' => 'CONFIRMADA',
                'confirmada_en' => now(),
                'confirmada_por' => 'paciente',
            ])->save();

            Auditoria::registrar('ACTUALIZAR', $cita, 'El paciente confirmó por enlace');
        }

        return view('publico.cita-confirmada', [
            'cita' => $cita,
            'resultado' => $resultado,
            'ajustes' => Ajuste::actual(),
        ]);
    }
}
