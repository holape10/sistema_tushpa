<?php

namespace Tests\Flujos;

use App\Support\Asistente\Asistente;
use App\Support\Campanas;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Pedidos de WhatsApp con IA, precios cuando sube el costo y qué comprar para las campañas */
class ComercioInteligenteTest extends FlujoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('central')->beginTransaction();
        config(['services.anthropic.limite_empresa' => 100, 'services.anthropic.tope_mensual_usd' => 20]);
    }

    protected function tearDown(): void
    {
        Asistente::$falso = null;
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    public function test_el_pedido_de_whatsapp_se_arma_con_los_productos_de_la_tienda_y_queda_como_proforma(): void
    {
        $arroz = $this->producto('ARROZ', 3.5, 50);
        $enviado = null;
        Asistente::$falso = function (array $p) use ($arroz, &$enviado) {
            $enviado = $p;

            return (object) ['stopReason' => 'end_turn', 'content' => [(object) ['type' => 'text', 'text' => json_encode([
                'cliente' => 'rosa', 'aviso' => '¿Qué marca de aceite?',
                'lineas' => [['texto' => '2 kilos de arroz', 'cantidad' => 2, 'producto' => $arroz->IdProducto],
                    ['texto' => 'un aceite', 'cantidad' => 1, 'producto' => 999999]],
            ])]], 'usage' => (object) ['inputTokens' => 900, 'outputTokens' => 150, 'cacheReadInputTokens' => 0, 'cacheCreationInputTokens' => 0]];
        };

        $this->get(route('proformas.index'))->assertOk()->assertSee(route('proformas.whatsapp'), false);
        $this->get(route('proformas.whatsapp'))->assertOk()->assertSeeText('Pedido de WhatsApp (IA)');
        $r = $this->postJson(route('proformas.whatsapp.leer'), ['texto' => 'hola mandame 2 kilos de arroz y un aceite, soy rosa'])->assertOk()->json();

        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame('ROSA', $r['cliente']);
        $this->assertSame($arroz->IdProducto, $r['lineas'][0]['producto']);
        $this->assertNull($r['lineas'][1]['producto'], 'Un producto que no es de la tienda no se empareja.');
        $this->assertStringContainsString('mandame 2 kilos de arroz', $enviado['messages'][0]['content'][0]['text']);

        $g = $this->postJson(route('proformas.guardar'), ['origen' => 'WHATSAPP', 'tdicod' => '1', 'clinum' => '00000000', 'clinom' => 'ROSA',
            'items' => [['id' => $arroz->IdProducto, 'cantidad' => 2, 'precio' => 3.5]]])->assertOk()->json();
        $this->assertSame('success', $g['estado'], json_encode($g));
        $this->assertSame('WHATSAPP', DB::table('proformas')->where('id_proforma', $g['id'])->value('origen'));
        $this->assertEquals(7.0, $g['total']);
    }

    public function test_si_sube_el_costo_se_sugiere_el_precio_que_mantiene_la_ganancia_y_se_sube_con_un_toque(): void
    {
        $jabon = $this->producto('JABON BOLIVAR', 10, 20);
        // Costaba 8 (ganaba 20%) y la última compra salió a 9
        DB::table('productos')->where('IdProducto', $jabon->IdProducto)->update(['costo' => 9]);
        DB::table('costo_historial')->insert(['IdProducto' => $jabon->IdProducto, 'costo_anterior' => 8, 'costo_nuevo' => 9, 'origen' => 'COMPRA',
            'com_cab_id' => null, 'id_empresa_negocio' => $this->sucursal, 'created_at' => now()]);

        $this->get('/inicio')->assertOk()->assertSeeText('1 producto(s) ganan menos');
        $this->get(route('productos.precios_costo'))->assertOk()->assertSeeText('JABON BOLIVAR')->assertSee('value="11.30"', false);

        $this->post(route('productos.precios_costo.guardar'), ['elegidos' => [$jabon->IdProducto], 'precios' => [$jabon->IdProducto => 11.3]])
            ->assertRedirect(route('productos.precios_costo'));
        $this->assertEquals(11.3, (float) DB::table('productos')->where('IdProducto', $jabon->IdProducto)->value('propun'));
        $this->get(route('productos.precios_costo'))->assertSeeText('Todo bien');
    }

    public function test_la_campana_sugiere_comprar_lo_vendido_el_anio_pasado_menos_el_stock(): void
    {
        $this->travelTo('2026-11-25 10:00');
        $sombrilla = $this->producto('SOMBRILLA 20', 20, 3);
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$sombrilla, 10]], '13'), 'PV');
        // Esa venta fue en la Navidad pasada
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->update(['fecha_hora' => '2025-12-15 11:00:00', 'ccafem' => '2025-12-15']);
        DB::table('producto_stock')->where('IdProducto', $sombrilla->IdProducto)->update(['stock' => 3]);

        $this->get('/inicio')->assertOk()->assertSeeText('Navidad y Año Nuevo empieza en 6 día(s)')->assertSeeText('El año pasado vendiste S/ 200.00');
        $this->get(route('campanas.index', ['campana' => 'navidad']))->assertOk()
            ->assertSeeText('Prepárate para Navidad y Año Nuevo')->assertSeeText('SOMBRILLA 20')
            ->assertSeeText('Productos por comprar');
        $plan = Campanas::plan($this->sucursal, 'navidad');
        $this->assertSame('anio_pasado', $plan['base']);
        $this->assertEquals(7, $plan['productos']->first()->comprar, '10 vendidas el año pasado menos 3 en stock.');
    }
}
