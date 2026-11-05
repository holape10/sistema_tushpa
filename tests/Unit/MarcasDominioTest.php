<?php

namespace Tests\Unit;

use App\Http\Middleware\IdentificarEmpresa;
use App\Models\Central\Cliente;
use App\Models\Central\Marca;
use App\Support\MarcaBlanca;
use App\Support\Tenancy\Contratos;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Marca blanca: las empresas de un revendedor entran por su dominio (2060….wilcatsystems.pe, base bd_ws_{RUC}) y solo
 * ahí; las de TUSHPA siguen entrando por tushpa.app como siempre. Ninguna abre en el dominio de la otra.
 */
class MarcasDominioTest extends TestCase
{
    private const DE_TUSHPA = '20111111111';

    private const DE_WILCAT = '20222222222';

    private Marca $wilcat;

    protected function setUp(): void
    {
        parent::setUp();
        // Base central de mentira en memoria: la prueba no toca la base central de verdad
        config(['database.connections.central' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'tenancy.dominio' => 'tushpa.app', 'tenancy.esquema' => 'https', 'tenancy.prefijo_bd' => 'bd_', 'tenancy.subdominio_admin' => 'admin', 'tenancy.principales' => []]);
        DB::purge('central');
        $central = Schema::connection('central');
        $central->create('clientes', function (Blueprint $t) {
            $t->id();
            $t->string('ruc');
            $t->string('subdominio')->nullable();
            $t->string('razon_social')->nullable();
            $t->string('estado')->default('ACTIVO');
            $t->string('base_datos');
            $t->timestamps();
        });
        $central->create('superadmins', fn (Blueprint $t) => $t->id());
        (include database_path('migrations/central/2027_01_24_000001_marcas.php'))->up();
        Tenancy::olvidarMarcas();

        $this->wilcat = Marca::create(['nombre' => 'WILCAT SYSTEMS', 'dominio' => 'wilcatsystems.pe', 'prefijo_bd' => 'bd_ws_']);
        Cliente::create(['ruc' => self::DE_TUSHPA, 'subdominio' => 'mirestaurante', 'base_datos' => 'bd_'.self::DE_TUSHPA]);
        Cliente::create(['ruc' => self::DE_WILCAT, 'subdominio' => 'contadores', 'base_datos' => 'bd_ws_'.self::DE_WILCAT, 'marca_id' => $this->wilcat->id]);
    }

    protected function tearDown(): void
    {
        Tenancy::olvidarMarcas();
        MarcaBlanca::olvidar();
        parent::tearDown();
    }

    /** Entra por esa dirección: la base que abrió, 'siguió' (no es de ninguna empresa) o el código de error */
    private function entrar(string $host): string
    {
        $original = Tenancy::baseActual();
        try {
            $siguio = false;
            (new IdentificarEmpresa)->handle(Request::create("https://{$host}/login"), function () use (&$siguio) {
                $siguio = true;

                return response('ok');
            });

            return Tenancy::cliente() ? Tenancy::baseActual() : ($siguio ? 'siguió' : 'nada');
        } catch (HttpException $e) {
            return (string) $e->getStatusCode();
        } finally {
            Tenancy::conectar($original);
            app()->forgetInstance('tenancy.cliente');
        }
    }

    public function test_las_empresas_de_tushpa_entran_como_siempre(): void
    {
        $this->assertSame('bd_'.self::DE_TUSHPA, $this->entrar(self::DE_TUSHPA.'.tushpa.app'));
        $this->assertSame('bd_'.self::DE_TUSHPA, $this->entrar('mirestaurante.tushpa.app'));
        $this->assertSame('404', $this->entrar('no-existe.tushpa.app'));
    }

    public function test_las_empresas_de_la_marca_entran_por_su_dominio_con_su_base(): void
    {
        $this->assertSame('bd_ws_'.self::DE_WILCAT, $this->entrar(self::DE_WILCAT.'.wilcatsystems.pe'));
        $this->assertSame('bd_ws_'.self::DE_WILCAT, $this->entrar('contadores.wilcatsystems.pe'));
        $this->assertNull(config('session.domain'), 'Su sesión vive solo en su dirección.');
    }

    public function test_ninguna_empresa_abre_en_el_dominio_de_la_otra(): void
    {
        $this->assertSame('404', $this->entrar(self::DE_WILCAT.'.tushpa.app'));
        $this->assertSame('404', $this->entrar('contadores.tushpa.app'));
        $this->assertSame('404', $this->entrar(self::DE_TUSHPA.'.wilcatsystems.pe'));
        $this->assertSame('404', $this->entrar('mirestaurante.wilcatsystems.pe'));
    }

    /** Lo que responde esa dirección: el código y, si redirige, a dónde ('siguió' = pasó a las rutas) */
    private function responder(string $url): string
    {
        try {
            $r = (new IdentificarEmpresa)->handle(Request::create($url), fn () => response('siguió'));

            return $r->isRedirect() ? $r->getStatusCode().' '.$r->headers->get('Location') : $r->getStatusCode().' '.($r->getContent() === 'siguió' ? 'siguió' : 'página');
        } catch (HttpException $e) {
            return (string) $e->getStatusCode();
        } finally {
            app()->forgetInstance('tenancy.cliente');
        }
    }

    public function test_la_portada_de_la_marca_lleva_a_cada_empresa_solo_si_es_suya(): void
    {
        $this->assertSame('200 página', $this->responder('https://wilcatsystems.pe/'));
        $this->assertSame('301 https://wilcatsystems.pe/', $this->responder('https://www.wilcatsystems.pe/'));
        // Con el RUC o el nombre de su sistema (o la dirección completa pegada) va a su login
        $this->assertSame('302 https://contadores.wilcatsystems.pe/login', $this->responder('https://wilcatsystems.pe/?cuenta='.self::DE_WILCAT));
        $this->assertSame('302 https://contadores.wilcatsystems.pe/login', $this->responder('https://wilcatsystems.pe/?cuenta=https://contadores.wilcatsystems.pe/login'));
        // Una empresa de TUSHPA no se encuentra desde la portada de la marca
        $this->assertSame('200 página', $this->responder('https://wilcatsystems.pe/?cuenta='.self::DE_TUSHPA));
        // Cualquier otra página de la portada vuelve al inicio; su contrato sí pasa (lo atiende su ruta)
        $this->assertSame('302 https://wilcatsystems.pe/', $this->responder('https://wilcatsystems.pe/registro'));
        $this->assertSame('200 siguió', $this->responder('https://wilcatsystems.pe/contrato/'.str_repeat('a', 40)));
        $this->assertSame('404', $this->entrar('a.b.wilcatsystems.pe'));
        $this->assertSame('404', $this->responder('https://admin.wilcatsystems.pe/otra-cosa'));
    }

    public function test_en_su_dominio_todo_se_ve_con_la_marca_y_su_soporte(): void
    {
        $this->wilcat->update(['telefono' => '965 123 456', 'correo' => 'soporte@wilcatsystems.pe', 'razon_social' => 'WILCAT SYSTEMS E.I.R.L.']);
        Tenancy::olvidarMarcas();
        $this->entrar(self::DE_WILCAT.'.wilcatsystems.pe');

        $this->assertSame(['WILCAT SYSTEMS', false, '51965123456', 'soporte@wilcatsystems.pe', 'WILCAT SYSTEMS E.I.R.L.'],
            [config('marca.nombre'), config('marca.es_tushpa'), config('soporte.whatsapp'), config('soporte.correo'), config('soporte.nombre')]);
        $this->assertStringContainsString('wilcatsystems.pe', config('soporte.firma_comprobante'), 'El pie de sus comprobantes es el de la marca.');
    }

    public function test_en_tushpa_todo_sigue_con_tushpa(): void
    {
        $soporte = config('soporte.whatsapp');
        $this->entrar(self::DE_TUSHPA.'.tushpa.app');

        $this->assertSame(['TUSHPA', true, $soporte], [config('marca.nombre'), config('marca.es_tushpa'), config('soporte.whatsapp')]);
    }

    public function test_el_enlace_del_contrato_va_al_dominio_de_su_marca(): void
    {
        $deWilcat = Cliente::where('ruc', self::DE_WILCAT)->first();
        $enlace = Contratos::enlace((object) ['cliente_id' => $deWilcat->id, 'token' => str_repeat('b', 40)]);

        $this->assertSame('https://wilcatsystems.pe/contrato/'.str_repeat('b', 40), $enlace);
    }

    public function test_una_marca_desactivada_no_abre_nada_y_un_dominio_ajeno_sigue_como_siempre(): void
    {
        $this->wilcat->update(['activa' => false]);
        Tenancy::olvidarMarcas();
        $this->assertSame('404', $this->entrar(self::DE_WILCAT.'.wilcatsystems.pe'));
        // Un dominio que no es de nadie (la IP del servidor, el local) sigue con la base del .env, como antes
        $this->assertSame('siguió', $this->entrar('otro-dominio.com'));
    }

    public function test_la_direccion_y_la_base_salen_de_su_marca(): void
    {
        $deWilcat = Cliente::where('ruc', self::DE_WILCAT)->first();
        $deTushpa = Cliente::where('ruc', self::DE_TUSHPA)->first();

        $this->assertSame('contadores.wilcatsystems.pe', $deWilcat->host());
        $this->assertSame('https://contadores.wilcatsystems.pe', $deWilcat->url());
        $this->assertSame('bd_ws_', $deWilcat->prefijoBd());
        $this->assertSame('mirestaurante.tushpa.app', $deTushpa->host());
        $this->assertSame('bd_', $deTushpa->prefijoBd());
        $this->assertSame('bd_ws_'.self::DE_WILCAT, Tenancy::nombreBase(self::DE_WILCAT, $deWilcat->prefijoBd()));
        $this->assertSame('bd_'.self::DE_TUSHPA, Tenancy::nombreBase(self::DE_TUSHPA));
    }

    public function test_sin_la_tabla_de_marcas_las_empresas_de_tushpa_siguen_entrando(): void
    {
        // Mientras se actualiza el sistema (la migración aún no corrió)
        Schema::connection('central')->drop('marcas');
        Tenancy::olvidarMarcas();

        $this->assertSame('bd_'.self::DE_TUSHPA, $this->entrar(self::DE_TUSHPA.'.tushpa.app'));
        $this->assertSame('siguió', $this->entrar(self::DE_WILCAT.'.wilcatsystems.pe'));
    }
}
