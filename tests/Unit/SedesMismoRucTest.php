<?php

namespace Tests\Unit;

use App\Http\Middleware\IdentificarEmpresa;
use App\Models\Central\Cliente;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Un RUC con varios sistemas separados (sedes que trabajan cada una por su lado): bd_{RUC}, bd_{RUC}_2… Cada uno entra
 * por su subdominio, el RUC abre el primero y los resúmenes de cada uno usan su propio rango de correlativos.
 */
class SedesMismoRucTest extends TestCase
{
    private const RUC = '20408925593';

    protected function setUp(): void
    {
        parent::setUp();
        // Base central de mentira en memoria: la prueba no toca la base central de verdad
        config(['database.connections.central' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'tenancy.dominio' => 'tushpa.app', 'tenancy.esquema' => 'https', 'tenancy.prefijo_bd' => 'bd_', 'tenancy.subdominio_admin' => 'admin', 'tenancy.principales' => []]);
        DB::purge('central');
        Schema::connection('central')->create('clientes', function (Blueprint $t) {
            $t->id();
            $t->string('ruc');
            $t->string('subdominio')->nullable();
            $t->string('estado')->default('ACTIVO');
            $t->string('base_datos');
            $t->unsignedBigInteger('marca_id')->nullable();
            $t->timestamps();
        });
        Tenancy::olvidarMarcas();
        Cliente::create(['ruc' => self::RUC, 'subdominio' => 'epc', 'base_datos' => 'bd_'.self::RUC]);
        Cliente::create(['ruc' => self::RUC, 'subdominio' => 'epc2', 'base_datos' => 'bd_'.self::RUC.'_2']);
    }

    protected function tearDown(): void
    {
        Tenancy::olvidarMarcas();
        parent::tearDown();
    }

    private function entrar(string $host): string
    {
        $original = Tenancy::baseActual();
        try {
            (new IdentificarEmpresa)->handle(Request::create("https://{$host}/login"), fn () => response('ok'));

            return Tenancy::cliente() ? Tenancy::baseActual() : 'nada';
        } catch (HttpException $e) {
            return (string) $e->getStatusCode();
        } finally {
            Tenancy::conectar($original);
            app()->forgetInstance('tenancy.cliente');
        }
    }

    public function test_cada_sede_entra_por_su_subdominio_y_el_ruc_abre_la_primera(): void
    {
        $this->assertSame('bd_'.self::RUC, $this->entrar('epc.tushpa.app'));
        $this->assertSame('bd_'.self::RUC.'_2', $this->entrar('epc2.tushpa.app'));
        $this->assertSame('bd_'.self::RUC, $this->entrar(self::RUC.'.tushpa.app'));
    }

    public function test_el_nombre_de_la_base_de_cada_sede_y_su_rango_de_resumenes(): void
    {
        $this->assertSame('bd_'.self::RUC.'_3', Tenancy::nombreBase(self::RUC, null, 3));
        $this->assertSame('bd_ws_'.self::RUC, Tenancy::nombreBase(self::RUC, 'bd_ws_'));
        $this->assertSame(1, Tenancy::sedeDeBase('bd_ws_'.self::RUC), 'El RUC no se confunde con el número de sede.');
        $this->assertSame(2, Tenancy::sedeDeBase('bd_ws_'.self::RUC.'_2'));
        $this->assertTrue(Cliente::all()->every(fn ($c) => Tenancy::esBaseDe($c)));
        $this->assertFalse(Tenancy::esBaseDe(new Cliente(['ruc' => self::RUC, 'base_datos' => 'bd_20999999999_2'])));

        $original = Tenancy::baseActual();
        try {
            Tenancy::conectar('bd_'.self::RUC);
            $this->assertSame(0, Tenancy::desfaseResumenes());
            Tenancy::conectar('bd_'.self::RUC.'_3');
            $this->assertSame(200, Tenancy::desfaseResumenes(), 'La 3.ª sede numera sus resúmenes del día desde 201.');
        } finally {
            Tenancy::conectar($original);
        }
    }
}
