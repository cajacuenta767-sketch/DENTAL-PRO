<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToClinica;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conductometria extends Model
{
    use Auditable, BelongsToClinica, HasFactory, SoftDeletes;

    protected $table = 'conductometrias';

    protected $fillable = [
        'clinica_id',
        'paciente_id',
        'doctor_id',
        'cita_id',
        'usuario_id',
        'diente',
        'diagnostico_pulpar',
        'diagnostico_periapical',
        'conductos',
        'solucion_irrigante',
        'medicacion_intraconducto',
        'cemento_sellador',
        'tecnica_obturacion',
        'estado',
        'fecha',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'conductos' => 'array',
            'fecha' => 'date',
            'diente' => 'integer',
        ];
    }

    public const ESTADOS = [
        'EN_TRATAMIENTO' => 'En Tratamiento',
        'CONDUCTOMETRIA_TOMADA' => 'Conductometría Tomada',
        'OBTURADO' => 'Obturado',
        'FINALIZADO' => 'Finalizado',
    ];

    public const DIAGNOSTICOS_PULPARES = [
        'PULPA_SANA' => 'Pulpa clínicamente sana',
        'PULPITIS_REVERSIBLE' => 'Pulpitis reversible',
        'PULPITIS_IRREVERSIBLE_SINTOMATICA' => 'Pulpitis irreversible sintomática',
        'PULPITIS_IRREVERSIBLE_ASINTOMATICA' => 'Pulpitis irreversible asintomática',
        'NECROSIS_PULPAR' => 'Necrosis pulpar',
        'PREVIAMENTE_TRATADO' => 'Previamente tratado (Retratamiento)',
        'TERAPIA_PREVIAMENTE_INICIADA' => 'Terapia previamente iniciada',
    ];

    public const DIAGNOSTICOS_PERIAPICALES = [
        'APICAL_SANO' => 'Tejidos apicales sanos',
        'PERIODONTITIS_APICAL_SINTOMATICA' => 'Periodontitis apical sintomática',
        'PERIODONTITIS_APICAL_ASINTOMATICA' => 'Periodontitis apical asintomática',
        'ABSCESO_APICAL_AGUDO' => 'Absceso apical agudo',
        'ABSCESO_APICAL_CRONICO' => 'Absceso apical crónico',
        'OSTEITIS_CONDENSANTE' => 'Osteítis condensante',
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

    /** Nombre anatómico descriptivo de la pieza FDI */
    public function getNombreDienteAttribute(): string
    {
        $nombres = [
            11 => 'Incisivo Central Superior Derecho',
            12 => 'Incisivo Lateral Superior Derecho',
            13 => 'Canino Superior Derecho',
            14 => 'Primer Premolar Superior Derecho',
            15 => 'Segundo Premolar Superior Derecho',
            16 => 'Primer Molar Superior Derecho',
            17 => 'Segundo Molar Superior Derecho',
            18 => 'Tercer Molar Superior Derecho',
            21 => 'Incisivo Central Superior Izquierdo',
            22 => 'Incisivo Lateral Superior Izquierdo',
            23 => 'Canino Superior Izquierdo',
            24 => 'Primer Premolar Superior Izquierdo',
            25 => 'Segundo Premolar Superior Izquierdo',
            26 => 'Primer Molar Superior Izquierdo',
            27 => 'Segundo Molar Superior Izquierdo',
            28 => 'Tercer Molar Superior Izquierdo',
            31 => 'Incisivo Central Inferior Izquierdo',
            32 => 'Incisivo Lateral Inferior Izquierdo',
            33 => 'Canino Inferior Izquierdo',
            34 => 'Primer Premolar Inferior Izquierdo',
            35 => 'Segundo Premolar Inferior Izquierdo',
            36 => 'Primer Molar Inferior Izquierdo',
            37 => 'Segundo Molar Inferior Izquierdo',
            38 => 'Tercer Molar Inferior Izquierdo',
            41 => 'Incisivo Central Inferior Derecho',
            42 => 'Incisivo Lateral Inferior Derecho',
            43 => 'Canino Inferior Derecho',
            44 => 'Primer Premolar Inferior Derecho',
            45 => 'Segundo Premolar Inferior Derecho',
            46 => 'Primer Molar Inferior Derecho',
            47 => 'Segundo Molar Inferior Derecho',
            48 => 'Tercer Molar Inferior Derecho',
        ];

        return $nombres[$this->diente] ?? "Pieza {$this->diente}";
    }

    /** Estado formateado con badge */
    public function getColorEstadoAttribute(): string
    {
        return match ($this->estado) {
            'FINALIZADO' => 'success',
            'OBTURADO' => 'azure',
            'CONDUCTOMETRIA_TOMADA' => 'indigo',
            default => 'warning',
        };
    }
}
