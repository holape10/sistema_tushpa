<?php

namespace Tests\Flujos;

use App\Models\Central\Cliente;
use App\Support\Asistente\Asistente;
use App\Support\Asistente\ConsultaSegura;
use App\Support\Asistente\Soporte;
use App\Support\Tenancy\Tenancy;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Soporte con IA del panel: consulta la base de una empresa con SQL de solo lectura (sin llamar a la API real) */
class SoporteIaTest extends FlujoTestCase
{
    /** @var list<array<string, mixed>> */
    private array $llamadas = [];

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('central')->beginTransaction();
        config(['services.anthropic.tope_mensual_usd' => 20, 'services.anthropic.modelo_soporte' => 'claude-sonnet-5-5']);
    }

    protected function tearDown(): void
    {
        Asistente::$falso = null;
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    private function respuesta(string $parada, array $contenido): object
    {
        return (object) ['stopReason' => $parada, 'content' => array_map(fn ($b) => (object) $b, $contenido),
            'usage' => (object) ['inputTokens' => 2000, 'outputTokens' => 300, 'cacheReadInputTokens' => 0, 'cacheCreationInputTokens' => 0]];
    }

    public function test_solo_se_permiten_consultas_de_lectura_de_la_propia_base(): void
    {
        $this->assertNull(ConsultaSegura::problema("SELECT REPLACE(ccanom, 'A', 'B') FROM cpe_cabecera WHERE ccaobs = 'UPDATE pendiente' LIMIT 5"));
        foreach (['UPDATE cpe_cabecera SET ccaitv = 0', 'SELECT 1; DELETE FROM cpe_cabecera', 'DELETE FROM users',
            "SELECT * FROM users INTO OUTFILE '/tmp/x'", 'SELECT SLEEP(30)', 'SELECT * FROM cpe_cabecera FOR UPDATE',
            'SELECT * FROM mysql.user', 'SELECT 1 -- comentario', 'SHOW DATABASES'] as $sql) {
            $this->assertNotNull(ConsultaSegura::problema($sql), "Debió rechazar: {$sql}");
        }
        DB::table('empresa')->where('IdEmpresa', $this->usuario->IdEmpresa)->update(['claveSunat' => 'SECRETO123']);

        $r = ConsultaSegura::ejecutar('SELECT IdEmpresa, claveSunat FROM empresa LIMIT 1');

        $this->assertSame('•••• (oculto)', $r['filas'][0]['claveSunat'], 'Las claves salen tapadas.');
        $this->assertSame((string) $this->usuario->IdEmpresa, (string) $r['filas'][0]['IdEmpresa']);
    }

    public function test_responde_consultando_la_base_de_la_empresa_sin_poder_cambiarla(): void
    {
        VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('JUANE', 15, 10), 2]], '03'), 'PV');
        $cliente = new Cliente(['razon_social' => 'RESTAURANTE DE PRUEBA SAC', 'ruc' => (string) $this->usuario->IdEmpresa, 'base_datos' => Tenancy::baseActual()]);
        $cliente->id = 999999;
        $respuestas = [
            $this->respuesta('tool_use', [
                ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'consulta_sql', 'input' => ['sql' => 'SELECT COUNT(*) AS boletas, SUM(ccaitv) AS total FROM cpe_cabecera WHERE tdocod = \'03\'']],
                ['type' => 'tool_use', 'id' => 'tu_2', 'name' => 'consulta_sql', 'input' => ['sql' => 'UPDATE cpe_cabecera SET ccaitv = 0']],
            ]),
            $this->respuesta('end_turn', [['type' => 'text', 'text' => 'Tiene **1 boleta** por S/ 30.00.']]),
        ];
        Asistente::$falso = function (array $p) use (&$respuestas) {
            $this->llamadas[] = $p;

            return array_shift($respuestas);
        };

        $r = Soporte::preguntar($cliente, 'Dueño', [], '¿Cuántas boletas tiene?');

        $this->assertSame('Tiene **1 boleta** por S/ 30.00.', $r['respuesta']);
        $resultados = $this->llamadas[1]['messages'][2]['content'];
        $this->assertStringContainsString('"boletas":1', $resultados[0]['content']);
        $this->assertStringContainsString('error', $resultados[1]['content'], 'El UPDATE se rechaza.');
        $this->assertSame(0, DB::table('cpe_cabecera')->where('ccaitv', 0)->count(), 'Nada cambió.');
        $this->assertSame('claude-sonnet-5-5', $this->llamadas[0]['model']);

        $uso = DB::connection('central')->table('asistente_uso')->latest('id')->first();
        $this->assertNull($uso->cliente_id, 'No gasta el cupo de la empresa.');
        $this->assertSame('Soporte: Dueño', $uso->usuario);
        $this->assertSame(2, (int) $uso->consultas);
    }
}
