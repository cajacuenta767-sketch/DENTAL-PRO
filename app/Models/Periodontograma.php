<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Periodontograma: sondaje de seis sitios por pieza, sangrado, placa,
 * recesión, movilidad y furca, con los índices resumen que usa el
 * periodoncista.
 */
class Periodontograma extends Model
{
    use Auditable, Concerns\BelongsToClinica, HasFactory, SoftDeletes;

    protected $table = 'periodontogramas';

    protected $fillable = ['paciente_id', 'doctor_id', 'cita_id', 'usuario_id', 'fecha', 'piezas', 'observaciones'];

    protected function casts(): array
    {
        return ['piezas' => 'array', 'fecha' => 'date'];
    }

    /** Seis sitios por pieza: tres vestibulares y tres linguales/palatinos. */
    public const SITIOS = ['dv', 'v', 'mv', 'dl', 'l', 'ml'];

    public const SITIOS_ETIQUETA = [
        'dv' => 'Disto-vestibular', 'v' => 'Vestibular', 'mv' => 'Mesio-vestibular',
        'dl' => 'Disto-lingual', 'l' => 'Lingual', 'ml' => 'Mesio-lingual',
    ];

    public const FURCA = [0 => 'Sin furca', 1 => 'Grado I', 2 => 'Grado II', 3 => 'Grado III'];

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

    /** Pieza en blanco con la estructura que espera el formulario. */
    public static function piezaEnBlanco(): array
    {
        return [
            'ausente' => false,
            'sondaje' => array_fill_keys(self::SITIOS, null),
            'sangrado' => array_fill_keys(self::SITIOS, false),
            'placa' => array_fill_keys(self::SITIOS, false),
            'recesion' => array_fill_keys(self::SITIOS, null),
            'movilidad' => 0,
            'furca' => 0,
            'nota' => null,
        ];
    }

    public static function piezasEnBlanco(): array
    {
        return collect(Odontograma::PIEZAS_ADULTO)->flatten()
            ->mapWithKeys(fn ($n) => [(string) $n => self::piezaEnBlanco()])
            ->all();
    }

    /**
     * Índices resumen: porcentaje de sitios con sangrado y placa, profundidad
     * media, sitios con bolsa (>= 4 mm) y piezas con movilidad.
     *
     * @return array{sitios: int, sangrado: float, placa: float, profundidad_media: float, bolsas: int, bolsas_profundas: int, movilidad: int, ausentes: int}
     */
    public function indices(): array
    {
        $sitios = 0;
        $sangrado = 0;
        $placa = 0;
        $suma = 0.0;
        $medidos = 0;
        $bolsas = 0;
        $profundas = 0;
        $movilidad = 0;
        $ausentes = 0;

        foreach ($this->piezas ?? [] as $pieza) {
            if (! empty($pieza['ausente'])) {
                $ausentes++;

                continue;
            }

            if ((int) ($pieza['movilidad'] ?? 0) > 0) {
                $movilidad++;
            }

            foreach (self::SITIOS as $sitio) {
                $sitios++;
                $sangrado += ! empty($pieza['sangrado'][$sitio]) ? 1 : 0;
                $placa += ! empty($pieza['placa'][$sitio]) ? 1 : 0;

                $valor = $pieza['sondaje'][$sitio] ?? null;

                if ($valor !== null && $valor !== '') {
                    $medidos++;
                    $suma += (float) $valor;
                    $bolsas += (float) $valor >= 4 ? 1 : 0;
                    $profundas += (float) $valor >= 6 ? 1 : 0;
                }
            }
        }

        return [
            'sitios' => $sitios,
            'sangrado' => $sitios ? round($sangrado / $sitios * 100, 1) : 0.0,
            'placa' => $sitios ? round($placa / $sitios * 100, 1) : 0.0,
            'profundidad_media' => $medidos ? round($suma / $medidos, 2) : 0.0,
            'bolsas' => $bolsas,
            'bolsas_profundas' => $profundas,
            'movilidad' => $movilidad,
            'ausentes' => $ausentes,
        ];
    }

    /** Diagnóstico orientativo según los índices (guía, no sustituye el criterio clínico). */
    public function diagnosticoOrientativo(): string
    {
        $i = $this->indices();

        return match (true) {
            $i['bolsas_profundas'] > 0 || $i['profundidad_media'] >= 5 => 'Compatible con periodontitis avanzada: bolsas de 6 mm o más.',
            $i['bolsas'] > 0 || $i['profundidad_media'] >= 4 => 'Compatible con periodontitis moderada: bolsas de 4 a 5 mm.',
            $i['sangrado'] >= 10 => 'Compatible con gingivitis: sangrado al sondaje en el '.$i['sangrado'].' % de los sitios.',
            default => 'Sin signos de enfermedad periodontal activa.',
        };
    }
}
