<?php

namespace Tests\Flujos;

use App\Support\CatalogoVenta;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Botones de más vendidos del Punto Venta: los productos que más se mueven en la sucursal, de más a menos */
class MasVendidosTest extends FlujoTestCase
{
    public function test_salen_los_que_mas_se_venden_de_mas_a_menos_sin_los_anulados_ni_desactivados(): void
    {
        $pan = $this->producto('PAN FRANCES', 0.3, 500);
        $gaseosa = $this->producto('INCA KOLA 500ML', 2.5, 50);
        $bolsa = $this->producto('BOLSA', 0.1, 500);
        $agua = $this->producto('AGUA 625ML', 1.5, 50);
        foreach ([[$pan, $gaseosa], [$pan, $bolsa], [$pan, $gaseosa], [$agua]] as $items) {
            VentaDirecta::registrar($this->usuario, $this->venta(array_map(fn ($p) => [$p, 1], $items), '13'), 'PV');
        }
        // Una venta anulada no cuenta; un producto desactivado no sale
        $anulada = VentaDirecta::registrar($this->usuario, $this->venta([[$agua, 1]], '13'), 'PV');
        VentaDirecta::registrar($this->usuario, $this->venta([[$agua, 1]], '13'), 'PV');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $anulada)->update(['ccabaj' => 'ANULADA']);
        DB::table('productos')->where('IdProducto', $bolsa->IdProducto)->update(['proest' => 'Inactivo']);
        Cache::flush();

        $ids = array_column(CatalogoVenta::masVendidos($this->usuario), 'id');

        // Pan en 3 ventas; gaseosa y agua en 2 (la anulada no cuenta)
        $this->assertSame([(int) $pan->IdProducto], array_slice($ids, 0, 1));
        $this->assertEqualsCanonicalizing([(int) $gaseosa->IdProducto, (int) $agua->IdProducto], array_slice($ids, 1, 2));
        $this->assertNotContains((int) $bolsa->IdProducto, $ids);

        $this->get(route('pv.index'))->assertOk()->assertSee('MÁS VENDIDOS')->assertSee('PAN FRANCES');
    }
}
