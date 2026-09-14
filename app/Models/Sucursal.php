<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sede de la clínica. Horarios, citas, caja e inventario se etiquetan con
 * la sucursal; los usuarios pueden estar ligados a una o ver todas.
 */
class Sucursal extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'sucursales';

    protected $fillable = ['nombre', 'codigo', 'direccion', 'telefono', 'email', 'color', 'principal', 'activo'];

    protected function casts(): array
    {
        return ['principal' => 'boolean', 'activo' => 'boolean'];
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'sucursal_id');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'sucursal_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'sucursal_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'sucursal_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /** La sede marcada como principal, o la primera activa. */
    public static function principal(): ?self
    {
        return static::activas()->orderByDesc('principal')->orderBy('id')->first();
    }
}
