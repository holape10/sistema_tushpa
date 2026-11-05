<?php

namespace Tests\Flujos;

use App\Http\Controllers\TurnoController;
use App\Models\EmpresaNegocio;
use App\Models\Producto;
use App\Support\AnulacionVenta;
use App\Support\CajaSinInternet;
use App\Support\CartaMesa;
use App\Support\Sunat\CambioFecha;
use App\Support\Sunat\RescateEnvios;
use App\Support\VentaDirecta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** El cierre de caja, la caja sin internet, los pedidos de la carta QR y los plazos de SUNAT */
class CajaYSunatTest extends FlujoTestCase
{
    public function test_el_cierre_de_caja_suma_las_ventas_y_no_cuenta_las_anuladas(): void
    {
        $plato = $this->producto('POLLO', 50, 20);
        VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]]), 'PV');
        VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 2]]), 'PV');
        $anulada = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 3]]), 'PV');
        AnulacionVenta::anular($anulada, 'PRUEBA', $this->usuario->IdUsuario);

        $r = TurnoController::resumen($this->turno);

        $this->assertEquals(150.00, (float) collect($r['ventasPorMedio'])->sum('monto'));

        $this->post(route('turnos.cerrar'), ['montocierre' => 150])->assertRedirect();
        $this->assertSame('CERRADO', $this->turno->fresh()->estado);
        $this->assertEquals(150.00, (float) DB::table('turno_medio_pago')->where('id_turno', $this->turno->id_turno)->sum('monto'));
    }

    /** Una venta hecha sin internet, como la sube la PC de caja */
    private function ventaSinInternet(array $serie, int $numero, float $precio, int $productoId, ?string $uuid = null): array
    {
        return ['uuid' => $uuid ?? (string) Str::uuid(), 'tdocod' => '03', 'serie' => $serie['serie'], 'numero' => $numero,
            'fecha' => now()->format('Y-m-d H:i:s'), 'id_turno' => $this->turno->id_turno, 'origen' => 'PV', 'tdicod' => '1',
            'clinum' => '00000000', 'clinom' => 'VENTA AL PORTADOR', 'total' => $precio, 'paga' => $precio,
            'items' => [['id' => $productoId, 'descripcion' => 'PRODUCTO', 'cantidad' => 1, 'precio' => $precio, 'presentacion' => null]],
            'pagos' => [['medio' => $this->efectivo, 'monto' => $precio]]];
    }

    public function test_un_movimiento_de_caja_con_un_monto_gigante_se_rechaza_sin_error_del_sistema(): void
    {
        $this->from(route('turnos.index'))->post(route('turnos.movimiento'), ['tip_caj_id' => '007', 'mov_com' => 'PRUEBA', 'importe' => 1243124124])
            ->assertRedirect(route('turnos.index'))->assertSessionHasErrors('importe');
        $this->assertSame(0, DB::table('movimientoscaja')->where('mov_com', 'PRUEBA')->count());
    }

    public function test_la_caja_sin_internet_nunca_duplica_una_venta_ni_un_numero(): void
    {
        $plato = $this->producto('ARROZ CHAUFA', 20, 10);
        $serie = CajaSinInternet::series($this->sucursal, 'CAJA01')['03'];
        $venta = $this->ventaSinInternet($serie, 1, 20, $plato->IdProducto);

        $primera = CajaSinInternet::registrar($this->usuario, 'CAJA01', $venta);
        $otraVez = CajaSinInternet::registrar($this->usuario, 'CAJA01', $venta);   // se cortó la red y la PC la reenvía

        $this->assertTrue($primera['ok']);
        $this->assertSame($primera['id'], $otraVez['id']);
        $this->assertSame(1, DB::table('cpe_cabecera')->where('serdoc', $serie['serie'])->count());
        $this->assertEquals(9.0, $this->stock($plato));

        // Otra venta con el mismo número (otro código): se rechaza, no se duplica el comprobante
        $mismoNumero = CajaSinInternet::registrar($this->usuario, 'CAJA01', $this->ventaSinInternet($serie, 1, 20, $plato->IdProducto));
        $this->assertFalse($mismoNumero['ok']);
        $this->assertSame(1, DB::table('cpe_cabecera')->where('serdoc', $serie['serie'])->count());

        // Otra caja sin internet tiene su propia serie: nunca choca con la primera
        $this->assertNotSame($serie['serie'], CajaSinInternet::series($this->sucursal, 'CAJA02')['03']['serie']);
    }

    public function test_el_pedido_de_la_carta_qr_corregido_por_el_mozo_llega_a_la_mesa_con_lo_que_quedo(): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['carta_activa' => 1, 'carta_pedido_mesa' => 1]);
        $piso = DB::table('pisos')->insertGetId(['pis_nom' => 'SALON', 'id_empresa_negocio' => $this->sucursal]);
        $mesaId = DB::table('mesas')->insertGetId(['mes_nom' => 'MESA 05', 'pis_id' => $piso, 'mes_est' => 'Libre',
            'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal]);
        $ceviche = $this->producto('CEVICHE CARTA', 30, 10);
        $cerveza = $this->producto('CERVEZA CARTA', 10, 10);
        $negocio = EmpresaNegocio::find($this->sucursal);
        $mesa = DB::table('mesas')->where('mes_id', $mesaId)->first();

        $r = CartaMesa::solicitar($negocio, $mesa, 'PEDIDO', [['id' => $ceviche->IdProducto, 'cantidad' => 1], ['id' => $cerveza->IdProducto, 'cantidad' => 3]]);
        $sol = DB::table('carta_solicitudes')->where('token', $r['token'])->value('id');
        CartaMesa::aceptar($this->usuario, $sol, [1, 2]);   // el cliente se pasó en una cerveza

        $pedido = DB::table('pedidos')->where('mes_id', $mesaId)->where('ped_est', 'Aperturado')->first();
        $this->assertEquals(50.00, (float) $pedido->ped_tot);
        $this->assertEquals(2.0, (float) DB::table('pedidos_detalle')->where('ped_id', $pedido->ped_id)->where('IdProducto', $cerveza->IdProducto)->sum('ped_det_can'));
        $this->assertSame('Ocupado', DB::table('mesas')->where('mes_id', $mesaId)->value('mes_est'));
        $this->assertEquals(50.00, CartaMesa::consumo($mesaId)['total']);
    }

    public function test_el_menu_pedido_desde_la_carta_qr_llega_a_la_cocina_con_la_entrada_que_eligio_el_cliente(): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['carta_activa' => 1, 'carta_pedido_mesa' => 1]);
        $piso = DB::table('pisos')->insertGetId(['pis_nom' => 'SALON', 'id_empresa_negocio' => $this->sucursal]);
        $mesaId = DB::table('mesas')->insertGetId(['mes_nom' => 'MESA 07', 'pis_id' => $piso, 'mes_est' => 'Libre',
            'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal]);
        $menu = $this->producto('MENU EJECUTIVO', 15, 10);
        DB::table('productos')->where('IdProducto', $menu->IdProducto)->update(['lleva_entrada' => 1]);
        $sopa = $this->producto('SOPA DE CASA', 0, 10);
        DB::table('productos')->where('IdProducto', $sopa->IdProducto)->update(['promocion' => Producto::OPCION]);
        $negocio = EmpresaNegocio::find($this->sucursal);
        $mesa = DB::table('mesas')->where('mes_id', $mesaId)->first();

        try {
            CartaMesa::solicitar($negocio, $mesa, 'PEDIDO', [['id' => $menu->IdProducto, 'cantidad' => 2]]);
            $this->fail('Sin elegir la entrada no debe pasar el pedido');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('MENU EJECUTIVO', $e->getMessage());
        }

        $r = CartaMesa::solicitar($negocio, $mesa, 'PEDIDO', [['id' => $menu->IdProducto, 'cantidad' => 2, 'opciones' => ['entrada' => [$sopa->IdProducto]]]]);
        CartaMesa::aceptar($this->usuario, DB::table('carta_solicitudes')->where('token', $r['token'])->value('id'), []);

        $pedido = DB::table('pedidos')->where('mes_id', $mesaId)->where('ped_est', 'Aperturado')->first();
        $detalle = DB::table('pedidos_detalle')->where('ped_id', $pedido->ped_id)->first();
        $this->assertEquals(30.00, (float) $pedido->ped_tot);
        $this->assertSame([$sopa->IdProducto => 1], array_map('intval', json_decode($detalle->opciones, true)['kardex']));
        $this->assertStringContainsString('SOPA DE CASA', CartaMesa::consumo($mesaId)['items'][0]['nombre']);
    }

    public function test_corregir_la_fecha_de_una_factura_vencida_mueve_tambien_su_vencimiento(): void
    {
        $plato = $this->producto('BANQUETE', 100, 10);
        $datos = $this->venta([[$plato, 1]], '01', ['fecVen' => '2026-11-05']);
        $datos['estadopago'] = $this->credito;
        $datos['id_med_pag'] = $datos['mon_med_pag'] = [];
        $datos['fecEmi'] = '2026-10-20';   // hoy es 30/10: ya pasó su plazo de 3 días
        $id = VentaDirecta::registrar($this->usuario, $datos, 'PV');

        $r = CambioFecha::aFechaValida($id, $this->sucursal, $this->usuario);

        $this->assertSame('2026-10-27', $r['nueva']);   // último día que SUNAT todavía acepta
        $cab = $this->cabecera($id);
        $this->assertSame('2026-11-12', $cab->ccafve);   // mantiene sus 16 días para pagar
        $this->assertSame('2026-11-12', (string) DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $id)->value('fec_ven'));
    }

    public function test_el_rescate_envia_las_facturas_en_su_ultimo_dia_de_plazo_y_no_las_vencidas(): void
    {
        $plato = $this->producto('MENU RESCATE', 10, 50);
        $ids = [];
        foreach (['2026-10-30' => 'hoy', '2026-10-29' => 'ayer', '2026-10-27' => 'ultimo dia', '2026-10-26' => 'vencida'] as $fecha => $nombre) {
            $ids[$nombre] = VentaDirecta::registrar($this->usuario, ['fecEmi' => $fecha] + $this->venta([[$plato, 1]], '01'), 'PV');
        }

        $porEnviar = RescateEnvios::porVencer($this->sucursal, Carbon::parse('2026-10-30'))->pluck('IdCpe_cabecera')->all();

        $this->assertContains($ids['ultimo dia'], $porEnviar);
        $this->assertContains($ids['ayer'], $porEnviar);
        $this->assertNotContains($ids['vencida'], $porEnviar);
        $this->assertNotContains($ids['hoy'], $porEnviar);   // la de hoy todavía tiene tiempo: la manda la caja
    }
}
