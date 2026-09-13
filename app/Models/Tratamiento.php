<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tratamiento extends Model
{
    use HasFactory;

    protected $table = 'tratamientos';

    protected $fillable = [
        'especialidad_id', 'nombre', 'descripcion', 'precio', 'duracion', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'duracion' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class, 'especialidad_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'tratamiento_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
