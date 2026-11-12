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

    public function test_el_administrador_da_por_cobradas_las_del_sistema_antiguo_hasta_una_fecha(): void
    {
        $vieja = $this->alCredito(500);
        $nueva = $this->alCredito(300);
        DB::table('cpe_cabecera')->whereIn('IdCpe_cabecera', [$vieja, $nueva])->update(['ped_tip' => 'ANTIGUO']);
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $vieja)->update(['ccafem' => '2025-03-10']);
        $cajas = DB::table('movimientoscaja')->count();

        $this->get(route('cuentas.index', 'cobrar'))->assertOk()->assertSee('2 cuenta(s) del sistema antiguo siguen pendientes');
        $this->post(route('cuentas.saldar_importadas', 'cobrar'), ['hasta' => '2025-12-31'])->assertSessionHas('success');

        $cuenta = DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $vieja)->first();
        $this->assertSame(['PAGADO', 0.0, 500.0], [$cuenta->estado_cob, (float) $cuenta->saldo, (float) $cuenta->abono]);
        $this->assertSame(1, DB::table('cuentas_cobrar_detalle')->where('cue_cob_id', $cuenta->cue_cob_id)->count(), 'Queda el cobro en su historial.');
        $this->assertSame('PENDIENTE', DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $nueva)->value('estado_cob'), 'La de después de la fecha sigue pendiente.');
        $this->assertSame($cajas, DB::table('movimientoscaja')->count(), 'No toca la caja.');
    }
}
