<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Flujos\FlujoTestCase;

/** Ingreso al sistema con usuario y contraseña (la base MySQL de pruebas, como los flujos) */
class AuthenticationTest extends FlujoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Auth::logout();
    }

    public function test_la_pantalla_de_ingreso_se_muestra(): void
    {
        $this->get('/login?escritorio=1')->assertOk()->assertSee('name="email"', false)->assertSee('name="password"', false);
    }

    public function test_entra_con_su_usuario_y_contrasena_y_va_a_su_inicio(): void
    {
        $this->post('/login', ['email' => $this->usuario->email, 'password' => 'clave-de-prueba'])->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($this->usuario);
    }

    public function test_con_la_contrasena_equivocada_no_entra(): void
    {
        $this->post('/login', ['email' => $this->usuario->email, 'password' => 'otra-clave'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_usuario_desactivado_no_entra_aunque_sepa_su_contrasena(): void
    {
        DB::table('users')->where('IdUsuario', $this->usuario->IdUsuario)->update(['estusu' => 0]);

        $this->post('/login', ['email' => $this->usuario->email, 'password' => 'clave-de-prueba'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_cerrar_sesion_lo_saca_del_sistema(): void
    {
        $this->actingAs($this->usuario)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }
}
