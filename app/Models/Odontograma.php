<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Odontograma extends Model
{
    use HasFactory;

    protected $table = 'odontogramas';

    protected $fillable = [
        'paciente_id', 'doctor_id', 'cita_id', 'tipo', 'piezas', 'observaciones', 'fecha',
    ];

    protected function casts(): array
    {
        return [
            'piezas' => 'array',
            'fecha' => 'date',
        ];
    }

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

    /** Caras registrables por pieza. */
    public const CARAS = ['vestibular', 'lingual', 'mesial', 'distal', 'oclusal'];

    /** Hallazgos disponibles con su color de representación. */
    public const ESTADOS = [
        'sano' => ['etiqueta' => 'Sano', 'color' => '#ffffff'],
        'caries' => ['etiqueta' => 'Caries', 'color' => '#d63939'],
        'obturado' => ['etiqueta' => 'Obturado', 'color' => '#206bc4'],
        'corona' => ['etiqueta' => 'Corona', 'color' => '#f59f00'],
        'endodoncia' => ['etiqueta' => 'Endodoncia', 'color' => '#ae3ec9'],
        'ausente' => ['etiqueta' => 'Ausente', 'color' => '#868e96'],
        'implante' => ['etiqueta' => 'Implante', 'color' => '#0ca678'],
        'fractura' => ['etiqueta' => 'Fractura', 'color' => '#f76707'],
        'sellante' => ['etiqueta' => 'Sellante', 'color' => '#4dabf7'],
        'extraccion' => ['etiqueta' => 'Indicado extracción', 'color' => '#e03131'],
    ];

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
        return $tipo === 'INFANTIL' ? self::PIEZAS_INFANTIL : self::PIEZAS_ADULTO;
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
