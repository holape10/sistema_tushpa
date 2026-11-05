<?php

namespace Tests\Flujos;

use App\Models\Central\Cliente;
use App\Models\User;
use App\Support\Estudio;
use App\Support\Sunat\XmlJsonPe;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as Peticion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Unit\CompraXmlTest;

/** Estudio contable: las empresas de sus clientes en una misma base, sin que se cruce la información entre ellas */
class EstudioContableTest extends FlujoTestCase
{
    private const EMPRESA_A = '20553510661';

    private const EMPRESA_B = '20100070970';

    private string $estudio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->estudio = (string) $this->usuario->IdEmpresa;
        Estudio::activar($this->estudio);
        foreach ([self::EMPRESA_A => 'LLAMA PE SA', self::EMPRESA_B => 'SUPERMERCADOS PERUANOS SA'] as $ruc => $nombre) {
            $this->post(route('estudio.guardar'), ['ruc' => $ruc, 'razon_social' => $nombre, 'direccion' => 'IQUITOS'])->assertSessionHasNoErrors();
        }
    }

    private function cambiar(string $ruc, ?User $usuario = null): void
    {
        $this->actingAs($usuario ?? $this->usuario)->post(route('estudio.cambiar'), ['ruc' => $ruc])->assertRedirect(route('inicio'));
        ($usuario ?? $this->usuario)->refresh();
    }

    private function contador(): User
    {
        $u = $this->usuario->replicate();
        $u->email = 'contador'.$this->usuario->IdUsuario.'@prueba.test';
        $u->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $u->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);

        return $u;
    }

    public function test_el_estudio_registra_empresas_y_trabaja_en_cada_una(): void
    {
        $this->get(route('estudio.index'))->assertOk()->assertSee('LLAMA PE SA')->assertSee('SUPERMERCADOS PERUANOS SA')->assertSee('Faltan credenciales');
        $sucursalA = Estudio::sucursalDe(self::EMPRESA_A);
        $this->assertNotNull($sucursalA, 'Cada empresa nace con su sucursal.');
        $this->assertTrue(DB::table('almacenes')->where('id_empresa_negocio', $sucursalA)->exists(), 'Y su almacén.');

        // RUC repetido o inventado no
        $this->post(route('estudio.guardar'), ['ruc' => self::EMPRESA_A, 'razon_social' => 'OTRA'])->assertSessionHasErrors('ruc');
        $this->post(route('estudio.guardar'), ['ruc' => '20553510662', 'razon_social' => 'INVENTADA'])->assertSessionHasErrors('ruc');

        $this->cambiar(self::EMPRESA_A);
        $this->assertSame([self::EMPRESA_A, $sucursalA], [(string) $this->usuario->IdEmpresa, (int) $this->usuario->id_empresa_negocio]);
        $this->assertSame($sucursalA, (int) DB::table('role_user')->where('user_IdUsuario', $this->usuario->IdUsuario)->value('id_empresa_negocio'));
        $this->get(route('inicio'))->assertOk()->assertSee('Empresa en la que trabajas', false)->assertSee('bg-amber-100', false);

        // Los usuarios del estudio se siguen administrando aunque se esté dentro de un cliente
        $this->get(route('usuarios.index'))->assertOk()->assertSee($this->usuario->email);
    }

    public function test_cada_usuario_solo_ve_las_empresas_que_le_asignaron(): void
    {
        $contador = $this->contador();
        $this->post(route('estudio.accesos', self::EMPRESA_A), ['usuarios' => [$contador->IdUsuario]])->assertSessionHas('success');

        $this->assertSame([$this->estudio, self::EMPRESA_A], Estudio::empresas($contador)->pluck('IdEmpresa')->all());
        $this->actingAs($contador)->post(route('estudio.cambiar'), ['ruc' => self::EMPRESA_B])->assertForbidden();
        $this->cambiar(self::EMPRESA_A, $contador);
        $this->assertSame(self::EMPRESA_A, (string) $contador->IdEmpresa);
        $this->actingAs($contador)->get(route('estudio.index'))->assertForbidden();
    }

    public function test_si_le_quitan_el_acceso_o_se_desactiva_la_empresa_vuelve_al_estudio(): void
    {
        $contador = $this->contador();
        Estudio::guardarAccesos(self::EMPRESA_A, [$contador->IdUsuario]);
        $this->cambiar(self::EMPRESA_A, $contador);

        Estudio::guardarAccesos(self::EMPRESA_A, []);
        $this->actingAs($contador)->get(route('inicio'))->assertOk();
        $this->assertSame([$this->estudio, $this->sucursal], [(string) $contador->fresh()->IdEmpresa, (int) $contador->fresh()->id_empresa_negocio]);

        // El administrador en una empresa que se desactiva también vuelve
        $this->cambiar(self::EMPRESA_B);
        $this->post(route('estudio.estado', self::EMPRESA_B))->assertSessionHas('success');
        $this->get(route('inicio'))->assertOk();
        $this->assertSame($this->estudio, (string) $this->usuario->fresh()->IdEmpresa);
    }

    public function test_una_sucursal_de_otra_empresa_nunca_se_queda(): void
    {
        $this->cambiar(self::EMPRESA_A);
        // RUC de A con la sucursal de B (por ejemplo, un cambio a medias): se corrige antes de mostrar nada
        DB::table('users')->where('IdUsuario', $this->usuario->IdUsuario)->update(['id_empresa_negocio' => Estudio::sucursalDe(self::EMPRESA_B)]);
        $this->actingAs($this->usuario->fresh())->get(route('inicio'))->assertOk();
        $this->assertSame([$this->estudio, $this->sucursal], [(string) $this->usuario->fresh()->IdEmpresa, (int) $this->usuario->fresh()->id_empresa_negocio]);
    }

    public function test_la_informacion_de_una_empresa_no_se_ve_en_otra(): void
    {
        // Datos de la empresa B con marcas fáciles de buscar
        $sucB = Estudio::sucursalDe(self::EMPRESA_B);
        $emp = DB::table('empleado')->insertGetId(['emp_nom' => 'TRABAJADORMARCAB', 'emp_ape_pat' => 'PRUEBA', 'emp_ape_mat' => '.', 'emp_num_doc' => '40000001',
            'tdicod' => '1', 'rol_id' => 4, 'est_cod' => '1', 'id_empresa_negocio' => $sucB]);
        DB::table('planilla_trabajadores')->insert(['emp_id' => $emp, 'IdEmpresa' => self::EMPRESA_B, 'cargo' => 'CARGOMARCAB', 'fecha_ingreso' => '2026-01-01',
            'sueldo' => 1500, 'asignacion_familiar' => false, 'sistema_pension' => 'ONP', 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('conta_plan')->insert(['IdEmpresa' => self::EMPRESA_B, 'cuenta' => '999991', 'descripcion' => 'CUENTAMARCAB', 'nivel' => 6, 'tipo' => 'GASTO',
            'naturaleza' => 'D', 'imputable' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('empresa')->where('IdEmpresa', self::EMPRESA_B)->update(['client_id' => 'IDSIREMARCAB']);
        DB::table('compras_xml')->insert(['IdEmpresa' => self::EMPRESA_B, 'ruc_emisor' => '20600695771', 'razon_emisor' => 'EMISORMARCAB', 'tipo' => '01',
            'serie' => 'F001', 'numero' => '1', 'fecha' => now()->subMonth()->format('Y-m-15'), 'moneda' => 'PEN', 'igv' => 0, 'total' => 10, 'created_at' => now(), 'updated_at' => now()]);
        $marcas = ['TRABAJADORMARCAB', 'CARGOMARCAB', 'CUENTAMARCAB', 'IDSIREMARCAB', 'EMISORMARCAB'];

        $pantallas = ['/planilla/trabajadores', '/planilla', '/planilla/parametros', '/contabilidad/plan', '/contabilidad/diario', '/contabilidad/mayor',
            '/contabilidad/balance', '/tributos', '/concar', '/sire/credenciales', '/sire/compras', '/sire/ventas', '/sire/compras/detalle', '/sire/dbf',
            '/asistencia/jornadas', '/asistencia/tareo', '/asistencia/matriz', '/inicio'];

        // En la empresa A no aparece nada de B
        $this->cambiar(self::EMPRESA_A);
        foreach ($pantallas as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            foreach ($marcas as $marca) {
                $this->assertStringNotContainsString($marca, $html, "{$url} de la empresa A muestra datos de B ({$marca}).");
            }
        }

        // En B sí se ven (la prueba de arriba revisa pantallas que de verdad muestran esos datos)
        $this->cambiar(self::EMPRESA_B);
        $vistas = '';
        foreach ($pantallas as $url) {
            $vistas .= $this->get($url)->assertOk()->getContent();
        }
        foreach ($marcas as $marca) {
            $this->assertStringContainsString($marca, $vistas, "Ninguna pantalla de B muestra {$marca}: la prueba no estaría revisando nada.");
        }
    }

    public function test_una_base_sin_estudio_sigue_igual(): void
    {
        DB::table('estudio_empresas')->delete();
        Estudio::olvidar();
        $this->get(route('inicio'))->assertOk()->assertDontSee('Empresa en la que trabajas', false);
        $this->get(route('estudio.index'))->assertNotFound();
    }

    public function test_el_plan_limita_cuantas_empresas_activas_lleva_el_estudio(): void
    {
        app()->instance('tenancy.cliente', (new Cliente)->forceFill(['id' => 999999, 'ruc' => $this->estudio, 'max_empresas' => 2]));
        $this->get(route('estudio.index'))->assertOk()->assertSee('Llegaste al límite de tu plan');
        $this->post(route('estudio.guardar'), ['ruc' => '20600695771', 'razon_social' => 'NUBEFACT SA'])->assertSessionHas('error');
        $this->assertFalse(DB::table('empresa')->where('IdEmpresa', '20600695771')->exists());

        // Desactiva una que ya no lleva: entra otra, pero la desactivada ya no puede volver sin cupo
        $this->post(route('estudio.estado', self::EMPRESA_A))->assertSessionHas('success');
        $this->post(route('estudio.guardar'), ['ruc' => '20600695771', 'razon_social' => 'NUBEFACT SA'])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->post(route('estudio.estado', self::EMPRESA_A))->assertSessionHas('error');
        $this->assertSame(2, Estudio::activas());
    }

    public function test_cada_empresa_guarda_sus_credenciales_cifradas_y_sus_cuentas(): void
    {
        $this->post(route('estudio.configurar', self::EMPRESA_A), [
            'client_id' => 'ID-API-A', 'client_secret' => 'SECRETO-API-A', 'sire_usuario' => 'usuarioa', 'sire_clave' => 'CLAVE-SIRE-A',
            'sol_usuario' => 'solprina', 'sol_clave' => 'CLAVE-SOL-A', 'cta_compras' => '63141', 'cta_por_pagar' => '42121',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $fila = DB::table('empresa')->where('IdEmpresa', self::EMPRESA_A)->first();
        $this->assertSame(['ID-API-A', 'USUARIOA', 'SOLPRINA'], [$fila->client_id, $fila->sire_usuario, $fila->sol_usuario]);
        foreach (['client_secret' => 'SECRETO-API-A', 'sire_clave' => 'CLAVE-SIRE-A', 'sol_clave' => 'CLAVE-SOL-A'] as $campo => $clave) {
            $this->assertNotSame($clave, $fila->{$campo}, "{$campo} se guarda cifrada.");
            $this->assertSame($clave, decrypt($fila->{$campo}, false));
        }
        $this->assertSame(['63141', '42121'], [DB::table('concar_config')->where('IdEmpresa', self::EMPRESA_A)->value('cta_compras'),
            DB::table('concar_config')->where('IdEmpresa', self::EMPRESA_A)->value('cta_por_pagar')]);
        $this->assertNull(DB::table('concar_config')->where('IdEmpresa', self::EMPRESA_B)->value('cta_compras'), 'La otra empresa no se toca.');

        // Las claves nunca se muestran; si no se escriben, se conservan
        $this->get(route('estudio.index'))->assertOk()->assertSee('SIRE ✔')->assertSee('SOL ✔')
            ->assertDontSee('SECRETO-API-A')->assertDontSee('CLAVE-SIRE-A')->assertDontSee('CLAVE-SOL-A');
        $this->post(route('estudio.configurar', self::EMPRESA_A), ['client_id' => 'ID-API-A', 'sire_usuario' => 'USUARIOA', 'sol_usuario' => 'SOLPRINA'])->assertSessionHas('success');
        $this->assertSame('CLAVE-SOL-A', decrypt(DB::table('empresa')->where('IdEmpresa', self::EMPRESA_A)->value('sol_clave'), false));
        // El propio estudio no se configura por esta vía
        $this->post(route('estudio.configurar', $this->estudio), ['client_id' => 'X'])->assertNotFound();
    }

    public function test_cada_empresa_trae_sus_xml_con_su_propia_clave_sol_y_se_cuentan_en_el_panel(): void
    {
        // Base central de mentira en memoria para el contador del panel
        config(['database.connections.central' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'services.jsonpe.token' => 'token-de-prueba']);
        DB::purge('central');
        Schema::connection('central')->create('clientes', fn (Blueprint $t) => $t->id());
        (include database_path('migrations/central/2027_01_22_000001_estudios_y_consumo_xml.php'))->up();
        app()->instance('tenancy.cliente', (new Cliente)->forceFill(['id' => 7, 'ruc' => $this->estudio, 'sol_usuario' => 'SOLDELESTUDIO', 'sol_clave' => 'clave-estudio']));

        foreach ([self::EMPRESA_A => 'SOLA', self::EMPRESA_B => 'SOLB'] as $ruc => $usuario) {
            DB::table('empresa')->where('IdEmpresa', $ruc)->update(['sol_usuario' => $usuario, 'sol_clave' => encrypt('clave-'.$usuario, false)]);
        }
        $zip = tempnam(sys_get_temp_dir(), 'zip');
        $z = new \ZipArchive;
        $z->open($zip, \ZipArchive::OVERWRITE);
        $z->addFromString('F001-134277.xml', CompraXmlTest::factura(self::EMPRESA_A));
        $z->close();
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['xml_base64' => base64_encode(file_get_contents($zip))]])]);

        $this->cambiar(self::EMPRESA_A);
        $this->assertNotNull(XmlJsonPe::traer(self::EMPRESA_A, '20553510661', '01', 'F001', '134277')['xml']);
        Http::assertSent(fn (Peticion $p) => $p['ruc'] === self::EMPRESA_A && $p['usuario'] === 'SOLA' && $p['password'] === 'clave-SOLA');
        Http::assertNotSent(fn (Peticion $p) => $p['usuario'] === 'SOLB' || $p['usuario'] === 'SOLDELESTUDIO');

        $mes = DB::connection('central')->table('consumo_xml')->where('cliente_id', 7)->first();
        $this->assertSame([1, 1], [(int) $mes->consultas, (int) $mes->traidos], 'El panel cuenta cada XML pedido a json.pe.');

        // Una empresa sin su Clave SOL no usa la del estudio
        DB::table('empresa')->where('IdEmpresa', self::EMPRESA_B)->update(['sol_usuario' => null, 'sol_clave' => null]);
        $this->cambiar(self::EMPRESA_B);
        $this->assertStringContainsString('Empresas del estudio', (string) XmlJsonPe::falta());
        $this->assertTrue(XmlJsonPe::traer(self::EMPRESA_B, '20553510661', '01', 'F001', '1')['detener']);
        Http::assertSentCount(1);
    }

    public function test_guardar_desde_una_pestana_de_otra_empresa_se_rechaza(): void
    {
        // Pestaña 1 abierta en A; en la pestaña 2 se cambió a B
        $this->cambiar(self::EMPRESA_A);
        $this->get(route('inicio'))->assertOk()->assertSee('<meta name="empresa-activa" content="'.self::EMPRESA_A.'">', false);
        $this->cambiar(self::EMPRESA_B);

        // Lo que se guarda desde la pantalla de A no cae en B
        $this->postJson(route('estudio.configurar', self::EMPRESA_A), ['cta_compras' => '63141'], ['X-Empresa' => self::EMPRESA_A])
            ->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, 'recarga la página'));
        $this->from(route('inicio'))->post(route('estudio.configurar', self::EMPRESA_A), ['cta_compras' => '63141', '_empresa' => self::EMPRESA_A])
            ->assertRedirect(route('inicio'))->assertSessionHas('error');
        $this->assertNull(DB::table('concar_config')->where('IdEmpresa', self::EMPRESA_A)->value('cta_compras'));

        // Desde una pantalla de la empresa actual sí se guarda, y el selector de una pestaña vieja sigue funcionando
        $this->postJson(route('estudio.configurar', self::EMPRESA_A), ['cta_compras' => '63141'], ['X-Empresa' => self::EMPRESA_B])->assertRedirect();
        $this->post(route('estudio.cambiar'), ['ruc' => self::EMPRESA_A, '_empresa' => self::EMPRESA_B])->assertRedirect(route('inicio'));
        $this->assertSame(self::EMPRESA_A, (string) $this->usuario->fresh()->IdEmpresa);
    }
}
