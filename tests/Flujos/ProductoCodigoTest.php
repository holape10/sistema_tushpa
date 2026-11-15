<?php

namespace Tests\Flujos;

use Illuminate\Support\Facades\DB;

/** El código interno de un producto no se repite en la sucursal; el nombre sí puede repetirse */
class ProductoCodigoTest extends FlujoTestCase
{
    private function crear(string $nombre, string $codigo, array $extra = [])
    {
        return $this->post(route('productos.store'), $extra + ['promocion' => 0, 'pronom' => $nombre, 'procod' => $codigo, 'umecod' => 'NIU', 'propun' => 10]);
    }

    public function test_no_deja_crear_un_producto_con_un_codigo_que_ya_existe(): void
    {
        $this->crear('GASEOSA 500 ML', 'GAS-500')->assertSessionHasNoErrors();

        // El mismo código (aunque cambien mayúsculas o espacios) no pasa
        $this->crear('GASEOSA 1 LITRO', ' gas-500 ')->assertSessionHasErrors(['procod' => 'Ya existe un producto con el código gas-500: GASEOSA 500 ML. Usa otro código.']);
        $this->assertSame(1, DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('pronom', 'like', 'GASEOSA%')->count());

        // El nombre sí puede repetirse con otro código; y vacío, el código se genera solo
        $this->crear('GASEOSA 500 ML', 'GAS-501')->assertSessionHasNoErrors();
        $this->crear('GASEOSA 500 ML', '')->assertSessionHasNoErrors();
        $this->assertSame(3, DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('pronom', 'GASEOSA 500 ML')->count());

        // Tampoco el código de barras de otro producto (las cajas buscan por los dos)
        $this->crear('AGUA', 'AGUA-1', ['codigo_barra' => '7750001112223'])->assertSessionHasNoErrors();
        $this->crear('AGUA CON GAS', '7750001112223')->assertSessionHasErrors('procod');
    }

    public function test_los_repetidos_que_ya_existian_se_siguen_editando(): void
    {
        $a = $this->producto('ARROZ', 4);
        $b = $this->producto('ARROZ EXTRA', 5);
        DB::table('productos')->whereIn('IdProducto', [$a->IdProducto, $b->IdProducto])->update(['procod' => 'ARR-1']);

        $this->put(route('productos.update', $b->IdProducto), ['pronom' => 'ARROZ EXTRA', 'propun' => 6])->assertSessionHasNoErrors();
        $this->assertEquals(6, DB::table('productos')->where('IdProducto', $b->IdProducto)->value('propun'));
    }
}
