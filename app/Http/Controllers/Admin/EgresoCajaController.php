<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\EgresoCaja;
use App\Models\Sucursal;
use App\Support\SucursalActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EgresoCajaController extends Controller
{
    public function index(Request $request): View
    {
        $sucursalActiva = SucursalActiva::id();
        $sucursalFiltro = $sucursalActiva ?: ($request->filled('sucursal_id') ? (int) $request->sucursal_id : null);

        $egresos = EgresoCaja::query()
            ->with(['sucursal', 'usuario'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->hasta))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria', $request->categoria))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($sucursalFiltro, fn ($q) => $q->where('sucursal_id', $sucursalFiltro))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $hoy = now()->toDateString();
        $egresosHoy = EgresoCaja::query()
            ->vigentes()
            ->whereDate('fecha', $hoy)
            ->when($sucursalFiltro, fn ($q) => $q->where('sucursal_id', $sucursalFiltro))
            ->sum('monto');

        $egresosMes = EgresoCaja::query()
            ->vigentes()
            ->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])
            ->when($sucursalFiltro, fn ($q) => $q->where('sucursal_id', $sucursalFiltro))
            ->sum('monto');

        return view('admin.caja.egresos.index', [
            'egresos' => $egresos,
            'categorias' => EgresoCaja::CATEGORIAS,
            'sucursales' => Sucursal::activas()->orderBy('nombre')->get(),
            'egresosHoy' => (float) $egresosHoy,
            'egresosMes' => (float) $egresosMes,
            'sucursalActiva' => $sucursalActiva,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01'],
            'concepto' => ['required', 'string', 'max:255'],
            'categoria' => ['required', 'string', 'in:' . implode(',', array_keys(EgresoCaja::CATEGORIAS))],
            'metodo_pago' => ['required', 'string', 'in:' . implode(',', EgresoCaja::METODOS)],
            'fecha' => ['required', 'date', 'before_or_equal:now'],
            'comprobante_tipo' => ['nullable', 'string', 'max:30'],
            'comprobante_numero' => ['nullable', 'string', 'max:100'],
            'comprobante_archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->hasFile('comprobante_archivo')) {
            $datos['comprobante_archivo'] = $request->file('comprobante_archivo')->store('egresos_comprobantes', 'public');
        }

        $datos['usuario_id'] = $request->user()->id;
        $datos['sucursal_id'] = SucursalActiva::id();
        $datos['estado'] = 'REGISTRADO';

        $egreso = EgresoCaja::create($datos);

        Auditoria::registrar('CREAR', $egreso, "Registró egreso de caja chica: {$egreso->concepto} por $" . number_format((float)$egreso->monto, 2));

        return redirect()->route('admin.egresos.index')->with('exito', "Egreso de $" . number_format((float)$egreso->monto, 2) . " registrado correctamente.");
    }

    public function update(Request $request, EgresoCaja $egreso): RedirectResponse
    {
        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01'],
            'concepto' => ['required', 'string', 'max:255'],
            'categoria' => ['required', 'string', 'in:' . implode(',', array_keys(EgresoCaja::CATEGORIAS))],
            'metodo_pago' => ['required', 'string', 'in:' . implode(',', EgresoCaja::METODOS)],
            'fecha' => ['required', 'date', 'before_or_equal:now'],
            'comprobante_tipo' => ['nullable', 'string', 'max:30'],
            'comprobante_numero' => ['nullable', 'string', 'max:100'],
            'comprobante_archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->hasFile('comprobante_archivo')) {
            if ($egreso->comprobante_archivo && Storage::disk('public')->exists($egreso->comprobante_archivo)) {
                Storage::disk('public')->delete($egreso->comprobante_archivo);
            }
            $datos['comprobante_archivo'] = $request->file('comprobante_archivo')->store('egresos_comprobantes', 'public');
        }

        $egreso->update($datos);

        Auditoria::registrar('EDITAR', $egreso, "Actualizó egreso de caja chica: {$egreso->concepto}");

        return redirect()->route('admin.egresos.index')->with('exito', "Egreso actualizado correctamente.");
    }

    public function anular(Request $request, EgresoCaja $egreso): RedirectResponse
    {
        $datos = $request->validate([
            'motivo_anulacion' => ['required', 'string', 'max:255'],
        ]);

        $egreso->update([
            'estado' => 'ANULADO',
            'observaciones' => ($egreso->observaciones ? $egreso->observaciones . "\n" : '') . "ANULADO: " . $datos['motivo_anulacion'],
        ]);

        Auditoria::registrar('ANULAR', $egreso, "Anuló egreso de caja chica: {$egreso->concepto}. Motivo: {$datos['motivo_anulacion']}");

        return redirect()->route('admin.egresos.index')->with('exito', "Egreso anulado.");
    }

    public function destroy(EgresoCaja $egreso): RedirectResponse
    {
        Auditoria::registrar('ELIMINAR', $egreso, "Eliminó egreso de caja chica {$egreso->concepto}");
        $egreso->delete();

        return redirect()->route('admin.egresos.index')->with('exito', "Egreso eliminado correctamente.");
    }
}
