<?php

namespace Tests\Flujos;

use App\Support\ResumenTelegram;
use App\Support\VentaDirecta;
use Illuminate\Http\Client\Request as Peticion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Lo que el dueño ve sin entrar: qué comprar en el Inicio y el resumen del día con las deudas de clientes por Telegram */
class ResumenTelegramTest extends FlujoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('central')->beginTransaction();
        config(['services.telegram.citas_token' => 'token-bot', 'cache.default' => 'array']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    private function ventaAlCredito(float $precio, string $vence, ?string $emision = null): void
    {
        $datos = $this->venta([[$this->producto('COCINA ROLO HOME', $precio, 5), 1]], '01', ['fecVen' => $vence]);
        $datos['fecEmi'] = $emision ?? $datos['fecEmi'];
        $datos['estadopago'] = $this->credito;
        $datos['id_med_pag'] = $datos['mon_med_pag'] = [];
        $datos['paga'] = 0;
        VentaDirecta::registrar($this->usuario, $datos, 'PV');
    }

    public function test_el_resumen_dice_cuanto_deben_los_clientes_y_lo_vencido(): void
    {
        $this->ventaAlCredito(130, now()->subDays(2)->toDateString(), now()->subDays(3)->toDateString());

        $texto = ResumenTelegram::mensaje($this->sucursal, now());

        $this->assertStringContainsString('Te deben S/ 130.00 (1 cliente(s)) · vencido S/ 130.00', $texto);
        $this->assertStringContainsString('CLIENTE EMPRESA SAC: S/ 130.00 (vencido hace 2 día(s))', $texto);
        $this->assertStringNotContainsString('*', $texto, 'Telegram no muestra los asteriscos de WhatsApp.');
    }

    public function test_solo_le_llega_a_los_administradores_que_lo_activaron(): void
    {
        $this->ventaAlCredito(50, now()->addDays(10)->toDateString());
        DB::table('users')->where('IdUsuario', $this->usuario->IdUsuario)->update(['telegram_chat_id' => '777', 'telegram_resumen' => false]);
        $this->assertSame(0, ResumenTelegram::enviar($this->sucursal, now()));

        $this->get(route('dueno.index'))->assertOk()->assertSeeText('Resumen del día por Telegram');
        $this->postJson(route('dueno.telegram.recibir', $this->usuario->IdUsuario), ['recibir' => true])->assertOk();

        $this->assertSame(1, ResumenTelegram::enviar($this->sucursal, now()));
        Http::assertSent(fn (Peticion $p) => str_contains($p->url(), 'sendMessage') && $p['chat_id'] === '777' && str_contains($p['text'], 'Te deben S/ 50.00'));
    }

    public function test_el_inicio_sugiere_comprar_lo_que_se_vende_rapido_aunque_no_tenga_minimo(): void
    {
        $agua = $this->producto('AGUA CHICA', 2, 3);
        // Se vendieron 70 en la última semana (10 por día) y quedan 3: no alcanza para los próximos días
        DB::table('movimientos_productos')->insert(['IdProducto' => $agua->IdProducto, 'id_almacen' => $this->almacen, 'id_empresa_negocio' => $this->sucursal,
            'cantidad' => 70, 'mov_tip' => 'E', 'fecha_mov' => now()->subDay()->toDateString()]);

        $this->get('/inicio')->assertOk()->assertSeeText('Por acabarse (1)')->assertSeeText('AGUA CHICA')->assertSeeText('comprar 17');
    }
}
