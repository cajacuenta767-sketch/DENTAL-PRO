<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinica extends Model
{
    protected $fillable = ['nombre', 'slug', 'estado', 'plan', 'vence_en'];

    protected function casts(): array
    {
        return ['vence_en' => 'datetime'];
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }

    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class);
    }

    public function sucursales(): HasMany
    {
        return $this->hasMany(Sucursal::class);
    }

    public function invitaciones(): HasMany
    {
        return $this->hasMany(Invitacion::class);
    }
}
