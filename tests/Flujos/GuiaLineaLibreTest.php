<?php

namespace Tests\Flujos;

use App\Support\Proformas;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Guía de remisión: líneas escritas a mano y la guía armada desde una proforma */
class GuiaLineaLibreTest extends FlujoTestCase
{
    public function test_la_guia_tiene_el_boton_de_linea_libre(): void
    {
        $this->get(route('guias.crear'))->assertOk()->assertSee('+ Línea libre')->assertSee('lineaLibre()', false);
    }

    private function proforma(): int
    {
        return Proformas::guardar($this->usuario, ['origen' => 'PV', 'tdicod' => '6', 'clinum' => '20100070970', 'clinom' => 'CONSTRUCTORA SAC',
            'clidir' => 'AV. LOS ROBLES 123', 'items' => [['id' => $this->producto('CEMENTO SOL', 32, 50)->IdProducto, 'cantidad' => 40, 'precio' => 32],
                ['id' => null, 'descripcion' => 'FLETE A OBRA', 'cantidad' => 1, 'precio' => 50]]]);
    }

    public function test_la_guia_se_arma_con_el_cliente_y_el_detalle_de_la_proforma(): void
    {
        $id = $this->proforma();

        $this->get(route('proformas.index'))->assertOk()->assertSee(route('guias.crear', ['proforma' => $id]), false);
        $this->get(route('guias.crear', ['proforma' => $id]))->assertOk()
            ->assertSee('de la proforma')->assertSee('CONSTRUCTORA SAC')->assertSee('20100070970')
            ->assertSee('CEMENTO SOL')->assertSee('FLETE A OBRA')->assertSee('AV. LOS ROBLES 123');
    }

    public function test_si_la_proforma_ya_se_cobro_la_guia_va_con_su_venta(): void
    {
        $id = $this->proforma();
        $venta = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('ARENA', 40, 10), 1]], '01'), 'PV');
        Proformas::marcarFacturada($id, $venta);

        $this->get(route('guias.crear', ['proforma' => $id]))->assertOk()->assertSee('de la venta')
            ->assertSee(DB::table('cpe_cabecera')->where('IdCpe_cabecera', $venta)->value('serdoc'));
    }
}
