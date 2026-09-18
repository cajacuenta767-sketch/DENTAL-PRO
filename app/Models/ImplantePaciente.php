<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToClinica;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImplantePaciente extends Model
{
    use Auditable, BelongsToClinica, HasFactory, SoftDeletes;

    protected $table = 'implantes_paciente';

    protected $fillable = [
        'clinica_id',
        'paciente_id',
        'doctor_id',
        'cita_id',
        'posicion_fdi',
        'marca',
        'modelo',
        'numero_lote',
        'numero_serie',
        'diametro_mm',
        'longitud_mm',
        'tipo_conexion',
        'torque_insercion_ncm',
        'isq_estabilidad',
        'injerto_oseo',
        'membrana',
        'fecha_colocacion',
        'fecha_rehabilitacion',
        'qr_pasaporte_token',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_colocacion' => 'date',
            'fecha_rehabilitacion' => 'date',
            'diametro_mm' => 'decimal:2',
            'longitud_mm' => 'decimal:2',
            'torque_insercion_ncm' => 'decimal:1',
            'isq_estabilidad' => 'integer',
            'posicion_fdi' => 'integer',
        ];
    }

    public const ESTADOS = [
        'COLOCADO' => 'Colocado / Período de Cicatrización',
        'OSTEOINTEGRADO' => 'Osteointegrado (Apto para rehabilitación)',
        'REHABILITADO' => 'Rehabilitado con Corona / Prótesis',
        'FALLIDO' => 'Falla de osteointegración / Explantado',
    ];

    public const CONEXIONES = [
        'CONO_MORSE' => 'Cono Morse (Sellado bacteriano friccional)',
        'HEXAGONO_INTERNO' => 'Hexágono Interno',
        'HEXAGONO_EXTERNO' => 'Hexágono Externo',
        'OCTAGONAL' => 'Octogonal / Tissue Level',
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

    public function getColorEstadoAttribute(): string
    {
        return match ($this->estado) {
            'REHABILITADO' => 'success',
            'OSTEOINTEGRADO' => 'azure',
            'COLOCADO' => 'indigo',
            'FALLIDO' => 'danger',
            default => 'secondary',
        };
    }
}
