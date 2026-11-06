<?php

namespace Tests\Flujos;

use App\Models\Turno;
use App\Models\User;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Permisos especiales: el administrador le apaga a cada persona lo delicado; lo no apagado sigue permitido */
class AccionesTest extends FlujoTestCase
{
    /** Un cajero con su turno abierto y, si se pide, acciones apagadas */
    private function cajero(array $bloqueadas = []): User
    {
        $u = $this->usuario->replicate();
        $u->email = 'cajero'.uniqid().'@prueba.test';
        $u->apeusu = 'CAJERO DE PRUEBA';
        $u->acciones_bloqueadas = $bloqueadas ? json_encode($bloqueadas) : null;
        $u->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $u->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        Turno::create(['turno' => 2, 'IdUsuario' => $u->IdUsuario, 'apertura' => now(), 'IdEmpresa' => $u->IdEmpresa,
            'id_empresa_negocio' => $this->sucursal, 'estado' => 'ABIERTO', 'monto' => 0]);

        return $u;
    }

    public function test_sin_el_permiso_el_cajero_no_anula_ventas(): void
    {
        $cajero = $this->cajero(['anular_ventas']);
        $plato = $this->producto('JUANE', 15, 10);
        $id = VentaDirecta::registrar($cajero, $this->venta([[$plato, 1]], '13'), 'PV');

        $this->actingAs($cajero)->postJson(route('ventas.anular', $id), ['motivo' => 'ERROR DEL CAJERO'])
            ->assertJson(['success' => false, 'message' => 'No tienes permiso para anular ventas. Pídeselo al administrador.']);

        $this->assertNull($this->cabecera($id)->ccabaj);
    }

    public function test_lo_que_no_se_apago_sigue_permitido(): void
    {
        $cajero = $this->cajero(['cambiar_precio']);
        $plato = $this->producto('JUANE', 15, 10);
        $id = VentaDirecta::registrar($cajero, $this->venta([[$plato, 1]], '13'), 'PV');

        $this->actingAs($cajero)->postJson(route('ventas.anular', $id), ['motivo' => 'ERROR DEL CAJERO'])->assertJson(['success' => true]);
    }

    public function test_sin_el_permiso_no_se_cobra_a_otro_precio_pero_si_al_de_lista(): void
    {
        $cajero = $this->cajero(['cambiar_precio']);
        $plato = $this->producto('JUANE', 15, 10);

        try {
            VentaDirecta::registrar($cajero, $this->venta([[$plato, 1, 10]], '13'), 'PV');
            $this->fail('Vendió a otro precio sin permiso.');
        } catch (\RuntimeException $e) {
            $this->assertSame('No tienes permiso para cobrar a otro precio. Pídeselo al administrador. JUANE se vende a S/ 15.00.', $e->getMessage());
        }
        $this->assertSame(0, DB::table('cpe_cabecera')->where('IdUsuario', $cajero->IdUsuario)->count());

        $this->assertGreaterThan(0, VentaDirecta::registrar($cajero, $this->venta([[$plato, 1]], '13'), 'PV'));
    }

    public function test_el_administrador_siempre_puede_aunque_tenga_algo_apagado(): void
    {
        DB::table('users')->where('IdUsuario', $this->usuario->IdUsuario)->update(['acciones_bloqueadas' => json_encode(['cambiar_precio'])]);
        $plato = $this->producto('JUANE', 15, 10);

        $this->assertGreaterThan(0, VentaDirecta::registrar($this->usuario->fresh(), $this->venta([[$plato, 1, 10]], '13'), 'PV'));
    }

    public function test_sin_el_permiso_no_anula_compras_ni_movimientos_de_caja(): void
    {
        $cajero = $this->cajero(['compras_editar', 'caja_anular']);
        $compra = DB::table('compras_cabecera')->insertGetId(['IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal, 'tdocod' => '01',
            'com_doc_ser' => 'F001', 'com_doc_num' => '9', 'prov_num' => '20100070970', 'com_fec' => now()->toDateString(), 'com_fec_ing' => now()->toDateString(),
            'mon_id' => 'PEN', 'total_com' => 50, 'id_almacen' => $this->almacen, 'est_compra' => 'Registrado', 'IdUsuario' => $this->usuario->IdUsuario]);

        $this->actingAs($cajero)->post(route('compras.anular', $compra))->assertSessionHas('error', 'No tienes permiso para editar y anular compras. Pídeselo al administrador.');
        $this->assertSame('Registrado', DB::table('compras_cabecera')->where('com_cab_id', $compra)->value('est_compra'));

        $this->actingAs($cajero)->post(route('turnos.anular_movimiento', 1))->assertSessionHas('error');
    }

    public function test_el_administrador_apaga_permisos_desde_la_ficha_y_queda_en_la_bitacora(): void
    {
        $cajero = $this->cajero();
        $datos = ['id_empresa_negocio' => $this->sucursal, 'tdicod' => '1', 'nombre_completo' => 'CAJERO DE PRUEBA', 'estusu' => '1', 'asistencia' => '0',
            'email' => $cajero->email, 'role_id' => '4', 'acciones_enviadas' => '1', 'acciones' => ['notas_credito', 'medio_pago', 'compras_editar', 'caja_anular']];

        $this->put(route('usuarios.update', $cajero->IdUsuario), $datos)->assertRedirect(route('usuarios.index'));

        $cajero->refresh();
        $this->assertFalse($cajero->puede('anular_ventas'));
        $this->assertFalse($cajero->puede('cambiar_precio'));
        $this->assertTrue($cajero->puede('notas_credito'));
        $cambio = json_decode(DB::table('bitacora')->where('accion', 'usuario.editar')->value('cambios'), true)['acciones'];
        $this->assertSame(['Anular ventas', 'Cobrar a otro precio'], $cambio['quito']);

        // Si se le pone de administrador, ya no le queda nada bloqueado
        $this->put(route('usuarios.update', $cajero->IdUsuario), ['role_id' => '2'] + $datos);
        $this->assertNull($cajero->fresh()->acciones_bloqueadas);
    }
}
