<?php

namespace Database\Seeders;

use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\DocumentoClinico;
use App\Models\DocumentoFiscal;
use App\Models\EstudioImagen;
use App\Models\Odontograma;
use App\Models\Pago;
use App\Models\Presupuesto;
use App\Models\Tratamiento;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos demo de los módulos avanzados: recetas y certificados,
 * presupuestos con su plan de tratamiento, documentos fiscales
 * y el enlace público de reservas.
 */
class ClinicaAvanzadaSeeder extends Seeder
{
    public function run(): void
    {
        $this->activarReservas();
        $this->documentosClinicos();
        $this->presupuestos();
        $this->documentosFiscales();
        $this->estudiosDeMuestra();

        $this->command->info(
            'Documentos clínicos: '.DocumentoClinico::count().
            ' · Presupuestos: '.Presupuesto::count().
            ' · Facturas: '.DocumentoFiscal::count().
            ' · Estudios: '.EstudioImagen::count()
        );
    }

    private function activarReservas(): void
    {
        $ajustes = Ajuste::actual();

        $ajustes->update([
            'reservas_online' => true,
            'reservas_token' => $ajustes->reservas_token ?: Str::lower(Str::random(32)),
            'reservas_anticipacion_dias' => 45,
            'reservas_minimo_horas' => 4,
            'reservas_mensaje' => 'Atendemos de lunes a sábado. Si necesitas una urgencia el mismo día, '
                .'llámanos y te ubicamos en el primer espacio libre.',
            'facturacion_activa' => true,
            'facturacion_serie' => 'A',
            'facturacion_tasa_iva' => 13,
        ]);
    }

    private function documentosClinicos(): void
    {
        $plantillas = [
            'RECETA' => [
                'titulo' => 'Receta médica',
                'contenido' => "Rp/\n\n1. Amoxicilina 500 mg — cápsulas\n   Tomar 1 cápsula cada 8 horas por 7 días.\n\n"
                    ."2. Ibuprofeno 400 mg — tabletas\n   Tomar 1 tableta cada 8 horas por 3 días en caso de dolor.",
                'indicaciones' => 'Tomar los medicamentos con alimentos. Evitar bebidas alcohólicas durante el tratamiento.',
                'vigencia' => 30,
            ],
            'CERTIFICADO' => [
                'titulo' => 'Certificado odontológico',
                'contenido' => 'Por medio del presente se hace constar que el paciente recibió atención odontológica '
                    .'en esta clínica en la fecha indicada, y se le recomienda reposo por 1 día.',
                'indicaciones' => 'Presentar ante su empleador o institución educativa.',
                'vigencia' => 90,
            ],
            'ORDEN_LABORATORIO' => [
                'titulo' => 'Orden de laboratorio dental',
                'contenido' => "Se solicita al laboratorio dental la elaboración de:\n\n"
                    ."- Trabajo: Corona de zirconio\n- Piezas: 16\n- Color: A2\n- Material: Zirconio monolítico\n"
                    .'- Fecha de entrega requerida: 7 días hábiles',
                'indicaciones' => null,
                'vigencia' => null,
            ],
        ];

        $citas = Cita::where('estado', 'COMPLETADA')->with(['paciente', 'doctor'])->inRandomOrder()->limit(45)->get();
        $usuario = Usuario::role('SUPER ADMINISTRADOR')->value('id');

        foreach ($citas as $i => $cita) {
            $tipo = array_keys($plantillas)[$i % count($plantillas)];
            $plantilla = $plantillas[$tipo];

            if (DocumentoClinico::where('cita_id', $cita->id)->where('tipo', $tipo)->exists()) {
                continue;
            }

            DocumentoClinico::create([
                'folio' => DocumentoClinico::siguienteFolio($tipo),
                'paciente_id' => $cita->paciente_id,
                'doctor_id' => $cita->doctor_id,
                'cita_id' => $cita->id,
                'usuario_id' => $usuario,
                'tipo' => $tipo,
                'titulo' => $plantilla['titulo'],
                'contenido' => $plantilla['contenido'],
                'indicaciones' => $plantilla['indicaciones'],
                'vigencia_dias' => $plantilla['vigencia'],
                'fecha_emision' => $cita->fecha,
                'estado' => 'EMITIDO',
            ]);
        }
    }

    private function presupuestos(): void
    {
        $tratamientos = Tratamiento::activos()->get();
        $usuario = Usuario::role('SUPER ADMINISTRADOR')->value('id');
        $estados = ['BORRADOR', 'PRESENTADO', 'APROBADO', 'APROBADO', 'EN_EJECUCION', 'COMPLETADO', 'RECHAZADO'];

        $odontogramas = Odontograma::with('paciente.aseguradora')->inRandomOrder()->limit(30)->get();

        foreach ($odontogramas as $i => $odontograma) {
            if (Presupuesto::where('odontograma_id', $odontograma->id)->exists()) {
                continue;
            }

            $estado = $estados[$i % count($estados)];

            $presupuesto = Presupuesto::create([
                'codigo' => Presupuesto::siguienteCodigo(),
                'paciente_id' => $odontograma->paciente_id,
                'doctor_id' => $odontograma->doctor_id,
                'usuario_id' => $usuario,
                'odontograma_id' => $odontograma->id,
                'estado' => $estado,
                'descuento' => rand(0, 3) === 0 ? rand(50, 300) : 0,
                'validez_dias' => 30,
                'fecha' => $odontograma->fecha,
                'notas' => 'Plan elaborado a partir del odontograma del '.$odontograma->fecha->format('d/m/Y').'.',
            ]);

            // Cada hallazgo del odontograma propone una línea del plan.
            $piezasAfectadas = collect($odontograma->piezas ?? [])
                ->filter(fn ($p) => ($p['estado'] ?? 'sano') !== 'sano'
                    || collect($p['caras'] ?? [])->contains(fn ($c) => $c !== 'sano'))
                ->take(rand(2, 5));

            foreach ($piezasAfectadas->values() as $orden => $pieza) {
                $numero = $piezasAfectadas->keys()[$orden];
                $tratamiento = $tratamientos->random();

                $lineaEjecutada = in_array($estado, ['EN_EJECUCION', 'COMPLETADO'], true)
                    && ($estado === 'COMPLETADO' || rand(0, 1) === 1);

                $presupuesto->detalles()->create([
                    'tratamiento_id' => $tratamiento->id,
                    'pieza_dental' => (string) $numero,
                    'cara' => collect(Odontograma::CARAS)->random(),
                    'descripcion' => $tratamiento->nombre,
                    'cantidad' => 1,
                    'precio_unitario' => $tratamiento->precio,
                    'subtotal' => $tratamiento->precio,
                    'estado' => $lineaEjecutada ? 'EJECUTADO' : 'PENDIENTE',
                    'fecha_ejecucion' => $lineaEjecutada ? $odontograma->fecha->copy()->addDays(rand(3, 25)) : null,
                    'orden' => $orden,
                ]);
            }

            $presupuesto->recalcular();
        }
    }

    private function documentosFiscales(): void
    {
        $ajustes = Ajuste::actual();
        $usuario = Usuario::role('SUPER ADMINISTRADOR')->value('id');

        $pagos = Pago::vigentes()
            ->where('estado', 'COMPLETADO')
            ->whereDoesntHave('documentoFiscal')
            ->with(['paciente', 'detalles'])
            ->inRandomOrder()
            ->limit(50)
            ->get();

        foreach ($pagos as $pago) {
            $tipo = rand(1, 5) === 1 ? 'CREDITO_FISCAL' : 'FACTURA';
            $correlativo = DocumentoFiscal::siguienteCorrelativo($tipo, $ajustes->facturacion_serie);
            $desglose = DocumentoFiscal::desglosarIva((float) $pago->monto_total, (float) $ajustes->facturacion_tasa_iva);

            DocumentoFiscal::create([
                'pago_id' => $pago->id,
                'usuario_id' => $usuario,
                'tipo' => $tipo,
                'serie' => $ajustes->facturacion_serie,
                'correlativo' => $correlativo,
                'numero_control' => DocumentoFiscal::componerNumeroControl($tipo, $ajustes->facturacion_serie, $correlativo),
                'codigo_generacion' => DocumentoFiscal::nuevoCodigoGeneracion(),
                'receptor_nombre' => $pago->paciente->nombre_completo,
                'receptor_documento' => $pago->paciente->numero_documento,
                'receptor_direccion' => $pago->paciente->direccion,
                'receptor_email' => $pago->paciente->email,
                'subtotal' => $desglose['subtotal'],
                'iva' => $desglose['iva'],
                'total' => (float) $pago->monto_total,
                'tasa_iva' => $ajustes->facturacion_tasa_iva,
                'estado' => rand(1, 30) === 1 ? 'ANULADO' : 'EMITIDO',
                'sello_recepcion' => mb_strtoupper(bin2hex(random_bytes(16))),
                'fecha_emision' => $pago->fecha_pago,
                'contenido' => [
                    'recibo' => $pago->codigo_recibo,
                    'emisor' => ['nombre' => $ajustes->nombre, 'nit' => $ajustes->nit, 'direccion' => $ajustes->direccion],
                    'lineas' => $pago->detalles->map(fn ($d) => [
                        'descripcion' => $d->descripcion,
                        'cantidad' => $d->cantidad,
                        'precio_unitario' => (float) $d->precio_unitario,
                        'subtotal' => (float) $d->subtotal,
                    ])->all(),
                ],
            ]);
        }
    }

    /**
     * Registra estudios de muestra con un SVG generado localmente: el seeder
     * no descarga nada y la galería y el visor quedan poblados.
     */
    private function estudiosDeMuestra(): void
    {
        $citas = Cita::where('estado', 'COMPLETADA')->with('paciente')->inRandomOrder()->limit(25)->get();
        $usuario = Usuario::role('SUPER ADMINISTRADOR')->value('id');

        $tipos = ['PANORAMICA', 'PANORAMICA', 'PERIAPICAL', 'BITEWING', 'FOTO_INTRAORAL'];

        foreach ($citas as $i => $cita) {
            if (EstudioImagen::where('cita_id', $cita->id)->exists()) {
                continue;
            }

            $tipo = $tipos[$i % count($tipos)];
            $ruta = "estudios/{$cita->paciente_id}/demo-{$cita->id}.svg";

            \Illuminate\Support\Facades\Storage::disk('public')->put($ruta, $this->svgDemostracion($tipo));

            EstudioImagen::create([
                'paciente_id' => $cita->paciente_id,
                'doctor_id' => $cita->doctor_id,
                'cita_id' => $cita->id,
                'usuario_id' => $usuario,
                'tipo' => $tipo,
                'titulo' => EstudioImagen::TIPOS[$tipo].' de control',
                'archivo' => $ruta,
                'nombre_original' => Str::slug(EstudioImagen::TIPOS[$tipo]).'-'.$cita->fecha->format('Ymd').'.svg',
                'mime' => 'image/svg+xml',
                'tamano' => strlen($this->svgDemostracion($tipo)),
                'piezas_referidas' => collect([16, 26, 36, 46, 11, 21])->random(rand(1, 3))->implode(', '),
                'fecha_estudio' => $cita->fecha,
                'hallazgos' => 'Estructuras óseas sin alteraciones evidentes. Se observan restauraciones previas.',
                'observaciones' => 'Imagen de demostración generada por el seeder.',
            ]);
        }
    }

    /** Placeholder en escala de grises que evoca una radiografía. */
    private function svgDemostracion(string $tipo): string
    {
        $dientes = '';

        foreach (range(0, 15) as $i) {
            $x = 60 + $i * 50;
            $dientes .= '<rect x="'.$x.'" y="150" width="34" height="60" rx="10" fill="#d8d8d8" opacity="0.85"/>'
                .'<rect x="'.$x.'" y="250" width="34" height="64" rx="10" fill="#cfcfcf" opacity="0.85"/>';
        }

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 460" width="900" height="460">
            <defs>
                <radialGradient id="f" cx="50%" cy="50%" r="70%">
                    <stop offset="0%" stop-color="#4a4a4a"/>
                    <stop offset="100%" stop-color="#111"/>
                </radialGradient>
            </defs>
            <rect width="900" height="460" fill="url(#f)"/>
            <ellipse cx="450" cy="232" rx="400" ry="150" fill="#2b2b2b"/>
            {$dientes}
            <text x="450" y="420" fill="#8a8a8a" font-family="sans-serif" font-size="18"
                  text-anchor="middle">{$tipo} · imagen de demostración</text>
        </svg>
        SVG;
    }
}
