<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToClinica;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaboratorioDental extends Model
{
    use Auditable, BelongsToClinica, HasFactory, SoftDeletes;

    protected $table = 'laboratorios_dentales';

    protected $fillable = [
        'clinica_id',
        'nombre',
        'contacto',
        'telefono',
        'email',
        'direccion',
        'especialidades',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(OrdenLaboratorio::class, 'laboratorio_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
