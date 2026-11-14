<?php

namespace Tests\Flujos;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Lo delicado del día a día del salón: pasar un pedido a otra mesa, juntar mesas (grupo grande), cuentas separadas
 * con descuento y quitar platos ya enviados (con la clave del administrador si lo pide el mozo).
 */
class MesasYCuentasTest extends FlujoTestCase
{
    private int $piso;

    /** @var array<int, int> */
    private array $mesas = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->piso = DB::table('pisos')->insertGetId(['pis_nom' => 'SALON', 'id_empresa_negocio' => $this->sucursal]);
        foreach ([1, 2, 3] as $n) {
            $this->mesas[$n] = DB::table('mesas')->insertGetId(['mes_nom' => 'MESA 0'.$n, 'pis_id' => $this->piso, 'mes_est' => 'Libre',
                'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal]);
        }
    }

    /** Abre la mesa, pide y manda la comanda; devuelve el pedido */
    private function pedir(int $mesa, array $platos): int
    {
        $this->postJson(route('comandas.set_servicio'), ['order_type' => 'salon', 'mesa_id' => $mesa, 'mesa_nombre' => 'MESA'])->assertOk();
        foreach ($platos as [$producto, $veces]) {
            for ($i = 0; $i < $veces; $i++) {
                $this->postJson(route('comandas.add_to_cart'), ['id' => $producto->IdProducto])->assertOk();
            }
        }
        $this->postJson(route('comandas.enviar'))->assertOk();

        return (int) DB::table('pedidos')->where('mes_id', $mesa)->where('ped_est', 'Aperturado')->value('ped_id');
    }

    private function cobrar(int $pedId, float $monto, array $extra = []): array
    {
        return $this->postJson(route('cobros.registrar'), $extra + [
            'ped_id' => $pedId, 'tdocod' => '03', 'estadopago' => $this->contado, 'fecEmi' => now()->toDateString(),
            'tdicod' => '1', 'clinum' => '00000000', 'clinom' => 'VENTA AL PORTADOR',
            'id_med_pag' => [$this->efectivo], 'mon_med_pag' => [$monto], 'paga' => $monto,
        ])->assertOk()->json();
    }

    private function estadoMesa(int $n): string
    {
        return (string) DB::table('mesas')->where('mes_id', $this->mesas[$n])->value('mes_est');
    }

    public function test_pasar_el_pedido_a_otra_mesa_libera_la_primera(): void
    {
        $pedId = $this->pedir($this->mesas[1], [[$this->producto('LOMO', 30, 10), 1]]);
        $this->pedir($this->mesas[3], [[$this->producto('CHICHA', 8, 10), 1]]);

        $this->postJson(route('comandas.cambiar_mesa'), ['ped_id' => $pedId, 'mes_id' => $this->mesas[2]])->assertJsonPath('success', true);
        $this->assertSame((int) $this->mesas[2], (int) DB::table('pedidos')->where('ped_id', $pedId)->value('mes_id'));
        $this->assertSame(['Libre', 'Ocupado'], [$this->estadoMesa(1), $this->estadoMesa(2)]);

        // A una mesa ocupada no se pasa: se juntan
        $this->postJson(route('comandas.cambiar_mesa'), ['ped_id' => $pedId, 'mes_id' => $this->mesas[3]])
            ->assertJsonPath('success', false)->assertJsonPath('message', fn ($m) => str_contains($m, 'Juntar mesas'));
    }

    public function test_juntar_dos_mesas_ocupadas_suma_las_cuentas_y_al_cobrar_se_liberan_las_dos(): void
    {
        $lomo = $this->producto('LOMO', 30, 10);
        $chicha = $this->producto('CHICHA', 8, 10);
        $grande = $this->pedir($this->mesas[1], [[$lomo, 2]]);
        $chica = $this->pedir($this->mesas[2], [[$chicha, 2]]);

        $this->postJson(route('comandas.unir_mesa'), ['ped_id' => $grande, 'ped_id_origen' => $chica])->assertJsonPath('success', true);
        $this->assertSame('Unido', DB::table('pedidos')->where('ped_id', $chica)->value('ped_est'));
        $this->assertEquals(76.0, (float) DB::table('pedidos')->where('ped_id', $grande)->value('ped_tot'));
        $this->assertSame($grande, (int) DB::table('mesas')->where('mes_id', $this->mesas[2])->value('unida_ped_id'));

        $r = $this->cobrar($grande, 76);
        $this->assertSame('success', $r['estado'] ?? null, $r['mensaje'] ?? '');
        $this->assertSame(['Libre', 'Libre'], [$this->estadoMesa(1), $this->estadoMesa(2)], 'Al cobrar el grupo quedan libres todas sus mesas.');
        $this->assertNull(DB::table('mesas')->where('mes_id', $this->mesas[2])->value('unida_ped_id'));
    }

    public function test_sumar_una_mesa_libre_al_grupo_y_soltarla(): void
    {
        $pedId = $this->pedir($this->mesas[1], [[$this->producto('PARRILLA', 90, 10), 1]]);

        $this->postJson(route('comandas.juntar_libres'), ['ped_id' => $pedId, 'mesas' => [$this->mesas[2], $this->mesas[3]]])->assertJsonPath('success', true);
        $this->assertSame(['Ocupado', 'Ocupado'], [$this->estadoMesa(2), $this->estadoMesa(3)]);

        $this->postJson(route('comandas.separar_mesa'), ['ped_id' => $pedId, 'mes_id' => $this->mesas[3]])->assertJsonPath('success', true);
        $this->assertSame('Libre', $this->estadoMesa(3));
        $this->assertSame('Ocupado', $this->estadoMesa(2));
        $this->postJson(route('comandas.separar_mesa'), ['ped_id' => $pedId, 'mes_id' => $this->mesas[3]])->assertJsonPath('success', false);
    }

    public function test_cuentas_separadas_con_descuento_cuadran_con_lo_pedido(): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['permite_descuentos' => 1]);
        $menu = $this->producto('MENU', 15, 10);
        $pedId = $this->pedir($this->mesas[1], [[$menu, 4]]);
        $clave = $menu->IdProducto.'||'.substr(md5(''), 0, 8).'|15.00';

        // Primera cuenta: 2 menús con 10% (S/ 27); segunda: los otros 2 sin descuento (S/ 30)
        $r = $this->cobrar($pedId, 27, ['separadas' => [$clave => 2], 'descuento_tipo' => 'P', 'descuento_valor' => 10]);
        $this->assertSame('success', $r['estado'] ?? null, $r['mensaje'] ?? '');
        $this->assertSame('Ocupado', $this->estadoMesa(1));
        $r = $this->cobrar($pedId, 30);
        $this->assertSame('success', $r['estado'] ?? null, $r['mensaje'] ?? '');

        $cuentas = DB::table('cpe_cabecera')->where('ped_id', $pedId)->orderBy('IdCpe_cabecera')->get(['ccaitv', 'descuento']);
        $this->assertSame([27.0, 30.0], $cuentas->map(fn ($c) => (float) $c->ccaitv)->all());
        $this->assertEquals(3.0, (float) $cuentas[0]->descuento);
        $bitacora = DB::table('bitacora')->where('accion', 'venta.descuento')->get();
        $this->assertCount(1, $bitacora, 'El descuento queda en la bitácora (la cuenta sin descuento no).');
        $this->assertTrue((bool) $bitacora[0]->alerta);
        $this->assertStringStartsWith('Hizo un descuento de S/ 3.00', $bitacora[0]->descripcion);
        $this->assertSame('Libre', $this->estadoMesa(1));
        $this->assertEquals(4.0, (float) DB::table('pedidos_detalle')->where('ped_id', $pedId)->sum('item_facturado'), 'Todo lo pedido quedó cobrado una sola vez.');
    }

    public function test_eliminar_todo_el_pedido_lo_anula_y_libera_la_mesa(): void
    {
        $lomo = $this->producto('LOMO', 30, 10);
        $chicha = $this->producto('CHICHA', 8, 10);
        $mozo = $this->usuarioConRol(8, 'MOZO DE PRUEBA');
        DB::table('users')->where('IdUsuario', $this->usuario->IdUsuario)->update(['password' => Hash::make('clave-admin')]);
        $this->actingAs($mozo);
        $pedId = $this->pedir($this->mesas[1], [[$lomo, 2], [$chicha, 1]]);
        $this->postJson(route('comandas.set_servicio'), ['order_type' => 'salon', 'mesa_id' => $this->mesas[1], 'mesa_nombre' => 'MESA', 'pedido_id' => $pedId])->assertOk();
        $this->get(route('comandas.menu'))->assertOk()->assertSee('Eliminar todo el pedido');
        $this->postJson(route('comandas.add_to_cart'), ['id' => $chicha->IdProducto])->assertOk();

        // Sin la clave del administrador no se puede
        $this->postJson(route('comandas.eliminar_todo'), ['reason' => 'PRUEBA', 'auth_user' => $this->usuario->email, 'auth_password' => 'otra'])
            ->assertJsonPath('success', false);
        $this->postJson(route('comandas.eliminar_todo'), ['reason' => 'PRUEBA', 'auth_user' => $this->usuario->email, 'auth_password' => 'clave-admin'])
            ->assertJsonPath('success', true)->assertJsonPath('total', 0);
        $this->postJson(route('comandas.enviar'))->assertOk()->assertJsonPath('success', true);

        $this->assertSame('Anulado', DB::table('pedidos')->where('ped_id', $pedId)->value('ped_est'));
        $this->assertSame(0, DB::table('pedidos_detalle')->where('ped_id', $pedId)->where('estadoitem', '!=', 'Eliminado')->count(), 'Todos los platos quedaron eliminados.');
        $this->assertStringContainsString('PRUEBA', (string) DB::table('pedidos_detalle')->where('ped_id', $pedId)->value('item_obs'));
        $this->assertSame('Libre', DB::table('mesas')->where('mes_id', $this->mesas[1])->value('mes_est'));
    }

    public function test_el_mozo_quita_un_plato_enviado_solo_con_la_clave_del_administrador(): void
    {
        $lomo = $this->producto('LOMO', 30, 10);
        $mozo = $this->usuarioConRol(8, 'MOZO DE PRUEBA');
        DB::table('users')->where('IdUsuario', $this->usuario->IdUsuario)->update(['password' => Hash::make('clave-admin')]);
        $this->actingAs($mozo);
        $pedId = $this->pedir($this->mesas[1], [[$lomo, 3]]);

        // Vuelve a la mesa: el carrito trae lo ya enviado
        $this->postJson(route('comandas.set_servicio'), ['order_type' => 'salon', 'mesa_id' => $this->mesas[1], 'mesa_nombre' => 'MESA', 'pedido_id' => $pedId])->assertOk();
        $this->get(route('comandas.menu'))->assertOk();
        $id = (string) array_key_first(session('comanda_cart'));

        $this->postJson(route('comandas.reducir_autorizado'), ['id' => $id, 'cantidad' => 2, 'reason' => 'SE EQUIVOCÓ'])->assertStatus(422);
        $this->postJson(route('comandas.reducir_autorizado'), ['id' => $id, 'cantidad' => 2, 'reason' => 'SE EQUIVOCÓ',
            'auth_user' => $this->usuario->email, 'auth_password' => 'no-es-la-clave'])->assertJsonPath('success', false);
        $this->postJson(route('comandas.reducir_autorizado'), ['id' => $id, 'cantidad' => 2, 'reason' => 'SE EQUIVOCÓ',
            'auth_user' => $this->usuario->email, 'auth_password' => 'clave-admin'])->assertJsonPath('success', true);
        $this->postJson(route('comandas.enviar'))->assertOk();

        $this->assertEquals(2.0, (float) DB::table('pedidos_detalle')->where('ped_id', $pedId)->where('estadoitem', '!=', 'Eliminado')->sum('ped_det_can'));
        $this->assertEquals(60.0, (float) DB::table('pedidos')->where('ped_id', $pedId)->value('ped_tot'));
        $quitado = DB::table('bitacora')->where('accion', 'comanda.quitar')->first();
        $this->assertSame((int) $mozo->IdUsuario, (int) $quitado->IdUsuario, 'Queda a nombre del mozo que lo quitó.');
        $this->assertStringContainsString('1 × LOMO', $quitado->descripcion);
        $this->assertSame('SE EQUIVOCÓ - Autorizado por: '.$this->usuario->email, json_decode($quitado->datos, true)['motivo'], 'Con el motivo y quién lo autorizó.');
    }

    /** Un usuario de la sucursal con ese rol (8 mozo, 4 caja, 13 promotor) */
    private function usuarioConRol(int $rol, string $nombre): User
    {
        $u = $this->usuario->replicate();
        $u->email = 'rol'.$rol.'-'.$this->usuario->IdUsuario.'@prueba.test';
        $u->apeusu = $nombre;
        $u->save();
        DB::table('role_user')->insert(['user_IdUsuario' => $u->IdUsuario, 'role_id' => $rol]);

        return $u;
    }
}
