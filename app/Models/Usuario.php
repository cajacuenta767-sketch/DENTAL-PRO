<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Usuario extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasRoles, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre',
        'email',
        'password',
        'avatar',
        'telefono',
        'estado',
        'proveedor',
        'proveedor_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class, 'usuario_id');
    }

    public function paciente(): HasOne
    {
        return $this->hasOne(Paciente::class, 'usuario_id');
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'activo';
    }

    /** Iniciales para el avatar de texto del navbar. */
    public function getInicialesAttribute(): string
    {
        $partes = preg_split('/\s+/', trim((string) $this->nombre)) ?: [];
        $iniciales = collect($partes)->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('');

        return $iniciales !== '' ? $iniciales : mb_strtoupper(mb_substr((string) $this->email, 0, 2));
    }

    public function getRolPrincipalAttribute(): string
    {
        return (string) ($this->getRoleNames()->first() ?? 'SIN ROL');
    }
}
