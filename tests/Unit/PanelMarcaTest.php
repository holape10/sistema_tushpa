<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\MarcaController;
use App\Http\Middleware\IdentificarEmpresa;
use App\Http\Middleware\SoloEnPanel;
use App\Models\Central\Marca;
use App\Models\Central\Superadmin;
use App\Support\Tenancy\Contratos;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * El panel de una marca (admin.wilcatsystems.pe): solo ahí entran sus usuarios, nunca en el de TUSHPA (ni al revés);
 * un admin.{otro dominio} no abre nada; y sus contratos salen a nombre del revendedor.
 */
class PanelMarcaTest extends TestCase
{
    private Marca $wilcat;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.central' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'tenancy.dominio' => 'tushpa.app', 'tenancy.subdominio_admin' => 'admin', 'tenancy.ruta_admin' => 'panel', 'tenancy.admin_ips' => []]);
        DB::purge('central');
        $central = Schema::connection('central');
        $central->create('clientes', fn (Blueprint $t) => $t->id());
        $central->create('superadmins', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->string('email');
            $t->string('password');
            $t->boolean('activo')->default(true);
            $t->boolean('es_dueno')->default(false);
            $t->string('remember_token')->nullable();
            $t->timestamp('ultimo_acceso')->nullable();
            $t->string('ultima_ip')->nullable();
            $t->timestamps();
        });
        (include database_path('migrations/central/2027_01_24_000001_marcas.php'))->up();
        Tenancy::olvidarMarcas();
        $this->wilcat = Marca::create(['nombre' => 'WILCAT SYSTEMS', 'razon_social' => 'WILCAT SYSTEMS E.I.R.L.', 'ruc' => '20567128441',
            'dominio' => 'wilcatsystems.pe', 'prefijo_bd' => 'bd_ws_', 'representante' => 'HUANAQUIRI CURICO WILDER RAMIRO', 'representante_dni' => '05365689',
            'direccion' => 'PJ. LOS JASMINES NRO. S/N A.H. LA TIERRA PROMETIDA']);
        Superadmin::create(['nombre' => 'DUEÑO', 'email' => 'dueno@prueba.test', 'password' => 'clave-de-prueba-1', 'es_dueno' => true]);
        Superadmin::create(['nombre' => 'WILCAT', 'email' => 'wilcat@prueba.test', 'password' => 'clave-de-prueba-2', 'marca_id' => $this->wilcat->id]);
        URL::defaults(['panelDominio' => 'tushpa.app']);
    }

    protected function tearDown(): void
    {
        Tenancy::olvidarMarcas();
        parent::tearDown();
    }

    /** @return array{0: string, 1: ?int} qué pasó al entrar ('panel', 'siguió' o el código de error) y de qué marca es el panel */
    private function entrar(string $url): array
    {
        $request = Request::create($url);
        $original = Tenancy::baseActual();
        try {
            $r = (new IdentificarEmpresa)->handle($request, fn () => response('ok'));

            return [$request->attributes->get('panel_admin') ? 'panel' : ($r->getStatusCode() === 200 ? 'siguió' : (string) $r->getStatusCode()),
                $request->attributes->get('panel_marca')];
        } catch (HttpException $e) {
            return [(string) $e->getStatusCode(), null];
        } finally {
            Tenancy::conectar($original);
        }
    }

    public function test_cada_panel_sabe_de_que_marca_es(): void
    {
        $this->assertSame(['panel', null], $this->entrar('https://admin.tushpa.app/panel/login'));
        $this->assertSame(['panel', $this->wilcat->id], $this->entrar('https://admin.wilcatsystems.pe/panel/login'));
        // Fuera de la ruta del panel, el admin de la marca tampoco existe
        $this->assertSame(['404', null], $this->entrar('https://admin.wilcatsystems.pe/otra-cosa'));
    }

    public function test_un_admin_de_otro_dominio_no_abre_el_panel(): void
    {
        // Alguien apunta admin.otro.com al servidor: IdentificarEmpresa no lo marca como panel y SoloEnPanel lo cierra
        $request = Request::create('https://admin.otro.com/panel/login');
        $this->assertSame('siguió', $this->entrar('https://admin.otro.com/panel/login')[0]);
        $this->expectException(HttpException::class);
        (new SoloEnPanel)->handle($request, fn () => response('ok'));
    }

    /** Intenta entrar al panel de esa marca (null = TUSHPA): true si entró */
    private function login(string $email, string $clave, ?int $marca): bool
    {
        $request = Request::create('/panel/login', 'POST', ['email' => $email, 'password' => $clave]);
        $request->attributes->set('panel_marca', $marca);
        $request->setLaravelSession(app('session')->driver('array'));
        try {
            app(LoginController::class)->store($request);
            $entro = Auth::guard('superadmin')->check();
            Auth::guard('superadmin')->logout();

            return $entro;
        } catch (ValidationException) {
            return false;
        }
    }

    public function test_cada_usuario_entra_solo_por_el_panel_de_su_marca(): void
    {
        $this->assertTrue($this->login('wilcat@prueba.test', 'clave-de-prueba-2', $this->wilcat->id));
        $this->assertFalse($this->login('wilcat@prueba.test', 'clave-de-prueba-2', null), 'El de WILCAT no entra a admin.tushpa.app.');
        $this->assertTrue($this->login('dueno@prueba.test', 'clave-de-prueba-1', null));
        $this->assertFalse($this->login('dueno@prueba.test', 'clave-de-prueba-1', $this->wilcat->id), 'El de TUSHPA no entra a admin.wilcatsystems.pe.');
    }

    public function test_el_dominio_y_el_prefijo_de_una_marca_no_se_confunden_con_los_de_tushpa(): void
    {
        $valida = fn (array $d) => Validator::make($d + ['nombre' => 'X'], array_diff_key(MarcaController::reglas(), ['dominio' => 0, 'prefijo_bd' => 0])
            + array_intersect_key(MarcaController::reglas(), $d))->passes();

        $this->assertTrue($valida(['dominio' => 'otrosistema.pe', 'prefijo_bd' => 'bd_os_']));
        $this->assertFalse($valida(['dominio' => 'tushpa.app']));
        $this->assertFalse($valida(['dominio' => 'wilcat.tushpa.app']));
        $this->assertFalse($valida(['dominio' => 'https://wilcatsystems.pe']));
        $this->assertFalse($valida(['dominio' => 'wilcatsystems.pe']), 'Ya es de otra marca.');
        $this->assertFalse($valida(['prefijo_bd' => 'bd_']), 'Sin letra se confundiría con las bases de TUSHPA.');
        $this->assertFalse($valida(['prefijo_bd' => 'bd_20_']));
        $this->assertFalse($valida(['prefijo_bd' => 'bd_ws_']), 'Ya es de otra marca.');
    }

    public function test_el_contrato_de_una_empresa_de_la_marca_sale_a_nombre_del_revendedor(): void
    {
        $p = Contratos::proveedor($this->wilcat);
        $this->assertSame(['WILCAT SYSTEMS E.I.R.L.', '20567128441', 'HUANAQUIRI CURICO WILDER RAMIRO', 'WILCAT SYSTEMS', 'www.wilcatsystems.pe'],
            [$p['razon_social'], $p['ruc'], $p['representante'], $p['marca'], $p['web']]);
        $this->assertSame(config('contrato.proveedor'), Contratos::proveedor(null), 'Las de TUSHPA, como siempre.');
    }
}
