<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Sucursal;
use App\Models\Usuario;
use App\Support\SucursalActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sedes de la clínica. Además del CRUD, atiende el selector del navbar con
 * el que un usuario no atado a una sede elige con cuál trabaja.
 */
class SucursalController extends Controller
{
    public function index(Request $request): View
    {
        $sucursales = Sucursal::query()
            ->withCount(['usuarios', 'horarios', 'citas', 'pagos'])
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%'.$request->buscar.'%';
                $q->where(fn ($s) => $s->where('nombre', 'ilike', $t)->orWhere('codigo', 'ilike', $t));
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->estado === 'activa'))
            ->orderByDesc('principal')
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('admin.sucursales.index', [
            'sucursales' => $sucursales,
            'totales' => [
                'sedes' => Sucursal::count(),
                'activas' => Sucursal::activas()->count(),
                'usuariosAtados' => Usuario::whereNotNull('sucursal_id')->count(),
                'citasSinSede' => Cita::whereNull('sucursal_id')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.sucursales.form', [
            'sucursal' => new Sucursal([
                'activo' => true,
                'principal' => ! Sucursal::activas()->where('principal', true)->exists(),
                'color' => '#0d9488',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $sucursal = DB::transaction(function () use ($datos) {
            $sucursal = Sucursal::create($datos);
            $this->asegurarPrincipal($sucursal);

            return $sucursal;
        });

        return redirect()->route('admin.sucursales.index')
            ->with('exito', "La sede {$sucursal->nombre} fue registrada.");
    }

    public function edit(Sucursal $sucursal): View
    {
        return view('admin.sucursales.form', compact('sucursal'));
    }

    public function update(Request $request, Sucursal $sucursal): RedirectResponse
    {
        $datos = $this->validar($request, $sucursal->id);

        DB::transaction(function () use ($sucursal, $datos) {
            $sucursal->update($datos);
            $this->asegurarPrincipal($sucursal);
        });

        // Si la sede que tenía elegida el usuario dejó de estar activa, vuelve a "todas".
        if (! $sucursal->activo && SucursalActiva::id() === $sucursal->id && SucursalActiva::puedeCambiar()) {
            SucursalActiva::cambiar(null);
        }

        return redirect()->route('admin.sucursales.index')
            ->with('exito', "La sede {$sucursal->nombre} fue actualizada.");
    }

    public function destroy(Sucursal $sucursal): RedirectResponse
    {
        if ($sucursal->activo && Sucursal::activas()->count() <= 1) {
            return back()->with('error', 'No puedes eliminar la única sede activa de la clínica.');
        }

        if ($sucursal->citas()->exists() || $sucursal->pagos()->exists()) {
            return back()->with('error', 'Esta sede tiene citas o cobros registrados. Desactívala en lugar de eliminarla.');
        }

        $nombre = $sucursal->nombre;

        DB::transaction(function () use ($sucursal) {
            // Lo que estaba ligado a la sede pasa a ser común a todas.
            foreach (['usuarios', 'horarios', 'lista_espera', 'insumos'] as $tabla) {
                DB::table($tabla)->where('sucursal_id', $sucursal->id)->update(['sucursal_id' => null]);
            }

            $eraPrincipal = $sucursal->principal;
            $sucursal->delete();

            if ($eraPrincipal && ($siguiente = Sucursal::activas()->orderBy('id')->first())) {
                $siguiente->update(['principal' => true]);
            }
        });

        if (session(SucursalActiva::CLAVE_SESION) == $sucursal->id) {
            SucursalActiva::cambiar(null);
        }

        return redirect()->route('admin.sucursales.index')
            ->with('exito', "La sede {$nombre} fue eliminada.");
    }

    /** Selector del navbar: fija (o limpia) la sede con la que trabaja el usuario. */
    public function cambiar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'sucursal_id' => ['nullable', Rule::exists('sucursales', 'id')->where('activo', true)->whereNull('deleted_at')],
        ], [], ['sucursal_id' => 'sede']);

        if (! SucursalActiva::puedeCambiar()) {
            return back()->with('error', 'Tu cuenta está ligada a una sede y no puede cambiarla.');
        }

        $id = filled($datos['sucursal_id'] ?? null) ? (int) $datos['sucursal_id'] : null;
        SucursalActiva::cambiar($id);

        $nombre = $id ? Sucursal::find($id)?->nombre : null;

        return back()->with('exito', $nombre ? "Ahora trabajas en la sede {$nombre}." : 'Ahora ves todas las sedes.');
    }

    private function validar(Request $request, ?int $ignorar = null): array
    {
        $request->merge(['codigo' => mb_strtoupper(trim((string) $request->codigo))]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'codigo' => [
                'required', 'string', 'max:10', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('sucursales', 'codigo')->ignore($ignorar)->whereNull('deleted_at'),
            ],
            'direccion' => ['nullable', 'string', 'max:500'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'principal' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
        ], [
            'codigo.regex' => 'El código solo admite letras mayúsculas, números, guion y guion bajo.',
            'color.regex' => 'El color debe ser un valor hexadecimal como #0d9488.',
        ], [
            'codigo' => 'código',
            'direccion' => 'dirección',
            'telefono' => 'teléfono',
            'email' => 'correo',
        ]);

        $datos['principal'] = $request->boolean('principal');
        $datos['activo'] = $request->boolean('activo');
        $datos['color'] = strtolower($datos['color']);

        if ($datos['principal'] && ! $datos['activo']) {
            throw ValidationException::withMessages([
                'principal' => 'La sede principal debe estar activa.',
            ]);
        }

        return $datos;
    }

    /**
     * Solo una sede es la principal: marcar una desmarca las demás, y si la
     * clínica se quedó sin principal activa se elige la primera disponible.
     */
    private function asegurarPrincipal(Sucursal $sucursal): void
    {
        if ($sucursal->principal) {
            Sucursal::where('id', '!=', $sucursal->id)->where('principal', true)->update(['principal' => false]);

            return;
        }

        if (! Sucursal::activas()->where('principal', true)->exists()) {
            Sucursal::activas()->orderBy('id')->first()?->update(['principal' => true]);
        }
    }
}
