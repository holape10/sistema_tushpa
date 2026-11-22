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
}
