<?php

namespace Tests\Flujos;

use App\Support\Asistente\Asistente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Pasar el cuaderno con foto: la IA lee la hoja y empareja cada línea con un producto; la hoja revisada se registra como una venta */
class CuadernoFotoTest extends FlujoTestCase
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

    public function test_la_ia_lee_la_hoja_y_solo_empareja_productos_de_la_sucursal(): void
    {
        $boxer = $this->producto('BOXER NIÑO', 7, 20);
        Asistente::$falso = function (array $p) use ($boxer) {
            $this->enviado = $p;

            return (object) ['stopReason' => 'end_turn', 'content' => [(object) ['type' => 'text', 'text' => json_encode([
                'es_cuaderno' => true, 'fecha' => now()->subDays(2)->toDateString(), 'total' => 20.5, 'aviso' => '',
                'lineas' => [
                    ['texto' => '2 boxer niño x7', 'cantidad' => 2, 'precio' => 7, 'importe' => 14, 'producto' => $boxer->IdProducto],
                    ['texto' => '1 lima delgada', 'cantidad' => 1, 'precio' => 6.5, 'importe' => 6.5, 'producto' => 999999],
                ],
            ])]], 'usage' => (object) ['inputTokens' => 3000, 'outputTokens' => 400, 'cacheReadInputTokens' => 0, 'cacheCreationInputTokens' => 0]];
        };

        $r = $this->post(route('cuaderno.leer'), ['foto' => UploadedFile::fake()->image('hoja.jpg', 900, 1600)], ['Accept' => 'application/json'])
            ->assertOk()->json();

        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame(now()->subDays(2)->toDateString(), $r['fecha']);
        $this->assertEquals(20.5, $r['total']);
        $this->assertSame($boxer->IdProducto, $r['lineas'][0]['producto']);
        $this->assertNull($r['lineas'][1]['producto'], 'Un producto que no es de la sucursal no se empareja.');
        $this->assertStringContainsString("{$boxer->IdProducto}: BOXER NIÑO", $this->enviado['messages'][0]['content'][1]['text'], 'La IA recibe los productos de la tienda.');
    }

    public function test_la_pantalla_abre_desde_el_panel_de_ventas(): void
    {
        $this->get(route('ventas.index'))->assertOk()->assertSee(route('cuaderno.index'), false);
        $this->get(route('cuaderno.index'))->assertOk()->assertSeeText('Pasar el cuaderno con foto (IA)');
    }

    public function test_la_hoja_revisada_queda_como_venta_con_la_fecha_del_cuaderno_y_baja_el_stock(): void
    {
        $boxer = $this->producto('BOXER NIÑO', 7, 20);
        $lima = $this->producto('LIMA DELGADA', 8, 5);

        $r = $this->postJson(route('cuaderno.registrar'), ['fecha' => now()->subDay()->toDateString(), 'tdocod' => '13', 'lineas' => [
            ['id' => $boxer->IdProducto, 'cantidad' => 2, 'precio' => 7],
            ['id' => $lima->IdProducto, 'cantidad' => 1, 'precio' => 6.5],
        ]])->assertOk()->json();

        $this->assertTrue($r['ok'], json_encode($r));
        $cab = $this->cabecera($r['id']);
        $this->assertSame('13', $cab->tdocod);
        $this->assertSame(now()->subDay()->toDateString(), substr((string) $cab->ccafem, 0, 10));
        $this->assertEquals(20.5, (float) $cab->ccaitv);
        $this->assertEquals(18.0, $this->stock($boxer));
        $this->assertEquals(4.0, $this->stock($lima));
    }

    public function test_no_registra_productos_de_otra_sucursal(): void
    {
        $ajeno = DB::table('productos')->insertGetId(['procod' => 'AJENO1', 'pronom' => 'AJENO', 'propun' => 5, 'proest' => 'Activo', 'promocion' => 0,
            'umecod' => 'NIU', 'IdEmpresa' => '20000000001', 'id_empresa_negocio' => 999999]);

        $this->postJson(route('cuaderno.registrar'), ['fecha' => now()->toDateString(), 'tdocod' => '13', 'lineas' => [['id' => $ajeno, 'cantidad' => 1, 'precio' => 5]]])
            ->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertSame(0, DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->count());
    }
}
