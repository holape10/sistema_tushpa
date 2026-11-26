<?php

namespace Tests\Flujos;

use App\Support\AvisosCelular;
use App\Support\AvisosTelegram;
use App\Support\Bitacora;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Http\Client\Request as Peticion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Mantenimiento › Avisos por Telegram: cada administrador elige qué le llega (alertas, precios, cambios y el resumen a su hora) */
class AvisosTelegramTest extends FlujoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Los avisos salen solo cuando lo anotado se confirma (afterCommit): la transacción de la prueba no cuenta
        $gestor = new DatabaseTransactionsManager([DB::getDefaultConnection()]);
        $this->app->instance('db.transactions', $gestor);
        DB::connection()->setTransactionManager($gestor);
        DB::connection('central')->beginTransaction();
        config(['services.telegram.citas_token' => 'token-bot', 'cache.default' => 'array']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        DB::table('users')->where('IdUsuario', $this->usuario->IdUsuario)->update(['telegram_chat_id' => '555']);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    private function recibe(array $avisos, string $hora = '23:00'): void
    {
        $this->postJson(route('avisos_telegram.guardar', $this->usuario->IdUsuario), ['avisos' => $avisos, 'hora' => $hora])->assertJson(['ok' => true]);
    }

    public function test_la_central_muestra_cada_aviso_y_guarda_lo_que_elige_cada_administrador(): void
    {
        $this->get(route('avisos_telegram.index'))->assertOk()->assertSeeText('Avisos por Telegram')
            ->assertSeeText('Alertas al momento')->assertSeeText('Cambios de precios')->assertSeeText('Pedidos de almacén');

        $this->recibe(['telegram_resumen' => true, 'telegram_precios' => true], '21:00');

        $u = DB::table('users')->where('IdUsuario', $this->usuario->IdUsuario)->first();
        $this->assertTrue((bool) $u->telegram_resumen);
        $this->assertTrue((bool) $u->telegram_precios);
        $this->assertFalse((bool) $u->telegram_alertas);
        $this->assertSame('21:00', $u->telegram_resumen_hora);
    }

    public function test_las_alertas_al_momento_llegan_solo_a_quien_las_pidio(): void
    {
        AvisosCelular::alDueno($this->sucursal, '🚫 Anularon una venta de S/ 50.00', 'B001-123 · motivo: error');
        AvisosTelegram::enviar();
        Http::assertNothingSent();

        $this->recibe(['telegram_alertas' => true]);
        AvisosCelular::alDueno($this->sucursal, '🚫 Anularon una venta de S/ 50.00', 'B001-123 · motivo: error');
        // Lo que tiene su propia opción (por ejemplo, el resumen) no sale como alerta
        AvisosCelular::alDueno($this->sucursal, '📊 Resumen de hoy', 'Vendiste S/ 100', telegram: false);
        AvisosTelegram::enviar();

        Http::assertSentCount(1);
        Http::assertSent(fn (Peticion $p) => $p['chat_id'] === '555' && str_contains($p['text'], 'Anularon una venta de S/ 50.00'));
    }

    public function test_los_cambios_de_precio_y_los_movimientos_importantes_salen_de_la_bitacora(): void
    {
        $this->recibe(['telegram_precios' => true]);
        Bitacora::registrar('producto.editar', 'Editó el producto ARROZ', ['propun' => ['etiqueta' => 'Precio de venta', 'antes' => '3.50', 'despues' => '3.80']]);
        Bitacora::registrar('compra.editar', 'Editó la compra F001-9', null, ['alerta' => true]);
        AvisosTelegram::enviar();

        Http::assertSentCount(1);
        Http::assertSent(fn (Peticion $p) => str_contains($p['text'], 'Editó el producto ARROZ') && str_contains($p['text'], 'Precio de venta: S/ 3.50 → S/ 3.80'));

        $this->recibe(['telegram_cambios' => true]);
        Bitacora::registrar('compra.editar', 'Editó la compra F001-9', null, ['alerta' => true]);
        AvisosTelegram::enviar();
        Http::assertSent(fn (Peticion $p) => str_contains($p['text'], 'Editó la compra F001-9'));
    }

    public function test_el_resumen_llega_a_la_hora_elegida_una_sola_vez_al_dia(): void
    {
        $this->recibe(['telegram_resumen' => true], '21:00');

        $this->travelTo(now()->setTime(20, 30));
        $this->assertSame(0, AvisosTelegram::resumenesProgramados());

        $this->travelTo(now()->setTime(21, 15));
        $this->assertGreaterThanOrEqual(1, AvisosTelegram::resumenesProgramados());
        $this->assertSame(0, AvisosTelegram::resumenesProgramados(), 'No se repite el mismo día.');
        Http::assertSent(fn (Peticion $p) => $p['chat_id'] === '555' && str_contains($p['text'], 'resumen'));
    }

    public function test_solo_el_administrador_configura_los_avisos(): void
    {
        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 3, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);

        $this->actingAs($cajero)->get(route('avisos_telegram.index'))->assertForbidden();
    }
}
