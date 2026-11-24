<?php

namespace Tests\Flujos;

use App\Models\Central\Cliente;
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
