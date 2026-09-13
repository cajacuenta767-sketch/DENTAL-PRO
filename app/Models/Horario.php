<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Horario extends Model
{
    use HasFactory;

    protected $table = 'horarios';

    protected $fillable = [
        'doctor_id', 'dia_semana', 'turno', 'hora_inicio', 'hora_fin', 'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public const DIAS = [
        'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO',
    ];

    public const TURNOS = ['MAÑANA', 'TARDE', 'NOCHE'];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function getRangoAttribute(): string
    {
        return substr((string) $this->hora_inicio, 0, 5).' - '.substr((string) $this->hora_fin, 0, 5);
    }
}
