<?php

namespace Tests\Flujos;

use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Ventas sin costo (importadas del sistema antiguo): la rentabilidad las muestra y el administrador las completa */
class RentabilidadCostosTest extends FlujoTestCase
{
    public function test_las_ventas_sin_costo_toman_el_costo_actual_del_producto(): void
    {
        $plato = $this->producto('CEMENTO', 30, 10);
        $plato->update(['costo' => 22]);
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 2]], '03'), 'PV');
        DB::table('cpe_detalle')->where('IdCpe_cabecera', $id)->update(['costo' => 0]); // como llegó del sistema antiguo

        $this->get(route('reportes.ver', 'rentabilidad'))->assertOk()->assertSee('1 línea(s) de venta no tienen costo');
        $this->post(route('reportes.completar_costos'))->assertSessionHas('success');

        $this->assertEquals(22.0, (float) DB::table('cpe_detalle')->where('IdCpe_cabecera', $id)->value('costo'));
        $this->get(route('reportes.ver', 'rentabilidad'))->assertOk()->assertDontSee('no tienen costo');
    }
}
