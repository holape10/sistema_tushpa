<?php

namespace Tests\Flujos;

use App\Support\Proformas;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Trabaja con pedidos: el vendedor toma el pedido con su número del día y la caja lo trae por ese número para cobrarlo */
class PedidosDelDiaTest extends FlujoTestCase
{
    private function pedido(int $cantidad = 2): array
    {
        $plato = $this->producto('CHAUFA '.uniqid(), 15, 50);
        $id = Proformas::guardar($this->usuario, ['origen' => 'PEDIDO', 'tdicod' => '1', 'clinum' => '00000000', 'clinom' => 'VENTA AL PORTADOR',
            'items' => [['id' => $plato->IdProducto, 'cantidad' => $cantidad, 'precio' => 15]]]);

        return [$id, DB::table('proformas')->where('id_proforma', $id)->first(), $plato];
    }

    private function trabajaConPedidos(bool $si = true): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['trabaja_pedidos' => $si]);
    }

    public function test_el_numero_empieza_en_1_cada_dia_y_no_gasta_el_correlativo_de_proformas(): void
    {
        $antes = (int) DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->value('NumProforma');

        [, $primero] = $this->pedido();
        [, $segundo] = $this->pedido();
        $this->travel(1)->days();
        [, $manana] = $this->pedido();

        $this->assertSame([1, 2, 1], [(int) $primero->pedido_dia, (int) $segundo->pedido_dia, (int) $manana->pedido_dia]);
        $this->assertSame('PEDIDO N° 2', Proformas::numero($segundo));
        $this->assertSame($antes, (int) DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->value('NumProforma'));
    }

    public function test_la_caja_trae_el_pedido_por_su_numero_y_al_cobrarlo_ya_no_vuelve(): void
    {
        $this->trabajaConPedidos();
        $this->pedido(1);
        [$id, , $plato] = $this->pedido(3);

        $r = $this->getJson(route('pv.pedido.buscar', ['numero' => 2]))->assertOk()->assertJson(['ok' => true]);
        $this->assertSame([2, $plato->IdProducto, 3.0], [$r->json('pedido.pedido'), $r->json('pedido.items.0.id'), (float) $r->json('pedido.items.0.cantidad')]);
        $this->getJson(route('pv.pedido.buscar', ['numero' => 9]))->assertJson(['ok' => false, 'mensaje' => 'No hay un pedido N° 9 de hoy en esta sucursal.']);

        // Se cobra como cualquier proforma: queda facturado y ya no se puede traer
        $venta = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 3, 15]]) + ['proforma_id' => $id], 'PV');
        $this->assertSame(['FACTURADA', $venta], [DB::table('proformas')->where('id_proforma', $id)->value('estado'), (int) DB::table('proformas')->where('id_proforma', $id)->value('IdCpe_cabecera')]);
        $this->getJson(route('pv.pedido.buscar', ['numero' => 2]))->assertJson(['ok' => false, 'mensaje' => 'El pedido N° 2 ya se cobró.']);
    }

    public function test_el_numero_de_ayer_no_se_confunde_con_el_de_hoy(): void
    {
        $this->trabajaConPedidos();
        $this->pedido();
        $this->travel(1)->days();

        $this->getJson(route('pv.pedido.buscar', ['numero' => 1]))->assertJson(['ok' => false]);
    }

    public function test_las_pantallas_salen_solo_si_la_sucursal_trabaja_con_pedidos(): void
    {
        $this->get(route('pv.pedido'))->assertRedirect();
        $this->get(route('pv.index'))->assertOk()->assertDontSee('N° DE PEDIDO');

        $this->trabajaConPedidos();
        $this->get(route('pv.pedido'))->assertOk()->assertSee('Tomar Pedido')->assertSee('GUARDAR PEDIDO')->assertDontSee('IMPRIMIR<span', false);
        $this->get(route('pv.index'))->assertOk()->assertSee('N° DE PEDIDO');
    }
}
