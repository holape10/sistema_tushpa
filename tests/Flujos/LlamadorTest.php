<?php

namespace Tests\Flujos;

use Illuminate\Support\Facades\DB;

/** Llamador: el pedido para llevar sale en "Preparando", pasa a "¡Listo!" cuando cocina lo despacha y sale al entregarlo */
class LlamadorTest extends FlujoTestCase
{
    private function pedir(string $tipo, array $extra = []): int
    {
        $plato = $this->producto('CEVICHE', 30);
        $this->postJson(route('comandas.set_servicio'), ['order_type' => $tipo] + $extra)->assertOk();
        $this->postJson(route('comandas.add_to_cart'), ['id' => $plato->IdProducto])->assertOk();
        $this->postJson(route('comandas.enviar'))->assertOk();

        return (int) DB::table('pedidos')->where('id_empresa_negocio', $this->sucursal)->orderByDesc('ped_id')->value('ped_id');
    }

    public function test_el_pedido_para_llevar_pasa_de_preparando_a_listo_y_sale_al_entregarlo(): void
    {
        $ped = $this->pedir('llevar');
        $datos = fn () => $this->getJson(route('llamador.datos'))->assertOk()->json();

        $this->assertSame([(string) $ped], array_column($datos()['preparando'], 'numero'));
        $this->assertSame([], $datos()['listos']);

        $ticket = (int) DB::table('cocina_tickets')->where('ped_id', $ped)->value('id');
        $this->postJson('/cocina/ticket/'.$ticket.'/listo')->assertOk();
        $this->assertSame([], $datos()['preparando']);
        $this->assertSame([(string) $ped], array_column($datos()['listos'], 'numero'));

        $this->postJson('/cocina/entregado/'.$ped)->assertOk();
        $this->assertSame([], $datos()['listos']);
        $this->get(route('llamador.index'))->assertOk()->assertSee('Acércate a recoger');
    }

    public function test_los_pedidos_de_mesa_no_salen_en_el_llamador(): void
    {
        $piso = DB::table('pisos')->insertGetId(['pis_nom' => 'SALON', 'id_empresa_negocio' => $this->sucursal]);
        $mesa = DB::table('mesas')->insertGetId(['mes_nom' => 'MESA 01', 'pis_id' => $piso, 'mes_est' => 'Libre',
            'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal]);
        $this->pedir('salon', ['mesa_id' => $mesa, 'mesa_nombre' => 'MESA 01']);

        $datos = $this->getJson(route('llamador.datos'))->assertOk()->json();
        $this->assertSame([], $datos['preparando']);
        $this->assertSame([], $datos['listos']);
    }
}
