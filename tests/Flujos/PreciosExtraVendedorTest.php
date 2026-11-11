<?php

namespace Tests\Flujos;

use App\Models\Central\Cliente;
use App\Models\Turno;
use App\Models\User;
use App\Support\CatalogoVenta;
use App\Support\Rubros;
use App\Support\VentaDirecta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Precio 2 y 3 de los productos (la caja los elige sin permiso especial) y el rol Vendedor (arma pedidos, la caja cobra) */
class PreciosExtraVendedorTest extends FlujoTestCase
{
    private function usuarioConRol(int $rol, array $bloqueadas = []): User
    {
        $u = $this->usuario->replicate();
        $u->email = 'rol'.$rol.uniqid().'@prueba.test';
        $u->acciones_bloqueadas = $bloqueadas ? json_encode($bloqueadas) : null;
        $u->save();
        DB::table('role_user')->insert(['role_id' => $rol, 'user_IdUsuario' => $u->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);

        return $u;
    }

    public function test_el_cajero_cobra_el_precio_2_sin_permiso_de_cambiar_precio_pero_no_otro(): void
    {
        $cajero = $this->usuarioConRol(4, ['cambiar_precio']);
        Turno::create(['turno' => 2, 'IdUsuario' => $cajero->IdUsuario, 'apertura' => now(), 'IdEmpresa' => $cajero->IdEmpresa,
            'id_empresa_negocio' => $this->sucursal, 'estado' => 'ABIERTO', 'monto' => 0]);
        $cemento = $this->producto('CEMENTO SOL', 32, 50);
        DB::table('productos')->where('IdProducto', $cemento->IdProducto)->update(['precio2' => 29, 'precio3' => 27.5]);

        $this->assertGreaterThan(0, VentaDirecta::registrar($cajero, $this->venta([[$cemento, 10, 29]], '13'), 'PV'));
        $this->assertGreaterThan(0, VentaDirecta::registrar($cajero, $this->venta([[$cemento, 10, 27.5]], '13'), 'PV'));

        $this->expectExceptionMessage('No tienes permiso para cobrar a otro precio');
        VentaDirecta::registrar($cajero, $this->venta([[$cemento, 1, 28]], '13'), 'PV');
    }

    public function test_el_producto_guarda_su_precio_2_y_3_y_en_cero_queda_sin_ese_precio(): void
    {
        $this->post(route('productos.store'), ['promocion' => 0, 'pronom' => 'FIERRO 1/2', 'umecod' => 'NIU', 'propun' => 40,
            'precio2' => 37.5, 'precio3' => 0, 'cat_id' => DB::table('categorias')->where('id_empresa_negocio', $this->sucursal)->value('cat_id')])
            ->assertSessionHasNoErrors();

        $fierro = DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('pronom', 'FIERRO 1/2')->first();
        $this->assertEquals(37.5, (float) $fierro->precio2);
        $this->assertNull($fierro->precio3);
        $this->get(route('productos.edit', $fierro->IdProducto))->assertOk()->assertSee('value="37.50"', false);
    }

    public function test_el_punto_de_venta_ofrece_el_precio_2_y_3_solo_si_tienen_valor(): void
    {
        $cemento = $this->producto('CEMENTO SOL', 32, 50);
        DB::table('productos')->where('IdProducto', $cemento->IdProducto)->update(['precio2' => 29]);

        $this->assertSame([['nombre' => 'Precio x Mayor', 'precio' => 29.0]],
            CatalogoVenta::porIds($this->usuario, [$cemento->IdProducto])[$cemento->IdProducto]['precios']);
    }

    public function test_el_vendedor_arma_pedidos_sin_caja_y_no_puede_cobrar(): void
    {
        $vendedor = $this->usuarioConRol(5);
        $cemento = $this->producto('CEMENTO SOL', 32, 50);

        $this->actingAs($vendedor)->get(route('pv.index'))->assertOk()->assertSee('GUARDAR PEDIDO')->assertDontSee('REGISTRAR<span', false);

        $this->actingAs($vendedor)->postJson(route('proformas.guardar'), ['origen' => 'PV', 'tdicod' => '1', 'clinum' => '00000000',
            'clinom' => 'VARIOS', 'items' => [['id' => $cemento->IdProducto, 'cantidad' => 2, 'precio' => 32]]])
            ->assertJson(['estado' => 'success']);
        $this->assertSame(1, DB::table('proformas')->where('IdUsuario', $vendedor->IdUsuario)->where('estado', 'PENDIENTE')->count());

        $this->actingAs($vendedor)->postJson(route('pv.registrar'), $this->venta([[$cemento, 1]]))->assertForbidden();
        $this->assertSame(0, DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->count());
    }

    public function test_el_cajero_sin_turno_sigue_sin_poder_entrar_al_punto_de_venta(): void
    {
        $cajero = $this->usuarioConRol(4);

        $this->actingAs($cajero)->get(route('pv.index'))->assertRedirect(route('turnos.index'));
    }

    private function excelProductos(string $contenido)
    {
        $ruta = tempnam(sys_get_temp_dir(), 'prod').'.csv';
        file_put_contents($ruta, $contenido);

        return $this->post(route('productos.importar'), ['archivo' => new UploadedFile($ruta, 'productos.csv', 'text/csv', null, true)]);
    }

    public function test_el_excel_acepta_hasta_fin_de_mes_el_mismo_nombre_con_otro_codigo(): void
    {
        $this->excelProductos("Codigo,Nombre,Precio venta\nP1,CEMENTO SOL,32\nP2,CEMENTO SOL,35\n")->assertSessionHasNoErrors();

        $this->assertSame(['P1' => 32.0, 'P2' => 35.0], DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('pronom', 'CEMENTO SOL')
            ->orderBy('procod')->pluck('propun', 'procod')->map(fn ($p) => (float) $p)->all());
    }

    public function test_desde_diciembre_el_excel_vuelve_a_actualizar_por_nombre(): void
    {
        $this->travelTo('2026-12-01 09:00');
        $this->excelProductos("Codigo,Nombre,Precio venta\nP1,CEMENTO SOL,32\nP2,CEMENTO SOL,35\n")->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('pronom', 'CEMENTO SOL')->count());
    }

    public function test_en_comercio_no_se_crean_insumos_ni_preparados(): void
    {
        $cliente = new Cliente(['rubro' => 'GENERAL']);
        $cliente->id = 999999;
        app()->instance('tenancy.cliente', $cliente);

        $this->assertSame([0, 6], Rubros::tiposProducto(), 'Comercio: producto y combo.');
        $this->post(route('productos.store'), ['promocion' => 4, 'pronom' => 'HARINA', 'umecod' => 'KGM', 'costo' => 3])->assertSessionHasErrors('promocion');
    }
}
