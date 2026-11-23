<?php

namespace Tests\Feature;

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Alertas del panel: los cambios delicados de una empresa salen con ⚠️ en la lista de clientes hasta que se descartan */
class AlertasPanelTest extends TestCase
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
        URL::defaults(['panelDominio' => strtolower((string) config('tenancy.dominio'))]);
    }

    protected function tearDown(): void
    {
        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }
        app()->forgetInstance('tenancy.cliente');
        parent::tearDown();
    }

    /** Un vendedor con su empresa (así la lista no depende de los clientes reales) */
    private function empresa(): array
    {
        $vendedor = Superadmin::create(['nombre' => 'VENDEDOR', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => false]);
        $cliente = Cliente::create(['ruc' => '20444444444', 'base_datos' => DB::connection()->getDatabaseName(), 'razon_social' => 'POLLERIA EL SABOREO',
            'estado' => 'ACTIVO', 'creado_por' => $vendedor->id]);

        return [$vendedor, $cliente];
    }

    public function test_la_alerta_sale_en_la_lista_y_al_descartarla_ya_no_sale(): void
    {
        [$vendedor, $cliente] = $this->empresa();
        DB::connection('central')->table('panel_alertas')->insert(['cliente_id' => $cliente->id, 'fecha' => now(), 'accion' => 'config.sucursal',
            'descripcion' => 'Cambió la configuración de la sucursal PRINCIPAL', 'usuario' => 'ADMIN', 'ip' => '38.253.147.174']);

        $this->actingAs($vendedor, 'superadmin');
        $this->get(route('admin.clientes.index'))->assertOk()->assertSee('⚠️ 1 alerta por revisar')->assertSee('con alertas por revisar');
        $this->get(route('admin.clientes.alertas', $cliente->id))->assertOk()->assertSee('Cambió la configuración de la sucursal PRINCIPAL')->assertSee('Editó una sucursal');

        $this->post(route('admin.clientes.alertas.descartar', $cliente->id))->assertRedirect();
        $this->get(route('admin.clientes.index'))->assertOk()->assertDontSee('alerta por revisar');
        $this->get(route('admin.clientes.alertas', $cliente->id))->assertOk()->assertSee('Nada por revisar')->assertSee('Descartada por VENDEDOR');
    }

    public function test_otro_vendedor_no_ve_las_alertas_ajenas(): void
    {
        [, $cliente] = $this->empresa();
        $otro = Superadmin::create(['nombre' => 'OTRO', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => false]);

        $this->actingAs($otro, 'superadmin');
        $this->get(route('admin.clientes.alertas', $cliente->id))->assertNotFound();
        $this->post(route('admin.clientes.alertas.descartar', $cliente->id))->assertNotFound();
        $this->get(route('admin.clientes.bitacora.excel', $cliente->id))->assertNotFound();
    }
}
