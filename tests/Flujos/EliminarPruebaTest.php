<?php

namespace Tests\Flujos;

use App\Support\EliminarPrueba;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Pruebas de impresión: se borran desde el panel de soporte y el correlativo regresa */
class EliminarPruebaTest extends FlujoTestCase
{
    private function stockDe(int $idProducto): float
    {
        return (float) DB::table('producto_stock')->where('IdProducto', $idProducto)->where('id_almacen', $this->almacen)->value('stock');
    }

    public function test_la_ultima_prueba_se_borra_del_todo_y_el_correlativo_regresa(): void
    {
        $plato = $this->producto('JUANE', 15, 10);
        $primera = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]], '03'), 'PV');
        $prueba = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 2]], '03'), 'PV');
        $numero = (int) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $prueba)->value('numdoc');
        $this->assertEquals(7.0, $this->stockDe($plato->IdProducto));

        $this->assertNotNull(EliminarPrueba::impedimento(DB::table('cpe_cabecera')->where('IdCpe_cabecera', $primera)->first()), 'Solo el último de la serie.');
        EliminarPrueba::ejecutar($prueba, $this->usuario->IdUsuario);

        $this->assertFalse(DB::table('cpe_cabecera')->where('IdCpe_cabecera', $prueba)->exists());
        foreach (['cpe_detalle', 'venta_medio_pago', 'movimientos_productos'] as $tabla) {
            $this->assertSame(0, DB::table($tabla)->where('IdCpe_cabecera', $prueba)->count(), "Sin rastro en {$tabla}.");
        }
        $this->assertEquals(9.0, $this->stockDe($plato->IdProducto), 'El stock vuelve.');
        $this->assertSame($numero - 1, (int) DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->value('BnuEmpresa'));

        $otra = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]], '03'), 'PV');
        $this->assertSame($numero, (int) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $otra)->value('numdoc'), 'El número se vuelve a usar.');
    }

    public function test_lo_que_sunat_ya_tiene_no_se_borra(): void
    {
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('JUANE', 15, 10), 1]], '03'), 'PV');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->update(['est_sunat' => 'ACEPTADO', 'enviado' => 1]);

        $this->expectException(\RuntimeException::class);
        EliminarPrueba::ejecutar($id, $this->usuario->IdUsuario);
    }
}
