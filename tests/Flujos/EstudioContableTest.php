<?php

namespace Tests\Flujos;

use App\Models\User;
use App\Support\Estudio;
use Illuminate\Support\Facades\DB;

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
}
