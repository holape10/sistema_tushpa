<?php

namespace Tests\Feature;

use App\Models\Central\Cliente;
use App\Models\Central\Plan;
use App\Models\Central\Superadmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Panel › Planes: un plan se elimina solo si ninguna empresa vigente lo tiene */
class PlanesEliminarTest extends TestCase
{
    private Superadmin $dueno;

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
        $this->dueno = Superadmin::create(['nombre' => 'DUEÑO', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => true]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    private function plan(): Plan
    {
        return Plan::create(['nombre' => 'PLAN PRUEBA', 'precio' => 10, 'activo' => true, 'en_web' => false, 'orden' => 0]);
    }

    private function cliente(Plan $plan, string $estado): Cliente
    {
        return Cliente::create(['ruc' => '20'.random_int(100000000, 999999999), 'base_datos' => 'bd_no_existe_'.uniqid(), 'razon_social' => 'EMPRESA',
            'estado' => $estado, 'plan_id' => $plan->id, 'plan' => $plan->nombre]);
    }

    public function test_elimina_el_plan_que_solo_tienen_empresas_eliminadas(): void
    {
        $plan = $this->plan();
        $eliminada = $this->cliente($plan, 'ELIMINADO');

        $this->actingAs($this->dueno, 'superadmin')->post(route('admin.planes.eliminar', $plan))
            ->assertRedirect(route('admin.planes.index'))->assertSessionHas('ok');

        $this->assertNull(Plan::find($plan->id));
        $this->assertNull($eliminada->fresh()->plan_id);
        $this->assertSame('PLAN PRUEBA', $eliminada->fresh()->plan);
    }

    public function test_no_elimina_un_plan_que_tiene_una_empresa(): void
    {
        $plan = $this->plan();
        $this->cliente($plan, 'SUSPENDIDO');

        $this->actingAs($this->dueno, 'superadmin')->post(route('admin.planes.eliminar', $plan))->assertSessionHasErrors('plan');

        $this->assertNotNull(Plan::find($plan->id));
    }
}
