<?php

namespace Tests\Flujos;

use App\Support\Asistente\Asistente;
use App\Support\Asistente\ProductoVoz;
use App\Support\ImagenProducto;
use Illuminate\Http\UploadedFile;
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

    public function test_con_foto_la_ia_lee_el_paquete_y_la_foto_queda_como_imagen_del_producto(): void
    {
        Asistente::$falso = function (array $p) {
            $this->enviado = $p;

            return (object) ['stopReason' => 'end_turn', 'content' => [(object) ['type' => 'text', 'text' => json_encode(['productos' => [
                ['nombre' => 'leche evaporada gloria 400 g', 'precio' => 4.5, 'costo' => null, 'stock' => 12, 'unidad' => 'NIU', 'categoria' => 'lacteos',
                    'codigo_barra' => '7751271 001019', 'presentaciones' => []],
            ], 'respuesta' => 'Leí la leche Gloria'])]],
                'usage' => (object) ['inputTokens' => 1800, 'outputTokens' => 150, 'cacheReadInputTokens' => 0, 'cacheCreationInputTokens' => 0]];
        };

        $leido = $this->post(route('productos.foto.leer'), ['foto' => UploadedFile::fake()->image('leche.jpg', 900, 1200), 'texto' => 'a 4.50 stock 12'],
            ['Accept' => 'application/json'])->assertOk()->json();

        $this->assertTrue($leido['ok'], json_encode($leido));
        $producto = $leido['productos'][0];
        $this->assertSame('LECHE EVAPORADA GLORIA 400 G', $producto['nombre']);
        $this->assertSame('7751271001019', $producto['codigo_barra'], 'El código de barras queda solo con sus dígitos.');
        $this->assertSame('image', $this->enviado['messages'][0]['content'][0]['type'], 'La IA recibe la foto.');
        $this->assertStringContainsString('a 4.50 stock 12', $this->enviado['messages'][0]['content'][1]['text']);
        $temporal = ProductoVoz::rutaFoto($producto['foto']);
        $this->assertNotNull($temporal, 'La foto espera a que se cree el producto.');

        $creado = $this->postJson(route('productos.voz.crear'), ['productos' => [$producto]])->assertOk()->json();

        $this->assertTrue($creado['ok'], json_encode($creado));
        $p = DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('pronom', 'LECHE EVAPORADA GLORIA 400 G')->first();
        $this->assertSame('7751271001019', $p->codigo_barra);
        $this->assertNotEmpty($p->imagenproducto, 'La foto pasa a ser la imagen del producto.');
        $this->assertFileExists(public_path($p->imagenproducto));
        $this->assertFileDoesNotExist($temporal, 'La foto de espera se borra al crear el producto.');
        ImagenProducto::borrar($p->imagenproducto);
    }

    public function test_el_nombre_se_guarda_en_mayusculas_aunque_se_escriba_en_minusculas(): void
    {
        $this->post(route('productos.store'), ['promocion' => 0, 'pronom' => '  burro juego  inflable color amarillo ', 'propun' => 40, 'umecod' => 'NIU'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('pronom', 'BURRO JUEGO INFLABLE COLOR AMARILLO')->exists());
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
