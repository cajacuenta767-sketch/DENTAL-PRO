<?php

namespace App\Http\Controllers\Concerns;

use App\Mail\CitaConfirmacionMail;
use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\Especialidad;
use App\Models\Paciente;
use App\Models\Tratamiento;
use App\Services\AgendaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Lógica común a las reservas en línea (formulario público con token y
 * portal del paciente): catálogo reservable, cupos con anticipación,
 * ventana permitida y creación de la cita PENDIENTE de origen ONLINE.
 */
trait ReservaCitas
{
    /** Especialidades con al menos un tratamiento y un doctor activos. */
    protected function especialidadesReservables()
    {
        return Especialidad::activas()
            ->whereHas('tratamientos', fn ($q) => $q->where('activo', true))
            ->whereHas('doctores', fn ($q) => $q->where('activo', true))
            ->orderBy('nombre')
            ->get();
    }

    /** Doctores y tratamientos de una especialidad, listos para los selectores. */
    protected function opcionesDeEspecialidad(int $especialidadId): array
    {
        return [
            'doctores' => Doctor::activos()
                ->where('especialidad_id', $especialidadId)
                ->orderBy('apellidos')
                ->get()
                ->map(fn ($d) => ['id' => $d->id, 'nombre' => $d->nombre_profesional]),
            'tratamientos' => Tratamiento::activos()
                ->where('especialidad_id', $especialidadId)
                ->orderBy('nombre')
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'nombre' => $t->nombre,
                    'duracion' => $t->duracion,
                    'precio' => (float) $t->precio,
                ]),
        ];
    }

    /** Cupos libres de un doctor en una fecha, descartando los que caen dentro de la anticipación mínima. */
    protected function cuposReservables(AgendaService $agenda, Doctor $doctor, string $fecha, ?int $duracion, Ajuste $ajustes): array
    {
        $limite = now()->addHours((int) $ajustes->reservas_minimo_horas);

        return array_values(array_filter(
            $agenda->horasLibres($doctor, $fecha, null, $duracion),
            fn ($hora) => Carbon::parse("{$fecha} {$hora}")->greaterThanOrEqualTo($limite)
        ));
    }

    /** Primer y último día que admite reservas según la configuración. */
    protected function ventanaReservas(Ajuste $ajustes): array
    {
        return [
            'minimo' => now()->addHours((int) $ajustes->reservas_minimo_horas)->toDateString(),
            'maximo' => now()->addDays((int) $ajustes->reservas_anticipacion_dias)->toDateString(),
        ];
    }

    protected function verificarVentana(string $fecha, string $hora, Ajuste $ajustes): void
    {
        $momento = Carbon::parse("{$fecha} {$hora}");

        if ($momento->lessThan(now()->addHours((int) $ajustes->reservas_minimo_horas))) {
            throw ValidationException::withMessages([
                'fecha' => "Las reservas en línea requieren al menos {$ajustes->reservas_minimo_horas} horas de anticipación.",
            ]);
        }

        if ($momento->greaterThan(now()->addDays((int) $ajustes->reservas_anticipacion_dias))) {
            throw ValidationException::withMessages([
                'fecha' => "Solo puedes reservar hasta {$ajustes->reservas_anticipacion_dias} días por adelantado.",
            ]);
        }
    }

    /**
     * Valida doctor, tratamiento y horario y crea la cita PENDIENTE de origen
     * ONLINE para un paciente ya identificado. La comprobación de cruce va
     * dentro de la transacción; el índice único de la agenda es la última barrera.
     */
    protected function crearCitaOnline(AgendaService $agenda, Paciente $paciente, array $datos, Ajuste $ajustes): Cita
    {
        $this->verificarVentana($datos['fecha'], $datos['hora'], $ajustes);

        $doctor = Doctor::activos()->findOrFail($datos['doctor_id']);
        $tratamiento = Tratamiento::activos()->findOrFail($datos['tratamiento_id']);

        if ((int) $tratamiento->especialidad_id !== (int) $doctor->especialidad_id) {
            throw ValidationException::withMessages([
                'tratamiento_id' => 'Ese profesional no realiza el tratamiento elegido.',
            ]);
        }

        if (! $agenda->horaValida($doctor, $datos['fecha'], $datos['hora'], $tratamiento->duracion)) {
            throw ValidationException::withMessages([
                'hora' => 'Ese profesional no atiende en el horario elegido.',
            ]);
        }

        return DB::transaction(function () use ($agenda, $paciente, $datos, $doctor, $tratamiento) {
            if ($agenda->conflicto($doctor, $datos['fecha'], $datos['hora'], $tratamiento->duracion)) {
                throw ValidationException::withMessages([
                    'hora' => 'Alguien tomó ese horario mientras completabas el formulario. Elige otro, por favor.',
                ]);
            }

            return Cita::create([
                'paciente_id' => $paciente->id,
                'doctor_id' => $doctor->id,
                'tratamiento_id' => $tratamiento->id,
                'fecha' => $datos['fecha'],
                'hora' => $datos['hora'],
                'estado' => 'PENDIENTE',
                'origen' => 'ONLINE',
                'motivo' => $datos['motivo'] ?? null,
            ]);
        });
    }

    /** Correo de confirmación (encolado). La reserva ya quedó tomada: el correo no debe hacerla fallar. */
    protected function notificarReserva(Cita $cita): void
    {
        if (blank($cita->paciente->email)) {
            return;
        }

        try {
            Mail::to($cita->paciente->email)->send(new CitaConfirmacionMail($cita));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
