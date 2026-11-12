<?php

namespace Tests\Flujos;

use App\Support\Asistente\Asistente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Contar un producto con una foto: la IA cuenta en la presentación que ve y el sistema lo pasa a la unidad del stock */
class ConteoFotoTest extends FlujoTestCase
{
    /** Lo que se le mandó a la IA (para revisar que no lleve el stock) */
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

    private function iaCuenta(array $datos): void
    {
        Asistente::$falso = function (array $p) use ($datos) {
            $this->enviado = $p;

            return (object) ['stopReason' => 'end_turn', 'content' => [(object) ['type' => 'text', 'text' => json_encode($datos + [
                'es_producto' => true, 'calculo' => '', 'presentacion' => 0, 'cantidad' => 0, 'sueltos' => 0, 'confianza' => 'alta', 'supone_oculto' => false, 'aviso' => '',
            ])]], 'usage' => (object) ['inputTokens' => 1600, 'outputTokens' => 120, 'cacheReadInputTokens' => 0, 'cacheCreationInputTokens' => 0]];
        };
    }

    private function contar(int $producto)
    {
        return $this->postJson(route('inventario.contar_foto'), ['producto' => $producto, 'foto' => UploadedFile::fake()->image('ruma.jpg', 1200, 900)]);
    }

    public function test_las_cajas_de_la_ruma_se_pasan_a_unidades_con_el_factor_de_la_presentacion(): void
    {
        $gaseosa = $this->producto('GASEOSA 500ML', 2.5, 137);
        DB::table('producto_presentacion')->insert(['IdProducto' => $gaseosa->IdProducto, 'umecod' => 'BX', 'nombre' => 'Caja x24', 'factor' => 24, 'precio' => 55, 'estado' => 1]);
        $this->iaCuenta(['calculo' => '3 filas × 2 columnas × 2 pisos = 12', 'presentacion' => 1, 'cantidad' => 12, 'sueltos' => 5, 'confianza' => 'media',
            'supone_oculto' => true, 'aviso' => 'No se ve la parte de atrás.']);

        $r = $this->contar($gaseosa->IdProducto)->assertOk()->json();

        $this->assertSame(293, (int) $r['cantidad'], '12 cajas × 24 + 5 sueltas.');
        $this->assertStringContainsString('12 Caja x24 × 24 + 5 sueltos = 293', $r['detalle']);
        $this->assertSame('media', $r['confianza']);
        $this->assertStringContainsString('parte que no se ve', $r['aviso']);
        $pregunta = $this->enviado['messages'][0]['content'][1]['text'];
        $this->assertStringContainsString('1: Caja x24 = 24', $pregunta);
        $this->assertStringNotContainsString('137', $pregunta, 'La IA nunca recibe el stock del sistema (el conteo ciego sigue ciego).');
        $this->assertSame(1, DB::connection('central')->table('asistente_uso')->count(), 'Cuenta como una pregunta del asistente.');
    }

    public function test_el_boton_esta_en_el_inventario_y_en_el_conteo_ciego(): void
    {
        $this->producto('ARENA FINA', 40, 10);

        $this->get(route('inventarios.create'))->assertOk()->assertSee('conFoto(p)', false)->assertSee(route('inventario.contar_foto'), false);
        $this->get(route('conteo_ciego.create'))->assertOk()->assertSee('conFoto(p)', false)->assertSee(route('inventario.contar_foto'), false);
    }

    public function test_si_la_ia_no_ve_el_producto_avisa_y_no_propone_nada(): void
    {
        $cemento = $this->producto('CEMENTO SOL 42.5KG', 30);
        $this->iaCuenta(['es_producto' => false]);

        $this->contar($cemento->IdProducto)->assertStatus(422)->assertJson(['ok' => false])->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'buena luz'));
    }

    public function test_solo_el_administrador_o_quien_tiene_el_conteo_ciego_y_solo_productos_de_su_sede(): void
    {
        $fierro = $this->producto('FIERRO 1/2', 40);
        $this->iaCuenta(['cantidad' => 30]);

        $otro = $this->usuario->replicate();
        $otro->email = 'cajero'.uniqid().'@prueba.test';
        $otro->save();
        DB::table('role_user')->insert(['role_id' => 17, 'user_IdUsuario' => $otro->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        $this->actingAs($otro);
        $this->contar($fierro->IdProducto)->assertForbidden();

        DB::table('modulos_usuario')->insert(['user_IdUsuario' => $otro->IdUsuario, 'mod_id' => DB::table('modulos')->where('mod_url', '/conteo-ciego')->value('mod_id')]);
        $this->contar($fierro->IdProducto)->assertOk()->assertJsonPath('cantidad', 30);

        DB::table('productos')->where('IdProducto', $fierro->IdProducto)->update(['id_empresa_negocio' => $this->sucursal + 999]);
        $this->contar($fierro->IdProducto)->assertStatus(422)->assertJsonPath('mensaje', 'Producto no encontrado.');
    }
}
