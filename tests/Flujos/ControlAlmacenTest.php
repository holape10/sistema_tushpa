<?php

namespace Tests\Flujos;

use App\Models\Producto;
use App\Models\User;
use App\Support\ControlInsumos;
use App\Support\Requerimientos;
use Illuminate\Support\Facades\DB;

/** Pedidos entre sedes, producción del día, inventario ciego y control de insumos */
class ControlAlmacenTest extends FlujoTestCase
{
    private function conRol(int $rol, int $sucursal, array $modulos = []): User
    {
        $u = $this->usuario->replicate();
        $u->email = 'rol'.$rol.uniqid().'@prueba.test';
        $u->id_empresa_negocio = $sucursal;
        $u->save();
        DB::table('role_user')->insert(['role_id' => $rol, 'user_IdUsuario' => $u->IdUsuario, 'id_empresa_negocio' => $sucursal]);
        foreach ($modulos as $url) {
            DB::table('modulos_usuario')->insert(['user_IdUsuario' => $u->IdUsuario, 'mod_id' => DB::table('modulos')->where('mod_url', $url)->value('mod_id')]);
        }

        return $u;
    }

    private function insumo(string $nombre, float $stock, int $sucursal = 0, int $almacen = 0, string $codigo = ''): Producto
    {
        $p = $this->producto($nombre, 0);
        $p->update(['promocion' => 4, 'costo' => 10, 'procod' => $codigo ?: $p->procod, 'id_empresa_negocio' => $sucursal ?: $this->sucursal]);
        if ($stock > 0) {
            DB::table('producto_stock')->insert(['IdProducto' => $p->IdProducto, 'id_almacen' => $almacen ?: $this->almacen,
                'id_empresa_negocio' => $sucursal ?: $this->sucursal, 'stock' => $stock, 'stock_inicial' => $stock]);
        }

        return $p->fresh();
    }

    private function stockEn(Producto $p, int $almacen): float
    {
        return (float) DB::table('producto_stock')->where('IdProducto', $p->IdProducto)->where('id_almacen', $almacen)->value('stock');
    }

    public function test_una_sede_pide_a_la_central_la_central_despacha_y_la_sede_recibe(): void
    {
        // Central: la sucursal de siempre. Sede Cusco: otra sucursal con su catálogo y su almacén
        $cusco = DB::table('empresa_negocios')->insertGetId(['IdEmpresa' => $this->usuario->IdEmpresa, 'nombre_comercial' => 'SEDE CUSCO', 'estado' => 'Activo']);
        $almacenCusco = DB::table('almacenes')->insertGetId(['descripcion' => 'ALMACEN CUSCO', 'predeterminado' => 1, 'id_empresa_negocio' => $cusco, 'direccion' => '-', 'ubigeo' => '']);
        $arrozCentral = $this->insumo('ARROZ COSTEÑO', 50, codigo: 'ARR01');
        $arrozCusco = $this->insumo('ARROZ', 2, $cusco, $almacenCusco, 'ARR01');
        $aceiteCentral = $this->insumo('ACEITE PRIMOR', 3);
        $aceiteCusco = $this->insumo('ACEITE PRIMOR', 0, $cusco, $almacenCusco);
        $almaceneroCusco = $this->conRol(17, $cusco, ['/requerimientos']);
        $almaceneroCentral = $this->conRol(17, $this->sucursal, ['/requerimientos']);

        $this->actingAs($almaceneroCusco)->post(route('requerimientos.store'), ['id_almacen' => $almacenCusco, 'fecha_para' => now()->toDateString(),
            'sucursal_origen' => $this->sucursal, 'urgente' => 1, 'items' => [
                ['id' => $arrozCusco->IdProducto, 'cantidad' => 20], ['id' => $aceiteCusco->IdProducto, 'cantidad' => 5]]])->assertSessionHasNoErrors();
        $rq = DB::table('requerimientos')->where('id_empresa_negocio', $cusco)->latest('id')->first();
        $this->assertSame([1, $this->sucursal], [(int) $rq->urgente, (int) $rq->sucursal_origen]);
        // La sede no lo puede comprar ella misma: es de la central
        $this->get(route('compras.create', ['requerimiento' => $rq->id]))->assertForbidden();

        // La central lo ve, empareja por código (arroz) y por nombre (aceite), y no deja enviar más de lo que tiene
        $this->actingAs($almaceneroCentral)->get(route('requerimientos.index'))->assertOk()->assertSee('te lo pide SEDE CUSCO');
        $lineas = DB::table('requerimientos_detalle')->where('requerimiento_id', $rq->id)->pluck('id', 'IdProducto');
        $enviar = fn (float $aceite) => $this->post(route('requerimientos.despachar', $rq->id), ['id_almacen_origen' => $this->almacen,
            'enviados' => [$lineas[$arrozCusco->IdProducto] => 20, $lineas[$aceiteCusco->IdProducto] => $aceite]]);
        $enviar(5)->assertSessionHas('error');
        $this->assertSame(50.0, $this->stockEn($arrozCentral, $this->almacen), 'Si algo falla, no sale nada.');
        $enviar(3)->assertSessionHas('success');
        $this->assertSame([30.0, 0.0], [$this->stockEn($arrozCentral, $this->almacen), $this->stockEn($aceiteCentral, $this->almacen)]);
        $this->assertSame(2.0, $this->stockEn($arrozCusco, $almacenCusco), 'En camino: aún no entra a la sede.');

        // Llegaron 19 de arroz (uno se rompió) y los 3 de aceite
        $this->actingAs($almaceneroCusco)->post(route('requerimientos.recibir', $rq->id), ['recibidos' => [$lineas[$arrozCusco->IdProducto] => 19]])
            ->assertSessionHas('success');
        $this->assertSame([21.0, 3.0], [$this->stockEn($arrozCusco, $almacenCusco), $this->stockEn($aceiteCusco, $almacenCusco)]);
        $this->assertSame('RECIBIDO', DB::table('requerimientos')->where('id', $rq->id)->value('estado'));
    }

    public function test_la_produccion_del_dia_calcula_los_insumos_de_las_recetas_y_pide_lo_que_falta(): void
    {
        $pescado = $this->insumo('PESCADO', 2);
        $limon = $this->insumo('LIMON', 100);
        $ceviche = $this->producto('CEVICHE', 30);
        $ceviche->update(['promocion' => 2]);
        foreach ([[$pescado, 0.25], [$limon, 1.5]] as [$insumo, $cantidad]) {
            DB::table('producto_receta')->insert(['IdProducto' => $ceviche->IdProducto, 'IdInsumo' => $insumo->IdProducto, 'cantidad' => $cantidad,
                'umecod' => 'NIU', 'id_empresa_negocio' => $this->sucursal]);
        }

        // 40 ceviches: 10 de pescado (hay 2, faltan 8) y 60 limones (alcanza)
        $this->post(route('requerimientos.produccion'), ['almacen' => $this->almacen, 'platos' => [$ceviche->IdProducto => 40]])->assertOk()
            ->assertViewHas('resultado', fn ($r) => $r->pluck('falta', 'nombre')->all() === ['PESCADO' => 8.0, 'LIMON' => 0.0]);
        $this->post(route('requerimientos.produccion'), ['almacen' => $this->almacen, 'platos' => [$ceviche->IdProducto => 40], 'pedir' => 1])
            ->assertRedirect(route('requerimientos.create', ['almacen' => $this->almacen]));
        $this->get(route('requerimientos.create', ['almacen' => $this->almacen]))->assertOk()
            ->assertViewHas('productos', fn ($p) => $p->firstWhere('nombre', 'PESCADO')->sugerido === 8.0 && $p->firstWhere('nombre', 'LIMON')->sugerido === 0.0);
    }

    public function test_al_registrar_la_compra_se_elige_si_fue_con_factura(): void
    {
        $limon = $this->insumo('LIMON', 0);
        $id = Requerimientos::guardar($this->usuario, ['id_almacen' => $this->almacen, 'fecha_para' => now()->toDateString(), 'items' => [['id' => $limon->IdProducto, 'cantidad' => 3]]]);

        $this->get(route('compras.create', ['requerimiento' => $id, 'comprobante' => '01']))->assertOk()
            ->assertViewHas('desdeOrden', fn ($o) => $o['tdocod'] === '01' && $o['proveedor']['tdicod'] === '6' && $o['proveedor']['num'] === '');
        $this->get(route('compras.create', ['requerimiento' => $id]))->assertOk()
            ->assertViewHas('desdeOrden', fn ($o) => $o['tdocod'] === '12' && $o['proveedor']['nom'] === 'VARIOS - MERCADO');
    }

    public function test_el_inventario_ciego_no_muestra_el_stock_y_el_ajuste_respeta_lo_vendido_despues(): void
    {
        $pescado = $this->insumo('PESCADO', 10);
        $almacenero = $this->conRol(17, $this->sucursal, ['/conteo-ciego']);

        $this->actingAs($almacenero)->get(route('conteo_ciego.create'))->assertOk()->assertSee('PESCADO')->assertDontSee('"stock"', false);
        $this->post(route('conteo_ciego.store'), ['id_almacen' => $this->almacen, 'contados' => [$pescado->IdProducto => 7]])->assertSessionHas('success');
        $conteo = DB::table('conteos_ciegos')->latest('id')->first();
        $this->get(route('conteo_ciego.show', $conteo->id))->assertForbidden();

        // Mientras tanto se vendió 1: al ajustar quedan 6 (los 3 que faltaron, sobre el stock de ahora)
        DB::table('producto_stock')->where('IdProducto', $pescado->IdProducto)->update(['stock' => 9]);
        $this->actingAs($this->usuario)->get(route('conteo_ciego.show', $conteo->id))->assertOk()->assertSee('30.00');
        $this->post(route('conteo_ciego.aplicar', $conteo->id))->assertSessionHas('success');
        $this->assertSame(6.0, $this->stockEn($pescado, $this->almacen));
        $this->assertSame('APLICADO', DB::table('conteos_ciegos')->where('id', $conteo->id)->value('estado'));
        $this->post(route('conteo_ciego.aplicar', $conteo->id))->assertSessionHas('error');
    }

    public function test_el_control_de_insumos_separa_lo_vendido_la_merma_y_lo_que_falto(): void
    {
        $pescado = $this->insumo('PESCADO', 0);
        $mov = fn (string $tip, float $cantidad, array $extra) => DB::table('movimientos_productos')->insert($extra + ['IdProducto' => $pescado->IdProducto,
            'id_almacen' => $this->almacen, 'id_empresa_negocio' => $this->sucursal, 'mov_tip' => $tip, 'tipo' => $tip === 'I' ? 1 : 2,
            'cantidad' => $cantidad, 'fecha_mov' => now()->toDateTimeString(), 'stock' => 0, 'stock_inicial' => 0]);
        $mov('E', 20, ['IdCpe_cabecera' => 1]);   // vendido en platos
        $mov('I', 2, ['IdCpe_cabecera' => 2, 'cod_tip_ope' => '05']); // una venta anulada
        $mov('E', 1, ['merma_id' => 1]);          // merma registrada
        $mov('E', 3, ['inv_cab_id' => 1]);        // faltó en el conteo

        $f = ControlInsumos::reporte($this->sucursal, $this->almacen, now()->toDateString(), now()->toDateString())->firstWhere('nombre', 'PESCADO');

        $this->assertSame([18.0, 1.0, 3.0, 16.7, 30.0], [$f->teorico, $f->mermas, $f->neto, $f->desviacion, $f->valor]);
        $this->get(route('control_insumos.index'))->assertOk()->assertSee('PESCADO')->assertSee('16.7%');
    }
}
