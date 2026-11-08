<?php

namespace Tests\Flujos;

use Anthropic\Core\Exceptions\APIConnectionException;
use App\Models\Empresa;
use App\Support\Asistente\Asistente;
use App\Support\Asistente\Herramientas;
use App\Support\VentaDirecta;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\DB;

/** Asistente IA: consulta solo lo de su empresa, anota cada pregunta con su costo y respeta los límites (sin llamar a la API real) */
class AsistenteTest extends FlujoTestCase
{
    /** @var list<array<string, mixed>> lo que se le mandó a la "API" en cada vuelta */
    private array $llamadas = [];

    protected function setUp(): void
    {
        parent::setUp();
        // El uso se anota en la base central: que la prueba no deje nada
        DB::connection('central')->beginTransaction();
        config(['services.anthropic.limite_empresa' => 100, 'services.anthropic.tope_mensual_usd' => 20, 'services.anthropic.modelo' => 'claude-opus-5-5']);
    }

    protected function tearDown(): void
    {
        Asistente::$falso = null;
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    private function bloque(array $datos): object
    {
        return (object) $datos;
    }

    /** Respuestas de la "API": primero pide un reporte, luego responde con texto */
    private function fingir(array $respuestas): void
    {
        Asistente::$falso = function (array $p) use (&$respuestas) {
            $this->llamadas[] = $p;

            return array_shift($respuestas);
        };
    }

    private function respuesta(string $parada, array $contenido, int $entrada = 3000, int $salida = 500): object
    {
        return (object) ['stopReason' => $parada, 'content' => array_map(fn ($b) => $this->bloque($b), $contenido),
            'usage' => (object) ['inputTokens' => $entrada, 'outputTokens' => $salida, 'cacheReadInputTokens' => 0, 'cacheCreationInputTokens' => 0]];
    }

    public function test_consulta_los_datos_responde_y_anota_el_costo(): void
    {
        $lomo = $this->producto('LOMO SALTADO', 30, 10);
        VentaDirecta::registrar($this->usuario, $this->venta([[$lomo, 2]], '13'), 'PV');
        $this->fingir([
            $this->respuesta('tool_use', [['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'reporte',
                'input' => ['clave' => 'ventas-producto', 'desde' => now()->toDateString(), 'hasta' => now()->toDateString()]]]),
            $this->respuesta('end_turn', [['type' => 'text', 'text' => 'Hoy vendiste **S/ 60.00**: 2 lomos saltados.']], 4000, 200),
        ]);

        $this->get(route('asistente.index'))->assertOk()->assertSee('Asistente IA');
        $r = $this->postJson(route('asistente.preguntar'), ['pregunta' => '¿Cuánto vendí hoy?'])->assertOk()->assertJson(['ok' => true]);

        $this->assertStringContainsString('<strong>S/ 60.00</strong>', $r->json('html'));
        // La segunda vuelta recibió los datos reales del reporte, ligados a la consulta que pidió
        $resultado = $this->llamadas[1]['messages'][2]['content'][0];
        $this->assertSame(['tool_result', 'tu_1'], [$resultado['type'], $resultado['toolUseID']]);
        $this->assertStringContainsString('LOMO SALTADO', $resultado['content']);
        $this->assertSame('claude-opus-5-5', $this->llamadas[0]['model']);

        $uso = DB::connection('central')->table('asistente_uso')->latest('id')->first();
        $this->assertSame([7000, 700, 1], [(int) $uso->tokens_entrada, (int) $uso->tokens_salida, (int) $uso->consultas]);
        // 7 000 de entrada a US$ 4/M + 700 de salida a US$ 20/M
        $this->assertEqualsWithDelta(0.042, (float) $uso->costo_usd, 0.00001);
        $this->assertSame(1, $r->json('cupo.usadas'));
        // Se recuerda la conversación para la siguiente pregunta
        $this->assertCount(2, session('asistente_historial'));
    }

    public function test_solo_consulta_las_sedes_de_su_empresa(): void
    {
        Empresa::create(['IdEmpresa' => '20100070970', 'NomEmpresa' => 'AJENA SAC', 'DirEmpresa' => '-', 'EstEmpresa' => 'Activo', 'tipo_envio' => 1]);
        $otraEmpresa = DB::table('empresa_negocios')->insertGetId(['IdEmpresa' => '20100070970', 'nombre_comercial' => 'AJENA', 'estado' => 'Activo']);
        $hoy = now()->toDateString();

        $ajeno = json_decode(Herramientas::ejecutar($this->usuario, 'reporte', ['clave' => 'ventas', 'desde' => $hoy, 'hasta' => $hoy, 'sucursal' => $otraEmpresa]), true);
        $this->assertStringContainsString('no la puede ver', $ajeno['error']);
        $propio = json_decode(Herramientas::ejecutar($this->usuario, 'reporte', ['clave' => 'ventas', 'desde' => $hoy, 'hasta' => $hoy]), true);
        $this->assertSame('Reporte de Ventas', $propio['reporte']);
        $this->assertArrayHasKey('error', json_decode(Herramientas::ejecutar($this->usuario, 'reporte', ['clave' => 'ventas', 'desde' => 'ayer', 'hasta' => $hoy]), true));
        $this->assertArrayHasKey('error', json_decode(Herramientas::ejecutar($this->usuario, 'borrar_todo', []), true));
    }

    public function test_respeta_el_limite_de_la_empresa_y_el_tope_del_mes(): void
    {
        $texto = fn () => $this->respuesta('end_turn', [['type' => 'text', 'text' => 'Listo.']]);
        $this->fingir([$texto(), $texto()]);
        config(['services.anthropic.limite_empresa' => 1]);

        $this->postJson(route('asistente.preguntar'), ['pregunta' => 'Hola'])->assertJson(['ok' => true]);
        $this->postJson(route('asistente.preguntar'), ['pregunta' => 'Otra'])->assertJson(['ok' => false])
            ->assertJsonPath('mensaje', 'Ya usaste todas las preguntas de este mes. Se renuevan el 1 del próximo mes.');

        config(['services.anthropic.limite_empresa' => 100, 'services.anthropic.tope_mensual_usd' => 0.001]);
        $this->postJson(route('asistente.preguntar'), ['pregunta' => 'Otra'])->assertJsonPath('mensaje', 'El asistente está en pausa por este mes. Vuelve el 1 del próximo mes.');
        $this->assertCount(1, $this->llamadas, 'Bloqueado no se llama a la API (no cuesta nada).');
    }

    public function test_explica_como_hacer_las_cosas_del_sistema(): void
    {
        $porTema = json_decode(Herramientas::ejecutar($this->usuario, 'ayuda_sistema', ['tema' => 'enviar_entre_sedes']), true);
        $this->assertStringContainsString('Enviar a otra sede', $porTema['guias'][0]['pasos']);

        // Con sus propias palabras también la encuentra
        $buscando = json_decode(Herramientas::ejecutar($this->usuario, 'ayuda_sistema', ['buscar' => 'como paso productos del almacen al restaurante']), true);
        $this->assertSame('Enviar productos o insumos del almacén a otra sede (sucursal)', $buscando['guias'][0]['titulo']);

        $nada = json_decode(Herramientas::ejecutar($this->usuario, 'ayuda_sistema', ['buscar' => 'xyz qwerty']), true);
        $this->assertArrayHasKey('aviso', $nada);
        $this->assertContains('ayuda_sistema', array_column(Herramientas::definiciones(), 'name'));
    }

    public function test_solo_el_administrador(): void
    {
        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);

        $this->actingAs($cajero)->get(route('asistente.index'))->assertForbidden();
        $this->actingAs($cajero)->postJson(route('asistente.preguntar'), ['pregunta' => 'Hola'])->assertForbidden();
    }

    public function test_si_la_api_falla_avisa_y_no_cuenta_como_pregunta(): void
    {
        Asistente::$falso = fn () => throw new APIConnectionException(
            new Request('POST', 'https://api.anthropic.com/v1/messages'));

        $this->postJson(route('asistente.preguntar'), ['pregunta' => 'Hola'])->assertJson(['ok' => false])
            ->assertJsonPath('mensaje', 'No se pudo conectar con el asistente. Revisa la conexión e intenta de nuevo.');
        $this->assertSame(0, Asistente::cupo()['usadas']);
        $this->assertSame(1, DB::connection('central')->table('asistente_uso')->whereNotNull('error')->count());
    }
}
