<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToClinica;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrazadoCefalometrico extends Model
{
    use Auditable, BelongsToClinica, HasFactory, SoftDeletes;

    protected $table = 'trazados_cefalometricos';

    protected $fillable = [
        'clinica_id',
        'paciente_id',
        'doctor_id',
        'cita_id',
        'usuario_id',
        'fecha',
        'tipo_analisis',
        'imagen_radiografia',
        'puntos',
        'medidas',
        'diagnostico_esqueletico',
        'patron_crecimiento',
        'interpretacion',
        'plan_tratamiento',
    ];

    protected function casts(): array
    {
        return [
            'puntos' => 'array',
            'medidas' => 'array',
            'fecha' => 'date',
        ];
    }

    public const PUNTOS_CLAVE = [
        'S' => 'Silla Turca (S)',
        'N' => 'Nasion (N)',
        'A' => 'Punto A (Subespinal)',
        'B' => 'Punto B (Supramentoniano)',
        'Pog' => 'Pogonion (Pog)',
        'Gn' => 'Gnation (Gn)',
        'Go' => 'Gonion (Go)',
        'UI_T' => 'Incisivo Superior - Borde',
        'UI_A' => 'Incisivo Superior - Ápice',
        'LI_T' => 'Incisivo Inferior - Borde',
        'LI_A' => 'Incisivo Inferior - Ápice',
    ];

    public const DIAGNOSTICOS = [
        'CLASE_I' => 'Clase I Esquelética (Relación Armónica)',
        'CLASE_II' => 'Clase II Esquelética (Retrognatismo mandibular o prognatismo maxilar)',
        'CLASE_III' => 'Clase III Esquelética (Prognatismo mandibular o hipoplasia maxilar)',
    ];

    public const PATRONES = [
        'MESOFACIAL' => 'Mesofacial (Normodivergente)',
        'BRAQUIFACIAL' => 'Braquifacial (Hipodivergente / Crecimiento Horizontal)',
        'DOLICOFACIAL' => 'Dolicofacial (Hiperdivergente / Crecimiento Vertical)',
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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * Calcula el ángulo en grados formado por tres puntos (vértice en p2): vector p2->p1 y p2->p3.
     */
    public static function calcularAngulo(array $p1, array $p2, array $p3): float
    {
        $v1x = (float)$p1['x'] - (float)$p2['x'];
        $v1y = (float)$p1['y'] - (float)$p2['y'];
        $v2x = (float)$p3['x'] - (float)$p2['x'];
        $v2y = (float)$p3['y'] - (float)$p2['y'];

        $dot = $v1x * $v2x + $v1y * $v2y;
        $mag1 = sqrt($v1x * $v1x + $v1y * $v1y);
        $mag2 = sqrt($v2x * $v2x + $v2y * $v2y);

        if ($mag1 == 0 || $mag2 == 0) return 0.0;

        $cos = max(-1.0, min(1.0, $dot / ($mag1 * $mag2)));
        return round(rad2deg(acos($cos)), 1);
    }

    /**
     * Calcula automáticamente los parámetros de Steiner y la clasificación esquelética.
     */
    public static function computarAnalisis(array $puntos): array
    {
        $medidas = [];

        // 1. Ángulo SNA (S-N-A)
        if (isset($puntos['S'], $puntos['N'], $puntos['A'])) {
            $medidas['SNA'] = self::calcularAngulo($puntos['S'], $puntos['N'], $puntos['A']);
        }

        // 2. Ángulo SNB (S-N-B)
        if (isset($puntos['S'], $puntos['N'], $puntos['B'])) {
            $medidas['SNB'] = self::calcularAngulo($puntos['S'], $puntos['N'], $puntos['B']);
        }

        // 3. Ángulo ANB (SNA - SNB)
        if (isset($medidas['SNA'], $medidas['SNB'])) {
            $medidas['ANB'] = round($medidas['SNA'] - $medidas['SNB'], 1);
        }

        // 4. Ángulo Mandibular Go-Gn a S-N (GoGn-SN)
        if (isset($puntos['Go'], $puntos['Gn'], $puntos['S'], $puntos['N'])) {
            // Ángulo entre vectores (Gn - Go) y (N - S)
            $v1x = (float)$puntos['Gn']['x'] - (float)$puntos['Go']['x'];
            $v1y = (float)$puntos['Gn']['y'] - (float)$puntos['Go']['y'];
            $v2x = (float)$puntos['N']['x'] - (float)$puntos['S']['x'];
            $v2y = (float)$puntos['N']['y'] - (float)$puntos['S']['y'];
            $dot = $v1x * $v2x + $v1y * $v2y;
            $mag1 = sqrt($v1x * $v1x + $v1y * $v1y);
            $mag2 = sqrt($v2x * $v2x + $v2y * $v2y);
            if ($mag1 > 0 && $mag2 > 0) {
                $cos = max(-1.0, min(1.0, $dot / ($mag1 * $mag2)));
                $medidas['GoGn_SN'] = round(rad2deg(acos($cos)), 1);
            }
        }

        // Diagnóstico esquelético
        $diagnostico = 'CLASE_I';
        if (isset($medidas['ANB'])) {
            if ($medidas['ANB'] > 4.0) {
                $diagnostico = 'CLASE_II';
            } elseif ($medidas['ANB'] < 0.0) {
                $diagnostico = 'CLASE_III';
            } else {
                $diagnostico = 'CLASE_I';
            }
        }

        // Patrón de crecimiento
        $patron = 'MESOFACIAL';
        if (isset($medidas['GoGn_SN'])) {
            if ($medidas['GoGn_SN'] > 36.0) {
                $patron = 'DOLICOFACIAL';
            } elseif ($medidas['GoGn_SN'] < 28.0) {
                $patron = 'BRAQUIFACIAL';
            } else {
                $patron = 'MESOFACIAL';
            }
        }

        return [
            'medidas' => $medidas,
            'diagnostico' => $diagnostico,
            'patron' => $patron,
        ];
    }
}
