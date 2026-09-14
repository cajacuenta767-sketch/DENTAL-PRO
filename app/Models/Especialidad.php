<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Especialidad extends Model
{
    use Auditable, HasFactory;

    protected $table = 'especialidades';

    protected $fillable = ['nombre', 'descripcion', 'color', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function doctores(): HasMany
    {
        return $this->hasMany(Doctor::class, 'especialidad_id');
    }

    public function tratamientos(): HasMany
    {
        return $this->hasMany(Tratamiento::class, 'especialidad_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
}
