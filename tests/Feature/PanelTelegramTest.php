<?php

namespace Tests\Feature;

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use App\Support\TelegramCitas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** ✈️ Telegram en el panel: quién usa el bot, con filtro por empresa (solo el dueño) */
class PanelTelegramTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        try {
            DB::connection('central')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin la base central: '.$e->getMessage());
        }
        if (! Schema::connection('central')->hasTable('telegram_vinculos')) {
            $this->markTestSkipped('Falta la migración central de Telegram.');
        }
        DB::connection('central')->beginTransaction();
        URL::defaults(['panelDominio' => strtolower((string) config('tenancy.dominio'))]);
    }

    protected function tearDown(): void
    {
        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }
        parent::tearDown();
    }

    private function vincular(string $base, int $usuario, string $nombre, string $chat): void
    {
        DB::connection('central')->table('telegram_vinculos')->insert(['codigo' => uniqid(), 'base' => $base, 'IdUsuario' => $usuario, 'chat_id' => $chat,
            'nombre' => $nombre, 'vinculado_at' => now(), 'created_at' => now()]);
    }

    private function dueno(bool $esDueno = true): Superadmin
    {
        return Superadmin::create(['nombre' => 'DUEÑO', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => $esDueno]);
    }

    public function test_lista_quien_usa_el_bot_y_filtra_por_empresa(): void
    {
        // Bases que no existen: la pantalla igual sale (sin los avisos de esas empresas)
        $polleria = Cliente::create(['ruc' => '20444444441', 'base_datos' => 'bd_prueba_tg_1', 'razon_social' => 'POLLERIA EL SABOREO', 'estado' => 'ACTIVO']);
        $bodega = Cliente::create(['ruc' => '20444444442', 'base_datos' => 'bd_prueba_tg_2', 'razon_social' => 'BODEGA DOÑA ROSA', 'estado' => 'ACTIVO']);
        $this->vincular($polleria->base_datos, 1, 'CARLOS CAJERO', '9000001');
        $this->vincular($bodega->base_datos, 1, 'ROSA DUEÑA', '9000002');
        $this->vincular(TelegramCitas::PANEL, 1, 'SOPORTE PANEL', '9000003');
        DB::connection('central')->table('telegram_preguntas')->insert(['chat_id' => '9000002', 'texto' => '¿cuánto vendí hoy?', 'created_at' => now()]);
        $this->actingAs($this->dueno(), 'superadmin');

        $this->get(route('admin.telegram.index'))->assertOk()->assertSeeText('CARLOS CAJERO')->assertSeeText('ROSA DUEÑA')->assertSeeText('SOPORTE PANEL');
        $this->get(route('admin.telegram.index', ['empresa' => $bodega->id]))->assertOk()->assertSeeText('ROSA DUEÑA')->assertDontSeeText('CARLOS CAJERO');
        $this->get(route('admin.telegram.index', ['empresa' => TelegramCitas::PANEL]))->assertOk()->assertSeeText('SOPORTE PANEL')->assertDontSeeText('ROSA DUEÑA');
    }

    public function test_solo_el_dueno_la_ve(): void
    {
        $this->actingAs($this->dueno(false), 'superadmin');

        $this->get(route('admin.telegram.index'))->assertNotFound();
    }
}
