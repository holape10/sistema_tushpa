<?php

namespace Tests\Flujos;

use App\Support\Reportes;
use App\Support\VentaDirecta;

/** Ventas por Cliente: cada comprobante con su cliente y cuánto fue al contado y al crédito (o un total por cliente) */
class ReporteVentasClienteTest extends FlujoTestCase
{
    private function filtros(array $extra = []): array
    {
        return ['desde' => now()->toDateString(), 'hasta' => now()->toDateString(), 'sucursal' => $this->sucursal] + $extra;
    }

    public function test_cada_comprobante_con_contado_y_credito_del_ultimo_al_primero(): void
    {
        $pollo = $this->producto('POLLO ENTERO', 30, 50);
        $contado = VentaDirecta::registrar($this->usuario, $this->venta([[$pollo, 1]], '01'), 'PV');
        $credito = VentaDirecta::registrar($this->usuario, $this->venta([[$pollo, 2]], '01', [
            'estadopago' => $this->credito, 'fecVen' => now()->addDays(15)->toDateString(), 'id_med_pag' => [], 'mon_med_pag' => [], 'paga' => 0,
        ]), 'PV');

        $r = Reportes::generar('ventas-cliente', $this->filtros(['tipo' => '01']));

        $this->assertSame(['fecha', 'comprobante', 'serie', 'numero', 'doc', 'cliente', 'contado', 'credito', 'total'], array_keys($r['columnas']));
        $numeros = array_column($r['filas'], 'numero');
        $this->assertSame([(string) $this->cabecera($credito)->numdoc, (string) $this->cabecera($contado)->numdoc], $numeros, 'El último primero.');
        $this->assertSame('FACTURA ELECTRONICA', $r['filas'][0]['comprobante']);
        $this->assertEquals([0.0, 60.0, 60.0], [$r['filas'][0]['contado'], $r['filas'][0]['credito'], $r['filas'][0]['total']]);
        $this->assertEquals([30.0, 0.0, 30.0], [$r['filas'][1]['contado'], $r['filas'][1]['credito'], $r['filas'][1]['total']]);
        $this->assertEquals([30.0, 60.0, 90.0], [$r['totales']['contado'], $r['totales']['credito'], $r['totales']['total']]);

        $this->get(route('reportes.ver', ['ventas-cliente']))->assertOk()->assertSee('Cada comprobante (contado / crédito)');
    }

    public function test_la_vista_resumen_da_un_total_por_cliente(): void
    {
        $pollo = $this->producto('POLLO ENTERO', 30, 50);
        VentaDirecta::registrar($this->usuario, $this->venta([[$pollo, 1]], '01'), 'PV');
        VentaDirecta::registrar($this->usuario, $this->venta([[$pollo, 1]], '01'), 'PV');

        $r = Reportes::generar('ventas-cliente', $this->filtros(['tipo' => '01', 'vista' => 'resumen']));

        $this->assertCount(1, $r['filas']);
        $this->assertSame(2, $r['filas'][0]['compras']);
        $this->assertEquals(60.0, $r['filas'][0]['total']);
    }
}
