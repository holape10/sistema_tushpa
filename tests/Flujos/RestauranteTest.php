<?php

namespace Tests\Flujos;

use App\Models\Producto;
use Illuminate\Support\Facades\DB;

/**
 * El día a día del restaurante por las mismas pantallas que usan el mozo y la caja: abrir la mesa, pedir, mandar a cocina,
 * cobrar (todo, por cuentas separadas, con cortesía, descuento y propina) y que la mesa quede libre.
 */
class RestauranteTest extends FlujoTestCase
{
    private int $mesa;

    protected function setUp(): void
    {
        parent::setUp();
        $piso = DB::table('pisos')->insertGetId(['pis_nom' => 'SALON', 'id_empresa_negocio' => $this->sucursal]);
        $this->mesa = DB::table('mesas')->insertGetId(['mes_nom' => 'MESA 01', 'pis_id' => $piso, 'mes_est' => 'Libre',
            'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal]);
    }

    /** El mozo abre la mesa, pide y manda la comanda; devuelve el pedido */
    private function pedirEnMesa(array $platos): int
    {
        $this->postJson(route('comandas.set_servicio'), ['order_type' => 'salon', 'mesa_id' => $this->mesa, 'mesa_nombre' => 'MESA 01'])->assertOk();
        foreach ($platos as [$producto, $veces]) {
            for ($i = 0; $i < $veces; $i++) {
                $this->postJson(route('comandas.add_to_cart'), ['id' => $producto->IdProducto])->assertOk();
            }
        }
        $this->postJson(route('comandas.enviar'))->assertOk();

        return (int) DB::table('pedidos')->where('mes_id', $this->mesa)->where('ped_est', 'Aperturado')->value('ped_id');
    }

    /** La caja cobra el pedido (o una parte: separadas = [clave => cantidad]) */
    private function cobrar(int $pedId, float $monto, array $extra = []): array
    {
        return $this->postJson(route('cobros.registrar'), $extra + [
            'ped_id' => $pedId, 'tdocod' => '03', 'estadopago' => $this->contado, 'fecEmi' => now()->toDateString(),
            'tdicod' => '1', 'clinum' => '00000000', 'clinom' => 'VENTA AL PORTADOR',
            'id_med_pag' => [$this->efectivo], 'mon_med_pag' => [$monto], 'paga' => $monto,
        ])->assertOk()->json();
    }

    private function clave(Producto $p, float $precio): string
    {
        return $p->IdProducto.'||'.substr(md5(''), 0, 8).'|'.number_format($precio, 2, '.', '');
    }

    public function test_mesa_completa_pedir_mandar_a_cocina_y_cobrar_libera_la_mesa(): void
    {
        $ceviche = $this->producto('CEVICHE', 35, 10);
        $cerveza = $this->producto('CERVEZA', 10, 24);

        $pedId = $this->pedirEnMesa([[$ceviche, 1], [$cerveza, 2]]);
        $this->assertSame('Ocupado', DB::table('mesas')->where('mes_id', $this->mesa)->value('mes_est'));
        $this->assertSame(2, DB::table('pedidos_detalle')->where('ped_id', $pedId)->count());
        $this->assertGreaterThan(0, DB::table('cocina_tickets')->where('ped_id', $pedId)->count(), 'La comanda no llegó a cocina.');

        $r = $this->cobrar($pedId, 55);

        $this->assertSame('success', $r['estado'] ?? null, $r['mensaje'] ?? '');
        $cab = DB::table('cpe_cabecera')->where('ped_id', $pedId)->first();
        $this->assertEquals(55.00, (float) $cab->ccaitv);
        $this->assertEquals(10.5, (float) $cab->por_igv);
        $this->assertSame('Cerrado', DB::table('pedidos')->where('ped_id', $pedId)->value('ped_est'));
        $this->assertSame('Libre', DB::table('mesas')->where('mes_id', $this->mesa)->value('mes_est'));
        $this->assertEquals(9.0, $this->stock($ceviche));
        $this->assertEquals(22.0, $this->stock($cerveza));
    }

    public function test_cuentas_separadas_la_mesa_sigue_ocupada_hasta_cobrar_todo(): void
    {
        $menu = $this->producto('MENU', 12, 10);
        $pedId = $this->pedirEnMesa([[$menu, 3]]);

        $this->assertSame('success', $this->cobrar($pedId, 12, ['separadas' => [$this->clave($menu, 12) => 1]])['estado']);
        $this->assertSame('Ocupado', DB::table('mesas')->where('mes_id', $this->mesa)->value('mes_est'));
        $this->assertSame('Aperturado', DB::table('pedidos')->where('ped_id', $pedId)->value('ped_est'));

        $this->assertSame('success', $this->cobrar($pedId, 24)['estado']);
        $this->assertSame('Libre', DB::table('mesas')->where('mes_id', $this->mesa)->value('mes_est'));
        $this->assertEquals(36.00, (float) DB::table('cpe_cabecera')->where('ped_id', $pedId)->sum('ccaitv'));
    }

    public function test_cortesia_no_se_cobra_y_la_propina_va_aparte_del_comprobante(): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['permite_cortesias' => 1, 'permite_propina' => 1]);
        $pisco = $this->producto('PISCO SOUR', 20, 10);
        $pedId = $this->pedirEnMesa([[$pisco, 3]]);

        // Los medios de pago cubren el comprobante (2 × 20); con lo que paga (S/ 50) también sale la propina y el vuelto
        $r = $this->cobrar($pedId, 40, ['cortesias' => [$this->clave($pisco, 20) => ['cantidad' => 1, 'motivo' => 'CUMPLEAÑOS']], 'propina' => 5, 'paga' => 50]);

        $this->assertSame('success', $r['estado'] ?? null, $r['mensaje'] ?? '');
        $cab = DB::table('cpe_cabecera')->where('ped_id', $pedId)->first();
        $this->assertEquals(40.00, (float) $cab->ccaitv, 'La cortesía no debe cobrarse.');
        $this->assertEquals(5.00, (float) DB::table('propinas')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->sum('monto'));
        $this->assertEquals(7.0, $this->stock($pisco), 'La cortesía igual sale del stock.');
        $this->assertEquals(5.00, (float) $cab->vuelto, 'Vuelto = paga 50 - total 40 - propina 5.');
    }

    public function test_la_foto_para_redes_solo_sale_si_la_sucursal_la_activa(): void
    {
        // Las mesas (al tocar una mesa ocupada) y la comanda de la mesa
        $this->get(route('comandas.seleccion'))->assertOk()->assertDontSee('Foto para redes');
        $this->postJson(route('comandas.set_servicio'), ['order_type' => 'salon', 'mesa_id' => $this->mesa, 'mesa_nombre' => 'MESA 01'])->assertOk();
        $this->get(route('comandas.menu'))->assertOk()->assertDontSee('Foto para redes');

        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['foto_redes' => true]);
        $this->get(route('comandas.menu'))->assertOk()->assertSee('Foto para redes')->assertSee('El cliente aceptó');
        $this->get(route('comandas.seleccion'))->assertOk()->assertSee('Foto para redes')->assertSee('El cliente aceptó');
    }

    public function test_un_doble_clic_en_cobrar_no_emite_dos_comprobantes(): void
    {
        $plato = $this->producto('CHAUFA', 18, 10);
        $pedId = $this->pedirEnMesa([[$plato, 1]]);

        $this->cobrar($pedId, 18, ['clave_cobro' => 'cobro-prueba-123']);
        $r = $this->cobrar($pedId, 18, ['clave_cobro' => 'cobro-prueba-123']);

        $this->assertSame('duplicado', $r['estado']);
        $this->assertSame(1, DB::table('cpe_cabecera')->where('ped_id', $pedId)->count());
    }
}
