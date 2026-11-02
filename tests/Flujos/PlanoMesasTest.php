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
        $this->get(route('comandas.seleccion'))->assertOk()->assertSee('MESA 01')->assertDontSee('plano-lienzo', false);
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

        $this->get(route('comandas.seleccion'))->assertOk()->assertSee('plano-lienzo', false)->assertSee('VIP')->assertSee('Barra')
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

    public function test_eliminar_una_mesa_libera_su_nombre_pero_no_si_esta_ocupada(): void
    {
        // MESA 02 (sin ubicar) se elimina y un box nuevo puede llamarse igual
        $this->postJson(route('mesas.plano.guardar', $this->piso), [
            'mesas' => [['id' => null, 'nombre' => 'MESA 02', 'forma' => 'BOX', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 130]],
            'elementos' => [], 'eliminar' => [$this->mesa2],
        ])->assertOk()->assertJsonPath('ok', true);
        $this->assertFalse(DB::table('mesas')->where('mes_id', $this->mesa2)->exists());
        $this->assertSame('BOX', DB::table('mesas')->where('pis_id', $this->piso)->where('mes_nom', 'MESA 02')->value('plano_forma'));

        // Una mesa con pedido abierto no se elimina
        $lomo = $this->producto('LOMO', 30, 5);
        $this->postJson(route('comandas.set_servicio'), ['order_type' => 'salon', 'mesa_id' => $this->mesa1, 'mesa_nombre' => 'MESA 01'])->assertOk();
        $this->postJson(route('comandas.add_to_cart'), ['id' => $lomo->IdProducto])->assertOk();
        $this->postJson(route('comandas.enviar'))->assertOk();
        $this->postJson(route('mesas.plano.guardar', $this->piso), ['mesas' => [], 'elementos' => [], 'eliminar' => [$this->mesa1]])
            ->assertStatus(422)->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'está ocupada'));
        $this->assertTrue(DB::table('mesas')->where('mes_id', $this->mesa1)->exists());
    }

    public function test_el_ambiente_puede_ser_mas_largo_que_ancho(): void
    {
        $this->postJson(route('mesas.plano.guardar', $this->piso), [
            'mesas' => [['id' => $this->mesa1, 'nombre' => 'MESA 01', 'forma' => 'CUADRADA', 'x' => 100, 'y' => 1400, 'w' => 90, 'h' => 90]],
            'elementos' => [], 'alto' => 1600, 'usar_plano' => true,
        ])->assertOk();
        $this->assertSame(1600, (int) DB::table('pisos')->where('pis_id', $this->piso)->value('plano_alto'));
        $this->assertSame(1400, (int) DB::table('mesas')->where('mes_id', $this->mesa1)->value('plano_y'), 'En un ambiente largo la mesa puede ir más abajo.');
        $this->get(route('comandas.seleccion'))->assertOk()->assertSee('aspect-ratio: 1000 / 1600', false);
        $this->get(route('mesas.plano'))->assertOk()->assertSee('Medidas del local');

        // Si se achica el ambiente, nada queda fuera
        $this->postJson(route('mesas.plano.guardar', $this->piso), [
            'mesas' => [['id' => $this->mesa1, 'nombre' => 'MESA 01', 'forma' => 'CUADRADA', 'x' => 100, 'y' => 1400, 'w' => 90, 'h' => 90]],
            'elementos' => [], 'alto' => 625,
        ])->assertOk();
        $this->assertSame(535, (int) DB::table('mesas')->where('mes_id', $this->mesa1)->value('plano_y'));
    }

    public function test_un_local_de_12_por_30_metros_conserva_el_tamano_de_las_mesas(): void
    {
        // 12 m = 600 unidades de ancho, 30 m = 1500 de largo (1 unidad = 2 cm); un box de 4 m (200) sigue midiendo 4 m
        $this->postJson(route('mesas.plano.guardar', $this->piso), [
            'mesas' => [['id' => $this->mesa1, 'nombre' => 'BOX 01', 'forma' => 'BOX', 'x' => 900, 'y' => 1000, 'w' => 200, 'h' => 130]],
            'elementos' => [], 'ancho' => 600, 'alto' => 1500, 'usar_plano' => true,
        ])->assertOk();
        $this->assertSame([600, 1500], [(int) DB::table('pisos')->where('pis_id', $this->piso)->value('plano_ancho'), (int) DB::table('pisos')->where('pis_id', $this->piso)->value('plano_alto')]);
        $box = DB::table('mesas')->where('mes_id', $this->mesa1)->first();
        $this->assertSame([200, 400], [(int) $box->plano_w, (int) $box->plano_x], 'El box mide lo mismo y se acomoda dentro de los 12 m (600 - 200).');
        $this->get(route('comandas.seleccion'))->assertOk()->assertSee('aspect-ratio: 600 / 1500', false);
        $this->get(route('mesas.plano'))->assertOk()->assertSee('Medidas del local');
    }
}
