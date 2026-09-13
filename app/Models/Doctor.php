<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    use HasFactory;

    protected $table = 'doctores';

    protected $fillable = [
        'usuario_id', 'especialidad_id', 'nombres', 'apellidos',
        'tipo_documento', 'numero_documento', 'fecha_nacimiento', 'genero',
        'telefono', 'email', 'direccion', 'colegiatura',
        'descripcion', 'observaciones', 'fotografia', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'activo' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class, 'especialidad_id');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'doctor_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'doctor_id');
    }

    public function historiales(): HasMany
    {
        return $this->hasMany(HistorialClinico::class, 'doctor_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'doctor_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->apellidos}");
    }

    public function getNombreProfesionalAttribute(): string
    {
        $trato = $this->genero === 'F' ? 'Dra.' : 'Dr.';

        return "{$trato} {$this->nombre_completo}";
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
