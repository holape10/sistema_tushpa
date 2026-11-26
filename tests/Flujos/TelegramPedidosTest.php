<?php

namespace Tests\Flujos;

use App\Support\Asistente\Asistente;
use App\Support\Asistente\TelegramAsistente;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\Client\Request as Peticion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Preguntarle al Asistente IA por Telegram y armar los pedidos a los proveedores */
class TelegramPedidosTest extends FlujoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('central')->beginTransaction();
        config(['services.telegram.citas_token' => 'token-bot', 'cache.default' => 'array',
            'services.anthropic.limite_empresa' => 100, 'services.anthropic.tope_mensual_usd' => 20]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
    }

    protected function tearDown(): void
    {
        Asistente::$falso = null;
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    private function vincular(string $chat, int $usuario): void
    {
        DB::connection('central')->table('telegram_vinculos')->insert(['codigo' => substr(md5($chat.$usuario), 0, 16), 'base' => Tenancy::baseActual(),
            'IdUsuario' => $usuario, 'chat_id' => $chat, 'vinculado_at' => now(), 'created_at' => now()]);
    }

    public function test_el_administrador_pregunta_por_telegram_y_le_responde_el_asistente(): void
    {
        $this->vincular('555', $this->usuario->IdUsuario);
        $enviado = null;
        Asistente::$falso = function (array $p) use (&$enviado) {
            $enviado = $p;

            return (object) ['stopReason' => 'end_turn', 'content' => [(object) ['type' => 'text', 'text' => "**Hoy vendiste S/ 350.00** en 12 ventas.\n- Lo más vendido: arroz"]],
                'usage' => (object) ['inputTokens' => 1000, 'outputTokens' => 50, 'cacheReadInputTokens' => 0, 'cacheCreationInputTokens' => 0]];
        };

        $this->assertTrue(TelegramAsistente::recibir('555', '¿cuánto vendí hoy?'));
        $this->assertSame(1, TelegramAsistente::responder());

        $this->assertSame('¿cuánto vendí hoy?', end($enviado['messages'])['content']);
        Http::assertSent(fn (Peticion $p) => str_contains($p->url(), 'sendMessage') && $p['chat_id'] === '555'
            && str_contains($p['text'], 'Hoy vendiste S/ 350.00 en 12 ventas.') && str_contains($p['text'], '• Lo más vendido') && ! str_contains($p['text'], '**'));
        $this->assertSame(0, TelegramAsistente::responder(), 'Cada pregunta se responde una sola vez.');
    }

    public function test_un_telegram_no_vinculado_no_pregunta_y_un_cajero_no_ve_el_negocio(): void
    {
        $this->assertFalse(TelegramAsistente::recibir('999', '¿cuánto vendí?'), 'Si no está vinculado, el bot le pide vincularlo.');

        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 3, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        $this->vincular('777', $cajero->IdUsuario);

        TelegramAsistente::recibir('777', '¿cuánto vendí hoy?');
        TelegramAsistente::responder();
        Http::assertSent(fn (Peticion $p) => $p['chat_id'] === '777' && str_contains($p['text'], 'Solo el administrador'));
    }

    public function test_los_pedidos_se_agrupan_por_el_proveedor_mas_barato_listos_para_whatsapp(): void
    {
        $arroz = $this->producto('ARROZ COSTEÑO', 4, 3);
        // Vende 10 por día (70 en la semana) y quedan 3: para 7 días hay que pedir 67
        DB::table('movimientos_productos')->insert(['IdProducto' => $arroz->IdProducto, 'id_almacen' => $this->almacen, 'id_empresa_negocio' => $this->sucursal,
            'cantidad' => 70, 'mov_tip' => 'E', 'fecha_mov' => now()->subDay()->toDateString()]);
        $prov = DB::table('proveedor')->insertGetId(['tdicod' => '6', 'prov_ruc' => '20111111111', 'prov_raz' => 'DISTRIBUIDORA BARATA SAC', 'prov_num_con' => '987654321',
            'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal]);
        $cab = DB::table('compras_cabecera')->insertGetId(['IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal, 'tdocod' => '01',
            'com_doc_ser' => 'F001', 'com_doc_num' => '1', 'prov_id' => $prov, 'prov_num' => '20111111111', 'com_fec' => now()->subDays(5)->toDateString(),
            'com_fec_ing' => now()->subDays(5)->toDateString(), 'mon_id' => 'PEN', 'total_com' => 31, 'id_almacen' => $this->almacen, 'est_compra' => 'Registrado', 'IdUsuario' => $this->usuario->IdUsuario]);
        DB::table('compras_detalle')->insert(['com_cab_id' => $cab, 'pro_id' => $arroz->IdProducto, 'ume_cod' => 'NIU', 'tip_igv' => '10', 'cantidad' => 10,
            'pre_uni' => 3.1, 'precio_costo' => 3.1, 'factor' => 1, 'total' => 31, 'IdEmpresa' => $this->usuario->IdEmpresa]);

        $this->get(route('compras.pedidos', ['dias' => 7]))->assertOk()
            ->assertSeeText('DISTRIBUIDORA BARATA SAC')->assertSeeText('ARROZ COSTEÑO')
            ->assertSee('https://wa.me/51987654321?text=', false)->assertSee(rawurlencode('• 67 ARROZ COSTEÑO'), false);
        $this->get(route('compras.index'))->assertOk()->assertSee(route('compras.pedidos'), false);
    }
}
