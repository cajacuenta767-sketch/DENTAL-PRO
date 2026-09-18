<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Odontograma extends Model
{
    use Auditable, Concerns\BelongsToClinica, HasFactory, SoftDeletes;

    protected $table = 'odontogramas';

    protected $fillable = [
        'paciente_id', 'doctor_id', 'cita_id', 'tipo', 'escala_frankl', 'piezas', 'observaciones', 'fecha',
    ];

    protected function casts(): array
    {
        return [
            'piezas' => 'array',
            'fecha' => 'date',
            'escala_frankl' => 'integer',
        ];
    }

    /** Escala de conducta de Frankl para odontopediatría */
    public const ESCALA_FRANKL = [
        1 => [
            'etiqueta' => 'Definitivamente Negativo (--)',
            'descripcion' => 'Rechazo al tratamiento, llanto fuerte, movimientos de defensa extremos.',
            'color' => 'danger',
            'icono' => 'ti-mood-sad',
            'simbolo' => '--',
        ],
        2 => [
            'etiqueta' => 'Negativo (-)',
            'descripcion' => 'Renuente a aceptar el tratamiento, poco cooperador, retraído.',
            'color' => 'warning',
            'icono' => 'ti-mood-neutral',
            'simbolo' => '-',
        ],
        3 => [
            'etiqueta' => 'Positivo (+)',
            'descripcion' => 'Acepta el tratamiento, cooperador con ciertas reservas, sigue instrucciones.',
            'color' => 'info',
            'icono' => 'ti-mood-smile',
            'simbolo' => '+',
        ],
        4 => [
            'etiqueta' => 'Definitivamente Positivo (++)',
            'descripcion' => 'Excelente compenetración con el odontólogo, entusiasmo y cooperación activa.',
            'color' => 'success',
            'icono' => 'ti-mood-happy',
            'simbolo' => '++',
        ],
    ];

    /** Numeración FDI: cuadrantes superiores e inferiores de la dentición permanente. */
    public const PIEZAS_ADULTO = [
        'superior_derecho' => [18, 17, 16, 15, 14, 13, 12, 11],
        'superior_izquierdo' => [21, 22, 23, 24, 25, 26, 27, 28],
        'inferior_derecho' => [48, 47, 46, 45, 44, 43, 42, 41],
        'inferior_izquierdo' => [31, 32, 33, 34, 35, 36, 37, 38],
    ];

    /** Numeración FDI de la dentición temporal. */
    public const PIEZAS_INFANTIL = [
        'superior_derecho' => [55, 54, 53, 52, 51],
        'superior_izquierdo' => [61, 62, 63, 64, 65],
        'inferior_derecho' => [85, 84, 83, 82, 81],
        'inferior_izquierdo' => [71, 72, 73, 74, 75],
    ];

    /** Numeración FDI dentición mixta (adulto y decidua combinados por cuadrante) */
    public const PIEZAS_MIXTO = [
        'superior_derecho' => [18, 17, 16, 55, 54, 53, 52, 51, 11, 12, 13, 14, 15],
        'superior_izquierdo' => [21, 22, 23, 24, 25, 61, 62, 63, 64, 65, 26, 27, 28],
        'inferior_derecho' => [48, 47, 46, 85, 84, 83, 82, 81, 41, 42, 43, 44, 45],
        'inferior_izquierdo' => [31, 32, 33, 34, 35, 71, 72, 73, 74, 75, 36, 37, 38],
    ];

    /** Caras registrables por pieza. */
    public const CARAS = ['vestibular', 'lingual', 'mesial', 'distal', 'oclusal'];

    /**
     * Hallazgos disponibles. La capa separa lo diagnosticado (evaluación,
     * en rojos y naranjas) de lo ya resuelto (ejecución, en azules y verdes),
     * que es lo que permite filtrar el mapa por etapa del tratamiento.
     */
    public const ESTADOS = [
        'sano' => ['etiqueta' => 'Sano', 'color' => '#ffffff', 'capa' => 'ninguna'],
        'caries' => ['etiqueta' => 'Caries', 'color' => '#d63939', 'capa' => 'evaluacion'],
        'fractura' => ['etiqueta' => 'Fractura', 'color' => '#f76707', 'capa' => 'evaluacion'],
        'extraccion' => ['etiqueta' => 'Indicado extracción', 'color' => '#e03131', 'capa' => 'evaluacion'],
        'movilidad' => ['etiqueta' => 'Movilidad', 'color' => '#f59f00', 'capa' => 'evaluacion'],
        'obturado' => ['etiqueta' => 'Obturado', 'color' => '#206bc4', 'capa' => 'ejecucion'],
        'corona' => ['etiqueta' => 'Corona', 'color' => '#7048e8', 'capa' => 'ejecucion'],
        'endodoncia' => ['etiqueta' => 'Endodoncia', 'color' => '#ae3ec9', 'capa' => 'ejecucion'],
        'sellante' => ['etiqueta' => 'Sellante', 'color' => '#4dabf7', 'capa' => 'ejecucion'],
        'implante' => ['etiqueta' => 'Implante', 'color' => '#0ca678', 'capa' => 'ejecucion'],
        'protesis' => ['etiqueta' => 'Prótesis', 'color' => '#12b886', 'capa' => 'ejecucion'],
        'ausente' => ['etiqueta' => 'Ausente', 'color' => '#868e96', 'capa' => 'ejecucion'],
    ];

    /** Hallazgos de una capa concreta: evaluacion o ejecucion. */
    public static function estadosDeCapa(string $capa): array
    {
        return array_filter(self::ESTADOS, fn ($e) => $e['capa'] === $capa);
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public static function cuadrantes(string $tipo): array
    {
        return match ($tipo) {
            'INFANTIL' => self::PIEZAS_INFANTIL,
            'MIXTO' => self::PIEZAS_MIXTO,
            default => self::PIEZAS_ADULTO,
        };
    }

    /** Cuenta las piezas afectadas separadas por capa. */
    public function resumenPorCapa(): array
    {
        $conteo = ['evaluacion' => 0, 'ejecucion' => 0];

        foreach ($this->piezas ?? [] as $pieza) {
            $hallazgos = collect($pieza['caras'] ?? [])->push($pieza['estado'] ?? 'sano');

            foreach (['evaluacion', 'ejecucion'] as $capa) {
                $claves = array_keys(self::estadosDeCapa($capa));

                if ($hallazgos->intersect($claves)->isNotEmpty()) {
                    $conteo[$capa]++;
                }
            }
        }

        return $conteo;
    }

    /** Grados de movilidad dentaria (índice de Miller). */
    public const MOVILIDAD = [0 => 'Sin movilidad', 1 => 'Grado I', 2 => 'Grado II', 3 => 'Grado III'];

    /**
     * Diferencias respecto a otro mapa (normalmente el odontograma anterior):
     * qué piezas o caras cambiaron y en qué sentido.
     *
     * @return array<int, array{pieza: string, cara: ?string, antes: string, despues: string}>
     */
    public function cambiosRespectoA(?array $anterior): array
    {
        if (! $anterior) {
            return [];
        }

        $cambios = [];

        foreach ($this->piezas ?? [] as $numero => $pieza) {
            $previa = $anterior[$numero] ?? null;
            $estadoAntes = $previa['estado'] ?? 'sano';
            $estadoAhora = $pieza['estado'] ?? 'sano';

            if ($estadoAntes !== $estadoAhora) {
                $cambios[] = ['pieza' => (string) $numero, 'cara' => null, 'antes' => $estadoAntes, 'despues' => $estadoAhora];

                continue;
            }

            foreach (self::CARAS as $cara) {
                $antes = $previa['caras'][$cara] ?? 'sano';
                $ahora = $pieza['caras'][$cara] ?? 'sano';

                if ($antes !== $ahora) {
                    $cambios[] = ['pieza' => (string) $numero, 'cara' => $cara, 'antes' => $antes, 'despues' => $ahora];
                }
            }
        }

        return $cambios;
    }

    /** Texto de diagnóstico listo para pegar en la historia clínica. */
    public function resumenTexto(): string
    {
        $lineas = [];

        foreach ($this->piezas ?? [] as $numero => $pieza) {
            $partes = [];
            $estado = $pieza['estado'] ?? 'sano';

            if ($estado !== 'sano') {
                $partes[] = mb_strtolower(self::ESTADOS[$estado]['etiqueta'] ?? $estado);
            } else {
                foreach ($pieza['caras'] ?? [] as $cara => $valor) {
                    if ($valor !== 'sano') {
                        $partes[] = mb_strtolower(self::ESTADOS[$valor]['etiqueta'] ?? $valor)." ({$cara})";
                    }
                }
            }

            if (($pieza['movilidad'] ?? 0) > 0) {
                $partes[] = 'movilidad '.self::MOVILIDAD[(int) $pieza['movilidad']];
            }

            if ($partes === []) {
                continue;
            }

            $linea = "Pieza {$numero}: ".implode(', ', $partes);

            if (! empty($pieza['urgente'])) {
                $linea .= ' [URGENTE]';
            }

            if (! empty($pieza['nota'])) {
                $linea .= ' — '.$pieza['nota'];
            }

            $lineas[] = $linea.'.';
        }

        return $lineas === [] ? 'Sin hallazgos: dentición sana.' : implode("\n", $lineas);
    }

    /** Piezas marcadas como urgentes. */
    public function getPiezasUrgentesAttribute(): array
    {
        return array_map('strval', array_keys(array_filter($this->piezas ?? [], fn ($p) => ! empty($p['urgente']))));
    }

    /** Número de piezas con algún hallazgo distinto de sano. */
    public function getPiezasAfectadasAttribute(): int
    {
        return collect($this->piezas ?? [])
            ->filter(fn ($pieza) => collect($pieza['caras'] ?? [])->contains(fn ($c) => $c !== 'sano')
                || (($pieza['estado'] ?? 'sano') !== 'sano'))
            ->count();
    }
}
