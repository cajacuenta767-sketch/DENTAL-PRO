<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paciente extends Model
{
    use HasFactory;

    protected $table = 'pacientes';

    protected $fillable = [
        'usuario_id', 'nombres', 'apellidos', 'tipo_documento', 'numero_documento',
        'fecha_nacimiento', 'genero', 'direccion', 'telefono', 'email',
        'grupo_sanguineo', 'alergias', 'enfermedades', 'medicamentos', 'habitos',
        'antecedentes', 'contacto_emergencia', 'telefono_emergencia',
        'observaciones', 'fotografia', 'activo',
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

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'paciente_id');
    }

    public function historiales(): HasMany
    {
        return $this->hasMany(HistorialClinico::class, 'paciente_id');
    }

    public function odontogramas(): HasMany
    {
        return $this->hasMany(Odontograma::class, 'paciente_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'paciente_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->apellidos}");
    }

    public function getEdadAttribute(): ?int
    {
        return $this->fecha_nacimiento?->age;
    }

    /** Saldo pendiente sumando todos los recibos no anulados. */
    public function getSaldoPendienteAttribute(): float
    {
        return (float) $this->pagos()->where('estado', '!=', 'ANULADO')->sum('monto_saldo');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeBuscar($query, ?string $termino)
    {
        if (blank($termino)) {
            return $query;
        }

        $t = '%'.mb_strtolower($termino).'%';

        return $query->where(function ($q) use ($t) {
            $q->whereRaw('LOWER(nombres) LIKE ?', [$t])
                ->orWhereRaw('LOWER(apellidos) LIKE ?', [$t])
                ->orWhereRaw('LOWER(numero_documento) LIKE ?', [$t])
                ->orWhereRaw('LOWER(COALESCE(telefono, \'\')) LIKE ?', [$t])
                ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$t]);
        });
    }
}
