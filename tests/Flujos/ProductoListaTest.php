<?php

namespace Tests\Flujos;

use Illuminate\Support\Facades\DB;

/** Lista de productos: muestra el costo y el stock (sumado de todos los almacenes de la sucursal) */
class ProductoListaTest extends FlujoTestCase
{
    public function test_muestra_el_costo_y_el_stock_de_todos_los_almacenes(): void
    {
        $arroz = $this->producto('ARROZ COSTEÑO 5 KG', 22, 10);
        DB::table('productos')->where('IdProducto', $arroz->IdProducto)->update(['costo' => 18.40]);
        $otro = DB::table('almacenes')->insertGetId(['descripcion' => 'ALMACEN 2', 'id_empresa_negocio' => $this->sucursal]);
        DB::table('producto_stock')->insert(['IdProducto' => $arroz->IdProducto, 'id_almacen' => $otro, 'id_empresa_negocio' => $this->sucursal,
            'stock' => 5.5, 'stock_inicial' => 0]);

        $this->get(route('productos.index', ['q' => 'ARROZ COSTEÑO']))->assertOk()
            ->assertSeeInOrder(['Costo', 'Precio', 'Stock'])
            ->assertSeeInOrder(['ARROZ COSTEÑO 5 KG', 'S/ 18.40', 'S/ 22.00', '15.5']);
    }

    public function test_ajustar_stock_desde_productos_es_solo_del_administrador(): void
    {
        $arroz = $this->producto('ARROZ COSTEÑO 5 KG', 22, 10);

        // El administrador ve «Ajustar» y ajusta el almacén: hay 10, real 7 → salida de 3
        $this->get(route('productos.index'))->assertOk()->assertSee('ajustar-stock', false)->assertSee('Stock real (lo que hay de verdad)');
        $this->from(route('productos.index'))->post(route('kardex.ajustar'), ['IdProducto' => $arroz->IdProducto, 'id_almacen' => $this->almacen, 'stock_real' => 7])
            ->assertRedirect(route('productos.index'));
        $this->assertEquals(7.0, $this->stock($arroz));

        // Un cajero no ve el botón ni la ventana
        $cajero = $this->usuario->replicate(['IdUsuario']);
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        $cajero->modulos()->attach(DB::table('modulos')->where('mod_url', '/productos')->value('mod_id'));
        $this->actingAs($cajero)->get(route('productos.index'))->assertOk()->assertDontSee('ajustar-stock', false)->assertDontSee('Stock real (lo que hay de verdad)');
    }
}
