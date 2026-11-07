<?php

namespace Tests\Flujos;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Restobar: el "para llevar" o delivery que arma caja se cobra de una vez; el del mozo espera en la lista */
class ParaLlevarCajaTest extends FlujoTestCase
{
    private function conRol(int $rol): User
    {
        $u = $this->usuario->replicate();
        $u->email = 'rol'.$rol.uniqid().'@prueba.test';
        $u->save();
        DB::table('role_user')->insert(['role_id' => $rol, 'user_IdUsuario' => $u->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);

        return $u;
    }

    /** @return array{0: int, 1: ?string} el pedido y a dónde lo manda la pantalla después de enviarlo */
    private function pedir(User $usuario, string $tipo): array
    {
        $plato = $this->producto('CEVICHE '.uniqid(), 30);
        $this->actingAs($usuario)->postJson(route('comandas.set_servicio'), ['order_type' => $tipo])->assertOk();
        $this->postJson(route('comandas.add_to_cart'), ['id' => $plato->IdProducto])->assertOk();
        $r = $this->postJson(route('comandas.enviar'))->assertOk()->assertJson(['success' => true]);

        return [(int) $r->json('pedido_id'), $r->json('cobrar')];
    }

    /** @return list<int> */
    private function enLista(User $usuario): array
    {
        return array_column($this->actingAs($usuario)->getJson(route('comandas.activos_llevar_delivery'))->assertOk()->json('pedidos'), 'ped_id');
    }

    public function test_el_para_llevar_de_caja_va_directo_a_cobrar_y_no_espera_en_la_lista(): void
    {
        $cajero = $this->conRol(4);
        [$llevar, $irA] = $this->pedir($cajero, 'llevar');
        [$delivery, $irADelivery] = $this->pedir($cajero, 'delivery');

        $this->assertSame([route('cobros.cobrar', $llevar), route('cobros.cobrar', $delivery)], [$irA, $irADelivery]);
        $this->assertSame([], $this->enLista($cajero));
    }

    public function test_el_para_llevar_del_mozo_si_queda_en_la_lista_para_que_caja_lo_cobre(): void
    {
        $mozo = $this->conRol(8);
        [$pedido, $irA] = $this->pedir($mozo, 'llevar');

        $this->assertNull($irA, 'El mozo vuelve a sus mesas.');
        $this->assertSame([$pedido], $this->enLista($this->conRol(4)));
    }

    public function test_si_caja_no_lo_cobro_aparece_en_la_lista_para_que_no_se_pierda(): void
    {
        $cajero = $this->conRol(4);
        [$pedido] = $this->pedir($cajero, 'llevar');

        $this->travel(11)->minutes();
        $this->assertSame([$pedido], $this->enLista($cajero));
    }

    public function test_al_editar_un_pedido_de_la_lista_caja_vuelve_a_la_lista(): void
    {
        [$pedido] = $this->pedir($this->conRol(8), 'llevar');
        $cajero = $this->conRol(4);
        $this->actingAs($cajero)->postJson(route('comandas.set_servicio'), ['order_type' => 'llevar', 'pedido_id' => $pedido])->assertOk();
        $this->postJson(route('comandas.add_to_cart'), ['id' => $this->producto('CHICHA', 5)->IdProducto])->assertOk();

        $this->postJson(route('comandas.enviar'))->assertOk()->assertJson(['success' => true, 'cobrar' => null]);
    }
}
