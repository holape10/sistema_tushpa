<?php

namespace Tests\Feature;

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use App\Support\Tenancy\VisibilidadPanel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Qué empresas ve cada usuario del panel: en una marca todos ven las de la marca; en TUSHPA cada uno las suyas */
class VisibilidadPanelTest extends TestCase
{
    private const MARCA = 88;

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

    private function usuario(?int $marca = null, bool $dueno = false): Superadmin
    {
        return Superadmin::create(['nombre' => 'USUARIO', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true,
            'es_dueno' => $dueno, 'marca_id' => $marca]);
    }

    private function empresa(string $ruc, ?int $marca, ?int $creador): Cliente
    {
        return Cliente::create(['ruc' => $ruc, 'base_datos' => 'bd_prueba_vis_'.$ruc, 'razon_social' => 'EMPRESA '.$ruc, 'estado' => 'ACTIVO',
            'marca_id' => $marca, 'creado_por' => $creador]);
    }

    public function test_todos_los_usuarios_de_una_marca_ven_las_mismas_empresas(): void
    {
        [$ana, $beto] = [$this->usuario(self::MARCA), $this->usuario(self::MARCA)];
        $deAna = $this->empresa('20888888881', self::MARCA, $ana->id);
        $deTushpa = $this->empresa('20888888882', null, $this->usuario()->id);

        $this->assertTrue(VisibilidadPanel::puedeVer($beto, $deAna), 'Beto ve la empresa que creó Ana (misma marca)');
        $this->assertFalse(VisibilidadPanel::puedeVer($beto, $deTushpa), 'Nunca una empresa de otra marca o de TUSHPA');
        $ids = VisibilidadPanel::filtrar(Cliente::query(), $beto)->whereIn('ruc', ['20888888881', '20888888882'])->pluck('id')->all();
        $this->assertSame([$deAna->id], $ids);
    }

    public function test_en_tushpa_cada_usuario_ve_solo_las_suyas_y_el_dueno_todas(): void
    {
        [$carla, $dante] = [$this->usuario(), $this->usuario()];
        $deCarla = $this->empresa('20888888883', null, $carla->id);
        $deMarca = $this->empresa('20888888884', self::MARCA, null);

        $this->assertTrue(VisibilidadPanel::puedeVer($carla, $deCarla));
        $this->assertFalse(VisibilidadPanel::puedeVer($dante, $deCarla), 'Otro usuario de TUSHPA no la ve');
        $this->assertTrue(VisibilidadPanel::puedeVer($this->usuario(dueno: true), $deMarca), 'El dueño ve todas');

        $this->actingAs($dante, 'superadmin');
        $this->get(route('admin.clientes.diagnostico', $deCarla->id))->assertNotFound();
        $this->actingAs($carla, 'superadmin');
        $this->get(route('admin.clientes.index'))->assertOk()->assertSee('EMPRESA 20888888883')->assertDontSee('EMPRESA 20888888884');
    }
}
