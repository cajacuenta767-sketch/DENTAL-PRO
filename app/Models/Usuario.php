<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Usuario extends Authenticatable implements MustVerifyEmail
{
    use Auditable, HasApiTokens, HasFactory, HasRoles, Notifiable;

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
        'debe_cambiar_password',
        'dos_factores',
        'sucursal_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'codigo_2fa',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'debe_cambiar_password' => 'boolean',
            'dos_factores' => 'boolean',
            'codigo_2fa_expira_en' => 'datetime',
            'ultimo_acceso_en' => 'datetime',
        ];
    }

    public function sucursal(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
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

    /** Solo tiene el rol de paciente: su casa es el portal, no el panel. */
    public function esPaciente(): bool
    {
        return $this->hasRole('PACIENTE') && ! $this->accedeAlPanel();
    }

    /** Alguien entra al panel cuando posee al menos un permiso de módulo. */
    public function accedeAlPanel(): bool
    {
        return $this->getAllPermissions()->isNotEmpty();
    }

    /** Ruta a la que se envía al usuario tras autenticarse. */
    public function destinoInicial(): string
    {
        return $this->accedeAlPanel() ? route('admin.home') : route('portal.inicio');
    }

    /** Genera y guarda (hasheado) un código de un solo uso para el doble factor. */
    public function generarCodigo2fa(int $minutos = 10): string
    {
        $codigo = (string) random_int(100000, 999999);

        $this->forceFill([
            'codigo_2fa' => Hash::make($codigo),
            'codigo_2fa_expira_en' => now()->addMinutes($minutos),
        ])->save();

        return $codigo;
    }

    /** Comprueba el código y lo consume para que no pueda reutilizarse. */
    public function verificarCodigo2fa(string $codigo): bool
    {
        $valido = filled($this->codigo_2fa)
            && $this->codigo_2fa_expira_en?->isFuture()
            && Hash::check(trim($codigo), $this->codigo_2fa);

        if ($valido) {
            $this->forceFill(['codigo_2fa' => null, 'codigo_2fa_expira_en' => null])->save();
        }

        return $valido;
    }

    /** Contraseña temporal para cuentas creadas por un administrador. */
    public static function passwordTemporal(): string
    {
        return Str::password(12, symbols: false);
    }
}
