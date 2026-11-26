<?php

namespace Tests\Flujos;

use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Cambio de producto (cambio sí, devolución no), precios por tipo de cliente y escanear al contar inventario */
class CambiosPreciosConteoTest extends FlujoTestCase
{
    private function ventaDe($producto, float $cantidad = 1): int
    {
        return VentaDirecta::registrar($this->usuario, $this->venta([[$producto, $cantidad]], '13'), 'PV');
    }

    private function linea(int $venta): int
    {
        return (int) DB::table('cpe_detalle')->where('IdCpe_cabecera', $venta)->value('IdCpe_detalle');
    }

    public function test_cambio_de_talla_del_mismo_precio_mueve_el_stock_sin_cobrar_nada(): void
    {
        $t4 = $this->producto('POLO NIÑO T4', 20, 5);
        $t6 = $this->producto('POLO NIÑO T6', 20, 3);
        $venta = $this->ventaDe($t4);   // T4: 5 → 4

        $this->get(route('cambios.create', $venta))->assertOk()->assertSeeText('Cambio de producto');
        $r = $this->postJson(route('cambios.store', $venta), ['devuelve' => [['linea' => $this->linea($venta), 'cantidad' => 1]], 'lleva' => [['id' => $t6->IdProducto, 'cantidad' => 1]]])
            ->assertOk()->json();

        $this->assertTrue($r['ok']);
        $this->assertEquals(0.0, $r['diferencia']);
        $this->assertEquals(5.0, $this->stock($t4), 'Lo devuelto vuelve al stock.');
        $this->assertEquals(2.0, $this->stock($t6), 'Lo que se lleva sale del stock.');
        $this->assertSame(1, DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->count(), 'Sin diferencia no hay otra venta.');
        $this->get($r['ver'])->assertOk()->assertSeeText('CB-000001');
    }

    public function test_si_se_lleva_algo_mas_caro_se_cobra_solo_la_diferencia_y_no_se_devuelve_dinero(): void
    {
        $polo = $this->producto('POLO SIMPLE', 20, 5);
        $casaca = $this->producto('CASACA', 50, 2);
        $venta = $this->ventaDe($polo);

        $this->postJson(route('cambios.store', $venta), ['devuelve' => [['linea' => $this->linea($venta), 'cantidad' => 1]], 'lleva' => [['id' => $this->producto('MEDIAS', 5, 9)->IdProducto, 'cantidad' => 1]]])
            ->assertStatus(422)->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'no se devuelve dinero'));

        $r = $this->postJson(route('cambios.store', $venta), ['devuelve' => [['linea' => $this->linea($venta), 'cantidad' => 1]], 'lleva' => [['id' => $casaca->IdProducto, 'cantidad' => 1]],
            'medio' => $this->efectivo])->assertOk()->json();

        $this->assertEquals(30.0, $r['diferencia']);
        $dif = DB::table('cambios')->value('IdCpe_diferencia');
        $this->assertEquals(30.0, (float) $this->cabecera($dif)->ccaitv, 'La diferencia sale con su propio comprobante.');
        $this->assertEquals(1.0, $this->stock($casaca));
    }

    public function test_lo_cambiado_no_se_devuelve_dos_veces_y_la_venta_ya_no_se_anula_entera(): void
    {
        $polo = $this->producto('POLO ROJO', 20, 5);
        $otro = $this->producto('POLO AZUL', 20, 5);
        $venta = $this->ventaDe($polo);
        $datos = ['devuelve' => [['linea' => $this->linea($venta), 'cantidad' => 1]], 'lleva' => [['id' => $otro->IdProducto, 'cantidad' => 1]]];
        $this->postJson(route('cambios.store', $venta), $datos)->assertOk();

        $this->postJson(route('cambios.store', $venta), $datos)->assertStatus(422)->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'solo quedan 0'));
        $this->postJson(route('ventas.anular', $venta), ['motivo' => 'PRUEBA DE ANULACION'])
            ->assertJson(['success' => false, 'message' => 'Esta venta tuvo un cambio de producto: ya no se puede anular completa.']);
        $this->assertNull($this->cabecera($venta)->ccabaj);
    }

    public function test_el_cliente_por_mayor_llega_a_la_caja_con_su_lista_de_precios(): void
    {
        $this->post(route('contactos.guardar', 'clientes'), ['tdicod' => '1', 'doc' => '45678912', 'nombre' => 'BODEGA DOÑA ROSA', 'lista_precio' => 'mayor', 'activo' => true])
            ->assertOk();
        $this->assertSame('mayor', DB::table('cliente')->where('clinum', '45678912')->value('lista_precio'));

        $this->getJson(route('cobros.cliente', '45678912'))->assertOk()->assertJsonPath('lista', 'mayor');
        DB::table('productos')->where('IdProducto', $this->producto('ACEITE PRIMOR', 9, 10)->IdProducto)->update(['precio2' => 8.2]);
        $this->getJson(route('pos.productos', ['q' => 'aceite primor']))->assertOk()->assertJsonPath('0.precio2', 8.2);
        $this->get(route('contactos.index', 'clientes'))->assertOk()->assertSeeText('POR MAYOR');
    }

    public function test_el_inventario_ciego_escanea_codigos_y_las_cajas_suman_su_factor(): void
    {
        $gaseosa = $this->producto('GASEOSA 500ML', 2.5, 0);
        DB::table('productos')->where('IdProducto', $gaseosa->IdProducto)->update(['codigo_barra' => '7750182002363']);
        DB::table('producto_presentacion')->insert(['IdProducto' => $gaseosa->IdProducto, 'umecod' => 'BX', 'nombre' => 'Caja x12', 'factor' => 12,
            'precio' => 28, 'estado' => 1, 'codigo_barra' => '17750182002360']);

        $this->get(route('conteo_ciego.create'))->assertOk()->assertSeeText('Escanear y contar')
            ->assertSee('7750182002363', false)->assertSee('17750182002360', false);
    }
}
