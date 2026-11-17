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
    /** Un cajero: $bloqueadas = lo que se le apagó; $permitidas = lo que solo tiene si se lo dan (anular, editar…) */
    private function cajero(array $bloqueadas = [], array $permitidas = []): User
    {
        $u = $this->usuario->replicate();
        $u->email = 'cajero'.uniqid().'@prueba.test';
        $u->apeusu = 'CAJERO DE PRUEBA';
        $u->acciones_bloqueadas = $bloqueadas ? json_encode($bloqueadas) : null;
        $u->acciones_permitidas = $permitidas ? json_encode($permitidas) : null;
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

    public function test_anular_y_editar_son_solo_del_administrador_salvo_que_se_los_den(): void
    {
        $plato = $this->producto('JUANE', 15, 10);
        // Un cajero cualquiera: no anula ni edita (ni ve esos botones)
        $cajero = $this->cajero();
        $id = VentaDirecta::registrar($cajero, $this->venta([[$plato, 1]], '13'), 'PV');
        $this->actingAs($cajero)->postJson(route('ventas.anular', $id), ['motivo' => 'ERROR DEL CAJERO'])
            ->assertJson(['success' => false, 'message' => 'No tienes permiso para anular ventas. Pídeselo al administrador.']);
        $this->get(route('ventas.editar', $id))->assertForbidden();
        $this->postJson(route('ventas.editar.guardar', $id), $this->venta([[$plato, 2]], '13'))
            ->assertJson(['estado' => 'error', 'mensaje' => 'No tienes permiso para editar ventas. Pídeselo al administrador.']);
        $this->get(route('ventas.index'))->assertOk()->assertDontSee('title="Anular"', false)->assertDontSee('title="Editar venta', false);
        $this->assertFalse($cajero->puede('notas_credito'));
        $this->assertTrue($cajero->puede('medio_pago'), 'Lo demás sigue permitido.');

        // Con el permiso dado, sí
        $conPermiso = $this->cajero(['cambiar_precio'], ['anular_ventas', 'editar_ventas']);
        $otra = VentaDirecta::registrar($conPermiso, $this->venta([[$plato, 1]], '13'), 'PV');
        $this->actingAs($conPermiso)->get(route('ventas.index'))->assertOk()->assertSee('title="Anular"', false);
        $this->get(route('ventas.editar', $otra))->assertRedirect();
        $this->postJson(route('ventas.anular', $otra), ['motivo' => 'ERROR DEL CAJERO'])->assertJson(['success' => true]);
    }

    public function test_sin_el_permiso_no_se_cobra_mas_barato_pero_si_al_de_lista_o_mas_caro(): void
    {
        $cajero = $this->cajero(['cambiar_precio']);
        $plato = $this->producto('JUANE', 15, 10);

        try {
            VentaDirecta::registrar($cajero, $this->venta([[$plato, 1, 10]], '13'), 'PV');
            $this->fail('Vendió más barato sin permiso.');
        } catch (\RuntimeException $e) {
            $this->assertSame('No tienes permiso para bajar el precio de lista. Pídeselo al administrador. JUANE se vende a S/ 15.00.', $e->getMessage());
        }
        $this->assertSame(0, DB::table('cpe_cabecera')->where('IdUsuario', $cajero->IdUsuario)->count());

        $this->assertGreaterThan(0, VentaDirecta::registrar($cajero, $this->venta([[$plato, 1]], '13'), 'PV'));
        // En la pantalla el precio se puede tocar (para subirlo), pero sin permiso para bajarlo
        $this->actingAs($cajero)->get(route('pv.index'))->assertOk()->assertSee('editarPrecio: true', false)->assertSee('puedeBajar: false', false)->assertSee('puedeSubir: true', false);
        // Subir el precio se puede mientras no le quiten «Subir el precio de lista» (por ejemplo, un plato con algo extra)
        $mas = VentaDirecta::registrar($cajero, $this->venta([[$plato, 1, 18]], '13'), 'PV');
        $this->assertSame(18.0, (float) DB::table('cpe_detalle')->where('IdCpe_cabecera', $mas)->value('cdepuni'));
    }

    public function test_sin_ninguno_de_los_dos_permisos_el_precio_no_se_cambia(): void
    {
        $cajero = $this->cajero(['cambiar_precio', 'subir_precio']);
        $plato = $this->producto('JUANE', 15, 10);

        foreach ([10 => 'bajar', 18 => 'subir'] as $precio => $verbo) {
            try {
                VentaDirecta::registrar($cajero, $this->venta([[$plato, 1, $precio]], '13'), 'PV');
                $this->fail("Pudo {$verbo} el precio sin permiso.");
            } catch (\RuntimeException $e) {
                $this->assertSame("No tienes permiso para {$verbo} el precio de lista. Pídeselo al administrador. JUANE se vende a S/ 15.00.", $e->getMessage());
            }
        }
        // Al precio de lista sí vende, y en la pantalla el precio queda fijo
        $this->assertGreaterThan(0, VentaDirecta::registrar($cajero, $this->venta([[$plato, 1]], '13'), 'PV'));
        $this->actingAs($cajero)->get(route('pv.index'))->assertOk()->assertSee('editarPrecio: false', false);

        // Solo sin «Subir»: bajar sí puede, subir no
        $soloBaja = $this->cajero(['subir_precio']);
        $this->assertGreaterThan(0, VentaDirecta::registrar($soloBaja, $this->venta([[$plato, 1, 12]], '13'), 'PV'));
        $this->expectExceptionMessage('No tienes permiso para subir el precio de lista.');
        VentaDirecta::registrar($soloBaja, $this->venta([[$plato, 1, 20]], '13'), 'PV');
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
        $this->assertFalse($cajero->puede('editar_ventas'));
        $this->assertFalse($cajero->puede('cambiar_precio'));
        $this->assertTrue($cajero->puede('notas_credito'));
        $cambio = json_decode(DB::table('bitacora')->where('accion', 'usuario.editar')->value('cambios'), true)['acciones'];
        $this->assertSame(['Bajar el precio de lista', 'Subir el precio de lista'], $cambio['quito']);

        // Si se le pone de administrador, no se le guarda nada (puede todo)
        $this->put(route('usuarios.update', $cajero->IdUsuario), ['role_id' => '2'] + $datos);
        $this->assertNull($cajero->fresh()->acciones_bloqueadas);
        $this->assertNull($cajero->fresh()->acciones_permitidas);
    }
}
