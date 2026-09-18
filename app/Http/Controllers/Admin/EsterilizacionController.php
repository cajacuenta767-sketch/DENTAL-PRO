<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\CicloEsterilizacion;
use App\Models\Sucursal;
use App\Support\SucursalActiva;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class EsterilizacionController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'fecha' => ['nullable', 'date'],
            'resultado' => ['nullable', 'string'],
        ]);

        $sucursalActiva = SucursalActiva::id();

        $query = CicloEsterilizacion::query()
            ->with(['usuario', 'sucursal'])
            ->when($sucursalActiva, fn ($q) => $q->where('sucursal_id', $sucursalActiva))
            ->when($request->filled('fecha'), fn ($q) => $q->whereDate('fecha', $request->fecha))
            ->when($request->filled('resultado'), fn ($q) => $q->where('resultado', $request->resultado))
            ->orderByDesc('fecha')->orderByDesc('id');

        $ciclos = $query->paginate(15)->withQueryString();

        $hoy = now()->toDateString();
        $ciclosHoy = CicloEsterilizacion::whereDate('fecha', $hoy)->count();
        $aprobadosHoy = CicloEsterilizacion::whereDate('fecha', $hoy)->where('resultado', 'APROBADO')->count();
        $paquetesVigentes = CicloEsterilizacion::where('resultado', 'APROBADO')
            ->whereDate('fecha_caducidad_paquetes', '>=', $hoy)
            ->sum('paquetes_esterilizados');

        return view('admin.esterilizacion.index', [
            'ciclos' => $ciclos,
            'ciclosHoy' => $ciclosHoy,
            'aprobadosHoy' => $aprobadosHoy,
            'paquetesVigentes' => (int) $paquetesVigentes,
            'tiposCarga' => CicloEsterilizacion::TIPOS_CARGA,
            'resultados' => CicloEsterilizacion::RESULTADOS,
        ]);
    }

    public function create(): View
    {
        // Proponer número de ciclo correlativo diario
        $ultimoHoy = CicloEsterilizacion::whereDate('fecha', now()->toDateString())->max('numero_ciclo') ?? 0;

        return view('admin.esterilizacion.form', [
            'ciclo' => new CicloEsterilizacion([
                'fecha' => now()->toDateString(),
                'numero_ciclo' => $ultimoHoy + 1,
                'hora_inicio' => now()->format('H:i'),
                'temperatura' => 134.0,
                'presion' => 2.10,
                'tiempo_esterilizacion' => 18,
                'tipo_carga' => 'INSTRUMENTAL_QUIRURGICO',
                'indicador_quimico' => 'CONFORME',
                'indicador_biologico' => 'NEGATIVO',
                'resultado' => 'APROBADO',
                'paquetes_esterilizados' => 5,
                'fecha_caducidad_paquetes' => now()->addDays(30)->toDateString(),
            ]),
            'tiposCarga' => CicloEsterilizacion::TIPOS_CARGA,
            'resultados' => CicloEsterilizacion::RESULTADOS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'autoclave_nombre' => ['required', 'string', 'max:100'],
            'numero_ciclo' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['nullable', 'string', 'max:10'],
            'hora_fin' => ['nullable', 'string', 'max:10'],
            'temperatura' => ['required', 'numeric', 'between:100,160'],
            'presion' => ['required', 'numeric', 'between:0.5,4.0'],
            'tiempo_esterilizacion' => ['required', 'integer', 'between:3,120'],
            'tipo_carga' => ['required', 'string', 'in:' . implode(',', array_keys(CicloEsterilizacion::TIPOS_CARGA))],
            'indicador_quimico' => ['required', 'string', 'in:CONFORME,NO_CONFORME'],
            'indicador_biologico' => ['required', 'string', 'in:NEGATIVO,POSITIVO,PENDIENTE'],
            'resultado' => ['required', 'string', 'in:APROBADO,RECHAZADO'],
            'paquetes_esterilizados' => ['required', 'integer', 'min:1', 'max:500'],
            'fecha_caducidad_paquetes' => ['required', 'date'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        $datos['qr_token'] = Str::random(32);
        $datos['usuario_id'] = $request->user()->id;
        $datos['sucursal_id'] = SucursalActiva::id();

        $ciclo = CicloEsterilizacion::create($datos);

        Auditoria::registrar('CREAR', $ciclo, "Registró ciclo de autoclave #{$ciclo->numero_ciclo} [{$ciclo->resultado}]");

        return redirect()->route('admin.esterilizacion.index')
            ->with('exito', "Ciclo de esterilización #{$ciclo->numero_ciclo} guardado correctamente.");
    }

    public function etiquetas(CicloEsterilizacion $ciclo): Response
    {
        $qrDataUri = app(\App\Services\QrService::class)->dataUri(
            route('esterilizacion.verificar-publica', $ciclo->qr_token),
            140
        );

        return Pdf::loadView('pdf.etiquetas-esterilizacion', [
            'ciclo' => $ciclo,
            'clinica' => Ajuste::actual(),
            'qrDataUri' => $qrDataUri,
        ])->setPaper('a4')
            ->stream("etiquetas-autoclave-ciclo-{$ciclo->numero_ciclo}.pdf");
    }

    public function verificarQr(string $token): View
    {
        $ciclo = CicloEsterilizacion::where('qr_token', $token)->firstOrFail();

        return view('admin.esterilizacion.verificar', [
            'ciclo' => $ciclo,
        ]);
    }
}
