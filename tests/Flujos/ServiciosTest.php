<?php

namespace Tests\Flujos;

use App\Models\Producto;
use App\Support\CatalogoVenta;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Servicios (mano de obra, transporte…): se crean, se venden y se facturan como un producto, pero sin stock ni kardex */
class ServiciosTest extends FlujoTestCase
{
    public function test_un_servicio_se_vende_sin_mover_stock(): void
    {
        $cat = DB::table('categorias')->where('id_empresa_negocio', $this->sucursal)->value('cat_id');
        $this->post(route('productos.store'), ['promocion' => Producto::SERVICIO, 'pronom' => 'SERVICIO DE LIJA DE CHASIS', 'umecod' => 'ZZ',
            'propun' => 250, 'cat_id' => $cat])->assertSessionHasNoErrors();
        $servicio = Producto::where('pronom', 'SERVICIO DE LIJA DE CHASIS')->firstOrFail();
        $this->assertSame('Servicio', $servicio->tipo_nombre);

        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$servicio, 3]], '01'), 'PV');

        $this->assertEquals(750.0, (float) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->value('ccaitv'));
        $this->assertSame(0, DB::table('movimientos_productos')->where('IdProducto', $servicio->IdProducto)->count(), 'Sin kardex.');
        $this->assertFalse(DB::table('producto_stock')->where('IdProducto', $servicio->IdProducto)->where('stock', '<', 0)->exists(), 'Sin stock negativo.');
        $this->assertNull(collect(CatalogoVenta::porIds($this->usuario, [$servicio->IdProducto]))->first()['stock'], 'La caja no le muestra stock.');
    }
}
