<?php

namespace Tests\Flujos;

use App\Models\User;
use App\Support\Contabilidad\Contabilidad;
use App\Support\FlujoCaja;
use Illuminate\Support\Facades\DB;

/**
 * Órdenes de compra (pedido → aprobación → compra) y mercadería en tránsito (factura registrada, el stock sube recién
 * cuando llega). Hoy es viernes 30/10/2026.
 */
class OrdenesCompraTest extends FlujoTestCase
{
    private function compra(int $productoId, float $cantidad, array $mas = []): array
    {
        return $mas + ['tdocod' => '01', 'serie' => 'F001', 'numero' => (string) random_int(1, 999999), 'fecEmi' => '2026-10-30', 'fecIng' => '2026-10-30',
            'estadopago' => $this->contado, 'moneda' => 'PEN', 'id_almacen' => $this->almacen, 'prov_tdicod' => '6', 'prov_num' => '20100070970',
            'prov_nom' => 'PROVEEDOR DE PRUEBA SAC', 'actualizar_costo' => true,
            'items' => [['id' => $productoId, 'tip_igv' => '10', 'cantidad' => $cantidad, 'costo' => 3]]];
    }

    private function ultimaCompra(): object
    {
        return DB::table('compras_cabecera')->where('id_empresa_negocio', $this->sucursal)->orderByDesc('com_cab_id')->first();
    }

    private function orden(int $productoId, float $cantidad = 10, array $mas = []): array
    {
        return $mas + ['fecha' => '2026-10-30', 'fecha_entrega' => '2026-11-10', 'prov_tdicod' => '6', 'prov_num' => '20100070970',
            'prov_nom' => 'Proveedor de prueba sac', 'moneda' => 'PEN', 'id_almacen' => $this->almacen,
            'items' => [['id' => $productoId, 'cantidad' => $cantidad, 'costo' => 3, 'tip_igv' => '10']]];
    }

    private function conModulo(int $rol, string $url): User
    {
        $u = $this->usuario->replicate();
        $u->email = 'rol'.$rol.uniqid().'@prueba.test';
        $u->apeusu = 'USUARIO ROL '.$rol;
        $u->save();
        DB::table('role_user')->insert(['role_id' => $rol, 'user_IdUsuario' => $u->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        DB::table('modulos_usuario')->insert(['user_IdUsuario' => $u->IdUsuario, 'mod_id' => DB::table('modulos')->where('mod_url', $url)->value('mod_id')]);

        return $u;
    }

    // ------------------------------------------------------------------ el stock de las compras

    public function test_una_compra_normal_sube_el_stock_y_al_anularla_baja(): void
    {
        $gaseosa = $this->producto('INCA KOLA', 5, 10);

        $this->postJson(route('compras.store'), $this->compra($gaseosa->IdProducto, 12))->assertJson(['estado' => 'success']);
        $this->assertEquals(22.0, $this->stock($gaseosa));
        $this->assertEquals(3.0, (float) $gaseosa->fresh()->costo, 'Se actualiza el costo.');

        $this->post(route('compras.anular', $this->ultimaCompra()->com_cab_id))->assertSessionHas('success');
        $this->assertEquals(10.0, $this->stock($gaseosa));
    }

    public function test_en_transito_el_stock_sube_recien_al_recibirla(): void
    {
        $gaseosa = $this->producto('INCA KOLA', 5, 10);

        $this->postJson(route('compras.store'), $this->compra($gaseosa->IdProducto, 12, ['en_transito' => true]))->assertJson(['estado' => 'success']);
        $compra = $this->ultimaCompra();
        $this->assertTrue((bool) $compra->en_transito);
        $this->assertEquals(10.0, $this->stock($gaseosa), 'La mercadería aún no llega.');
        $this->assertEquals(0.0, (float) $gaseosa->fresh()->costo, 'El costo cambia recién cuando llega.');

        // Editarla (por ejemplo, la cantidad facturada) tampoco toca el stock
        $this->putJson(route('compras.update', $compra->com_cab_id), $this->compra($gaseosa->IdProducto, 15, ['serie' => $compra->com_doc_ser, 'numero' => $compra->com_doc_num]))
            ->assertJson(['estado' => 'success']);
        $this->assertEquals(10.0, $this->stock($gaseosa));

        $this->post(route('compras.recibir', $compra->com_cab_id), ['fecha' => '2026-10-29'])->assertSessionHas('success');
        $this->assertEquals(25.0, $this->stock($gaseosa));
        $this->assertEquals(3.0, (float) $gaseosa->fresh()->costo);
        $recibida = DB::table('compras_cabecera')->where('com_cab_id', $compra->com_cab_id)->first();
        $this->assertSame([0, '2026-10-29', '2026-10-29'], [(int) $recibida->en_transito, $recibida->fecha_recepcion, $recibida->com_fec_ing]);
        $this->assertSame('2026-10-29', DB::table('movimientos_productos')->where('com_cab_id', $compra->com_cab_id)->value('fecha_mov'), 'En el kardex con la fecha en que llegó.');

        $this->post(route('compras.recibir', $compra->com_cab_id), ['fecha' => '2026-10-30'])->assertSessionHas('error', 'Esta compra ya se recibió en el almacén.');
        $this->assertEquals(25.0, $this->stock($gaseosa));
    }

    public function test_anular_una_compra_en_transito_no_saca_nada_del_stock(): void
    {
        $gaseosa = $this->producto('INCA KOLA', 5, 10);
        $this->postJson(route('compras.store'), $this->compra($gaseosa->IdProducto, 12, ['en_transito' => true]))->assertJson(['estado' => 'success']);

        $this->post(route('compras.anular', $this->ultimaCompra()->com_cab_id))->assertSessionHas('success');

        $this->assertEquals(10.0, $this->stock($gaseosa));
        $this->assertSame(0, DB::table('movimientos_productos')->where('com_cab_id', $this->ultimaCompra()->com_cab_id)->count());
    }

    // ------------------------------------------------------------------ órdenes de compra

    public function test_la_orden_se_pide_se_aprueba_y_se_vuelve_compra_una_sola_vez(): void
    {
        $gaseosa = $this->producto('INCA KOLA', 5, 0);
        $almacenero = $this->conModulo(4, '/ordenes-compra');

        $this->actingAs($almacenero)->postJson(route('ordenes_compra.store'), $this->orden($gaseosa->IdProducto))->assertJson(['estado' => 'success']);
        $orden = DB::table('ordenes_compra')->where('id_empresa_negocio', $this->sucursal)->first();
        $this->assertSame(['PENDIENTE', 1, 'PROVEEDOR DE PRUEBA SAC', '30.00'], [$orden->estado, (int) $orden->numero, $orden->prov_nom, $orden->total]);

        // Quien la pidió no la aprueba; tampoco se compra sin aprobar
        $this->actingAs($almacenero)->post(route('ordenes_compra.decidir', $orden->id), ['accion' => 'aprobar'])
            ->assertSessionHas('error', 'Solo el administrador aprueba o rechaza órdenes de compra.');
        $this->actingAs($this->usuario)->postJson(route('compras.store'), $this->compra($gaseosa->IdProducto, 10, ['orden_id' => $orden->id]))
            ->assertJson(['estado' => 'error']);

        $this->post(route('ordenes_compra.decidir', $orden->id), ['accion' => 'aprobar'])->assertSessionHas('success');
        $this->get(route('compras.create', ['orden' => $orden->id]))->assertOk()->assertSee('INCA KOLA')->assertSee('"codigo":"OC-000001"', false);

        $this->postJson(route('compras.store'), $this->compra($gaseosa->IdProducto, 10, ['orden_id' => $orden->id]))->assertJson(['estado' => 'success']);
        $compra = $this->ultimaCompra();
        $this->assertSame(['OC-000001', $orden->id], [$compra->orden_compra, (int) $compra->orden_id]);
        $orden = DB::table('ordenes_compra')->where('id', $orden->id)->first();
        $this->assertSame(['COMPRADA', $compra->com_cab_id], [$orden->estado, (int) $orden->com_cab_id]);
        $this->assertEquals(10.0, $this->stock($gaseosa));

        $this->postJson(route('compras.store'), $this->compra($gaseosa->IdProducto, 10, ['orden_id' => $orden->id]))->assertJson(['estado' => 'error']);
        $this->assertTrue((bool) DB::table('bitacora')->where('accion', 'compra.orden_aprobar')->exists());
    }

    public function test_lo_que_pide_el_administrador_ya_nace_aprobado_y_un_rechazo_lleva_motivo(): void
    {
        $gaseosa = $this->producto('INCA KOLA', 5);
        $this->postJson(route('ordenes_compra.store'), $this->orden($gaseosa->IdProducto))->assertJson(['estado' => 'success']);
        $this->assertSame('APROBADA', DB::table('ordenes_compra')->where('id_empresa_negocio', $this->sucursal)->value('estado'));

        $almacenero = $this->conModulo(4, '/ordenes-compra');
        $this->actingAs($almacenero)->postJson(route('ordenes_compra.store'), $this->orden($gaseosa->IdProducto))->assertJson(['estado' => 'success']);
        $id = (int) DB::table('ordenes_compra')->where('id_empresa_negocio', $this->sucursal)->max('id');
        $this->actingAs($this->usuario)->post(route('ordenes_compra.decidir', $id), ['accion' => 'rechazar'])->assertSessionHasErrors('motivo');
        $this->post(route('ordenes_compra.decidir', $id), ['accion' => 'rechazar', 'motivo' => 'PRECIO ALTO'])->assertSessionHas('success');
        $this->assertSame(['RECHAZADA', 'PRECIO ALTO', 2], [DB::table('ordenes_compra')->where('id', $id)->value('estado'),
            DB::table('ordenes_compra')->where('id', $id)->value('motivo'), (int) DB::table('ordenes_compra')->where('id', $id)->value('numero')]);
    }

    public function test_sin_tipo_de_igv_el_producto_va_gravado(): void
    {
        $gaseosa = $this->producto('INCA KOLA', 5);
        $datos = $this->orden($gaseosa->IdProducto);
        unset($datos['items'][0]['tip_igv']);

        $this->postJson(route('ordenes_compra.store'), $datos)->assertJson(['estado' => 'success']);

        $this->assertSame('10', DB::table('ordenes_compra_detalle')->value('tip_igv'));
    }

    public function test_sin_la_opcion_en_su_menu_no_ve_las_ordenes(): void
    {
        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);

        $this->actingAs($cajero)->get(route('ordenes_compra.index'))->assertForbidden();
    }

    public function test_la_orden_aprobada_sale_en_el_flujo_de_caja_hasta_que_se_compra(): void
    {
        $gaseosa = $this->producto('INCA KOLA', 5);
        $this->postJson(route('ordenes_compra.store'), $this->orden($gaseosa->IdProducto, 100))->assertJson(['estado' => 'success']);

        $semanas = FlujoCaja::proyeccion($this->sucursal, 3, false)['semanas'];
        $this->assertSame(300.0, $semanas[2]['salidas']['Órdenes de compra aprobadas'], 'Entrega el 10/11: tercera semana.');

        $id = (int) DB::table('ordenes_compra')->where('id_empresa_negocio', $this->sucursal)->value('id');
        $this->postJson(route('compras.store'), $this->compra($gaseosa->IdProducto, 100, ['orden_id' => $id]))->assertJson(['estado' => 'success']);
        $this->assertSame(0.0, FlujoCaja::proyeccion($this->sucursal, 3, false)['semanas'][2]['salidas']['Órdenes de compra aprobadas']);
    }

    // ------------------------------------------------------------------ contabilidad: la 28

    public function test_contablemente_lo_que_no_llega_va_a_la_28_y_pasa_a_la_20_cuando_llega(): void
    {
        $ruc = (string) $this->usuario->IdEmpresa;
        Contabilidad::asegurar($ruc);
        DB::table('conta_config')->where('IdEmpresa', $ruc)->update(['asiento_destino' => 1]);
        $gaseosa = $this->producto('INCA KOLA', 5);
        $this->postJson(route('compras.store'), $this->compra($gaseosa->IdProducto, 118, ['en_transito' => true, 'fecEmi' => '2026-09-25', 'fecIng' => '2026-09-25']))
            ->assertJson(['estado' => 'success']);
        $this->post(route('compras.recibir', $this->ultimaCompra()->com_cab_id), ['fecha' => '2026-10-05'])->assertSessionHas('success');

        Contabilidad::centralizarCompras($ruc, '202609');
        Contabilidad::centralizarCompras($ruc, '202610');

        $movimientos = fn (string $periodo) => DB::table('conta_asiento_detalle as d')->join('conta_asientos as a', 'a.id', '=', 'd.asiento_id')
            ->where('a.IdEmpresa', $ruc)->where('a.periodo', $periodo)->where('a.glosa', 'like', '%TRÁNSITO%')->orderBy('d.id')
            ->get(['d.cuenta', 'd.debe', 'd.haber'])->map(fn ($m) => [(string) $m->cuenta, (float) $m->debe, (float) $m->haber])->all();
        $this->assertSame([['28', 300.0, 0.0], ['611111', 0.0, 300.0]], $movimientos('202609'), 'Septiembre: 354 con IGV = 300 a la 28.');
        $this->assertSame([['201111', 300.0, 0.0], ['28', 0.0, 300.0]], $movimientos('202610'), 'Octubre: llegó, de la 28 a la 20.');
    }
}
