<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\PagoDetalle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReporteController extends Controller
{
    public function index(Request $request): View
    {
        [$desde, $hasta] = $this->rango($request);

        return view('admin.reportes.index', array_merge(
            ['desde' => $desde, 'hasta' => $hasta, 'filtros' => $request->only(['doctor_id', 'metodo', 'estado'])],
            $this->financiero($request, $desde, $hasta),
            $this->productividad($desde, $hasta),
            $this->padron($desde, $hasta),
            $this->rentabilidad($desde, $hasta),
            ['doctores' => \App\Models\Doctor::orderBy('apellidos')->get()],
        ));
    }

    public function exportar(Request $request, string $seccion): Response
    {
        [$desde, $hasta] = $this->rango($request);

        $datos = match ($seccion) {
            'financiero' => $this->financiero($request, $desde, $hasta),
            'citas' => $this->productividad($desde, $hasta),
            'padron' => $this->padron($desde, $hasta),
            'tratamientos' => $this->rentabilidad($desde, $hasta),
        };

        $titulos = [
            'financiero' => 'Reporte Financiero y de Caja',
            'citas' => 'Reporte de Citas y Productividad',
            'padron' => 'Padrón de Pacientes',
            'tratamientos' => 'Rentabilidad por Tratamiento',
        ];

        return Pdf::loadView("pdf.reportes.{$seccion}", array_merge($datos, [
            'clinica' => Ajuste::actual(),
            'desde' => $desde,
            'hasta' => $hasta,
            'titulo' => $titulos[$seccion],
        ]))->setPaper('letter', 'landscape')
            ->download("reporte-{$seccion}-".$desde->format('Ymd').'-'.$hasta->format('Ymd').'.pdf');
    }

    /** Rango de fechas del filtro; por defecto el mes en curso. */
    private function rango(Request $request): array
    {
        $desde = $request->filled('desde')
            ? Carbon::parse($request->desde)->startOfDay()
            : now()->startOfMonth();

        $hasta = $request->filled('hasta')
            ? Carbon::parse($request->hasta)->endOfDay()
            : now()->endOfMonth();

        return $desde->lessThanOrEqualTo($hasta) ? [$desde, $hasta] : [$hasta, $desde];
    }

    /** 1. Financiero y caja. */
    private function financiero(Request $request, Carbon $desde, Carbon $hasta): array
    {
        $base = fn (): Builder => Pago::query()
            ->vigentes()
            ->whereBetween('fecha_pago', [$desde, $hasta])
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('doctor_id', $request->doctor_id))
            ->when($request->filled('metodo'), fn ($q) => $q->where('metodo_pago', $request->metodo))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado));

        return [
            'finTotal' => (float) $base()->sum('monto_pagado'),
            'finSaldos' => (float) $base()->sum('monto_saldo'),
            'finEfectivo' => (float) $base()->where('metodo_pago', 'EFECTIVO')->sum('monto_pagado'),
            'finDigital' => (float) $base()->whereIn('metodo_pago', ['TARJETA', 'QR', 'TRANSFERENCIA'])->sum('monto_pagado'),
            'finRecibos' => $base()->count(),
            'finPorMetodo' => $base()
                ->selectRaw('metodo_pago, COUNT(*) AS recibos, SUM(monto_pagado) AS total')
                ->groupBy('metodo_pago')
                ->orderByDesc('total')
                ->get(),
            'finListado' => $base()
                ->with(['paciente', 'doctor'])
                ->orderByDesc('fecha_pago')
                ->limit(200)
                ->get(),
        ];
    }

    /** 2. Citas y productividad. */
    private function productividad(Carbon $desde, Carbon $hasta): array
    {
        $base = fn (): Builder => Cita::query()->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()]);

        $total = $base()->count();
        $completadas = $base()->where('estado', 'COMPLETADA')->count();

        return [
            'citTotal' => $total,
            'citCompletadas' => $completadas,
            'citCanceladas' => $base()->where('estado', 'CANCELADA')->count(),
            'citTasaAsistencia' => $total > 0 ? round($completadas / $total * 100, 1) : 0.0,
            'citPorEstado' => $base()
                ->selectRaw('estado, COUNT(*) AS total')
                ->groupBy('estado')
                ->orderByDesc('total')
                ->get(),
            'citPorDoctor' => $base()
                ->selectRaw('doctor_id, COUNT(*) AS total, COUNT(*) FILTER (WHERE estado = \'COMPLETADA\') AS completadas')
                ->groupBy('doctor_id')
                ->with('doctor.especialidad')
                ->orderByDesc('total')
                ->get(),
            'citPorMes' => $base()
                ->selectRaw("TO_CHAR(fecha, 'YYYY-MM') AS periodo, COUNT(*) AS agendadas, COUNT(*) FILTER (WHERE estado = 'COMPLETADA') AS completadas")
                ->groupBy('periodo')
                ->orderBy('periodo')
                ->get(),
        ];
    }

    /** 3. Padrón de pacientes. */
    private function padron(Carbon $desde, Carbon $hasta): array
    {
        return [
            'pacTotal' => Paciente::count(),
            'pacNuevos' => Paciente::whereBetween('created_at', [$desde, $hasta])->count(),
            'pacActivos' => Paciente::activos()->count(),
            'pacPorGenero' => Paciente::selectRaw('genero, COUNT(*) AS total')->groupBy('genero')->get(),
            'pacConSaldo' => Paciente::query()
                ->select('pacientes.*')
                ->selectSub($saldo = Pago::selectRaw('COALESCE(SUM(monto_saldo), 0)')
                    ->whereColumn('pagos.paciente_id', 'pacientes.id')
                    ->where('estado', '!=', 'ANULADO'), 'saldo_total')
                ->whereHas('pagos', fn ($q) => $q->vigentes()->where('monto_saldo', '>', 0))
                ->orderByDesc($saldo->clone())
                ->limit(100)
                ->get(),
            'pacFrecuentes' => Paciente::withCount(['citas' => fn ($q) => $q->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])])
                ->orderByDesc('citas_count')
                ->limit(15)
                ->get(),
        ];
    }

    /** 4. Rentabilidad por tratamiento. */
    private function rentabilidad(Carbon $desde, Carbon $hasta): array
    {
        $lineas = PagoDetalle::query()
            ->join('pagos', 'pagos.id', '=', 'pago_detalles.pago_id')
            ->whereBetween('pagos.fecha_pago', [$desde, $hasta])
            ->where('pagos.estado', '!=', 'ANULADO')
            ->selectRaw('pago_detalles.descripcion, SUM(pago_detalles.cantidad) AS unidades, SUM(pago_detalles.subtotal) AS facturado')
            ->groupBy('pago_detalles.descripcion')
            ->orderByDesc('facturado')
            ->limit(50)
            ->get();

        return [
            'traLineas' => $lineas,
            'traFacturado' => (float) $lineas->sum('facturado'),
            'traUnidades' => (int) $lineas->sum('unidades'),
            'traPorEspecialidad' => PagoDetalle::query()
                ->join('pagos', 'pagos.id', '=', 'pago_detalles.pago_id')
                ->join('tratamientos', 'tratamientos.id', '=', 'pago_detalles.tratamiento_id')
                ->join('especialidades', 'especialidades.id', '=', 'tratamientos.especialidad_id')
                ->whereBetween('pagos.fecha_pago', [$desde, $hasta])
                ->where('pagos.estado', '!=', 'ANULADO')
                ->selectRaw('especialidades.nombre, especialidades.color, SUM(pago_detalles.subtotal) AS facturado')
                ->groupBy('especialidades.nombre', 'especialidades.color')
                ->orderByDesc('facturado')
                ->get(),
        ];
    }
}
