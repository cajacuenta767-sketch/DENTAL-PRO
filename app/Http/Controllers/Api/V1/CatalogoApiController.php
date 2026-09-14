<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DoctorResource;
use App\Http\Resources\EspecialidadResource;
use App\Http\Resources\TratamientoResource;
use App\Models\Doctor;
use App\Models\Especialidad;
use App\Models\Tratamiento;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Catálogos de solo lectura: doctores, especialidades y tratamientos. */
class CatalogoApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(self::forzarJson(...))];
    }

    public function doctores(Request $request): AnonymousResourceCollection
    {
        $this->autorizar($request, 'doctores.ver');

        $doctores = Doctor::query()
            ->with('especialidad')
            ->when($request->filled('especialidad_id'), fn ($q) => $q->where('especialidad_id', $request->especialidad_id))
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.mb_strtolower($request->q).'%';
                $q->where(fn ($s) => $s->whereRaw('LOWER(nombres) LIKE ?', [$t])->orWhereRaw('LOWER(apellidos) LIKE ?', [$t]));
            })
            ->where('activo', $this->soloActivos($request))
            ->orderBy('apellidos')->orderBy('nombres')
            ->paginate($this->porPagina($request))
            ->withQueryString();

        return DoctorResource::collection($doctores);
    }

    public function especialidades(Request $request): AnonymousResourceCollection
    {
        $this->autorizar($request, 'especialidades.ver');

        $especialidades = Especialidad::query()
            ->where('activo', $this->soloActivos($request))
            ->orderBy('nombre')
            ->paginate($this->porPagina($request))
            ->withQueryString();

        return EspecialidadResource::collection($especialidades);
    }

    public function tratamientos(Request $request): AnonymousResourceCollection
    {
        $this->autorizar($request, 'tratamientos.ver');

        $tratamientos = Tratamiento::query()
            ->with('especialidad')
            ->when($request->filled('especialidad_id'), fn ($q) => $q->where('especialidad_id', $request->especialidad_id))
            ->when($request->filled('q'), fn ($q) => $q->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($request->q).'%']))
            ->where('activo', $this->soloActivos($request))
            ->orderBy('nombre')
            ->paginate($this->porPagina($request))
            ->withQueryString();

        return TratamientoResource::collection($tratamientos);
    }

    /** Por defecto solo los activos; ?activo=0 devuelve los desactivados. */
    private function soloActivos(Request $request): bool
    {
        return $request->filled('activo') ? $request->boolean('activo') : true;
    }

    /** Toda respuesta de la API es JSON aunque el cliente no mande Accept. */
    private static function forzarJson(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }

    /** Exige el permiso del usuario y, si entró con token, que el token lo incluya. */
    private function autorizar(Request $request, string $permiso): void
    {
        $usuario = $request->user();

        abort_unless($usuario->estaActivo(), 403, 'Tu cuenta está inactiva.');
        abort_unless($usuario->can($permiso), 403, "No tienes el permiso {$permiso}.");
        abort_unless(! $usuario->currentAccessToken() || $usuario->tokenCan($permiso), 403, "El token no tiene la capacidad {$permiso}.");
    }

    private function porPagina(Request $request): int
    {
        return min(100, max(1, $request->integer('per_page', 15)));
    }
}
