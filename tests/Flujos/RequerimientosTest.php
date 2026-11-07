<?php

namespace Tests\Flujos;

use App\Models\Producto;
use App\Models\User;
use App\Support\Requerimientos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Requerimientos del día: cocina pide lo que falta, el administrador lo compra en el mercado y lo registra como compra */
class RequerimientosTest extends FlujoTestCase
{
    private function cocinero(): User
    {
        $u = $this->usuario->replicate();
        $u->email = 'cocina'.uniqid().'@prueba.test';
        $u->save();
        DB::table('role_user')->insert(['role_id' => 16, 'user_IdUsuario' => $u->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        DB::table('modulos_usuario')->insert(['user_IdUsuario' => $u->IdUsuario, 'mod_id' => DB::table('modulos')->where('mod_url', '/requerimientos')->value('mod_id')]);

        return $u;
    }

    private function insumo(string $nombre, float $stock, float $minimo = 0): Producto
    {
        $p = $this->producto($nombre, 0, $stock);
        $p->update(['promocion' => 4, 'stock_min' => $minimo, 'costo' => 5]);

        return $p;
    }

    /** Lo que salió del almacén un día (ventas, recetas…) */
    private function salida(Producto $p, float $cantidad, string $fecha): void
    {
        DB::table('movimientos_productos')->insert(['IdProducto' => $p->IdProducto, 'id_almacen' => $this->almacen, 'id_empresa_negocio' => $this->sucursal,
            'mov_tip' => 'E', 'tipo' => 2, 'cantidad' => $cantidad, 'fecha_mov' => $fecha, 'fecha_registro' => $fecha, 'stock' => 0, 'stock_inicial' => 0]);
    }

    public function test_sugiere_lo_que_falta_y_lo_que_se_acabaria_segun_lo_que_se_gasta(): void
    {
        $limon = $this->insumo('LIMON', 2, 5);
        $pescado = $this->insumo('PESCADO', 3);
        $this->insumo('SAL', 40, 2);
        $this->insumo('AJI NO USADO', 0);
        // Pescado: 14 kg en la última semana = 2 por día; 3 no alcanzan para 2 días
        foreach (range(1, 7) as $dia) {
            $this->salida($pescado, 2, now()->subDays($dia)->toDateTimeString());
        }

        $s = Requerimientos::sugerencias($this->sucursal, $this->almacen, CarbonImmutable::now())->keyBy('nombre');

        $this->assertSame(['falta', 3.0], [$s['LIMON']->estado, $s['LIMON']->sugerido]);
        $this->assertSame(['se_acaba', 2.0, 1.0, 1.5], [$s['PESCADO']->estado, $s['PESCADO']->consumo_dia, $s['PESCADO']->sugerido, $s['PESCADO']->dias]);
        $this->assertSame(['ok', 0.0], [$s['SAL']->estado, $s['SAL']->sugerido]);
        $this->assertSame('ok', $s['AJI NO USADO']->estado, 'Sin mínimo ni consumo no se pide solo.');
        $this->assertSame(['LIMON', 'PESCADO'], $s->where('estado', '!=', 'ok')->pluck('nombre')->all(), 'Lo más urgente primero.');
    }

    public function test_cocina_pide_el_administrador_compra_en_el_mercado_y_queda_registrado_como_compra(): void
    {
        $limon = $this->insumo('LIMON', 2, 5);
        $culantro = $this->insumo('CULANTRO', 0, 1);
        $admin = $this->usuario;
        $cocinero = $this->cocinero();

        $this->actingAs($cocinero)->get(route('requerimientos.create'))->assertOk()->assertSee('LIMON');
        $this->post(route('requerimientos.store'), ['id_almacen' => $this->almacen, 'fecha_para' => now()->toDateString(), 'items' => [
            ['id' => $limon->IdProducto, 'cantidad' => 3, 'nota' => 'limón sutil'], ['id' => $culantro->IdProducto, 'cantidad' => 1], ['id' => 999999, 'cantidad' => 0],
        ]])->assertSessionHasNoErrors()->assertRedirect();
        $rq = DB::table('requerimientos')->where('id_empresa_negocio', $this->sucursal)->latest('id')->first();
        $this->assertSame('PENDIENTE', $rq->estado);
        $this->get(route('requerimientos.imprimir', $rq->id))->assertOk()->assertSee('limón sutil');
        // El cocinero no compra ni marca
        $this->post(route('requerimientos.marcar', $rq->id), ['lineas' => []])->assertForbidden();

        // Le llega al administrador en su inicio
        $this->actingAs($admin)->get(route('inicio'))->assertOk()->assertSee('Requerimientos por comprar')->assertSee(Requerimientos::codigo($rq));

        // En el mercado: 3.5 de limón a S/ 4 y el culantro no había
        $lineas = DB::table('requerimientos_detalle')->where('requerimiento_id', $rq->id)->pluck('id', 'IdProducto');
        $this->post(route('requerimientos.marcar', $rq->id), ['registrar' => 1, 'lineas' => [
            $lineas[$limon->IdProducto] => ['comprado' => 3.5, 'precio' => 4], $lineas[$culantro->IdProducto] => ['comprado' => 0, 'precio' => ''],
        ]])->assertRedirect(route('compras.create', ['requerimiento' => $rq->id]));
        $this->get(route('compras.create', ['requerimiento' => $rq->id]))->assertOk()
            ->assertViewHas('desdeOrden', fn ($o) => array_map(fn ($i) => [$i['id'], $i['cantidad'], $i['costo']], $o['items']) === [[$limon->IdProducto, 3.5, 4.0]]);

        $this->postJson(route('compras.store'), ['tdocod' => '12', 'serie' => 'T001', 'numero' => '15', 'fecEmi' => now()->toDateString(),
            'fecIng' => now()->toDateString(), 'estadopago' => $this->contado, 'moneda' => 'PEN', 'id_almacen' => $this->almacen,
            'prov_tdicod' => '0', 'prov_num' => '00000000', 'prov_nom' => 'VARIOS - MERCADO', 'requerimiento_id' => $rq->id,
            'items' => [['id' => $limon->IdProducto, 'tip_igv' => '10', 'cantidad' => 3.5, 'costo' => 4]]])->assertJson(['estado' => 'success']);

        $rq = DB::table('requerimientos')->find($rq->id);
        $this->assertSame('COMPRADO', $rq->estado);
        $this->assertNotNull($rq->com_cab_id);
        $this->assertEqualsWithDelta(5.5, $this->stock($limon), 0.001, 'El stock sube con la compra.');
        // Ya comprado: no se vuelve a registrar ni a anular
        $this->get(route('compras.create', ['requerimiento' => $rq->id]))->assertRedirect(route('requerimientos.index'));
        $this->post(route('requerimientos.anular', $rq->id), ['motivo' => 'x'])->assertSessionHas('error');
    }

    public function test_cada_sede_solo_ve_y_pide_lo_suyo(): void
    {
        $otra = DB::table('empresa_negocios')->insertGetId(['IdEmpresa' => $this->usuario->IdEmpresa, 'nombre_comercial' => 'SEDE CUSCO', 'estado' => 'Activo']);
        $almacenOtra = DB::table('almacenes')->insertGetId(['descripcion' => 'ALMACEN CUSCO', 'predeterminado' => 1, 'id_empresa_negocio' => $otra, 'direccion' => '-', 'ubigeo' => '']);
        $ajeno = DB::table('requerimientos')->insertGetId(['numero' => 1, 'id_empresa_negocio' => $otra, 'id_almacen' => $almacenOtra,
            'fecha_para' => now()->toDateString(), 'IdUsuario' => $this->usuario->IdUsuario, 'estado' => 'PENDIENTE', 'created_at' => now(), 'updated_at' => now()]);
        $limon = $this->insumo('LIMON', 2, 5);

        $this->get(route('requerimientos.show', $ajeno))->assertNotFound();
        $this->post(route('requerimientos.store'), ['id_almacen' => $almacenOtra, 'fecha_para' => now()->toDateString(),
            'items' => [['id' => $limon->IdProducto, 'cantidad' => 1]]])->assertSessionHas('error', 'Almacén no válido.');
        DB::table('productos')->where('IdProducto', $limon->IdProducto)->update(['id_empresa_negocio' => $otra]);
        $this->post(route('requerimientos.store'), ['id_almacen' => $this->almacen, 'fecha_para' => now()->toDateString(),
            'items' => [['id' => $limon->IdProducto, 'cantidad' => 1]]])->assertSessionHas('error');
    }

    public function test_sin_la_opcion_en_su_menu_no_entra(): void
    {
        $mozo = $this->usuario->replicate();
        $mozo->email = 'mozo'.uniqid().'@prueba.test';
        $mozo->save();
        DB::table('role_user')->insert(['role_id' => 8, 'user_IdUsuario' => $mozo->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);

        $this->actingAs($mozo)->get(route('requerimientos.create'))->assertForbidden();
    }
}
