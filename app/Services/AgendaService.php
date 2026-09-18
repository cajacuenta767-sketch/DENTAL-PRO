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
 * configurados con las citas que ya tiene tomadas, respetando la duración
 * de cada tratamiento: una endodoncia de 90 minutos bloquea tres cupos de
 * media hora, no uno.
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

    public function intervalo(): int
    {
        return max(5, (int) Ajuste::actual()->minutos_intervalo_cita);
    }

    /**
     * Devuelve los cupos del doctor en la fecha dada. Un cupo está ocupado
     * cuando cualquier cita vigente se cruza con su franja.
     *
     * @return Collection<int, array{hora: string, turno: string, disponible: bool, cita: ?Cita}>
     */
    public function cupos(Doctor $doctor, string $fecha, ?int $ignorarCitaId = null, ?int $sucursalId = null): Collection
    {
        $dia = CarbonImmutable::parse($fecha);
        $clave = self::DIAS[(int) $dia->dayOfWeek];
        $intervalo = $this->intervalo();

        $turnos = $doctor->horarios()
            ->activos()
            ->where('dia_semana', $clave)
            ->when($sucursalId, fn ($q) => $q->where(function ($sub) use ($sucursalId) {
                $sub->where('sucursal_id', $sucursalId)->orWhereNull('sucursal_id');
            }))
            ->orderBy('hora_inicio')
            ->get();

        if ($turnos->isEmpty()) {
            return collect();
        }

        $ocupadas = $this->citasDelDia($doctor, $dia, $ignorarCitaId);
        $cupos = collect();

        foreach ($turnos as $turno) {
            $cursor = $dia->setTimeFromTimeString((string) $turno->hora_inicio);
            $fin = $dia->setTimeFromTimeString((string) $turno->hora_fin);

            while ($cursor < $fin) {
                $finCupo = $cursor->addMinutes($intervalo);
                $cita = $ocupadas->first(fn (Cita $c) => $c->inicio < $finCupo && $c->fin > $cursor);

                $cupos->push([
                    'hora' => $cursor->format('H:i'),
                    'turno' => $turno->turno,
                    'disponible' => $cita === null,
                    'cita' => $cita,
                ]);

                $cursor = $finCupo;
            }
        }

        return $cupos->unique('hora')->sortBy('hora')->values();
    }

    /**
     * Solo las horas en las que cabe una cita de $duracion minutos: el cupo
     * y los siguientes que necesite deben estar libres y ser consecutivos
     * dentro del mismo turno.
     */
    public function horasLibres(Doctor $doctor, string $fecha, ?int $ignorarCitaId = null, ?int $duracion = null, ?int $sucursalId = null): array
    {
        $cupos = $this->cupos($doctor, $fecha, $ignorarCitaId, $sucursalId)->values();
        $intervalo = $this->intervalo();
        $necesarios = max(1, (int) ceil(max(1, (int) $duracion) / $intervalo));
        $libres = [];

        foreach ($cupos as $i => $cupo) {
            if (! $cupo['disponible']) {
                continue;
            }

            $cabe = true;
            $esperada = CarbonImmutable::parse("{$fecha} {$cupo['hora']}");

            for ($k = 0; $k < $necesarios; $k++) {
                $siguiente = $cupos->get($i + $k);

                if (! $siguiente || ! $siguiente['disponible'] || $siguiente['turno'] !== $cupo['turno']
                    || CarbonImmutable::parse("{$fecha} {$siguiente['hora']}")->notEqualTo($esperada)) {
                    $cabe = false;
                    break;
                }

                $esperada = $esperada->addMinutes($intervalo);
            }

            if ($cabe) {
                $libres[] = $cupo['hora'];
            }
        }

        return $libres;
    }

    /** Etiqueta legible del día de la semana en español. */
    public function nombreDia(string $fecha): string
    {
        return config('odontosuite.dias_semana.'.self::DIAS[(int) CarbonImmutable::parse($fecha)->dayOfWeek]);
    }

    /**
     * Comprueba que el doctor atienda ese día y que la cita completa
     * (desde la hora hasta hora + duración) quepa dentro de un turno.
     */
    public function horaValida(Doctor $doctor, string $fecha, string $hora, ?int $duracion = null, ?int $sucursalId = null): bool
    {
        $clave = self::DIAS[(int) CarbonImmutable::parse($fecha)->dayOfWeek];
        $inicio = CarbonImmutable::parse("{$fecha} {$hora}");
        $fin = $inicio->addMinutes(max(1, (int) $duracion));

        return Horario::query()
            ->where('doctor_id', $doctor->id)
            ->where('dia_semana', $clave)
            ->where('activo', true)
            ->when($sucursalId, fn ($q) => $q->where(function ($sub) use ($sucursalId) {
                $sub->where('sucursal_id', $sucursalId)->orWhereNull('sucursal_id');
            }))
            ->where('hora_inicio', '<=', $inicio->format('H:i:s'))
            ->where('hora_fin', '>=', $fin->format('H:i:s'))
            ->exists();
    }

    /**
     * Devuelve la cita vigente que se cruza con la franja propuesta, o null
     * si el doctor está libre. Es la comprobación que hacen los formularios.
     */
    public function conflicto(Doctor $doctor, string $fecha, string $hora, ?int $duracion = null, ?int $ignorarCitaId = null): ?Cita
    {
        $inicio = CarbonImmutable::parse("{$fecha} {$hora}");
        $fin = $inicio->addMinutes(max(1, (int) ($duracion ?: $this->intervalo())));

        return $this->citasDelDia($doctor, $inicio, $ignorarCitaId)
            ->first(fn (Cita $c) => $c->inicio < $fin && $c->fin > $inicio);
    }

    /** @return Collection<int, Cita> */
    private function citasDelDia(Doctor $doctor, CarbonImmutable $dia, ?int $ignorarCitaId): Collection
    {
        return Cita::query()
            ->where('doctor_id', $doctor->id)
            ->whereDate('fecha', $dia->toDateString())
            ->vigentes()
            ->when($ignorarCitaId, fn ($q) => $q->where('id', '!=', $ignorarCitaId))
            ->with(['paciente:id,nombres,apellidos,telefono', 'tratamiento:id,nombre,duracion'])
            ->orderBy('hora')
            ->get();
    }
}
