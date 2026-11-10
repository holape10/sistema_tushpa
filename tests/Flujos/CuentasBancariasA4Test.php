<?php

namespace Tests\Flujos;

use App\Models\Empresa;
use App\Support\Impresion\ComprobantePdf;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Cuentas bancarias de la empresa: se configuran en Empresa y salen solo en el comprobante A4 */
class CuentasBancariasA4Test extends FlujoTestCase
{
    public function test_se_guardan_solo_las_cuentas_completas(): void
    {
        $empresa = Empresa::find($this->usuario->IdEmpresa);

        $this->patch(route('empresas.update', $empresa->IdEmpresa), ['nomEmpresa' => $empresa->NomEmpresa, 'dirEmpresa' => $empresa->DirEmpresa,
            'produccion' => $empresa->produccion, 'txtWsUsuario' => 'MODDATOS', 'envio' => 0, 'icbper' => 0.5, 'tip_env_fac' => '01', 'cuentas' => [
                ['banco' => 'bcp', 'moneda' => 'PEN', 'numero' => '191-1234567-0-12', 'cci' => '00219100123456701255'],
                ['banco' => 'interbank', 'moneda' => 'USD', 'numero' => '', 'cci' => '00389801234567890143'],
                ['banco' => '', 'moneda' => 'PEN', 'numero' => '999', 'cci' => ''],
            ]])->assertSessionHasNoErrors();

        $this->assertSame([
            ['banco' => 'BCP', 'moneda' => 'PEN', 'numero' => '191-1234567-0-12', 'cci' => '00219100123456701255'],
            ['banco' => 'INTERBANK', 'moneda' => 'USD', 'numero' => '', 'cci' => '00389801234567890143'],
        ], $empresa->fresh()->cuentas_bancarias);
    }

    public function test_salen_en_el_a4_y_no_en_el_ticket(): void
    {
        Empresa::where('IdEmpresa', $this->usuario->IdEmpresa)->update(['cuentas_bancarias' => json_encode([
            ['banco' => 'BCP', 'moneda' => 'PEN', 'numero' => '191-1234567-0-12', 'cci' => '00219100123456701255']])]);
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('CEMENTO', 30, 10), 1]], '01'), 'PV');

        $this->get(route('cobros.voucher', ['id' => $id, 'formato' => 'A4']))->assertOk()
            ->assertSee('CUENTAS BANCARIAS')->assertSee('191-1234567-0-12')->assertSee('00219100123456701255');
        $this->get(route('cobros.voucher', ['id' => $id, 'formato' => 'TICKET']))->assertOk()->assertDontSee('00219100123456701255');
        $this->assertStringStartsWith('%PDF', ComprobantePdf::generar($id)[1]);

        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['diseno_a4' => 'SIMPLE']);
        $this->get(route('cobros.voucher', ['id' => $id, 'formato' => 'A4']))->assertOk()->assertSee('00219100123456701255');
    }

    public function test_el_a4_simple_lleva_el_giro_y_no_repite_la_direccion_ya_escrita(): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['diseno_a4' => 'SIMPLE', 'direccion' => 'AV. ALMIRANTE GRAU 1656',
            'cabecera_a4' => 'PRINCIPAL: AV. ALMIRANTE GRAU 1656 - SUCURSAL: CAL. LOS TRIUNFADORES', 'giro_a4' => 'VENTA DE ARENA BLANCA Y AMARILLA',
            'telefono' => '917 872369 - 933 902802 - 991 409574 - 965 936588']);
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('ARENA', 500, 10), 1]], '01'), 'PV');

        $html = $this->get(route('cobros.voucher', ['id' => $id, 'formato' => 'A4']))->assertOk()
            ->assertSee('VENTA DE ARENA BLANCA Y AMARILLA')->assertSee('CELULAR: 917 872369 - 933 902802 - 991 409574 - 965 936588')
            ->assertSee('CANCELADO')->getContent();
        $this->assertSame(1, substr_count($html, 'AV. ALMIRANTE GRAU 1656'), 'La dirección ya está en las líneas extra: no se repite.');
    }

    public function test_la_leyenda_de_la_amazonia_solo_sale_en_sucursales_exoneradas(): void
    {
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('CEMENTO', 30, 10), 1]], '01'), 'PV');
        $leyenda = 'TRANSFERIDOS EN LA AMAZON';

        foreach (['MODERNO', 'SIMPLE'] as $diseno) {
            DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['tip_igv_pred' => '10', 'diseno_a4' => $diseno]);
            $this->get(route('cobros.voucher', ['id' => $id, 'formato' => 'A4']))->assertOk()->assertDontSee($leyenda);
            DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['tip_igv_pred' => '20']);
            $this->get(route('cobros.voucher', ['id' => $id, 'formato' => 'A4']))->assertOk()->assertSee($leyenda);
        }
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['tip_igv_pred' => '10']);
        $this->get(route('cobros.voucher', ['id' => $id, 'formato' => 'TICKET']))->assertOk()->assertDontSee($leyenda);
    }
}
