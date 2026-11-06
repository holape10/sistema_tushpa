<?php

namespace Tests\Flujos;

use App\Support\AnulacionVenta;
use App\Support\FlujoCaja;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Flujo de caja proyectado (hoy es viernes 30/10/2026: la semana 1 va del lunes 26/10 al domingo 01/11) */
class FlujoCajaTest extends FlujoTestCase
{
    /** Venta al crédito que vence en esa fecha */
    private function ventaAlCredito(float $precio, string $vence, string $emision = '2026-10-30'): int
    {
        $plato = $this->producto('SERVICIO '.$precio, $precio);

        return VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]], '01', ['estadopago' => $this->credito, 'fecEmi' => $emision, 'fecVen' => $vence,
            'id_med_pag' => [], 'mon_med_pag' => [], 'paga' => 0]), 'PV');
    }

    private function compraAlCredito(float $costo, string $vence): void
    {
        $insumo = $this->producto('INSUMO '.$costo, 1);
        $this->postJson(route('compras.store'), ['tdocod' => '01', 'serie' => 'F001', 'numero' => (string) random_int(1, 99999), 'fecEmi' => '2026-10-30',
            'fecIng' => '2026-10-30', 'fecVen' => $vence, 'estadopago' => $this->credito, 'moneda' => 'PEN', 'id_almacen' => $this->almacen,
            'prov_tdicod' => '6', 'prov_num' => '20100070970', 'prov_nom' => 'PROVEEDOR DE PRUEBA SAC',
            'items' => [['id' => $insumo->IdProducto, 'tip_igv' => '10', 'cantidad' => 1, 'costo' => $costo]]])->assertJson(['estado' => 'success']);
    }

    public function test_cada_cobro_y_pago_cae_en_la_semana_de_su_vencimiento(): void
    {
        $this->ventaAlCredito(500, '2026-11-05');
        $this->compraAlCredito(200, '2026-11-12');

        $semanas = FlujoCaja::proyeccion($this->sucursal, 4, false)['semanas'];

        $this->assertSame(['2026-11-02', '2026-11-08'], [$semanas[1]['desde'], $semanas[1]['hasta']]);
        $this->assertSame(500.0, $semanas[1]['entradas']['Por cobrar']);
        $this->assertSame(200.0, $semanas[2]['salidas']['Por pagar a proveedores']);
        $this->assertSame([0.0, 0.0], [$semanas[0]['entra'], $semanas[3]['sale']]);
    }

    public function test_el_saldo_se_acumula_y_avisa_la_semana_que_no_alcanza(): void
    {
        $this->ventaAlCredito(100, '2026-11-05');
        $this->compraAlCredito(300, '2026-11-12');
        $this->post(route('flujo_caja.saldo'), ['saldo' => 150])->assertSessionHas('success');

        $flujo = FlujoCaja::proyeccion($this->sucursal, 4, false);

        $this->assertSame([150.0, 250.0, -50.0, -50.0], array_column($flujo['semanas'], 'saldo'));
        $this->assertSame(2, $flujo['negativa'], 'La tercera semana se queda sin dinero.');
    }

    public function test_lo_vencido_va_aparte_y_lo_anulado_no_cuenta(): void
    {
        $this->ventaAlCredito(80, '2026-10-20', '2026-10-10');
        $anulada = $this->ventaAlCredito(999, '2026-11-05');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $anulada)->update(['tdocod' => '13']);
        AnulacionVenta::anular($anulada, 'ERROR DEL CAJERO', $this->usuario->IdUsuario);

        $flujo = FlujoCaja::proyeccion($this->sucursal, 4, false);

        $this->assertSame(80.0, $flujo['atrasado']['cobrar']);
        $this->assertSame(0.0, array_sum(array_column($flujo['semanas'], 'entra')));
    }

    public function test_el_estimado_es_el_promedio_semanal_de_las_ventas_al_contado(): void
    {
        $plato = $this->producto('MENU', 100, 50);
        foreach (['2026-10-06', '2026-10-13', '2026-10-20', '2026-10-27'] as $fecha) {
            $id = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]], '13'), 'PV');
            DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->update(['ccafem' => $fecha]);
        }

        $semanas = FlujoCaja::proyeccion($this->sucursal, 2, true)['semanas'];

        $this->assertSame(100.0, $semanas[1]['entradas']['Ventas al contado (estimado)'], '4 ventas de S/ 100 en 4 semanas = S/ 100 por semana.');
        $this->assertSame(42.86, $semanas[0]['entradas']['Ventas al contado (estimado)'], 'A la semana en curso le quedan 3 de sus 7 días.');
    }

    public function test_la_pantalla_y_el_excel_solo_para_quien_tiene_la_opcion(): void
    {
        $this->ventaAlCredito(500, '2026-11-05');
        $this->get(route('flujo_caja.index'))->assertOk()->assertSee('Flujo de caja proyectado')->assertSee('S/ 500.00');
        $this->get(route('flujo_caja.exportar'))->assertOk()->assertDownload();

        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        $this->actingAs($cajero)->get(route('flujo_caja.index'))->assertForbidden();
        $this->actingAs($cajero)->post(route('flujo_caja.saldo'), ['saldo' => 1])->assertForbidden();
    }
}
