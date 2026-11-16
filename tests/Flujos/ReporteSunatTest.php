<?php

namespace Tests\Flujos;

use App\Support\Reportes;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Reporte SUNAT: código, serie y número por separado, ordenado así, y con los anulados (en 0) para que no falte ningún correlativo */
class ReporteSunatTest extends FlujoTestCase
{
    public function test_ordena_por_codigo_serie_y_numero_e_incluye_los_anulados_en_cero(): void
    {
        $juane = $this->producto('JUANE', 15, 50);
        $boleta1 = VentaDirecta::registrar($this->usuario, $this->venta([[$juane, 1]], '03'), 'PV');
        $factura = VentaDirecta::registrar($this->usuario, $this->venta([[$juane, 2]], '01', ['ccandi' => '20100070970', 'ccanom' => 'CLIENTE SAC']), 'PV');
        $anulada = VentaDirecta::registrar($this->usuario, $this->venta([[$juane, 1]], '03'), 'PV');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $anulada)->update(['ccabaj' => now()]);
        $sucursal = (int) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $boleta1)->value('id_empresa_negocio');

        $r = Reportes::generar('sunat', ['desde' => now()->toDateString(), 'hasta' => now()->toDateString(), 'sucursal' => $sucursal]);
        $numero = fn ($id) => str_pad((string) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->value('numdoc'), 8, '0', STR_PAD_LEFT);

        $this->assertSame(['01', '03', '03'], array_column($r['filas'], 'codigo'));
        $this->assertSame([$numero($factura), $numero($boleta1), $numero($anulada)], array_column($r['filas'], 'numero'));
        $this->assertSame('ANULADO', $r['filas'][2]['sunat']);
        $this->assertEquals(0, $r['filas'][2]['total']);
        $this->assertEquals(45.0, array_sum(array_column($r['filas'], 'total')));
        $this->assertSame(['codigo', 'serie', 'numero'], array_slice(array_keys($r['columnas']), 2, 3));

        $soloAnulados = Reportes::generar('sunat', ['desde' => now()->toDateString(), 'hasta' => now()->toDateString(), 'sucursal' => $sucursal, 'estado_sunat' => 'ANULADO']);
        $this->assertSame([$numero($anulada)], array_column($soloAnulados['filas'], 'numero'));
    }
}
