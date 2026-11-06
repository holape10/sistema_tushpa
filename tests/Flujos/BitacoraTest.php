<?php

namespace Tests\Flujos;

use App\Models\User;
use App\Support\Bitacora;
use App\Support\LineaTiempo;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Bitácora: quién cambió qué y qué había antes; nunca guarda claves y no deja registros de lo que no se hizo */
class BitacoraTest extends FlujoTestCase
{
    private function ultima(string $accion): ?object
    {
        return DB::table('bitacora')->where('accion', $accion)->orderByDesc('id')->first();
    }

    /** @return array<string, mixed> */
    private function cambios(object $fila): array
    {
        return json_decode((string) $fila->cambios, true) ?: [];
    }

    private function datosUsuario(array $mas = []): array
    {
        return $mas + ['id_empresa_negocio' => $this->sucursal, 'tdicod' => '1', 'nombre_completo' => 'rosa caja prueba', 'estusu' => '1',
            'asistencia' => '0', 'email' => 'rosa'.$this->sucursal, 'role_id' => '4', 'password' => 'clave-inicial', 'password_confirmation' => 'clave-inicial'];
    }

    public function test_editar_el_precio_guarda_quien_lo_hizo_y_el_precio_anterior(): void
    {
        $lomo = $this->producto('LOMO SALTADO', 20);

        $this->put(route('productos.update', $lomo->IdProducto), ['pronom' => 'LOMO SALTADO', 'propun' => 25])->assertRedirect(route('productos.index'));

        $fila = $this->ultima('producto.editar');
        $this->assertNotNull($fila);
        $this->assertSame([(int) $this->usuario->IdUsuario, 'producto', (string) $lomo->IdProducto], [(int) $fila->IdUsuario, $fila->referencia_tipo, $fila->referencia_id]);
        $this->assertSame('Administrador', $fila->rol);
        $this->assertSame(['etiqueta' => 'Precio de venta', 'antes' => '20.00', 'despues' => '25.00'], $this->cambios($fila)['propun']);
        $this->assertArrayNotHasKey('pronom', $this->cambios($fila), 'Solo se anota lo que cambió.');

        // Guardar sin cambiar nada no llena la bitácora
        $this->put(route('productos.update', $lomo->IdProducto), ['pronom' => 'LOMO SALTADO', 'propun' => 25]);
        $this->assertSame(1, DB::table('bitacora')->where('accion', 'producto.editar')->count());
    }

    public function test_cambiar_el_rol_y_la_clave_de_un_usuario_es_alerta_y_la_clave_no_se_guarda(): void
    {
        $this->post(route('usuarios.store'), $this->datosUsuario())->assertRedirect(route('usuarios.index'));
        $rosa = User::where('email', 'rosa'.$this->sucursal)->firstOrFail();
        $this->assertNotNull($this->ultima('usuario.crear'));

        $this->put(route('usuarios.update', $rosa->IdUsuario), $this->datosUsuario(['role_id' => '2', 'password' => 'clave-nueva-123',
            'password_confirmation' => 'clave-nueva-123']))->assertRedirect(route('usuarios.index'));

        $fila = $this->ultima('usuario.editar');
        $cambios = $this->cambios($fila);
        $this->assertTrue((bool) $fila->alerta);
        $this->assertSame(['Cajero', 'Administrador'], [$cambios['rol']['antes'], $cambios['rol']['despues']]);
        $this->assertSame(['etiqueta' => 'Contraseña', 'antes' => null, 'despues' => '(cambió)'], $cambios['password']);
        $this->assertStringNotContainsString('clave-nueva-123', $fila->cambios);
        $this->assertStringNotContainsString($rosa->fresh()->password, $fila->cambios, 'Ni siquiera el hash de la clave.');
    }

    public function test_un_ingreso_fallido_queda_registrado_sin_la_clave_escrita(): void
    {
        Auth::logout();

        $this->post('/login', ['email' => $this->usuario->email, 'password' => 'clave-equivocada'])->assertSessionHasErrors('email');

        $fila = $this->ultima('sesion.fallido');
        $this->assertNotNull($fila);
        $this->assertNull($fila->IdUsuario, 'El intento no se le atribuye al dueño de la cuenta.');
        $this->assertSame(['usuario', (string) $this->usuario->IdUsuario], [$fila->referencia_tipo, $fila->referencia_id]);
        $this->assertStringNotContainsString('clave-equivocada', json_encode((array) $fila));

        $this->post('/login', ['email' => $this->usuario->email, 'password' => 'clave-de-prueba'])->assertRedirect();
        $this->assertSame((int) $this->usuario->IdUsuario, (int) $this->ultima('sesion.ingreso')->IdUsuario);
    }

    public function test_anular_una_nota_de_venta_queda_como_alerta_con_su_motivo(): void
    {
        $plato = $this->producto('JUANE', 15, 10);
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 2]], '13'), 'PV');

        $this->postJson(route('ventas.anular', $id), ['motivo' => 'ERROR DEL CAJERO'])->assertJson(['success' => true]);

        $fila = $this->ultima('venta.anular');
        $this->assertTrue((bool) $fila->alerta);
        $this->assertSame(['venta', (string) $id], [$fila->referencia_tipo, $fila->referencia_id]);
        $this->assertStringContainsString('S/ 30.00', $fila->descripcion);
        $this->assertSame('ERROR DEL CAJERO', json_decode($fila->datos, true)['motivo']);
    }

    /** Una compra al contado de un producto */
    private function compra(int $productoId, float $cantidad, float $costo): array
    {
        return ['tdocod' => '01', 'serie' => 'F001', 'numero' => '123', 'fecEmi' => now()->toDateString(), 'fecIng' => now()->toDateString(),
            'estadopago' => $this->contado, 'moneda' => 'PEN', 'id_almacen' => $this->almacen, 'prov_tdicod' => '6', 'prov_num' => '20100070970',
            'prov_nom' => 'PROVEEDOR DE PRUEBA SAC', 'items' => [['id' => $productoId, 'tip_igv' => '10', 'cantidad' => $cantidad, 'costo' => $costo]]];
    }

    public function test_editar_y_anular_una_compra_guarda_los_productos_de_antes_y_de_despues(): void
    {
        $gaseosa = $this->producto('INCA KOLA 500ML', 5);
        $this->postJson(route('compras.store'), $this->compra($gaseosa->IdProducto, 12, 3))->assertJson(['estado' => 'success']);
        $id = (int) DB::table('compras_cabecera')->where('id_empresa_negocio', $this->sucursal)->value('com_cab_id');
        $this->assertSame((string) $id, $this->ultima('compra.registrar')->referencia_id);

        $this->putJson(route('compras.update', $id), $this->compra($gaseosa->IdProducto, 10, 3))->assertJson(['estado' => 'success']);

        $fila = $this->ultima('compra.editar');
        $this->assertTrue((bool) $fila->alerta);
        $productos = $this->cambios($fila)['productos'];
        $this->assertSame(['INCA KOLA 500ML × 10 a 3.00'], $productos['agrego']);
        $this->assertSame(['INCA KOLA 500ML × 12 a 3.00'], $productos['quito']);

        $this->post(route('compras.anular', $id))->assertSessionHas('success');
        $this->assertStringStartsWith('Anuló la compra F001-', $this->ultima('compra.anular')->descripcion);
    }

    public function test_el_inventario_fisico_anota_cada_diferencia_y_el_faltante_es_alerta(): void
    {
        $gaseosa = $this->producto('INCA KOLA 500ML', 5, 24);
        $agua = $this->producto('AGUA SAN LUIS', 2, 10);

        $this->post(route('inventarios.store'), ['id_almacen' => $this->almacen, 'fecha' => now()->toDateString(), 'items' => json_encode([
            ['IdProducto' => $gaseosa->IdProducto, 'cantidad' => 20], ['IdProducto' => $agua->IdProducto, 'cantidad' => 10],
        ])])->assertSessionHasNoErrors();

        $fila = $this->ultima('almacen.inventario');
        $this->assertTrue((bool) $fila->alerta);
        $this->assertSame(['INCA KOLA 500ML: 24 → 20'], json_decode($fila->datos, true)['diferencias'], 'Lo que cuadró no se anota.');
    }

    public function test_cambiar_la_clave_sol_de_la_empresa_es_alerta_y_no_guarda_la_clave(): void
    {
        $this->patch(route('empresas.update', $this->usuario->IdEmpresa), ['nomEmpresa' => 'RESTAURANTE DE PRUEBA SAC', 'dirEmpresa' => 'JR. PRUEBA 123',
            'envio' => 1, 'txtWsUsuario' => 'USUARIO1', 'txtWsContrasena' => 'CLAVE-SOL-SECRETA'])->assertSessionHasNoErrors();

        $fila = $this->ultima('config.empresa');
        $this->assertTrue((bool) $fila->alerta);
        $this->assertSame('(cambió)', $this->cambios($fila)['claveSunat']['despues']);
        $this->assertStringNotContainsString('CLAVE-SOL-SECRETA', $fila->cambios);
        $this->assertStringNotContainsString((string) DB::table('empresa')->where('IdEmpresa', $this->usuario->IdEmpresa)->value('claveSunat'), $fila->cambios,
            'Tampoco la clave cifrada.');
    }

    public function test_si_la_operacion_se_deshace_su_registro_tambien(): void
    {
        try {
            DB::transaction(function () {
                Bitacora::registrar('producto.editar', 'Cambio que al final no se guardó');
                throw new \RuntimeException('falla después de registrar');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, DB::table('bitacora')->where('descripcion', 'Cambio que al final no se guardó')->count());
    }

    public function test_solo_el_administrador_ve_y_exporta_la_bitacora(): void
    {
        $lomo = $this->producto('LOMO', 20);
        $this->put(route('productos.update', $lomo->IdProducto), ['pronom' => 'LOMO', 'propun' => 22]);

        $this->get(route('bitacora.index'))->assertOk()->assertSee('Editó el producto LOMO')->assertSee('Precio de venta');
        $this->get(route('bitacora.index', ['q' => 'no existe nada asi']))->assertOk()->assertDontSee('Editó el producto LOMO');
        $this->get(route('bitacora.exportar'))->assertOk()->assertDownload();

        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        $this->actingAs($cajero)->get(route('bitacora.index'))->assertForbidden();
        $this->actingAs($cajero)->get(route('bitacora.exportar'))->assertForbidden();
    }

    public function test_la_linea_de_tiempo_muestra_los_cambios_de_la_bitacora(): void
    {
        $lomo = $this->producto('LOMO', 20);
        $this->put(route('productos.update', $lomo->IdProducto), ['pronom' => 'LOMO', 'propun' => 22]);

        $evento = LineaTiempo::eventos($this->sucursal, now()->toDateString())->firstWhere('tipo', 'cambios');

        $this->assertSame('Editó el producto LOMO', $evento['titulo']);
        $this->assertSame('Precio de venta: 20.00 → 22.00', $evento['detalle']);
    }
}
