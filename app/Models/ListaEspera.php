<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pacientes que quieren un turno antes de lo disponible. Cuando se libera
 * un cupo, recepción los contacta y agenda desde aquí.
 */
class ListaEspera extends Model
{
    use Auditable, Concerns\BelongsToClinica, HasFactory;

    protected $table = 'lista_espera';

    protected $fillable = [
        'paciente_id', 'doctor_id', 'especialidad_id', 'tratamiento_id', 'cita_id', 'usuario_id', 'sucursal_id',
        'fecha_desde', 'fecha_hasta', 'preferencia_turno', 'prioridad', 'estado', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_desde' => 'date',
            'fecha_hasta' => 'date',
        ];
    }

    public const TURNOS = ['MAÑANA' => 'Mañana', 'TARDE' => 'Tarde', 'CUALQUIERA' => 'Cualquiera'];

    public const PRIORIDADES = ['BAJA' => 'Baja', 'NORMAL' => 'Normal', 'ALTA' => 'Alta'];

    public const ESTADOS = [
        'ESPERANDO' => 'Esperando',
        'CONTACTADO' => 'Contactado',
        'AGENDADO' => 'Agendado',
        'CANCELADO' => 'Cancelado',
    ];

    public const COLORES_ESTADO = [
        'ESPERANDO' => 'warning',
        'CONTACTADO' => 'azure',
        'AGENDADO' => 'success',
        'CANCELADO' => 'secondary',
    ];

    public const COLORES_PRIORIDAD = ['BAJA' => 'secondary', 'NORMAL' => 'azure', 'ALTA' => 'danger'];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class, 'especialidad_id');
    }

    public function tratamiento(): BelongsTo
    {
        return $this->belongsTo(Tratamiento::class, 'tratamiento_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function scopeAbiertas($query)
    {
        return $query->whereIn('estado', ['ESPERANDO', 'CONTACTADO']);
    }

    public function getEstadoLegibleAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function getColorEstadoAttribute(): string
    {
        return self::COLORES_ESTADO[$this->estado] ?? 'secondary';
    }

    public function getColorPrioridadAttribute(): string
    {
        return self::COLORES_PRIORIDAD[$this->prioridad] ?? 'secondary';
    }
}
