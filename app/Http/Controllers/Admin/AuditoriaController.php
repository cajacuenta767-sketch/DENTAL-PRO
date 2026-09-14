<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $eventos = Auditoria::query()
            ->with('usuario')
            ->when($request->filled('usuario_id'), fn ($q) => $q->where('usuario_id', $request->usuario_id))
            ->when($request->filled('accion'), fn ($q) => $q->where('accion', $request->accion))
            ->when($request->filled('modelo'), fn ($q) => $q->where('modelo', $request->modelo))
            ->when($request->filled('modelo_id'), fn ($q) => $q->where('modelo_id', $request->modelo_id))
            ->when($request->filled('buscar'), fn ($q) => $q->where('descripcion', 'ilike', '%'.$request->buscar.'%'))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('created_at', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('created_at', '<=', $request->hasta))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.auditoria.index', [
            'eventos' => $eventos,
            'usuarios' => Usuario::orderBy('nombre')->get(['id', 'nombre']),
            'acciones' => Auditoria::ACCIONES,
            'modelos' => Auditoria::MODELOS,
            'totales' => [
                'hoy' => Auditoria::whereDate('created_at', now()->toDateString())->count(),
                'ingresos' => Auditoria::where('accion', 'INGRESO')->whereDate('created_at', now()->toDateString())->count(),
                'fallidos' => Auditoria::where('accion', 'ACCESO_FALLIDO')->where('created_at', '>=', now()->subDay())->count(),
                'eliminaciones' => Auditoria::where('accion', 'ELIMINAR')->where('created_at', '>=', now()->subDays(7))->count(),
            ],
        ]);
    }
}
