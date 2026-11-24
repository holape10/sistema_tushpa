<?php

namespace Tests\Flujos;

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use App\Support\Bitacora;
use App\Support\Tenancy\AlertasPanel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

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

    public function test_la_alerta_tambien_le_llega_al_usuario_del_panel_que_tiene_la_empresa(): void
    {
        config(['tenancy.dominio' => config('tenancy.dominio') ?: 'tushpa.test',
            'services.telegram.token' => 'bot-dueno', 'services.telegram.chat_id' => '123', 'services.telegram.citas_token' => 'bot-panel']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $vendedor = Superadmin::create(['nombre' => 'VENDEDOR', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true,
            'es_dueno' => false, 'telegram_chat_id' => '999']);
        $sinTelegram = Superadmin::create(['nombre' => 'OTRO', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => false]);
        $suya = Cliente::create(['ruc' => '20555555557', 'base_datos' => 'bd_prueba_alertas', 'razon_social' => 'EMPRESA DEL VENDEDOR', 'estado' => 'ACTIVO', 'creado_por' => $vendedor->id]);
        $ajena = Cliente::create(['ruc' => '20555555558', 'base_datos' => 'bd_prueba_alertas2', 'razon_social' => 'EMPRESA DE OTRO', 'estado' => 'ACTIVO', 'creado_por' => $sinTelegram->id]);

        app()->instance('tenancy.cliente', $suya);
        Bitacora::registrar('usuario.crear', 'Creó el usuario CAJERO 2');
        app()->instance('tenancy.cliente', $ajena);
        Bitacora::registrar('usuario.crear', 'Creó el usuario CAJERO 3');
        app()->forgetInstance('tenancy.cliente');
        app()->terminate();

        $enviados = collect(Http::recorded())->map(fn ($p) => [$p[0]['chat_id'], str_contains($p[0]['text'], 'EMPRESA DEL VENDEDOR') ? 'suya' : 'ajena']);
        $this->assertEqualsCanonicalizing([['123', 'suya'], ['999', 'suya'], ['123', 'ajena']], $enviados->all(),
            'Al dueño le llegan todas; al vendedor solo las de sus empresas; quien no vinculó su Telegram no recibe.');
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
