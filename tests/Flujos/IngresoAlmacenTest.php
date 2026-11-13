<?php

namespace Tests\Flujos;

use Illuminate\Support\Facades\DB;

/** Ingreso de productos al almacén: se busca como en el Punto de venta (nombre, código o código de barras) y se registra */
class IngresoAlmacenTest extends FlujoTestCase
{
    public function test_la_pantalla_trae_los_productos_con_su_codigo_de_barras_para_buscarlos(): void
    {
        $p = $this->producto('ARROZ COSTEÑO 5KG', 20);
        DB::table('productos')->where('IdProducto', $p->IdProducto)->update(['codigo_barra' => '7751234567890', 'costo' => 15.5]);

        $html = $this->get(route('kardex.movimiento', ['tipo' => 'I']))->assertOk()
            ->assertSee('↑ ↓ para elegir, Enter agrega')->assertDontSee('id="btn_agregar"', false)->getContent();

        $this->assertStringContainsString('"barra":"7751234567890"', $html);
        $this->assertStringContainsString('"nom":"ARROZ COSTE', $html);
    }

    public function test_el_ingreso_se_registra_y_sube_el_stock(): void
    {
        $p = $this->producto('ARROZ COSTEÑO 5KG', 20, 3);

        $this->post(route('kardex.guardar_movimiento'), ['mov_tip' => 'I', 'cod_tip_ope' => '02', 'id_almacen' => $this->almacen, 'fecha' => now()->toDateString(),
            'items' => [['IdProducto' => $p->IdProducto, 'cantidad' => 10, 'costo' => 15.5]]])->assertSessionHasNoErrors();

        $this->assertEquals(13.0, $this->stock($p));
    }

    private function ajustar(int $producto, float $real)
    {
        return $this->post(route('kardex.ajustar'), ['IdProducto' => $producto, 'id_almacen' => $this->almacen, 'stock_real' => $real, 'observacion' => 'CONTEO']);
    }

    private function ultimoMovimiento(int $producto): object
    {
        return DB::table('movimientos_productos')->where('IdProducto', $producto)->orderByDesc('mov_pro_id')->first();
    }

    public function test_ajustar_stock_a_menos_hace_la_salida_de_la_diferencia(): void
    {
        $p = $this->producto('ACEITE PRIMOR 1L', 9, 10);

        $this->ajustar($p->IdProducto, 6)->assertSessionHas('success');

        $this->assertEquals(6.0, $this->stock($p));
        $m = $this->ultimoMovimiento($p->IdProducto);
        $this->assertSame(['E', '28', 4.0], [$m->mov_tip, $m->cod_tip_ope, (float) $m->cantidad], 'Salida de 4 por ajuste de inventario en el kardex.');
    }

    public function test_ajustar_stock_a_mas_hace_el_ingreso_de_la_diferencia(): void
    {
        $p = $this->producto('ACEITE PRIMOR 1L', 9, 10);

        $this->ajustar($p->IdProducto, 12)->assertSessionHas('success');

        $this->assertEquals(12.0, $this->stock($p));
        $m = $this->ultimoMovimiento($p->IdProducto);
        $this->assertSame(['I', '28', 2.0], [$m->mov_tip, $m->cod_tip_ope, (float) $m->cantidad]);
    }

    public function test_si_el_stock_real_es_igual_no_se_mueve_nada(): void
    {
        $p = $this->producto('ACEITE PRIMOR 1L', 9, 10);
        $antes = DB::table('movimientos_productos')->where('IdProducto', $p->IdProducto)->count();

        $this->ajustar($p->IdProducto, 10)->assertSessionHas('success', fn ($m) => str_contains($m, 'no hizo falta'));

        $this->assertSame($antes, DB::table('movimientos_productos')->where('IdProducto', $p->IdProducto)->count());
        $this->get(route('kardex.stock'))->assertOk()->assertSee('AJUSTAR STOCK');
    }
}
