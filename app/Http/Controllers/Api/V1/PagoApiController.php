<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PagoResource;
use App\Models\Pago;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Recibos de caja de solo lectura, con sus líneas. */
class PagoApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(self::forzarJson(...))];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->autorizar($request, 'pagos.ver');

        $request->validate([
            'paciente_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'estado' => ['nullable', 'in:'.implode(',', Pago::ESTADOS)],
        ]);

        $pagos = Pago::query()
            ->with(['paciente', 'doctor', 'detalles.tratamiento'])
            ->when($request->filled('paciente_id'), fn ($q) => $q->where('paciente_id', $request->paciente_id))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_pago', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_pago', '<=', $request->hasta))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->orderByDesc('fecha_pago')->orderByDesc('id')
            ->paginate($this->porPagina($request))
            ->withQueryString();

        return PagoResource::collection($pagos);
    }

    public function show(Request $request, Pago $pago): PagoResource
    {
        $this->autorizar($request, 'pagos.ver');

        return new PagoResource($pago->load(['paciente', 'doctor', 'detalles.tratamiento']));
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
