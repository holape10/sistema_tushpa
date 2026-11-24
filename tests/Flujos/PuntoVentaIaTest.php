<?php

namespace Tests\Flujos;

use App\Support\Asistente\Asistente;
use Illuminate\Support\Facades\DB;

/** Punto Venta IA: lo que dice el cajero se vuelve acciones de la venta; lo que no existe se crea al aceptar */
class PuntoVentaIaTest extends FlujoTestCase
{
    private array $enviado = [];

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('central')->beginTransaction();
        config(['services.anthropic.limite_empresa' => 100, 'services.anthropic.tope_mensual_usd' => 20]);
    }

    protected function tearDown(): void
    {
        Asistente::$falso = null;
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    /** La IA responde estas acciones (cada una con todos sus campos, como pide el esquema) */
    private function iaResponde(array $acciones, string $respuesta = 'Listo'): void
    {
        $base = ['producto' => null, 'cantidad' => null, 'precio' => null, 'linea' => null, 'medio' => null, 'monto' => null, 'valor' => null];
        Asistente::$falso = function (array $p) use ($acciones, $respuesta, $base) {
            $this->enviado = $p;

            return (object) ['stopReason' => 'end_turn', 'content' => [(object) ['type' => 'text', 'text' => json_encode([
                'acciones' => array_map(fn ($a) => $a + $base, $acciones), 'respuesta' => $respuesta,
            ])]], 'usage' => (object) ['inputTokens' => 800, 'outputTokens' => 90, 'cacheReadInputTokens' => 0, 'cacheCreationInputTokens' => 0]];
        };
    }

    public function test_la_pantalla_es_el_punto_venta_con_el_asistente_de_voz(): void
    {
        $this->get(route('pv.ia'))->assertOk()->assertSee('Punto Venta IA')->assertSee('punto-venta-ia.js', false)->assertSee('Toca el micrófono');
        $this->get(route('pv.index'))->assertOk()->assertDontSee('punto-venta-ia.js', false);
    }

    public function test_la_ia_arma_productos_y_pagos_y_encuentra_los_del_catalogo(): void
    {
        $jabon = $this->producto('JABON BOLIVAR', 3.5, 20);
        $this->producto('INCA KOLA 500 ML', 2.5, 20);
        $this->producto('INCA KOLA 1 L', 5, 20);
        $this->iaResponde([
            ['tipo' => 'agregar', 'producto' => 'jabon', 'cantidad' => 2],
            ['tipo' => 'agregar', 'producto' => 'inca kola', 'cantidad' => 1],
            ['tipo' => 'agregar', 'producto' => 'detergente opal', 'cantidad' => 1, 'precio' => 6],
            ['tipo' => 'pago', 'medio' => 'EFECTIVO', 'monto' => 30],
            ['tipo' => 'cobrar'],
        ], 'Agregué 2 jabones; ¿cuál inca kola?');

        $r = $this->postJson(route('pv.ia.interpretar'), ['texto' => 'quiero 2 jabones, una inca kola y un detergente opal a 6, paga 30 en efectivo y cobra',
            'detalle' => [], 'total' => 0, 'medios' => ['EFECTIVO', 'YAPE']])->assertOk()->json();

        $this->assertTrue($r['ok']);
        [$a1, $a2, $a3] = $r['acciones'];
        $this->assertSame($jabon->IdProducto, $a1['encontrado']['id'], 'Un solo jabón: ese.');
        $this->assertNull($a2['encontrado']);
        $this->assertCount(2, $a2['opciones'], 'Dos inca kola: se elige.');
        $this->assertNull($a3['encontrado']);
        $this->assertSame([], $a3['opciones'], 'No existe: se ofrece crear.');
        $this->assertSame('pago', $r['acciones'][3]['tipo']);
        $this->assertStringContainsString('Medios de pago: EFECTIVO, YAPE', $this->enviado['messages'][0]['content']);
        $this->assertSame(1, DB::connection('central')->table('asistente_uso')->where('pregunta', 'like', 'Punto Venta IA:%')->count(), 'Cuenta como una pregunta.');
    }

    public function test_lo_simple_se_busca_sin_ia_y_lo_que_no_existe_se_crea_al_aceptar(): void
    {
        $this->producto('JABON BOLIVAR', 3.5, 20);
        $this->getJson(route('pv.ia.buscar', ['producto' => 'jabon']))->assertOk()->assertJsonPath('encontrado.nombre', 'JABON BOLIVAR');

        $r = $this->postJson(route('pv.ia.producto'), ['nombre' => 'detergente opal', 'precio' => 6])->assertOk()->json();
        $this->assertSame('DETERGENTE OPAL', $r['producto']['nombre']);
        $this->assertEquals(6.0, $r['producto']['precio']);
        $this->assertSame(1, DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('pronom', 'DETERGENTE OPAL')->count());
        // Aceptar otra vez no lo duplica
        $this->postJson(route('pv.ia.producto'), ['nombre' => 'DETERGENTE OPAL', 'precio' => 6])->assertOk();
        $this->assertSame(1, DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('pronom', 'DETERGENTE OPAL')->count());
    }

    public function test_si_la_ia_no_esta_disponible_lo_dice(): void
    {
        config(['services.anthropic.limite_empresa' => 0]);
        $this->iaResponde([]);
        $this->postJson(route('pv.ia.interpretar'), ['texto' => 'paga todo con yape', 'medios' => ['YAPE']])
            ->assertOk()->assertJson(['ok' => false])->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'preguntas'));
    }
}
