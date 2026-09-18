<?php

namespace Database\Seeders;

use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\Usuario;
use App\Services\InventarioService;
use Illuminate\Database\Seeder;

class InventarioSeeder extends Seeder
{
    /** [código, nombre, categoría, unidad, mínimo, costo] */
    private const INSUMOS = [
        ['ANE-001', 'LIDOCAÍNA 2% CON EPINEFRINA', 'ANESTESIA', 'CAJA', 5, 180],
        ['ANE-002', 'ARTICAÍNA 4%', 'ANESTESIA', 'CAJA', 4, 260],
        ['ANE-003', 'AGUJA DENTAL CORTA 30G', 'ANESTESIA', 'CAJA', 6, 95],
        ['RES-001', 'RESINA COMPUESTA A2', 'RESTAURACION', 'TUBO', 8, 220],
        ['RES-002', 'RESINA COMPUESTA A3', 'RESTAURACION', 'TUBO', 8, 220],
        ['RES-003', 'ADHESIVO DENTAL UNIVERSAL', 'RESTAURACION', 'FRASCO', 4, 340],
        ['RES-004', 'ÁCIDO GRABADOR 37%', 'RESTAURACION', 'FRASCO', 5, 75],
        ['RES-005', 'IONÓMERO DE VIDRIO', 'RESTAURACION', 'UNIDAD', 5, 290],
        ['END-001', 'LIMAS ROTATORIAS NITI', 'ENDODONCIA', 'PAQUETE', 3, 680],
        ['END-002', 'HIPOCLORITO DE SODIO 5.25%', 'ENDODONCIA', 'FRASCO', 6, 55],
        ['END-003', 'CONOS DE GUTAPERCHA', 'ENDODONCIA', 'CAJA', 4, 150],
        ['END-004', 'CEMENTO SELLADOR ENDODÓNTICO', 'ENDODONCIA', 'UNIDAD', 3, 420],
        ['ORT-001', 'BRACKETS METÁLICOS ROTH 022', 'ORTODONCIA', 'CAJA', 3, 780],
        ['ORT-002', 'ARCO NITI SUPERIOR 014', 'ORTODONCIA', 'PAQUETE', 5, 190],
        ['ORT-003', 'LIGADURAS ELÁSTICAS', 'ORTODONCIA', 'PAQUETE', 8, 60],
        ['CIR-001', 'SUTURA SEDA 3-0', 'CIRUGIA', 'CAJA', 4, 240],
        ['CIR-002', 'HOJA DE BISTURÍ N° 15', 'CIRUGIA', 'CAJA', 5, 85],
        ['CIR-003', 'GASA ESTÉRIL', 'CIRUGIA', 'PAQUETE', 10, 45],
        ['PRO-001', 'PASTA PROFILÁCTICA', 'PROFILAXIS', 'FRASCO', 6, 110],
        ['PRO-002', 'FLÚOR EN GEL', 'PROFILAXIS', 'FRASCO', 5, 130],
        ['PRO-003', 'CEPILLO PROFILÁCTICO', 'PROFILAXIS', 'PAQUETE', 8, 70],
        ['DES-001', 'EYECTOR DE SALIVA', 'DESECHABLE', 'PAQUETE', 12, 40],
        ['DES-002', 'VASO DESCARTABLE', 'DESECHABLE', 'PAQUETE', 12, 35],
        ['DES-003', 'BABERO DESCARTABLE', 'DESECHABLE', 'PAQUETE', 10, 65],
        ['BIO-001', 'GUANTES DE NITRILO TALLA M', 'BIOSEGURIDAD', 'CAJA', 10, 120],
        ['BIO-002', 'MASCARILLA QUIRÚRGICA', 'BIOSEGURIDAD', 'CAJA', 10, 90],
        ['BIO-003', 'GLUTARALDEHÍDO 2%', 'BIOSEGURIDAD', 'FRASCO', 4, 210],
        ['BIO-004', 'BOLSA DE ESTERILIZACIÓN', 'BIOSEGURIDAD', 'PAQUETE', 6, 130],
        ['INS-001', 'ESPEJO BUCAL N° 5', 'INSTRUMENTAL', 'UNIDAD', 6, 45],
        ['INS-002', 'FRESA DIAMANTADA REDONDA', 'INSTRUMENTAL', 'PAQUETE', 8, 160],
    ];

    public function __construct(private readonly InventarioService $inventario) {}

    public function run(): void
    {
        $usuarios = Usuario::role(['ADMINISTRADOR', 'RECEPCION', 'SUPER ADMINISTRADOR'])->pluck('id');

        foreach (self::INSUMOS as [$codigo, $nombre, $categoria, $unidad, $minimo, $costo]) {
            $insumo = Insumo::updateOrCreate(['codigo' => $codigo], [
                'nombre' => $nombre,
                'categoria' => $categoria,
                'unidad_medida' => $unidad,
                'stock_actual' => 0,
                'stock_minimo' => $minimo,
                'costo_unitario' => $costo,
                'proveedor' => ['Dental Import SRL', 'Distribuidora Odonto', 'Casa Dental La Paz'][array_rand([0, 1, 2])],
                'ubicacion' => 'Estante '.chr(65 + rand(0, 4)).rand(1, 6),
                'fecha_vencimiento' => rand(0, 1) ? now()->addMonths(rand(2, 30))->toDateString() : null,
                'activo' => true,
            ]);

            if ($insumo->movimientos()->exists()) {
                continue;
            }

            // Compra inicial y consumo de los últimos meses.
            $this->inventario->registrar($insumo, [
                'tipo' => 'ENTRADA',
                'cantidad' => $minimo * rand(3, 8),
                'costo_unitario' => $costo,
                'motivo' => 'Compra inicial de existencias',
                'referencia' => 'FAC-'.rand(10000, 99999),
                'usuario_id' => $usuarios->random(),
                'fecha' => now()->subMonths(4),
            ]);

            foreach (range(1, rand(3, 8)) as $i) {
                $disponible = (float) $insumo->fresh()->stock_actual;

                if ($disponible < 2) {
                    break;
                }

                $this->inventario->registrar($insumo, [
                    'tipo' => rand(1, 12) === 1 ? 'MERMA' : 'SALIDA',
                    'cantidad' => min($disponible, rand(1, max(2, (int) ($minimo / 2)))),
                    'motivo' => 'Consumo clínico del período',
                    'usuario_id' => $usuarios->random(),
                    'fecha' => now()->subDays(rand(1, 110)),
                ]);
            }
        }

        $this->command->info(
            'Insumos: '.Insumo::count().
            ' · Movimientos: '.MovimientoInventario::count().
            ' · Bajo mínimo: '.Insumo::activos()->bajoMinimo()->count()
        );
    }
}
