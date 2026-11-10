<?php

namespace Tests\Flujos;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Empresa con varias sucursales: en el login se elige dónde trabajar y cada usuario entra solo a la suya */
class LoginSucursalTest extends FlujoTestCase
{
    private int $agustinos;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::logout();
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['nombre_comercial' => 'Principal Pomarosa']);
        $this->agustinos = DB::table('empresa_negocios')->insertGetId(['IdEmpresa' => $this->usuario->IdEmpresa, 'tipo_negocio' => 'Sucursal - '.$this->usuario->IdEmpresa,
            'nombre_comercial' => 'Sucursal Agustinos', 'direccion' => 'AGUSTINOS 1', 'estado' => 'Activo']);
    }

    private function cajeroDe(int $sucursal): User
    {
        $u = $this->usuario->replicate();
        $u->email = 'cajero'.uniqid();
        $u->id_empresa_negocio = $sucursal;
        $u->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $u->IdUsuario, 'id_empresa_negocio' => $sucursal]);

        return $u;
    }

    private function entrar(User $u, int $sucursal)
    {
        return $this->post(route('login'), ['email' => $u->email, 'password' => 'clave-de-prueba', 'sucursal' => $sucursal]);
    }

    public function test_el_login_muestra_las_sucursales_activas(): void
    {
        DB::table('empresa_negocios')->insert(['IdEmpresa' => $this->usuario->IdEmpresa, 'tipo_negocio' => 'X',
            'nombre_comercial' => 'Sucursal Cerrada', 'direccion' => '-', 'estado' => 'Inactivo']);

        $this->get('/login?escritorio=1')->assertOk()->assertSee('name="sucursal"', false)
            ->assertSee('PRINCIPAL POMAROSA')->assertSee('SUCURSAL AGUSTINOS')->assertDontSee('SUCURSAL CERRADA');
    }

    public function test_cada_usuario_entra_solo_a_su_sucursal(): void
    {
        $cajero = $this->cajeroDe($this->agustinos);

        $this->entrar($cajero, $this->sucursal)->assertSessionHasErrors(['email' => 'Tu usuario no es de PRINCIPAL POMAROSA. Elige SUCURSAL AGUSTINOS para entrar.']);
        $this->assertGuest();

        $this->entrar($cajero, $this->agustinos)->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($cajero);
    }

    public function test_el_administrador_entra_a_cualquiera_y_queda_trabajando_en_ella(): void
    {
        $this->entrar($this->usuario, $this->agustinos)->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($this->usuario);
        $this->assertEquals($this->agustinos, DB::table('users')->where('IdUsuario', $this->usuario->IdUsuario)->value('id_empresa_negocio'));
    }

    public function test_con_una_sola_sucursal_el_login_no_pregunta(): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->agustinos)->update(['estado' => 'Inactivo']);

        $this->get('/login?escritorio=1')->assertOk()->assertDontSee('name="sucursal"', false);
        $this->post(route('login'), ['email' => $this->usuario->email, 'password' => 'clave-de-prueba'])->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_la_descripcion_distingue_sucursales_con_el_mismo_nombre(): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['nombre_comercial' => 'RUIZ SOUZA', 'tipo_negocio' => 'Serie 1']);
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->agustinos)->update(['nombre_comercial' => 'RUIZ SOUZA', 'tipo_negocio' => 'Serie 2']);

        $this->get('/login?escritorio=1')->assertOk()->assertSee('RUIZ SOUZA · SERIE 1')->assertSee('RUIZ SOUZA · SERIE 2');
    }
}
