<?php

namespace Tests\Flujos;

use Illuminate\Support\Facades\DB;

/** Plano del local: el dueño ubica las mesas, crea boxes y pone la barra; el mozo las ve así en Comandas */
class PlanoMesasTest extends FlujoTestCase
{
    private int $piso;

    private int $mesa1;

    private int $mesa2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->piso = DB::table('pisos')->insertGetId(['pis_nom' => 'SALON', 'id_empresa_negocio' => $this->sucursal]);
        $base = ['pis_id' => $this->piso, 'mes_est' => 'Libre', 'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal];
        $this->mesa1 = DB::table('mesas')->insertGetId(['mes_nom' => 'MESA 01'] + $base);
        $this->mesa2 = DB::table('mesas')->insertGetId(['mes_nom' => 'MESA 02'] + $base);
    }

    public function test_por_defecto_las_mesas_se_ven_en_cuadricula(): void
    {
        $this->get(route('comandas.seleccion'))->assertOk()->assertSee('MESA 01')->assertDontSee('aspect-[16/10]', false);
        $this->get(route('mesas.plano'))->assertOk()->assertSee('Dibuja tu local');
    }

    public function test_el_plano_ubica_mesas_crea_un_box_y_el_mozo_lo_ve(): void
    {
        $this->postJson(route('mesas.plano.guardar', $this->piso), [
            'mesas' => [
                ['id' => $this->mesa1, 'nombre' => 'mesa 01', 'forma' => 'REDONDA', 'x' => 102, 'y' => 48, 'w' => 90, 'h' => 90],
                ['id' => null, 'nombre' => 'BOX 01', 'forma' => 'BOX', 'x' => 990, 'y' => 400, 'w' => 200, 'h' => 130],
            ],
            'elementos' => [['tipo' => 'BARRA', 'texto' => 'Barra', 'x' => 300, 'y' => 550, 'w' => 300, 'h' => 60, 'color' => '#7c2d12'],
                ['tipo' => 'NAVE', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10]],
            'usar_plano' => true,
        ])->assertOk()->assertJsonPath('ok', true);

        $m1 = DB::table('mesas')->where('mes_id', $this->mesa1)->first();
        $this->assertSame(['REDONDA', 100, 50], [$m1->plano_forma, (int) $m1->plano_x, (int) $m1->plano_y], 'Se acomoda en pasos de 5.');
        $box = DB::table('mesas')->where('pis_id', $this->piso)->where('mes_nom', 'BOX 01')->first();
        $this->assertNotNull($box, 'El box dibujado se crea como mesa.');
        $this->assertSame(800, (int) $box->plano_x, 'No se sale del lienzo (1000 - 200 de ancho).');
        $this->assertNull(DB::table('mesas')->where('mes_id', $this->mesa2)->value('plano_x'), 'La que no está en el plano queda sin ubicar.');
        $this->assertSame(1, DB::table('plano_elementos')->where('pis_id', $this->piso)->count(), 'Lo que no es un elemento conocido no se guarda.');
        $this->assertSame('PLANO', DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->value('vista_mesas'));

        $this->get(route('comandas.seleccion'))->assertOk()->assertSee('aspect-[16/10]', false)->assertSee('VIP')->assertSee('Barra')
            ->assertSee('Mesas que aún no están en el plano')->assertSee('MESA 02');
        $this->getJson(route('comandas.mesas_por_piso', $this->piso))->assertOk()->assertJsonPath('vista', fn ($v) => str_contains($v, 'BOX 01'));
    }

    public function test_no_deja_dos_mesas_con_el_mismo_nombre(): void
    {
        // MESA 02 queda sin ubicar y conserva su nombre: un box nuevo no puede llamarse igual
        $this->postJson(route('mesas.plano.guardar', $this->piso), [
            'mesas' => [['id' => null, 'nombre' => 'MESA 02', 'forma' => 'BOX', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 130]], 'elementos' => [],
        ])->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertSame(2, DB::table('mesas')->where('pis_id', $this->piso)->count());
    }
}
