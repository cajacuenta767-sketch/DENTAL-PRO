<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\Cita;
use App\Support\SucursalActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmPostOpController extends Controller
{
    public function index(Request $request): View
    {
        $sucursalActiva = SucursalActiva::id();
        $desde = now()->subDays(3)->toDateString();
        $hasta = now()->toDateString();

        // Citas de los últimos 3 días que fueron completadas o en curso
        $citas = Cita::query()
            ->with(['paciente', 'doctor', 'tratamiento'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereIn('estado', ['COMPLETADA', 'EN_CURSO'])
            ->when($sucursalActiva, fn ($q) => $q->where('sucursal_id', $sucursalActiva))
            ->orderByDesc('fecha')->orderByDesc('hora')
            ->paginate(20);

        $clinica = Ajuste::actual();

        return view('admin.crm_postop.index', [
            'citas' => $citas,
            'clinica' => $clinica,
        ]);
    }

    public function marcarContactado(Request $request, Cita $cita): RedirectResponse
    {
        $request->validate([
            'postop_estado' => ['required', 'string', 'in:CONTACTADO,REQUIERE_REVISION,SIN_RESPUESTA'],
        ]);

        $cita->update([
            'postop_contactado_en' => now(),
            'postop_estado' => $request->postop_estado,
        ]);

        Auditoria::registrar('EDITAR', $cita, "Marcó seguimiento post-op como {$request->postop_estado} para {$cita->paciente->nombre_completo}");

        return back()->with('exito', 'Seguimiento post-operatorio registrado correctamente.');
    }
}
