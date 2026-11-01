<?php

namespace Tests\Unit;

use App\Models\Central\Cliente;
use App\Support\Tenancy\PagosOnline;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Pago en línea del servicio (Culqi, simulado): al confirmarse el pago la empresa se reactiva y corre su vencimiento */
class PagosOnlineTest extends TestCase
{
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-01 10:00:00');
        // Base central de mentira en memoria: la prueba no toca la base central de verdad
        config(['database.connections.central' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'services.culqi.publica' => 'pk_test_x', 'services.culqi.privada' => 'sk_test_x', 'tenancy.dominio' => 'tushpa.app']);
        DB::purge('central');
        $central = Schema::connection('central');
        $central->create('planes', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->decimal('precio', 10, 2);
            $t->decimal('precio_anual', 10, 2)->nullable();
            $t->timestamps();
        });
        $central->create('clientes', function (Blueprint $t) {
            $t->id();
            $t->string('ruc');
            $t->string('razon_social');
            $t->string('nombre_comercial')->nullable();
            $t->string('base_datos')->nullable();
            $t->string('subdominio')->nullable();
            $t->string('estado');
            $t->string('motivo_suspension')->nullable();
            $t->string('plan')->nullable();
            $t->foreignId('plan_id')->nullable();
            $t->date('vence_el')->nullable();
            $t->date('suspendido_el')->nullable();
            $t->date('prueba_hasta')->nullable();
            $t->text('aviso_mensaje')->nullable();
            $t->date('aviso_fecha')->nullable();
            $t->tinyInteger('aviso_suspender')->default(0);
            $t->string('contacto_nombre')->nullable();
            $t->string('contacto_telefono')->nullable();
            $t->string('contacto_correo')->nullable();
            $t->text('notas')->nullable();
            $t->unsignedBigInteger('creado_por')->nullable();
            $t->timestamps();
        });
        (include database_path('migrations/central/2027_01_11_000001_pagos_online.php'))->up();

        $plan = DB::connection('central')->table('planes')->insertGetId(['nombre' => 'RESTAURANTE', 'precio' => 80]);
        $this->cliente = Cliente::create(['ruc' => '20609838427', 'razon_social' => 'COCOROCO E.I.R.L.', 'estado' => 'SUSPENDIDO', 'plan_id' => $plan,
            'vence_el' => '2026-10-25', 'motivo_suspension' => 'Falta de pago', 'contacto_correo' => 'dueno@cocoroco.pe']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_con_tarjeta_la_empresa_se_reactiva_y_vence_en_un_mes_desde_hoy(): void
    {
        Http::fake(['api.culqi.com/v2/orders' => Http::response(['id' => 'ord_test_1'], 201),
            'api.culqi.com/v2/charges' => Http::response(['id' => 'chr_test_1', 'outcome' => ['user_message' => 'Su compra ha sido exitosa.']], 201)]);

        $pago = PagosOnline::preparar($this->cliente, 1);
        $this->assertSame('ord_test_1', $pago->orden);
        $this->assertTrue(PagosOnline::cobrar($pago->id, 'tkn_test_abc', null)['ok']);

        $this->cliente->refresh();
        $this->assertSame('ACTIVO', $this->cliente->estado);
        $this->assertNull($this->cliente->motivo_suspension);
        $this->assertSame('2026-12-01', $this->cliente->vence_el->toDateString(), 'Ya había vencido: el mes se cuenta desde hoy.');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v2/charges') && $r['amount'] === 8000 && $r['source_id'] === 'tkn_test_abc');
    }

    public function test_si_el_banco_rechaza_la_tarjeta_sigue_suspendida(): void
    {
        Http::fake(['api.culqi.com/v2/orders' => Http::response(['id' => 'ord_test_2'], 201),
            'api.culqi.com/v2/charges' => Http::response(['user_message' => 'Tu tarjeta no tiene fondos suficientes.'], 402)]);

        $pago = PagosOnline::preparar($this->cliente, 1);
        $r = PagosOnline::cobrar($pago->id, 'tkn_test_sin_fondos', null);

        $this->assertFalse($r['ok']);
        $this->assertSame('Tu tarjeta no tiene fondos suficientes.', $r['mensaje']);
        $this->assertSame('SUSPENDIDO', $this->cliente->refresh()->estado);
        $this->assertSame('FALLIDO', DB::connection('central')->table('pagos_online')->value('estado'));
    }

    public function test_con_qr_se_activa_cuando_culqi_confirma_la_orden_y_solo_una_vez(): void
    {
        $this->cliente->update(['estado' => 'ACTIVO', 'vence_el' => '2026-11-20']);
        Http::fake(['api.culqi.com/v2/orders' => Http::response(['id' => 'ord_test_3'], 201),
            'api.culqi.com/v2/orders/ord_test_3' => Http::sequence()->push(['state' => 'pending'])->push(['state' => 'paid'])->push(['state' => 'paid'])]);

        PagosOnline::preparar($this->cliente, 12);
        $this->assertFalse(PagosOnline::revisarOrden('ord_test_3'), 'Aún no paga.');
        $this->assertTrue(PagosOnline::revisarOrden('ord_test_3'));
        $this->assertTrue(PagosOnline::revisarOrden('ord_test_3'), 'El aviso repetido de Culqi no vuelve a sumar.');

        // Aún no vencía: el año se suma desde su vencimiento
        $this->assertSame('2027-11-20', $this->cliente->refresh()->vence_el->toDateString());
        $this->assertSame(1, DB::connection('central')->table('pagos_online')->where('estado', 'PAGADO')->count());
    }

    public function test_si_el_banco_pide_verificacion_se_cobra_despues_de_confirmar(): void
    {
        Http::fake(['api.culqi.com/v2/orders' => Http::response([], 500),
            'api.culqi.com/v2/charges' => Http::sequence()
                ->push(['action_code' => 'REVIEW', 'user_message' => 'El usuario necesita autenticarse'], 200)
                ->push(['id' => 'chr_test_3ds'], 201)]);

        $pago = PagosOnline::preparar($this->cliente, 1);
        $this->assertNull($pago->orden, 'Sin orden igual se puede pagar con tarjeta o Yape.');

        $r = PagosOnline::cobrar($pago->id, 'tkn_test_3ds', null, 'dispositivo-1');
        $this->assertTrue($r['tresds']);
        $this->assertSame('PENDIENTE', DB::connection('central')->table('pagos_online')->value('estado'), 'Pedir verificación no es un rechazo.');

        $this->assertTrue(PagosOnline::cobrar($pago->id, 'tkn_test_3ds', null, 'dispositivo-1', ['eci' => '05', 'xid' => 'x'])['ok']);
        $this->assertSame('ACTIVO', $this->cliente->refresh()->estado);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v2/charges') && ($r['authentication_3DS']['eci'] ?? null) === '05'
            && $r['antifraud_details']['device_finger_print_id'] === 'dispositivo-1');
    }
}
