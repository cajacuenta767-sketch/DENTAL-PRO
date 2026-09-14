<?php

namespace Tests\Feature;

use App\Models\DocumentoClinico;
use App\Models\Pago;
use App\Models\Secuencia;
use Tests\CasoClinico;

class SecuenciaTest extends CasoClinico
{
    public function test_una_secuencia_nueva_cuenta_desde_uno(): void
    {
        $this->assertSame(1, Secuencia::siguiente('x'));
        $this->assertSame(2, Secuencia::siguiente('x'));
        $this->assertSame(3, Secuencia::siguiente('x'));
    }

    public function test_el_valor_inicial_respeta_la_numeracion_previa(): void
    {
        $this->assertSame(42, Secuencia::siguiente('y', fn () => 41));
        $this->assertSame(43, Secuencia::siguiente('y', fn () => 41));
    }

    public function test_el_correlativo_de_recibos_continua_el_ultimo_emitido(): void
    {
        $anio = now()->year;

        Pago::create([
            'codigo_recibo' => "REC-{$anio}-00007",
            'paciente_id' => $this->paciente->id,
            'usuario_id' => $this->admin->id,
            'monto_total' => 100,
            'monto_pagado' => 100,
            'monto_saldo' => 0,
            'metodo_pago' => 'EFECTIVO',
            'estado' => 'COMPLETADO',
            'fecha_pago' => now(),
        ]);

        $this->assertSame("REC-{$anio}-00008", Pago::siguienteCodigo());
    }

    public function test_los_folios_de_documentos_son_consecutivos(): void
    {
        $primero = DocumentoClinico::siguienteFolio('RECETA');
        $segundo = DocumentoClinico::siguienteFolio('RECETA');

        $this->assertNotSame($primero, $segundo);
        $this->assertSame((int) substr($primero, -5) + 1, (int) substr($segundo, -5));
        $this->assertStringStartsWith('REC-'.now()->year.'-', $primero);
    }
}
