<?php

namespace Tests\Flujos;

use App\Support\Asistente\Asistente;
use Illuminate\Support\Facades\DB;

/** Productos › Crear con voz (IA): la IA llena nombre, precio, stock y presentaciones; al aceptar se crean como en el formulario */
class ProductoVozTest extends FlujoTestCase
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

    public function test_la_ia_entiende_varios_productos_con_sus_presentaciones(): void
    {
        Asistente::$falso = function (array $p) {
            $this->enviado = $p;

            return (object) ['stopReason' => 'end_turn', 'content' => [(object) ['type' => 'text', 'text' => json_encode(['productos' => [
                ['nombre' => 'Jabon Bolivar', 'precio' => 3.5, 'costo' => null, 'stock' => 24, 'unidad' => 'NIU', 'categoria' => 'limpieza',
                    'presentaciones' => [['nombre' => 'caja x12', 'unidad' => 'BX', 'factor' => 12, 'precio' => 38]]],
                ['nombre' => 'ARROZ COSTEÑO 5 KG', 'precio' => 22, 'costo' => 19, 'stock' => null, 'unidad' => 'XXX', 'categoria' => null, 'presentaciones' => []],
            ], 'respuesta' => 'Entendí 2 productos'])]],
                'usage' => (object) ['inputTokens' => 900, 'outputTokens' => 200, 'cacheReadInputTokens' => 0, 'cacheCreationInputTokens' => 0]];
        };

        $r = $this->postJson(route('productos.voz.interpretar'), ['texto' => 'jabón bolívar a 3.50 stock 24 la caja de 12 a 38, arroz costeño 5 kilos a 22 costo 19'])
            ->assertOk()->json();

        $this->assertTrue($r['ok']);
        $this->assertSame('JABON BOLIVAR', $r['productos'][0]['nombre']);
        $this->assertSame([['nombre' => 'CAJA X12', 'unidad' => 'BX', 'factor' => 12, 'precio' => 38]], $r['productos'][0]['presentaciones']);
        $this->assertSame('NIU', $r['productos'][1]['unidad'], 'Una unidad que no existe pasa a NIU.');
        $this->assertStringContainsString('BX=', $this->enviado['messages'][0]['content'], 'La IA recibe las unidades de la lista.');
    }

    public function test_al_aceptar_se_crean_con_stock_inicial_presentaciones_y_categoria(): void
    {
        $r = $this->postJson(route('productos.voz.crear'), ['productos' => [
            ['nombre' => 'jabon bolivar', 'precio' => 3.5, 'stock' => 24, 'unidad' => 'NIU', 'categoria' => 'LIMPIEZA',
                'presentaciones' => [['nombre' => 'CAJA X12', 'unidad' => 'BX', 'factor' => 12, 'precio' => 38]]],
        ]])->assertOk()->json();

        $this->assertTrue($r['ok'], json_encode($r));
        $p = DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('pronom', 'JABON BOLIVAR')->first();
        $this->assertNotNull($p);
        $this->assertEquals(3.5, (float) $p->propun);
        $this->assertSame('LIMPIEZA', DB::table('categorias')->where('cat_id', $p->cat_id)->value('cat_nom'));
        $this->assertEquals(24.0, (float) DB::table('producto_stock')->where('IdProducto', $p->IdProducto)->sum('stock'), 'El stock inicial entra al kardex.');
        $pres = DB::table('producto_presentacion')->where('IdProducto', $p->IdProducto)->first();
        $this->assertEquals([12.0, 38.0], [(float) $pres->factor, (float) $pres->precio]);

        // Otra vez el mismo nombre: no se duplica
        $otra = $this->postJson(route('productos.voz.crear'), ['productos' => [['nombre' => 'JABON BOLIVAR', 'precio' => 3.5]]])->assertOk()->json();
        $this->assertFalse($otra['resultados'][0]['ok']);
        $this->assertSame(1, DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('pronom', 'JABON BOLIVAR')->count());
    }
}
