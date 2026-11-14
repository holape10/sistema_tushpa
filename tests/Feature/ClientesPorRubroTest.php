<?php

namespace Tests\Feature;

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Lista de clientes del panel filtrada por rubro (solo restobares, solo comercios, los sin rubro) */
class ClientesPorRubroTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        try {
            DB::connection('central')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin la base central: '.$e->getMessage());
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

    public function test_filtra_por_rubro_y_los_sin_rubro(): void
    {
        // Un vendedor ve solo sus empresas: así la lista no depende de los clientes reales de la base
        $vendedor = Superadmin::create(['nombre' => 'VENDEDOR', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => false]);
        foreach ([['20111111111', 'RESTOBAR LA BRASA', 'RESTOBAR'], ['20222222222', 'BODEGA DON PEPE', 'COMERCIO'], ['20333333333', 'EMPRESA SIN GIRO', null]] as [$ruc, $nombre, $rubro]) {
            Cliente::create(['ruc' => $ruc, 'base_datos' => 'bd_'.$ruc, 'razon_social' => $nombre, 'rubro' => $rubro, 'estado' => 'ACTIVO', 'creado_por' => $vendedor->id]);
        }
        $this->actingAs($vendedor, 'superadmin');

        $this->get(route('admin.clientes.index', ['rubro' => 'RESTOBAR']))->assertOk()
            ->assertSee('RESTOBAR LA BRASA')->assertDontSee('BODEGA DON PEPE')->assertDontSee('EMPRESA SIN GIRO')->assertSee('Restobar (1)');
        $this->get(route('admin.clientes.index', ['rubro' => 'sin']))->assertOk()
            ->assertSee('EMPRESA SIN GIRO')->assertDontSee('RESTOBAR LA BRASA')->assertSee('Sin rubro (1)');
        $this->get(route('admin.clientes.index', ['rubro' => 'NO-EXISTE']))->assertOk()
            ->assertSee('RESTOBAR LA BRASA')->assertSee('BODEGA DON PEPE')->assertSee('EMPRESA SIN GIRO');
    }
}
