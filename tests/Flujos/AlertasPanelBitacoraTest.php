<?php

namespace Tests\Flujos;

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use App\Support\Bitacora;
use App\Support\TelegramCitas;
use App\Support\Tenancy\AlertasPanel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Lo delicado de la bitácora de una empresa (editar sucursal o empresa, importar precios, usuarios) también va como alerta al panel */
class AlertasPanelBitacoraTest extends FlujoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        try {
            DB::connection('central')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin la base central: '.$e->getMessage());
        }
        if (! Schema::connection('central')->hasTable('panel_alertas')) {
            $this->markTestSkipped('Falta la migración central de las alertas.');
        }
        DB::connection('central')->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }
        app()->forgetInstance('tenancy.cliente');
        parent::tearDown();
    }

    public function test_solo_lo_delicado_va_al_panel(): void
    {
        config(['tenancy.dominio' => config('tenancy.dominio') ?: 'tushpa.test']);
        $cliente = Cliente::create(['ruc' => '20555555555', 'base_datos' => 'bd_prueba_alertas', 'razon_social' => 'EMPRESA DE PRUEBA', 'estado' => 'ACTIVO']);
        app()->instance('tenancy.cliente', $cliente);

        Bitacora::registrar('config.sucursal', 'Cambió la configuración de la sucursal PRINCIPAL');
        Bitacora::registrar('producto.importar', 'Importó productos desde Excel: 0 creados, 122 actualizados');
        Bitacora::registrar('venta.anular', 'Anuló la B001-1');

        $alertas = DB::connection('central')->table('panel_alertas')->where('cliente_id', $cliente->id)->orderBy('id')->get();
        $this->assertSame(['config.sucursal', 'producto.importar'], $alertas->pluck('accion')->all());
        $this->assertSame($this->usuario->apeusu, $alertas[0]->usuario);
        $this->assertNotNull($alertas[0]->bitacora_id);
    }

    /** Telegram vinculado en la Agenda de un usuario del panel */
    private function vincular(Superadmin $usuario, string $chat): void
    {
        DB::connection('central')->table('telegram_vinculos')->insert(['codigo' => Str::lower(Str::random(16)), 'base' => TelegramCitas::PANEL,
            'IdUsuario' => $usuario->id, 'chat_id' => $chat, 'nombre' => 'CEL '.$chat, 'vinculado_at' => now()]);
    }

    public function test_la_alerta_le_llega_a_los_telegram_del_dueno_y_del_usuario_del_panel_que_tiene_la_empresa(): void
    {
        config(['tenancy.dominio' => config('tenancy.dominio') ?: 'tushpa.test',
            'services.telegram.token' => 'bot-dueno', 'services.telegram.chat_id' => '123', 'services.telegram.citas_token' => 'bot-panel']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        // Solo los usuarios de esta prueba
        DB::connection('central')->table('superadmins')->update(['activo' => false]);
        $dueno = Superadmin::create(['nombre' => 'DUEÑO', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => true]);
        $vendedor = Superadmin::create(['nombre' => 'VENDEDOR', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => false]);
        $sinTelegram = Superadmin::create(['nombre' => 'OTRO', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => false]);
        $this->vincular($dueno, '123');
        $this->vincular($dueno, 'D2');
        $this->vincular($vendedor, 'V1');
        $this->vincular($vendedor, 'V2');
        $this->vincular($vendedor, 'V3');
        $suya = Cliente::create(['ruc' => '20555555557', 'base_datos' => 'bd_prueba_alertas', 'razon_social' => 'EMPRESA DEL VENDEDOR', 'estado' => 'ACTIVO', 'creado_por' => $vendedor->id]);
        $ajena = Cliente::create(['ruc' => '20555555558', 'base_datos' => 'bd_prueba_alertas2', 'razon_social' => 'EMPRESA DE OTRO', 'estado' => 'ACTIVO', 'creado_por' => $sinTelegram->id]);

        app()->instance('tenancy.cliente', $suya);
        Bitacora::registrar('usuario.crear', 'Creó el usuario CAJERO 2');
        app()->instance('tenancy.cliente', $ajena);
        Bitacora::registrar('usuario.crear', 'Creó el usuario CAJERO 3');
        app()->forgetInstance('tenancy.cliente');
        app()->terminate();

        $enviados = collect(Http::recorded())->map(fn ($p) => $p[0]['chat_id'].':'.(str_contains($p[0]['text'], 'EMPRESA DEL VENDEDOR') ? 'suya' : 'ajena'));
        $this->assertEqualsCanonicalizing(['123:suya', 'D2:suya', 'V1:suya', 'V2:suya', 'V3:suya', '123:ajena', 'D2:ajena'], $enviados->all(),
            'El dueño recibe todas (sin repetir su chat del .env); el vendedor, en sus 3 Telegram, solo las de sus empresas; quien no vinculó no recibe.');
    }

    public function test_cada_usuario_del_panel_vincula_hasta_3_telegram(): void
    {
        config(['services.telegram.citas_token' => 'bot-panel']);
        Cache::flush();
        $vendedor = Superadmin::create(['nombre' => 'VENDEDOR', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => false]);
        foreach (['V1', 'V2', 'V3'] as $chat) {
            $this->vincular($vendedor, $chat);
        }
        DB::connection('central')->table('telegram_vinculos')->insert(['codigo' => 'cuartocuartocuar', 'base' => TelegramCitas::PANEL, 'IdUsuario' => $vendedor->id]);
        Http::fake([
            'api.telegram.org/*/getUpdates*' => Http::response(['ok' => true, 'result' => [['update_id' => 1,
                'message' => ['text' => '/start cuartocuartocuar', 'chat' => ['id' => 'V4'], 'from' => ['first_name' => 'CUARTO']]]]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        TelegramCitas::recibir();

        $this->assertSame(['V1', 'V2', 'V3'], TelegramCitas::chatsPanel((int) $vendedor->id), 'El cuarto no se vincula.');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && $r['chat_id'] === 'V4' && str_contains($r['text'], 'ya tiene 3 Telegram'));

        // Al quitar uno, queda espacio y su ficha sigue con otro de los vinculados
        TelegramCitas::quitarPanel((int) $vendedor->id, (int) TelegramCitas::vinculosPanel((int) $vendedor->id)->first()->id);
        $this->assertSame(['V2', 'V3'], TelegramCitas::chatsPanel((int) $vendedor->id));
        $this->assertSame('V2', DB::connection('central')->table('superadmins')->where('id', $vendedor->id)->value('telegram_chat_id'));
    }

    public function test_la_alerta_muestra_exactamente_que_cambio(): void
    {
        config(['tenancy.dominio' => config('tenancy.dominio') ?: 'tushpa.test']);
        $cliente = Cliente::create(['ruc' => '20555555556', 'base_datos' => DB::connection()->getDatabaseName(), 'razon_social' => 'EMPRESA DE PRUEBA', 'estado' => 'ACTIVO']);
        app()->instance('tenancy.cliente', $cliente);
        config(['services.telegram.token' => 'bot-prueba', 'services.telegram.chat_id' => '123']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Bitacora::registrar('config.sucursal', 'Cambió la configuración de la sucursal PRINCIPAL', [
            'Bnu' => ['etiqueta' => 'Correlativo de boletas', 'antes' => '1520', 'despues' => '1'],
            'claveSunat' => ['etiqueta' => 'Clave SOL', 'antes' => 'SECRETO', 'despues' => '(cambió)'],
        ]);
        app()->forgetInstance('tenancy.cliente');

        // Al terminar la petición sale el aviso al Telegram del dueño, con el detalle
        app()->terminate();
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.telegram.org/botbot-prueba/sendMessage') && $r['chat_id'] === '123'
            && str_contains($r['text'], 'RUC 20555555556') && str_contains($r['text'], 'Correlativo de boletas: 1520 → 1') && ! str_contains($r['text'], 'SECRETO'));
        $alerta = DB::connection('central')->table('panel_alertas')->where('cliente_id', $cliente->id)->first();

        $detalles = AlertasPanel::detalles($cliente, collect([$alerta->bitacora_id]));

        $this->assertSame(['etiqueta' => 'Correlativo de boletas', 'antes' => '1520', 'despues' => '1'], $detalles[$alerta->bitacora_id][0]);
        $html = view('admin.clientes.partials.alerta_cambios', ['cambios' => $detalles[$alerta->bitacora_id]])->render();
        $this->assertMatchesRegularExpression('/Correlativo de boletas:.*1520.*→.*>1</s', $html);
        $this->assertStringContainsString('cambió (no se muestra por seguridad)', $html, 'Las claves no se muestran.');
    }
}
