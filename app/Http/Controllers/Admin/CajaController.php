<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\CierreCaja;
use App\Models\EgresoCaja;
use App\Models\Sucursal;
use App\Support\SucursalActiva;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Arqueo y cierre diario de caja por sucursal y turnos con soporte para egresos. */
class CajaController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'sucursal_id' => ['nullable', 'integer'],
            'turno' => ['nullable', 'string'],
        ]);

        $sucursalActiva = SucursalActiva::id();
        $sucursalFiltro = $sucursalActiva ?: ($request->filled('sucursal_id') ? (int) $request->sucursal_id : null);

        $cierres = CierreCaja::query()
            ->with(['sucursal', 'usuario'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->hasta))
            ->when($request->filled('turno'), fn ($q) => $q->where('turno', $request->turno))
            ->when($sucursalFiltro, fn ($q) => $q->where('sucursal_id', $sucursalFiltro))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $hoy = now()->toDateString();
        $resumenHoy = CierreCaja::resumenDelDia($hoy, $sucursalActiva);
        $cierreHoy = $this->cierreDe($hoy, $sucursalActiva);
        $fondo = $this->fondoSugerido($hoy, $sucursalActiva);

        return view('admin.caja.index', [
            'cierres' => $cierres,
            'sucursales' => Sucursal::activas()->orderBy('nombre')->get(),
            'sucursalActiva' => $sucursalActiva,
            'hoy' => Carbon::parse($hoy),
            'resumenHoy' => $resumenHoy,
            'cierreHoy' => $cierreHoy,
            'turnos' => CierreCaja::TURNOS,
            'efectivoEsperadoHoy' => round($fondo + ($resumenHoy['efectivo_neto'] ?? $resumenHoy['efectivo']), 2),
        ]);
    }

    /** Resumen del día y formulario de cierre. */
    public function arqueo(Request $request): View
    {
        $request->validate([
            'fecha' => ['nullable', 'date'],
            'turno' => ['nullable', 'string', 'in:'.implode(',', array_keys(CierreCaja::TURNOS))],
        ]);

        $fecha = $request->filled('fecha') ? Carbon::parse($request->fecha)->startOfDay() : now()->startOfDay();
        $turno = $request->query('turno', 'COMPLETO');
        $sucursalId = SucursalActiva::id();

        return view('admin.caja.arqueo', [
            'fecha' => $fecha,
            'turno' => $turno,
            'turnos' => CierreCaja::TURNOS,
            'sucursal' => SucursalActiva::modelo(),
            'resumen' => CierreCaja::resumenDelDia($fecha->toDateString(), $sucursalId),
            'fondoSugerido' => $this->fondoSugerido($fecha->toDateString(), $sucursalId),
            'cierreExistente' => $this->cierreDe($fecha->toDateString(), $sucursalId, $turno),
        ]);
    }

    public function cerrar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'turno' => ['nullable', 'string', 'in:'.implode(',', array_keys(CierreCaja::TURNOS))],
            'fondo_inicial' => ['required', 'numeric', 'min:0'],
            'efectivo_contado' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'fondo_inicial' => 'fondo inicial',
            'efectivo_contado' => 'efectivo contado',
        ]);

        $fecha = Carbon::parse($datos['fecha'])->toDateString();
        $turno = $datos['turno'] ?? 'COMPLETO';
        $sucursalId = SucursalActiva::id();

        try {
            $cierre = DB::transaction(function () use ($datos, $fecha, $turno, $sucursalId, $request) {
                // Bloquea el cierre previo del día y turno para que dos cajeros no cierren a la vez.
                $existente = CierreCaja::query()
                    ->whereDate('fecha', $fecha)
                    ->where('turno', $turno)
                    ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId), fn ($q) => $q->whereNull('sucursal_id'))
                    ->lockForUpdate()
                    ->first();

                if ($existente) {
                    return null;
                }

                $resumen = CierreCaja::resumenDelDia($fecha, $sucursalId);
                $fondo = round((float) $datos['fondo_inicial'], 2);
                $esperado = round($fondo + ($resumen['efectivo_neto'] ?? $resumen['efectivo']), 2);
                $contado = round((float) $datos['efectivo_contado'], 2);

                $nuevoCierre = CierreCaja::create([
                    'sucursal_id' => $sucursalId,
                    'usuario_id' => $request->user()->id,
                    'fecha' => $fecha,
                    'turno' => $turno,
                    'fondo_inicial' => $fondo,
                    'efectivo_esperado' => $esperado,
                    'efectivo_contado' => $contado,
                    'diferencia' => round($contado - $esperado, 2),
                    'total_cobrado' => $resumen['total'],
                    'total_egresos' => $resumen['egresos_total'] ?? 0.0,
                    'efectivo_egresos' => $resumen['egresos_efectivo'] ?? 0.0,
                    'recibos' => $resumen['recibos'],
                    'totales' => $resumen,
                    'observaciones' => $datos['observaciones'] ?? null,
                    'estado' => 'CERRADO',
                    'cerrado_en' => now(),
                ]);

                // Asociar egresos huérfanos del día al cierre creado
                EgresoCaja::query()
                    ->whereDate('fecha', $fecha)
                    ->whereNull('cierre_caja_id')
                    ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
                    ->update(['cierre_caja_id' => $nuevoCierre->id]);

                return $nuevoCierre;
            });
        } catch (UniqueConstraintViolationException) {
            $cierre = null;
        }

        if ($cierre === null) {
            return back()->withInput()
                ->with('error', 'La caja del '.Carbon::parse($fecha)->format('d/m/Y').' ('.(CierreCaja::TURNOS[$turno] ?? $turno).') ya fue cerrada para esta sucursal.');
        }

        Auditoria::registrar('CERRAR', $cierre, sprintf(
            'Cerró la caja del %s [%s]: esperado %s, contado %s, diferencia %s',
            Carbon::parse($fecha)->format('d/m/Y'),
            $cierre->turno,
            number_format((float) $cierre->efectivo_esperado, 2),
            number_format((float) $cierre->efectivo_contado, 2),
            number_format((float) $cierre->diferencia, 2),
        ));

        $mensaje = 'Caja del '.Carbon::parse($fecha)->format('d/m/Y').' cerrada con éxito.';

        if ((float) $cierre->diferencia !== 0.0) {
            $mensaje .= ' Diferencia de '.number_format((float) $cierre->diferencia, 2).'.';
        }

        return redirect()->route('admin.caja.show', $cierre)->with('exito', $mensaje);
    }

    public function show(CierreCaja $cierre): View
    {
        $cierre->load(['sucursal', 'usuario', 'egresos.usuario']);

        return view('admin.caja.show', ['cierre' => $cierre]);
    }

    public function pdf(CierreCaja $cierre): Response
    {
        $cierre->load(['sucursal', 'usuario', 'egresos.usuario']);

        return Pdf::loadView('pdf.cierre-caja', [
            'cierre' => $cierre,
            'clinica' => Ajuste::actual(),
        ])->setPaper('letter')
            ->stream('cierre-caja-'.$cierre->fecha->format('Ymd').'-'.strtolower($cierre->turno).'.pdf');
    }

    private function cierreDe(string $fecha, ?int $sucursalId, ?string $turno = null): ?CierreCaja
    {
        return CierreCaja::query()
            ->whereDate('fecha', $fecha)
            ->when($turno, fn ($q) => $q->where('turno', $turno))
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId), fn ($q) => $q->whereNull('sucursal_id'))
            ->first();
    }

    /** Fondo inicial propuesto: el fondo del último cierre anterior de la sucursal, o 0. */
    private function fondoSugerido(string $fecha, ?int $sucursalId): float
    {
        $anterior = CierreCaja::query()
            ->whereDate('fecha', '<=', $fecha)
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId), fn ($q) => $q->whereNull('sucursal_id'))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->first();

        return $anterior ? (float) $anterior->fondo_inicial : 0.0;
    }
}
