<?php

namespace Tests\Flujos;

use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Cuentas por cobrar del sistema antiguo ("pendiente", "cancelado"): quedan con el estado del sistema y se pueden cobrar */
class CuentasImportadasTest extends FlujoTestCase
{
    private function alCredito(float $precio): int
    {
        return VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('SERVICIO '.$precio, $precio), 1]], '01', ['estadopago' => $this->credito,
            'fecVen' => now()->addDays(30)->toDateString(), 'id_med_pag' => [], 'mon_med_pag' => [], 'paga' => 0]), 'PV');
    }

    public function test_los_estados_del_sistema_antiguo_se_normalizan_y_sale_el_boton_cobrar(): void
    {
        $pendiente = $this->alCredito(1825.83);
        $pagada = $this->alCredito(280);
        DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $pendiente)->update(['estado_cob' => 'pendiente']);
        DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $pagada)->update(['estado_cob' => 'cancelado', 'abono' => 280, 'saldo' => 0]);
        $this->get(route('cuentas.index', 'cobrar'))->assertOk()->assertDontSee('>Cobrar</button>', false);

        (require database_path('migrations/2027_02_21_100000_cuentas_estado_normalizado.php'))->up();

        $this->assertSame(['PENDIENTE', 'PAGADO'], [
            DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $pendiente)->value('estado_cob'),
            DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $pagada)->value('estado_cob'),
        ]);
        $this->get(route('cuentas.index', 'cobrar'))->assertOk()->assertSee('>Cobrar</button>', false);
    }
}
