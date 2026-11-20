<?php

namespace Tests\Flujos;

use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Al cobrar una cuenta por cobrar (o pagar una por pagar), el recibo sale solo en la impresora de caja, sin ventana */
class ReciboCuentaTest extends FlujoTestCase
{
    private function cuentaPorCobrar(): int
    {
        $datos = $this->venta([[$this->producto('MENU EJECUTIVO', 15, 10), 4]], '01', ['fecVen' => now()->addDays(15)->toDateString()]);
        $datos['estadopago'] = $this->credito;
        $datos['id_med_pag'] = $datos['mon_med_pag'] = [];
        $id = VentaDirecta::registrar($this->usuario, $datos, 'PV');

        return (int) DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $id)->value('cue_cob_id');
    }

    private function cobrar(int $cuenta, float $monto)
    {
        return $this->postJson(route('cuentas.pagar', ['cobrar', $cuenta]), [
            'fecha' => now()->toDateString(), 'medios' => [['id' => $this->efectivo, 'monto' => $monto]],
        ])->assertOk();
    }

    public function test_con_impresora_de_caja_el_recibo_se_imprime_solo(): void
    {
        DB::table('configuracion_impresoras')->insert(['descripcion' => 'CAJA', 'ruta' => 'CAJA', 'IdEmpresa' => $this->usuario->IdEmpresa,
            'id_empresa_negocio' => $this->sucursal, 'tip_conex_imp' => 'COMPARTIDO', 'columnas' => 42, 'diseno' => 'CLASICO', 'activo' => 1, 'predeterminado' => 1]);
        $cuenta = $this->cuentaPorCobrar();

        $r = $this->cobrar($cuenta, 20)->assertJson(['estado' => 'ok', 'impresora' => 'CAJA']);
        $cola = DB::table('cola_impresion')->where('tipo', 'RECIBO')->first();
        $this->assertNotNull($cola, 'El recibo quedó en la cola de la impresora de caja.');
        $ticket = base64_decode($cola->contenido);
        $this->assertStringContainsString('RECIBO DE COBRO', $ticket);
        $this->assertStringContainsString('20.00', $ticket);
        $this->assertStringContainsString('40.00', $ticket, 'Y el saldo que queda.');
        $this->get($r->json('recibo'))->assertOk()->assertSee('RECIBO DE COBRO');
    }

    public function test_sin_impresora_la_pantalla_lo_imprime_sin_abrir_otra_ventana(): void
    {
        $cuenta = $this->cuentaPorCobrar();

        $this->cobrar($cuenta, 60)->assertJson(['estado' => 'ok', 'impresora' => null]);
        $this->assertSame(0, DB::table('cola_impresion')->count());
        $this->get(route('cuentas.index', 'cobrar'))->assertOk()->assertDontSee("window.open(d.recibo, '_blank')", false);
    }
}
