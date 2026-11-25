<?php

namespace Tests\Flujos;

use App\Models\Producto;
use App\Support\ProveedoresPrecios;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Cobrar el fiado por WhatsApp, Precio x Mayor automático por cantidad y a qué proveedor comprarle más barato */
class FiadoMayorProveedoresTest extends FlujoTestCase
{
    public function test_el_fiado_se_cobra_por_whatsapp_con_el_saldo_de_todas_sus_compras_y_como_pagar(): void
    {
        DB::table('medios_pagos')->insert(['nom_med_pag' => 'YAPE', 'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal, 'qr' => 'imagenes/qr/yape.png']);
        foreach ([60, 25] as $precio) {
            $datos = $this->venta([[$this->producto('COCINA '.$precio, $precio, 5), 1]], '01', ['fecVen' => now()->addDays(7)->toDateString(), 'telefono' => '987654321']);
            $datos['estadopago'] = $this->credito;
            $datos['id_med_pag'] = $datos['mon_med_pag'] = [];
            $datos['paga'] = 0;
            VentaDirecta::registrar($this->usuario, $datos, 'PV');
        }

        $this->get(route('cuentas.index', 'cobrar'))->assertOk()->assertSee(route('cuentas.whatsapp', ['tipo' => 'cobrar', 'documento' => '20100070970']), false);
        $destino = $this->get(route('cuentas.whatsapp', ['tipo' => 'cobrar', 'documento' => '20100070970']))->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://wa.me/51987654321?text=', $destino);
        $texto = rawurldecode(substr($destino, strpos($destino, '=') + 1));
        $this->assertStringContainsString('*Total por pagar: S/ 85.00*', $texto, 'Suma todas sus compras pendientes.');
        $this->assertStringContainsString('Puedes pagar por Yape', $texto);
        $this->assertStringContainsString('imagenes/qr/yape.png', $texto, 'Va el enlace a su QR.');
    }

    public function test_el_precio_x_mayor_se_cobra_solo_desde_la_cantidad_y_no_pide_permiso(): void
    {
        $this->post(route('productos.store'), ['promocion' => 0, 'pronom' => 'JABON FORTUNA', 'propun' => 1.5, 'precio2' => 1.2, 'mayor_desde' => 12, 'umecod' => 'NIU'])
            ->assertSessionHasNoErrors();
        $jabon = Producto::where('id_empresa_negocio', $this->sucursal)->where('pronom', 'JABON FORTUNA')->first();
        $this->assertEquals(12.0, (float) $jabon->mayor_desde);

        // La caja recibe desde cuántas unidades va el Precio x Mayor
        $catalogo = $this->getJson(route('pos.productos', ['q' => 'jabon fortuna']))->assertOk()->json();
        $this->assertSame(['desde' => 12, 'precio' => 1.2], $catalogo[0]['mayor']);

        // Sin Precio x Mayor no queda la cantidad
        $this->put(route('productos.update', $jabon->IdProducto), ['promocion' => 0, 'pronom' => 'JABON FORTUNA', 'propun' => 1.5, 'precio2' => '', 'mayor_desde' => 12, 'umecod' => 'NIU']);
        $this->assertNull(DB::table('productos')->where('IdProducto', $jabon->IdProducto)->value('mayor_desde'));
    }

    public function test_compara_proveedores_y_dice_cuanto_se_ahorra_con_el_mas_barato(): void
    {
        $arroz = $this->producto('ARROZ COSTEÑO', 4, 0);
        $compra = function (string $ruc, string $razon, float $costo, string $fecha) use ($arroz) {
            $prov = DB::table('proveedor')->insertGetId(['tdicod' => '6', 'prov_ruc' => $ruc, 'prov_raz' => $razon, 'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal]);
            $cab = DB::table('compras_cabecera')->insertGetId(['IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal, 'tdocod' => '01',
                'com_doc_ser' => 'F001', 'com_doc_num' => (string) random_int(1, 99999), 'prov_id' => $prov, 'prov_num' => $ruc, 'com_fec' => $fecha, 'com_fec_ing' => $fecha,
                'mon_id' => 'PEN', 'total_com' => $costo * 10, 'id_almacen' => $this->almacen, 'est_compra' => 'Registrado', 'IdUsuario' => $this->usuario->IdUsuario]);
            DB::table('compras_detalle')->insert(['com_cab_id' => $cab, 'pro_id' => $arroz->IdProducto, 'ume_cod' => 'NIU', 'tip_igv' => '10', 'cantidad' => 10,
                'pre_uni' => $costo, 'precio_costo' => $costo, 'factor' => 1, 'total' => $costo * 10, 'IdEmpresa' => $this->usuario->IdEmpresa]);
        };
        $compra('20111111111', 'DISTRIBUIDORA BARATA SAC', 3.10, now()->subDays(20)->toDateString());
        $compra('20222222222', 'MAYORISTA CARO SAC', 3.40, now()->subDays(2)->toDateString());

        $c = ProveedoresPrecios::comparar($this->sucursal)->first();
        $this->assertSame('DISTRIBUIDORA BARATA SAC', $c->mejor->proveedor);
        $this->assertSame('MAYORISTA CARO SAC', $c->ultimo->proveedor);
        $this->assertEquals(0.30, $c->ahorro);

        $this->get(route('compras.comparar'))->assertOk()->assertSeeText('Ahorras S/ 0.30 por unidad con DISTRIBUIDORA BARATA SAC');
        $this->getJson(route('compras.productos', ['q' => 'arroz']))->assertOk()
            ->assertJsonPath('0.mejor.proveedor', 'DISTRIBUIDORA BARATA SAC')->assertJsonPath('0.mejor.costo', 3.1);
    }
}
