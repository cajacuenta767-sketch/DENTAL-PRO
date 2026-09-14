<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\Especialidad;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\Tratamiento;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $hoy = Cita::delDia();

        return view('admin.home', [
            'kpis' => [
                'citasHoy' => (clone $hoy)->count(),
                'citasHoyPendientes' => (clone $hoy)->where('estado', 'PENDIENTE')->count(),
                'citasHoyConfirmadas' => (clone $hoy)->where('estado', 'CONFIRMADA')->count(),
                'citasHoyAtendidas' => (clone $hoy)->where('estado', 'COMPLETADA')->count(),
                'pacientes' => Paciente::count(),
                'pacientesNuevos' => Paciente::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
                'ingresosMes' => (float) Pago::vigentes()
                    ->whereBetween('fecha_pago', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('monto_pagado'),
                'recibosMes' => Pago::vigentes()
                    ->whereBetween('fecha_pago', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'doctores' => Doctor::count(),
                'especialidades' => Especialidad::count(),
                'tratamientos' => Tratamiento::count(),
                'tratamientosActivos' => Tratamiento::activos()->count(),
                'horarios' => \App\Models\Horario::count(),
                'horariosActivos' => \App\Models\Horario::activos()->count(),
                'usuarios' => Usuario::count(),
                'roles' => Role::count(),
                'permisos' => Permission::count(),
            ],
            'rendimiento' => $this->rendimientoSemestral(),
            'estadoGlobal' => $this->estadoGlobalCitas(),
            'ingresosPorMetodo' => Pago::vigentes()
                ->selectRaw('metodo_pago, SUM(monto_pagado) AS total')
                ->groupBy('metodo_pago')
                ->orderByDesc('total')
                ->get(),
            'topTratamientos' => Cita::query()
                ->selectRaw('tratamiento_id, COUNT(*) AS total')
                ->with('tratamiento.especialidad')
                ->groupBy('tratamiento_id')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
            'proximasCitas' => Cita::with(['paciente', 'doctor', 'tratamiento'])
                ->whereDate('fecha', '>=', now()->toDateString())
                ->vigentes()
                ->orderBy('fecha')->orderBy('hora')
                ->limit(8)
                ->get(),
            'ultimosPagos' => Pago::with(['paciente', 'doctor'])
                ->vigentes()
                ->orderByDesc('fecha_pago')
                ->limit(5)
                ->get(),
        ]);
    }

    /** Citas agendadas y completadas de los últimos seis meses. */
    private function rendimientoSemestral(): array
    {
        $desde = now()->subMonths(5)->startOfMonth();

        $filas = Cita::query()
            ->where('fecha', '>=', $desde->toDateString())
            ->selectRaw("TO_CHAR(fecha, 'YYYY-MM') AS periodo, COUNT(*) AS agendadas, COUNT(*) FILTER (WHERE estado = 'COMPLETADA') AS completadas")
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get()
            ->keyBy('periodo');

        $etiquetas = [];
        $agendadas = [];
        $completadas = [];

        for ($i = 0; $i < 6; $i++) {
            $mes = $desde->copy()->addMonths($i);
            $clave = $mes->format('Y-m');

            $etiquetas[] = ucfirst($mes->translatedFormat('M Y'));
            $agendadas[] = (int) ($filas[$clave]->agendadas ?? 0);
            $completadas[] = (int) ($filas[$clave]->completadas ?? 0);
        }

        return compact('etiquetas', 'agendadas', 'completadas');
    }

    /** Reparto de citas por estado para la dona del panel. */
    private function estadoGlobalCitas(): array
    {
        $conteos = Cita::selectRaw('estado, COUNT(*) AS total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $etiquetas = [];
        $series = [];
        $colores = [];

        $paleta = [
            'COMPLETADA' => '#2fb344',
            'CONFIRMADA' => '#4299e1',
            'PENDIENTE' => '#f76707',
            'EN_CURSO' => '#f59f00',
            'CANCELADA' => '#d63939',
        ];

        foreach (Cita::ESTADOS as $estado) {
            $etiquetas[] = ucfirst(mb_strtolower(str_replace('_', ' ', $estado)));
            $series[] = (int) ($conteos[$estado] ?? 0);
            $colores[] = $paleta[$estado] ?? '#adb5bd';
        }

        return [
            'etiquetas' => $etiquetas,
            'series' => $series,
            'colores' => $colores,
            'total' => array_sum($series),
            'detalle' => $conteos,
        ];
    }
}
