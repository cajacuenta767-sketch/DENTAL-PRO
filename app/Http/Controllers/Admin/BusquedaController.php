<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\Presupuesto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Buscador único de la barra superior. Solo devuelve resultados de los
 * módulos sobre los que el usuario tiene permiso de lectura.
 */
class BusquedaController extends Controller
{
    public function sugerencias(Request $request): JsonResponse
    {
        $termino = trim((string) $request->query('q', ''));

        if (mb_strlen($termino) < 2) {
            return response()->json(['grupos' => []]);
        }

        return response()->json(['grupos' => $this->buscar($termino, 5)]);
    }

    public function index(Request $request): View
    {
        $termino = trim((string) $request->query('q', ''));

        return view('admin.busqueda', [
            'termino' => $termino,
            'grupos' => mb_strlen($termino) >= 2 ? $this->buscar($termino, 25) : [],
        ]);
    }

    /** @return array<int, array{titulo: string, icono: string, items: array}> */
    private function buscar(string $termino, int $limite): array
    {
        $t = '%'.mb_strtolower($termino).'%';
        $grupos = [];

        if (Gate::allows('pacientes.ver')) {
            $pacientes = Paciente::buscar($termino)->orderBy('apellidos')->limit($limite)->get();

            if ($pacientes->isNotEmpty()) {
                $grupos[] = [
                    'titulo' => 'Pacientes',
                    'icono' => 'ti ti-user-heart',
                    'items' => $pacientes->map(fn ($p) => [
                        'titulo' => $p->nombre_completo,
                        'detalle' => $p->tipo_documento.' '.$p->numero_documento.($p->telefono ? ' · '.$p->telefono : ''),
                        'url' => route('admin.pacientes.show', $p),
                    ])->all(),
                ];
            }
        }

        if (Gate::allows('citas.ver')) {
            $citas = Cita::with(['paciente', 'doctor'])
                ->where(fn ($q) => $q->whereRaw('LOWER(token) LIKE ?', [$t])
                    ->orWhereHas('paciente', fn ($p) => $p->whereRaw('LOWER(nombres) LIKE ?', [$t])
                        ->orWhereRaw('LOWER(apellidos) LIKE ?', [$t])
                        ->orWhereRaw('LOWER(numero_documento) LIKE ?', [$t])))
                ->orderByDesc('fecha')
                ->limit($limite)
                ->get();

            if ($citas->isNotEmpty()) {
                $grupos[] = [
                    'titulo' => 'Citas',
                    'icono' => 'ti ti-calendar-event',
                    'items' => $citas->map(fn ($c) => [
                        'titulo' => $c->paciente->nombre_completo.' · '.$c->token,
                        'detalle' => $c->fecha_hora.' · '.$c->doctor->nombre_profesional.' · '.$c->estado_legible,
                        'url' => route('admin.citas.show', $c),
                    ])->all(),
                ];
            }
        }

        if (Gate::allows('doctores.ver')) {
            $doctores = Doctor::with('especialidad')
                ->where(fn ($q) => $q->whereRaw('LOWER(nombres) LIKE ?', [$t])
                    ->orWhereRaw('LOWER(apellidos) LIKE ?', [$t])
                    ->orWhereRaw('LOWER(numero_documento) LIKE ?', [$t]))
                ->limit($limite)
                ->get();

            if ($doctores->isNotEmpty()) {
                $grupos[] = [
                    'titulo' => 'Doctores',
                    'icono' => 'ti ti-stethoscope',
                    'items' => $doctores->map(fn ($d) => [
                        'titulo' => $d->nombre_profesional,
                        'detalle' => $d->especialidad->nombre,
                        'url' => route('admin.doctores.show', $d),
                    ])->all(),
                ];
            }
        }

        if (Gate::allows('pagos.ver')) {
            $pagos = Pago::with('paciente')
                ->where(fn ($q) => $q->whereRaw('LOWER(codigo_recibo) LIKE ?', [$t])
                    ->orWhereHas('paciente', fn ($p) => $p->whereRaw('LOWER(nombres) LIKE ?', [$t])
                        ->orWhereRaw('LOWER(apellidos) LIKE ?', [$t])))
                ->orderByDesc('fecha_pago')
                ->limit($limite)
                ->get();

            if ($pagos->isNotEmpty()) {
                $grupos[] = [
                    'titulo' => 'Recibos',
                    'icono' => 'ti ti-receipt',
                    'items' => $pagos->map(fn ($p) => [
                        'titulo' => $p->codigo_recibo.' · '.$p->paciente->nombre_completo,
                        'detalle' => number_format($p->monto_total, 2).' · '.$p->estado.' · '.$p->fecha_pago->format('d/m/Y'),
                        'url' => route('admin.pagos.show', $p),
                    ])->all(),
                ];
            }
        }

        if (Gate::allows('presupuestos.ver')) {
            $presupuestos = Presupuesto::with('paciente')
                ->where(fn ($q) => $q->whereRaw('LOWER(codigo) LIKE ?', [$t])
                    ->orWhereHas('paciente', fn ($p) => $p->whereRaw('LOWER(nombres) LIKE ?', [$t])
                        ->orWhereRaw('LOWER(apellidos) LIKE ?', [$t])))
                ->orderByDesc('fecha')
                ->limit($limite)
                ->get();

            if ($presupuestos->isNotEmpty()) {
                $grupos[] = [
                    'titulo' => 'Presupuestos',
                    'icono' => 'ti ti-file-invoice',
                    'items' => $presupuestos->map(fn ($p) => [
                        'titulo' => $p->codigo.' · '.$p->paciente->nombre_completo,
                        'detalle' => number_format($p->total, 2).' · '.$p->estado_legible,
                        'url' => route('admin.presupuestos.show', $p),
                    ])->all(),
                ];
            }
        }

        return $grupos;
    }
}
