<?php

namespace Tests\Feature;

use App\Models\Central\Cliente;
use App\Models\Central\Marca;
use App\Models\Central\Superadmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Tablero del panel: el dueño filtra las empresas por el usuario que las creó y por marca */
class TableroFiltrosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        try {
            DB::connection('central')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin la base central: '.$e->getMessage());
        }
        if (! Schema::connection('central')->hasColumn('clientes', 'marca_id')) {
            $this->markTestSkipped('Falta la migración central de marcas.');
        }
        DB::connection('central')->beginTransaction();
        URL::defaults(['panelDominio' => strtolower((string) config('tenancy.dominio'))]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    public function test_el_dueno_filtra_por_usuario_y_por_marca(): void
    {
        DB::connection('central')->table('clientes')->update(['estado' => 'ELIMINADO']);
        $dueno = Superadmin::create(['nombre' => 'DUEÑO', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => true]);
        $vendedor = Superadmin::create(['nombre' => 'VENDEDOR', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => false]);
        $marca = Marca::create(['nombre' => 'MARCA PRUEBA', 'dominio' => 'marcaprueba'.uniqid().'.pe', 'prefijo_bd' => 'bd_mp_', 'activa' => true]);
        foreach ([['20111111111', $vendedor->id, null], ['20222222222', $dueno->id, $marca->id], ['20333333333', $dueno->id, null]] as [$ruc, $por, $m]) {
            Cliente::create(['ruc' => $ruc, 'base_datos' => 'bd_no_existe_'.$ruc, 'razon_social' => 'EMPRESA '.$ruc, 'estado' => 'ACTIVO',
                'creado_por' => $por, 'marca_id' => $m]);
        }
        $this->actingAs($dueno, 'superadmin');

        $this->get(route('admin.tablero'))->assertOk()->assertSee('Todas tus empresas (3)');
        $this->get(route('admin.tablero', ['usuario' => $vendedor->id]))->assertOk()->assertSee('Todas tus empresas (1)');
        $this->get(route('admin.tablero', ['marca' => $marca->id]))->assertOk()->assertSee('Todas tus empresas (1)');
        $this->get(route('admin.tablero', ['marca' => 'tushpa']))->assertOk()->assertSee('Todas tus empresas (2)');
    }
}
