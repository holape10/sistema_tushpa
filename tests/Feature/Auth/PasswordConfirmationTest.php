<?php

namespace Tests\Feature\Auth;

use Tests\Flujos\FlujoTestCase;

/** Volver a escribir la contraseña antes de algo delicado */
class PasswordConfirmationTest extends FlujoTestCase
{
    public function test_la_pantalla_para_confirmar_se_muestra(): void
    {
        $this->get('/confirm-password')->assertOk();
    }

    public function test_con_su_contrasena_queda_confirmada(): void
    {
        $this->post('/confirm-password', ['password' => 'clave-de-prueba'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_con_otra_contrasena_no_se_confirma(): void
    {
        $this->post('/confirm-password', ['password' => 'otra-clave'])->assertSessionHasErrors('password');

        $this->assertNull(session('auth.password_confirmed_at'));
    }
}
