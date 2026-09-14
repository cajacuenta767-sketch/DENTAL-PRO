<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventarioController extends Controller
{
    public function __construct(private readonly InventarioService $inventario) {}

    public function index(Request $request): View
    {
        $insumos = Insumo::query()
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%'.$request->buscar.'%';
                $q->where(fn ($s) => $s->where('nombre', 'ilike', $t)
                    ->orWhere('codigo', 'ilike', $t)
                    ->orWhere('proveedor', 'ilike', $t));
            })
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria', $request->categoria))
            ->when($request->alerta === 'minimo', fn ($q) => $q->bajoMinimo())
            ->when($request->alerta === 'vencer', fn ($q) => $q->porVencer(90))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('admin.inventario.index', [
            'insumos' => $insumos,
            'resumen' => $this->inventario->resumen(),
            'alertas' => Insumo::activos()->bajoMinimo()->orderBy('stock_actual')->limit(8)->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.inventario.form', [
            'insumo' => new Insumo([
                'activo' => true,
                'categoria' => 'OTRO',
                'unidad_medida' => 'UNIDAD',
                'stock_actual' => 0,
                'stock_minimo' => 0,
                'costo_unitario' => 0,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $inicial = (float) ($datos['stock_actual'] ?? 0);

        // El stock inicial entra como el primer movimiento del kardex; ficha y
        // movimiento se crean juntos o no se crea nada.
        $datos['stock_actual'] = 0;

        $insumo = DB::transaction(function () use ($datos, $inicial, $request) {
            $insumo = Insumo::create($datos);

            if ($inicial > 0) {
                $this->inventario->registrar($insumo, [
                    'tipo' => 'ENTRADA',
                    'cantidad' => $inicial,
                    'costo_unitario' => $insumo->costo_unitario,
                    'motivo' => 'Carga inicial de existencias',
                    'usuario_id' => $request->user()->id,
                ]);
            }

            return $insumo;
        });

        return redirect()->route('admin.inventario.show', $insumo)
            ->with('exito', 'El insumo fue registrado.');
    }

    public function show(Insumo $insumo): View
    {
        return view('admin.inventario.show', [
            'insumo' => $insumo,
            'movimientos' => $insumo->movimientos()
                ->with(['usuario', 'cita.paciente'])
                ->orderByDesc('fecha')->orderByDesc('id')
                ->paginate(20),
            'consumoMes' => (float) $insumo->movimientos()
                ->whereIn('tipo', ['SALIDA', 'MERMA'])
                ->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('cantidad'),
        ]);
    }

    public function edit(Insumo $insumo): View
    {
        return view('admin.inventario.form', compact('insumo'));
    }

    public function update(Request $request, Insumo $insumo): RedirectResponse
    {
        $datos = $this->validar($request, $insumo->id);

        // El stock solo cambia por movimientos, nunca editando la ficha.
        unset($datos['stock_actual']);
        $insumo->update($datos);

        return redirect()->route('admin.inventario.show', $insumo)
            ->with('exito', 'El insumo fue actualizado. Las existencias se ajustan desde los movimientos.');
    }

    public function destroy(Insumo $insumo): RedirectResponse
    {
        if ($insumo->movimientos()->exists()) {
            return back()->with('error', 'No puedes eliminar un insumo con movimientos registrados. Desactívalo en su lugar.');
        }

        $insumo->delete();

        return redirect()->route('admin.inventario.index')
            ->with('exito', 'El insumo fue eliminado.');
    }

    /** Registra entrada, salida, ajuste o merma sobre un insumo. */
    public function movimiento(Request $request, Insumo $insumo): RedirectResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', 'in:'.implode(',', MovimientoInventario::TIPOS)],
            // Un ajuste puede fijar el saldo en cero; los demás necesitan cantidad.
            'cantidad' => ['required', 'numeric', 'min:0', $request->tipo === 'AJUSTE' ? 'max:9999999' : 'gt:0'],
            'costo_unitario' => ['nullable', 'numeric', 'min:0'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'cita_id' => ['nullable', 'exists:citas,id'],
        ], [], ['costo_unitario' => 'costo unitario']);

        $this->inventario->registrar($insumo, $datos + ['usuario_id' => $request->user()->id]);

        return back()->with('exito', "Movimiento registrado. Existencias actuales: {$insumo->fresh()->stock_actual} {$insumo->unidad_medida}.");
    }

    /** Kardex general de todos los insumos. */
    public function kardex(Request $request): View
    {
        $movimientos = MovimientoInventario::query()
            ->with(['insumo', 'usuario'])
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('insumo_id'), fn ($q) => $q->where('insumo_id', $request->insumo_id))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->hasta))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.inventario.kardex', [
            'movimientos' => $movimientos,
            'insumos' => Insumo::orderBy('nombre')->get(),
        ]);
    }

    private function validar(Request $request, ?int $ignorar = null): array
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:40', 'unique:insumos,codigo'.($ignorar ? ",{$ignorar}" : '')],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'categoria' => ['required', 'in:'.implode(',', Insumo::CATEGORIAS)],
            'unidad_medida' => ['required', 'string', 'max:20'],
            'stock_actual' => ['nullable', 'numeric', 'min:0'],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
            'costo_unitario' => ['required', 'numeric', 'min:0'],
            'proveedor' => ['nullable', 'string', 'max:150'],
            'ubicacion' => ['nullable', 'string', 'max:100'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'activo' => ['nullable', 'boolean'],
        ], [], [
            'unidad_medida' => 'unidad de medida',
            'stock_actual' => 'existencias iniciales',
            'stock_minimo' => 'existencias mínimas',
            'costo_unitario' => 'costo unitario',
            'fecha_vencimiento' => 'fecha de vencimiento',
        ]);

        $datos['nombre'] = mb_strtoupper($datos['nombre']);
        $datos['codigo'] = mb_strtoupper($datos['codigo']);
        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }
}
