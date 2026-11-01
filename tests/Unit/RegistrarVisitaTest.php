<?php

namespace Tests\Unit;

use App\Http\Middleware\RegistrarVisita;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/** Visitas de la demostración (demos.tushpa.app): solo cuando se abre una pantalla, no las consultas de fondo */
class RegistrarVisitaTest extends TestCase
{
    private const NAVEGADOR = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        // Base central de mentira en memoria: la prueba no toca la base central de verdad
        config(['database.connections.central' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'sitio.demo.url' => 'https://demos.tushpa.app', 'tenancy.dominio' => 'tushpa.app']);
        DB::purge('central');
        Schema::connection('central')->create('web_visitas', function (Blueprint $t) {
            $t->id();
            $t->dateTime('fecha');
            $t->string('ruta', 120);
            $t->char('visitante', 16);
            $t->string('origen', 80)->nullable();
            $t->string('dispositivo', 10);
            $t->char('pais', 2)->nullable();
            $t->string('region', 80)->nullable();
            $t->string('ciudad', 80)->nullable();
            $t->string('sitio', 10)->default('web');
        });
    }

    private function visitar(string $url, string $sitio, string $modo = 'navigate'): void
    {
        $request = Request::create($url, 'GET', server: ['HTTP_USER_AGENT' => self::NAVEGADOR, 'HTTP_SEC_FETCH_MODE' => $modo, 'REMOTE_ADDR' => '190.12.34.56']);
        (new RegistrarVisita)->handle($request, fn () => new Response('<html></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']), $sitio);
    }

    public function test_abrir_una_pantalla_de_la_demo_cuenta_como_visita_de_la_demo(): void
    {
        $this->visitar('https://demos.tushpa.app/login', 'demos');

        $this->assertSame(['demos'], DB::connection('central')->table('web_visitas')->pluck('sitio')->all());
    }

    public function test_las_actualizaciones_automaticas_de_la_demo_no_cuentan(): void
    {
        $this->visitar('https://demos.tushpa.app/comandas/mesas/1', 'demos', 'cors');

        $this->assertSame(0, DB::connection('central')->table('web_visitas')->count());
    }

    public function test_las_otras_empresas_no_se_cuentan_como_demo(): void
    {
        $this->visitar('https://mirestaurante.tushpa.app/comandas', 'demos');

        $this->assertSame(0, DB::connection('central')->table('web_visitas')->count());
    }

    public function test_la_web_se_sigue_contando_como_web(): void
    {
        $this->visitar('https://tushpa.app/', 'web');

        $this->assertSame(['web'], DB::connection('central')->table('web_visitas')->pluck('sitio')->all());
    }
}
