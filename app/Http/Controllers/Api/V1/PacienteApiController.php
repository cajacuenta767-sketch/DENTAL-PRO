<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PacienteResource;
use App\Models\Paciente;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Pacientes vía API. Las reglas de validación son las mismas del panel
 * (Admin\PacienteController) sin la fotografía, que no viaja por JSON.
 */
class PacienteApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(self::forzarJson(...))];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->autorizar($request, 'pacientes.ver');

        $pacientes = Paciente::query()
            ->with('aseguradora')
            ->buscar($request->query('q'))
            ->when($request->filled('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->orderBy('apellidos')->orderBy('nombres')
            ->paginate($this->porPagina($request))
            ->withQueryString();

        return PacienteResource::collection($pacientes);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'pacientes.crear');

        $datos = $request->validate([
            'nombres' => ['required', 'string', 'max:150'],
            'apellidos' => ['required', 'string', 'max:150'],
            'aseguradora_id' => ['nullable', 'exists:aseguradoras,id'],
            'numero_afiliado' => ['nullable', 'string', 'max:60'],
            'tipo_documento' => ['required', 'in:CI,DNI,PASAPORTE,CE'],
            'numero_documento' => ['required', 'string', 'max:20', 'unique:pacientes,numero_documento'],
            'fecha_nacimiento' => ['nullable', 'date', 'before_or_equal:today'],
            'genero' => ['required', 'in:M,F,O'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'grupo_sanguineo' => ['nullable', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'alergias' => ['nullable', 'string', 'max:1000'],
            'enfermedades' => ['nullable', 'string', 'max:1000'],
            'medicamentos' => ['nullable', 'string', 'max:1000'],
            'habitos' => ['nullable', 'string', 'max:1000'],
            'antecedentes' => ['nullable', 'string', 'max:1000'],
            'contacto_emergencia' => ['nullable', 'string', 'max:150'],
            'telefono_emergencia' => ['nullable', 'string', 'max:50'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'activo' => ['nullable', 'boolean'],
        ], [], [
            'numero_documento' => 'número de documento',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'grupo_sanguineo' => 'grupo sanguíneo',
            'aseguradora_id' => 'obra social',
            'numero_afiliado' => 'número de afiliado',
        ]);

        // Por la API un paciente nuevo queda activo salvo que se indique lo contrario.
        $datos['activo'] = $request->has('activo') ? $request->boolean('activo') : true;

        $paciente = Paciente::create($datos)->load('aseguradora');

        return (new PacienteResource($paciente))->response()->setStatusCode(201);
    }

    public function show(Request $request, Paciente $paciente): PacienteResource
    {
        $this->autorizar($request, 'pacientes.ver');

        return new PacienteResource($paciente->load('aseguradora'));
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
