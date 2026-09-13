<?php

namespace App\Services;

use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\Horario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Calcula los cupos de atención de un doctor cruzando sus horarios
 * configurados con las citas que ya tiene tomadas.
 */
class AgendaService
{
    /** Índice del día de la semana (Carbon usa 0 = domingo) a la clave usada en horarios. */
    private const DIAS = [
        0 => 'DOMINGO',
        1 => 'LUNES',
        2 => 'MARTES',
        3 => 'MIERCOLES',
        4 => 'JUEVES',
        5 => 'VIERNES',
        6 => 'SABADO',
    ];

    /**
     * Devuelve los cupos del doctor en la fecha dada.
     *
     * @return Collection<int, array{hora: string, disponible: bool, cita: ?Cita}>
     */
    public function cupos(Doctor $doctor, string $fecha, ?int $ignorarCitaId = null): Collection
    {
        $dia = CarbonImmutable::parse($fecha);
        $clave = self::DIAS[(int) $dia->dayOfWeek];
        $intervalo = max(5, (int) Ajuste::actual()->minutos_intervalo_cita);

        $turnos = $doctor->horarios()
            ->activos()
            ->where('dia_semana', $clave)
            ->orderBy('hora_inicio')
            ->get();

        if ($turnos->isEmpty()) {
            return collect();
        }

        $ocupadas = Cita::query()
            ->where('doctor_id', $doctor->id)
            ->whereDate('fecha', $dia->toDateString())
            ->vigentes()
            ->when($ignorarCitaId, fn ($q) => $q->where('id', '!=', $ignorarCitaId))
            ->with(['paciente:id,nombres,apellidos', 'tratamiento:id,nombre'])
            ->get()
            ->keyBy(fn (Cita $cita) => substr((string) $cita->hora, 0, 5));

        $cupos = collect();

        foreach ($turnos as $turno) {
            $cursor = $dia->setTimeFromTimeString((string) $turno->hora_inicio);
            $fin = $dia->setTimeFromTimeString((string) $turno->hora_fin);

            while ($cursor < $fin) {
                $hora = $cursor->format('H:i');

                $cupos->push([
                    'hora' => $hora,
                    'turno' => $turno->turno,
                    'disponible' => ! $ocupadas->has($hora),
                    'cita' => $ocupadas->get($hora),
                ]);

                $cursor = $cursor->addMinutes($intervalo);
            }
        }

        return $cupos->unique('hora')->sortBy('hora')->values();
    }

    /** Solo las horas libres, para alimentar el <select> del formulario de citas. */
    public function horasLibres(Doctor $doctor, string $fecha, ?int $ignorarCitaId = null): array
    {
        return $this->cupos($doctor, $fecha, $ignorarCitaId)
            ->where('disponible', true)
            ->pluck('hora')
            ->all();
    }

    /** Etiqueta legible del día de la semana en español. */
    public function nombreDia(string $fecha): string
    {
        return config('odontosuite.dias_semana.'.self::DIAS[(int) CarbonImmutable::parse($fecha)->dayOfWeek]);
    }

    /** Comprueba que el doctor atienda ese día y a esa hora. */
    public function horaValida(Doctor $doctor, string $fecha, string $hora): bool
    {
        $clave = self::DIAS[(int) CarbonImmutable::parse($fecha)->dayOfWeek];

        return Horario::query()
            ->where('doctor_id', $doctor->id)
            ->where('dia_semana', $clave)
            ->where('activo', true)
            ->where('hora_inicio', '<=', $hora)
            ->where('hora_fin', '>', $hora)
            ->exists();
    }
}
