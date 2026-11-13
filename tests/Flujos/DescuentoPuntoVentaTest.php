<?php

namespace Tests\Flujos;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Descuento a la venta en el Punto de venta de comercios: hasta el máximo de la caja; más, con la clave del administrador */
class DescuentoPuntoVentaTest extends FlujoTestCase
{
    private function permitir(bool $si = true, float $maximo = 10): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['permite_descuentos' => $si, 'descuento_max_cajero' => $maximo]);
    }

    private function cajero(): User
    {
        $u = $this->usuario->replicate();
        $u->email = 'caja'.uniqid().'@prueba.test';
        $u->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $u->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        // La caja abierta de la prueba pasa a ser de este cajero (sin turno propio no puede vender)
        DB::table('turnos')->where('id_empresa_negocio', $this->sucursal)->whereNull('cierre')->update(['IdUsuario' => $u->IdUsuario]);

        return $u;
    }

    /** Venta de S/ 100 con descuento, pagada con lo que queda */
    private function conDescuento(string $tipo, float $valor, float $paga, array $extra = []): array
    {
        $datos = $this->venta([[$this->producto('TALADRO '.uniqid(), 100, 5), 1]]);

        return $extra + ['descuento_tipo' => $tipo, 'descuento_valor' => $valor, 'descuento_motivo' => 'CLIENTE FRECUENTE', 'mon_med_pag' => [$paga], 'paga' => $paga] + $datos;
    }

    public function test_la_caja_descuenta_hasta_su_maximo_y_el_comprobante_sale_con_el_descuento(): void
    {
        $this->permitir();
        $this->actingAs($this->cajero());

        $id = (int) $this->postJson(route('pv.registrar'), $this->conDescuento('P', 10, 90))->assertJson(['estado' => 'success'])->json('id');

        $this->assertEquals(90.0, (float) $this->cabecera($id)->ccaitv);
        $this->assertEquals(90.0, (float) DB::table('cpe_detalle')->where('IdCpe_cabecera', $id)->value('cdevve'), 'El descuento se reparte en la línea.');
        $this->assertTrue(DB::table('bitacora')->where('accion', 'venta.descuento')->exists());
    }

    public function test_mas_del_maximo_pide_la_clave_del_administrador(): void
    {
        $this->permitir();
        $this->actingAs($this->cajero());

        $this->postJson(route('pv.registrar'), $this->conDescuento('S', 25, 75))->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'clave de un administrador'));

        $id = (int) $this->postJson(route('pv.registrar'), $this->conDescuento('S', 25, 75, ['clave_admin' => 'clave-de-prueba']))
            ->assertJson(['estado' => 'success'])->json('id');
        $this->assertEquals(75.0, (float) $this->cabecera($id)->ccaitv);
    }

    public function test_sin_activar_no_se_descuenta_y_la_opcion_sale_para_comercios(): void
    {
        $this->permitir(false);
        $this->postJson(route('pv.registrar'), $this->conDescuento('S', 5, 95))->assertJsonPath('mensaje', 'Esta sucursal no tiene activados los descuentos.');

        $this->permitir();
        $this->get(route('pv.index'))->assertOk()->assertSee('descuentos: true', false)->assertSee('🏷️ Descuento');
        $this->get(route('sucursales.edit', $this->sucursal))->assertOk()->assertSee('Permitir descuentos a la venta');
    }

    public function test_el_maximo_de_la_caja_puede_ser_en_soles(): void
    {
        $this->permitir(true, 5);
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['descuento_max_tipo' => 'S']);
        $this->actingAs($this->cajero());

        $this->postJson(route('pv.registrar'), $this->conDescuento('S', 5, 95))->assertJson(['estado' => 'success']);
        $this->postJson(route('pv.registrar'), $this->conDescuento('S', 6, 94))
            ->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'máximo para caja es S/ 5.00'));
    }

    public function test_el_codigo_interno_sale_en_el_ticket_solo_si_la_sucursal_lo_activa(): void
    {
        $id = (int) $this->postJson(route('pv.registrar'), $this->venta([[$this->producto('CARGADOR 45W', 30, 5), 1]]))->json('id');
        $codigo = DB::table('cpe_detalle')->where('IdCpe_cabecera', $id)->value('procod');

        $this->get(route('cobros.voucher', $id))->assertOk()->assertDontSee('Cód: '.$codigo);
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['ticket_codigo' => 1]);
        $this->get(route('cobros.voucher', $id))->assertOk()->assertSee('Cód: '.$codigo);
    }
}
