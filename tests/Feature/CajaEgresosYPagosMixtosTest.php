<?php

namespace Tests\Feature;

use App\Models\CierreCaja;
use App\Models\EgresoCaja;
use App\Models\Pago;
use App\Models\Sucursal;
use Tests\CasoClinico;

class CajaEgresosYPagosMixtosTest extends CasoClinico
{
    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sucursal = Sucursal::create([
            'nombre' => 'Sede Central',
            'codigo' => 'SUC-01',
            'activa' => true,
        ]);
    }

    public function test_puede_registrar_egreso_de_caja_chica(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.egresos.store'), [
            'monto' => 45.50,
            'concepto' => 'Compra urgente de gasas estériles',
            'categoria' => 'INSUMOS_MENORES',
            'metodo_pago' => 'EFECTIVO',
            'fecha' => now()->format('Y-m-d H:i'),
            'comprobante_tipo' => 'RECIBO',
            'comprobante_numero' => 'REC-0012',
            'observaciones' => 'Comprado en farmacia vecina',
        ]);

        $response->assertRedirect(route('admin.egresos.index'));
        $this->assertDatabaseHas('egresos_caja', [
            'concepto' => 'Compra urgente de gasas estériles',
            'monto' => 45.50,
            'categoria' => 'INSUMOS_MENORES',
            'metodo_pago' => 'EFECTIVO',
            'estado' => 'REGISTRADO',
        ]);
    }

    public function test_puede_anular_egreso_con_motivo(): void
    {
        $egreso = EgresoCaja::create([
            'usuario_id' => $this->admin->id,
            'monto' => 30.00,
            'concepto' => 'Error de digitación',
            'categoria' => 'OTRO',
            'metodo_pago' => 'EFECTIVO',
            'fecha' => now(),
            'estado' => 'REGISTRADO',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.egresos.anular', $egreso), [
            'motivo_anulacion' => 'Monto duplicado por equivocación de cajero',
        ]);

        $response->assertRedirect(route('admin.egresos.index'));
        $this->assertDatabaseHas('egresos_caja', [
            'id' => $egreso->id,
            'estado' => 'ANULADO',
        ]);
    }

    public function test_calcula_resumen_dia_con_egresos_y_pagos_mixtos(): void
    {
        $hoy = now()->toDateString();

        // 1. Pago puramente en EFECTIVO: 100.00
        Pago::create([
            'paciente_id' => $this->paciente->id,
            'usuario_id' => $this->admin->id,
            'codigo_recibo' => 'REC-1001',
            'monto_total' => 100.00,
            'monto_pagado' => 100.00,
            'metodo_pago' => 'EFECTIVO',
            'estado' => 'PAGADO',
            'fecha_pago' => now(),
        ]);

        // 2. Pago MIXTO: 200.00 (80 efectivo, 120 tarjeta)
        Pago::create([
            'paciente_id' => $this->paciente->id,
            'usuario_id' => $this->admin->id,
            'codigo_recibo' => 'REC-1002',
            'monto_total' => 200.00,
            'monto_pagado' => 200.00,
            'metodo_pago' => 'MIXTO',
            'desglose_metodos' => [
                'EFECTIVO' => 80.00,
                'TARJETA' => 120.00,
            ],
            'estado' => 'PAGADO',
            'fecha_pago' => now(),
        ]);

        // 3. Egreso en efectivo: 50.00
        EgresoCaja::create([
            'usuario_id' => $this->admin->id,
            'monto' => 50.00,
            'concepto' => 'Pago de taxi courier',
            'categoria' => 'SERVICIOS',
            'metodo_pago' => 'EFECTIVO',
            'fecha' => now(),
            'estado' => 'PAGADO',
        ]);

        // 4. Egreso anulado (no debe restar): 25.00
        EgresoCaja::create([
            'usuario_id' => $this->admin->id,
            'monto' => 25.00,
            'concepto' => 'Egreso anulado de prueba',
            'categoria' => 'OTROS',
            'metodo_pago' => 'EFECTIVO',
            'fecha' => now(),
            'estado' => 'ANULADO',
        ]);

        $resumen = CierreCaja::resumenDelDia($hoy);

        $this->assertEquals(300.00, $resumen['total']);
        $this->assertEquals(180.00, $resumen['efectivo']); // 100 + 80
        $this->assertEquals(120.00, $resumen['por_metodo']['TARJETA']);
        $this->assertEquals(50.00, $resumen['egresos_total']);
        $this->assertEquals(50.00, $resumen['egresos_efectivo']);
        $this->assertEquals(130.00, $resumen['efectivo_neto']); // 180 - 50
    }

    public function test_puede_cerrar_caja_por_turnos_independientes(): void
    {
        $hoy = now()->toDateString();

        // Cierre de Turno Mañana
        $respManana = $this->actingAs($this->admin)->post(route('admin.caja.cerrar'), [
            'fecha' => $hoy,
            'turno' => 'MANANA',
            'fondo_inicial' => 100.00,
            'efectivo_contado' => 100.00,
            'observaciones' => 'Cierre turno mañana sin novedades',
        ]);
        $respManana->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cierres_caja', [
            'turno' => 'MANANA',
            'fondo_inicial' => 100.00,
        ]);

        // Cierre de Turno Tarde en la misma fecha y sucursal
        $respTarde = $this->actingAs($this->admin)->post(route('admin.caja.cerrar'), [
            'fecha' => $hoy,
            'turno' => 'TARDE',
            'fondo_inicial' => 150.00,
            'efectivo_contado' => 150.00,
            'observaciones' => 'Cierre turno tarde sin novedades',
        ]);
        $respTarde->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cierres_caja', [
            'turno' => 'TARDE',
            'fondo_inicial' => 150.00,
        ]);
    }

    public function test_valida_pago_mixto_requiere_desglose_correcto(): void
    {
        // Desglose descuadrado (monto_pagado: 100, suma: 70)
        $respInvalido = $this->actingAs($this->admin)->post(route('admin.pagos.store'), [
            'paciente_id' => $this->paciente->id,
            'metodo_pago' => 'MIXTO',
            'monto_pagado' => 100.00,
            'fecha_pago' => now()->format('Y-m-d\TH:i'),
            'detalles' => [
                [
                    'tratamiento_id' => $this->tratamiento->id,
                    'descripcion' => 'Consulta',
                    'cantidad' => 1,
                    'precio_unitario' => 100.00,
                ],
            ],
            'desglose_metodos' => [
                'EFECTIVO' => 50.00,
                'TARJETA' => 20.00,
            ],
        ]);

        $respInvalido->assertSessionHasErrors('desglose_metodos');

        // Desglose cuadrado (monto_pagado: 100, suma: 100)
        $respValido = $this->actingAs($this->admin)->post(route('admin.pagos.store'), [
            'paciente_id' => $this->paciente->id,
            'metodo_pago' => 'MIXTO',
            'monto_pagado' => 100.00,
            'fecha_pago' => now()->format('Y-m-d\TH:i'),
            'detalles' => [
                [
                    'tratamiento_id' => $this->tratamiento->id,
                    'descripcion' => 'Consulta',
                    'cantidad' => 1,
                    'precio_unitario' => 100.00,
                ],
            ],
            'desglose_metodos' => [
                'EFECTIVO' => 60.00,
                'TARJETA' => 40.00,
            ],
        ]);

        $respValido->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pagos', [
            'paciente_id' => $this->paciente->id,
            'metodo_pago' => 'MIXTO',
            'monto_pagado' => 100.00,
        ]);
    }
}
