<?php

namespace Tests\Unit;

use App\Models\DocumentoFiscal;
use PHPUnit\Framework\TestCase;

class DocumentoFiscalTest extends TestCase
{
    public function test_desglosar_iva_separa_base_imponible_e_impuesto(): void
    {
        $resultado = DocumentoFiscal::desglosarIva(113.0, 13.0);

        $this->assertSame(['subtotal', 'iva'], array_keys($resultado));
        $this->assertEqualsWithDelta(100.00, $resultado['subtotal'], 0.001);
        $this->assertEqualsWithDelta(13.00, $resultado['iva'], 0.001);
    }

    public function test_desglosar_iva_con_tasa_cero_devuelve_todo_como_subtotal(): void
    {
        $resultado = DocumentoFiscal::desglosarIva(50.0, 0.0);

        $this->assertEqualsWithDelta(50.00, $resultado['subtotal'], 0.001);
        $this->assertEqualsWithDelta(0.00, $resultado['iva'], 0.001);
    }

    public function test_componer_numero_control_para_factura(): void
    {
        $numero = DocumentoFiscal::componerNumeroControl('FACTURA', 'A', 7);

        $this->assertSame('DTE-01-000A-000000000000007', $numero);
    }

    public function test_componer_numero_control_usa_codigo_del_tipo_y_normaliza_serie(): void
    {
        $this->assertSame(
            'DTE-03-00AB-000000000000123',
            DocumentoFiscal::componerNumeroControl('CREDITO_FISCAL', 'ab', 123)
        );

        $this->assertSame(
            'DTE-01-000Z-000000000000001',
            DocumentoFiscal::componerNumeroControl('TIPO_DESCONOCIDO', 'z', 1)
        );
    }
}
