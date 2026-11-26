<?php

namespace Tests\Flujos;

use App\Http\Controllers\Admin\ComprobanteController;
use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/** Panel › Comprobantes SUNAT de una empresa: los días con boletas sin resumen se envían de un clic, como en la empresa */
class PanelResumenDiaTest extends FlujoTestCase
{
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('central')->beginTransaction();
        URL::defaults(['panelDominio' => strtolower((string) config('tenancy.dominio'))]);
        // La empresa del panel es la de esta prueba (su base es la base de pruebas)
        $this->cliente = Cliente::create(['ruc' => (string) $this->usuario->IdEmpresa, 'base_datos' => DB::connection()->getDatabaseName(),
            'razon_social' => 'EMPRESA DE PRUEBA', 'estado' => 'ACTIVO']);
        $dueno = Superadmin::create(['nombre' => 'DUEÑO', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => true]);
        $this->actingAs($dueno, 'superadmin');
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    public function test_las_boletas_pendientes_se_agrupan_por_dia_para_el_resumen(): void
    {
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('ARROZ', 3.5, 10), 2]], '03'), 'PV');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->update(['est_sunat' => 'PENDIENTE']);

        // Las boletas pendientes del día (el panel abre la base de la empresa en otra conexión, que no ve esta prueba)
        $sinResumen = (new \ReflectionMethod(ComprobanteController::class, 'boletasSinResumen'))->invoke(null);
        $this->assertSame([$id], $sinResumen->where('ccafem', now()->toDateString())->pluck('IdCpe_cabecera')->map(fn ($x) => (int) $x)->all());

        $this->get(route('admin.clientes.comprobantes', $this->cliente->id))->assertOk()->assertSeeText('Comprobantes SUNAT');
    }

    public function test_un_dia_sin_boletas_pendientes_no_manda_nada_a_sunat(): void
    {
        $this->postJson(route('admin.clientes.comprobantes.resumen_dia', $this->cliente->id), ['fecha' => now()->subDays(3)->toDateString()])
            ->assertOk()->assertExactJson([]);
    }
}
