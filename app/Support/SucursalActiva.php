<?php

namespace App\Support;

use App\Models\Sucursal;
use Illuminate\Support\Facades\Auth;

/**
 * Sucursal con la que trabaja el usuario en esta sesión. Un usuario ligado
 * a una sede solo ve esa; el resto elige desde el selector del navbar y,
 * si no elige, ve todas (null).
 */
class SucursalActiva
{
    public const CLAVE_SESION = 'sucursal_activa_id';

    public static function id(): ?int
    {
        $usuario = Auth::user();

        if ($usuario?->sucursal_id) {
            return (int) $usuario->sucursal_id;
        }

        $id = session(self::CLAVE_SESION);

        return $id ? (int) $id : null;
    }

    public static function modelo(): ?Sucursal
    {
        $id = self::id();

        return $id ? Sucursal::find($id) : null;
    }

    /** El usuario puede cambiar de sede si no está atado a una. */
    public static function puedeCambiar(): bool
    {
        return Auth::check() && ! Auth::user()->sucursal_id && Sucursal::activas()->count() > 1;
    }

    public static function cambiar(?int $id): void
    {
        if ($id === null) {
            session()->forget(self::CLAVE_SESION);

            return;
        }

        session([self::CLAVE_SESION => $id]);
    }
}
