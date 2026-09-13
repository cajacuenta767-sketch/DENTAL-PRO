<?php

namespace Tests\Feature;

use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Services\InventarioService;
use Illuminate\Validation\ValidationException;
use Tests\CasoClinico;

class InventarioTest extends CasoClinico
{
    private Insumo $insumo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin);

        $this->insumo = Insumo::create([
            'codigo' => 'ANE-001',
            'nombre' => 'LIDOCAÍNA 2%',
            'categoria' => 'ANESTESIA',
            'unidad_medida' => 'CAJA',
            'stock_actual' => 0,
            'stock_minimo' => 5,
            'costo_unitario' => 180,
            'activo' => true,
        ]);
    }

    private function movimiento(array $datos): void
    {
        app(InventarioService::class)->registrar($this->insumo, $datos);
        $this->insumo->refresh();
    }

    public function test_una_entrada_suma_al_stock_y_deja_rastro_en_el_kardex(): void
    {
        $this->movimiento(['tipo' => 'ENTRADA', 'cantidad' => 20]);

        $this->assertSame('20.00', $this->insumo->stock_actual);
        $this->assertSame('20.00', MovimientoInventario::first()->stock_resultante);
    }

    public function test_una_salida_resta_del_stock(): void
    {
        $this->movimiento(['tipo' => 'ENTRADA', 'cantidad' => 20]);
        $this->movimiento(['tipo' => 'SALIDA', 'cantidad' => 7]);

        $this->assertSame('13.00', $this->insumo->stock_actual);
    }

    public function test_un_ajuste_fija_el_saldo_contado(): void
    {
        $this->movimiento(['tipo' => 'ENTRADA', 'cantidad' => 20]);
        $this->movimiento(['tipo' => 'AJUSTE', 'cantidad' => 8]);

        $this->assertSame('8.00', $this->insumo->stock_actual);
    }

    public function test_no_se_puede_sacar_mas_de_lo_que_hay(): void
    {
        $this->movimiento(['tipo' => 'ENTRADA', 'cantidad' => 5]);

        $this->expectException(ValidationException::class);
        $this->movimiento(['tipo' => 'SALIDA', 'cantidad' => 6]);
    }

    public function test_el_stock_nunca_queda_negativo_tras_un_intento_fallido(): void
    {
        $this->movimiento(['tipo' => 'ENTRADA', 'cantidad' => 5]);

        try {
            $this->movimiento(['tipo' => 'SALIDA', 'cantidad' => 50]);
        } catch (ValidationException) {
            // Se espera el rechazo.
        }

        $this->assertSame('5.00', $this->insumo->fresh()->stock_actual);
        $this->assertSame(1, MovimientoInventario::count(), 'El movimiento rechazado no debe registrarse.');
    }

    public function test_el_semaforo_de_existencias_refleja_el_minimo(): void
    {
        $this->movimiento(['tipo' => 'ENTRADA', 'cantidad' => 4]);
        $this->assertSame('critico', $this->insumo->nivel_stock);

        $this->movimiento(['tipo' => 'ENTRADA', 'cantidad' => 20]);
        $this->assertSame('normal', $this->insumo->fresh()->nivel_stock);

        $this->movimiento(['tipo' => 'SALIDA', 'cantidad' => 24]);
        $this->assertSame('agotado', $this->insumo->fresh()->nivel_stock);
    }

    public function test_el_insumo_bajo_minimo_aparece_en_las_alertas(): void
    {
        $this->movimiento(['tipo' => 'ENTRADA', 'cantidad' => 3]);

        $this->assertTrue(Insumo::activos()->bajoMinimo()->where('id', $this->insumo->id)->exists());

        $this->get('/admin/inventario')->assertOk()->assertSee('LIDOCAÍNA 2%');
    }
}
