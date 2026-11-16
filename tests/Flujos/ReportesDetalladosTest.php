<?php

namespace Tests\Flujos;

use App\Support\Reportes;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Reportes «Ventas Detallado» (cada comprobante con sus productos) y «Ventas por Producto Detallado» (cada venta de cada producto) */
class ReportesDetalladosTest extends FlujoTestCase
{
    private function filtros(array $extra = []): array
    {
        return ['desde' => now()->toDateString(), 'hasta' => now()->toDateString(), 'sucursal' => $this->sucursal] + $extra;
    }

    public function test_cada_comprobante_lleva_sus_productos_y_los_anulados_no_salen(): void
    {
        $juane = $this->producto('JUANE', 15, 50);
        $cecina = $this->producto('CECINA', 20, 50);
        $primera = VentaDirecta::registrar($this->usuario, $this->venta([[$juane, 2], [$cecina, 1]], '03'), 'PV');
        VentaDirecta::registrar($this->usuario, $this->venta([[$cecina, 1]], '13'), 'PV');
        $anulada = VentaDirecta::registrar($this->usuario, $this->venta([[$juane, 5]], '03'), 'PV');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $anulada)->update(['ccabaj' => now()]);

        $r = Reportes::generar('ventas-detallado', $this->filtros());

        $grupos = array_values(array_filter($r['filas'], fn ($f) => isset($f['_grupo'])));
        $this->assertCount(2, $grupos);
        $serie = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $primera)->first(['serdoc', 'numdoc']);
        $this->assertStringContainsString('BOLETA '.$serie->serdoc.'-'.$serie->numdoc, $grupos[0]['_grupo']);
        $this->assertEquals(50.0, $grupos[0]['_grupo_total']);
        // Debajo de la boleta, sus 2 productos
        $this->assertSame(['JUANE', 'CECINA'], [$r['filas'][1]['producto'], $r['filas'][2]['producto']]);
        $this->assertEquals([2.0, 15.0, 30.0], [$r['filas'][1]['cantidad'], $r['filas'][1]['precio'], $r['filas'][1]['total']]);
        // Las cabeceras no se suman dos veces
        $this->assertEquals(70.0, $r['totales']['total']);
        $this->assertSame(['Comprobantes', 2], $r['resumen'][0]);

        $this->get(route('reportes.ver', ['ventas-detallado']))->assertOk()->assertSee('JUANE')->assertSee($serie->serdoc.'-'.$serie->numdoc);
        $this->get(route('reportes.ver', ['ventas-detallado', 'formato' => 'excel']))->assertOk()->assertDownload();
    }

    public function test_por_producto_detallado_busca_un_producto(): void
    {
        $juane = $this->producto('JUANE', 15, 50);
        $cecina = $this->producto('CECINA', 20, 50);
        VentaDirecta::registrar($this->usuario, $this->venta([[$juane, 2], [$cecina, 1]], '03'), 'PV');
        VentaDirecta::registrar($this->usuario, $this->venta([[$juane, 1]], '13'), 'PV');

        $r = Reportes::generar('ventas-producto-detalle', $this->filtros(['producto' => 'jua']));

        $this->assertSame(['JUANE', 'JUANE'], array_column($r['filas'], 'producto'));
        $this->assertSame(['BOLETA', 'N. VENTA'], array_column($r['filas'], 'tipo'));
        $this->assertEquals(45.0, $r['totales']['total']);
        $this->assertCount(3, Reportes::generar('ventas-producto-detalle', $this->filtros())['filas']);
    }
}
