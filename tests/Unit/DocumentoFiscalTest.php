<?php

namespace Tests\Unit;

use App\Models\DocumentoFiscal;
use PHPUnit\Framework\TestCase;

/**
 * Cálculos fiscales puros: no tocan base de datos, así que sirven de
 * red rápida sobre el desglose de IVA y el formato del número de control.
 */
class DocumentoFiscalTest extends TestCase
{
    public function test_el_desglose_reparte_el_total_sin_perder_centavos(): void
    {
        $desglose = DocumentoFiscal::desglosarIva(113.00, 13.0);

        $this->assertSame(100.00, $desglose['subtotal']);
        $this->assertSame(13.00, $desglose['iva']);
        $this->assertSame(113.00, round($desglose['subtotal'] + $desglose['iva'], 2));
    }

    public function test_un_total_con_redondeo_dificil_sigue_cuadrando(): void
    {
        $desglose = DocumentoFiscal::desglosarIva(99.99, 13.0);

        $this->assertSame(99.99, round($desglose['subtotal'] + $desglose['iva'], 2));
    }

    public function test_sin_impuesto_todo_el_total_es_base_imponible(): void
    {
        $desglose = DocumentoFiscal::desglosarIva(250.00, 0.0);

        $this->assertSame(250.00, $desglose['subtotal']);
        $this->assertSame(0.00, $desglose['iva']);
    }

    public function test_el_numero_de_control_usa_serie_y_correlativo_rellenados(): void
    {
        $numero = DocumentoFiscal::componerNumeroControl(
            array_key_first(DocumentoFiscal::TIPOS),
            'a',
            7
        );

        $this->assertMatchesRegularExpression('/^DTE-\d{2}-000A-\d{15}$/', $numero);
        $this->assertStringEndsWith('000000000000007', $numero);
    }
}
