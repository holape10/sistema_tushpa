<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Auth;
use Tests\Flujos\FlujoTestCase;

/**
 * La contraseña olvidada la cambia el administrador: se entra con un usuario (no un correo), así que no hay recuperación
 * ni verificación por correo (antes esas direcciones existían y daban error).
 */
class PasswordResetTest extends FlujoTestCase
{
    public function test_no_hay_recuperacion_de_contrasena_por_correo(): void
    {
        Auth::logout();

        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => $this->usuario->email])->assertNotFound();
        $this->get('/reset-password/token-cualquiera')->assertNotFound();
        $this->post('/reset-password', ['token' => 'x', 'email' => $this->usuario->email, 'password' => 'clave-nueva',
            'password_confirmation' => 'clave-nueva'])->assertNotFound();
    }

    public function test_no_hay_verificacion_de_correo(): void
    {
        $this->get('/verify-email')->assertNotFound();
        $this->post('/email/verification-notification')->assertNotFound();
    }
}
