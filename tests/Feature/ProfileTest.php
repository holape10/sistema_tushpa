<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Flujos\FlujoTestCase;

/** La pantalla de Perfil de Laravel no está abierta: nadie cambia sus datos ni borra su cuenta por ahí */
class ProfileTest extends FlujoTestCase
{
    public function test_nadie_puede_borrar_su_propia_cuenta_ni_cambiar_su_perfil(): void
    {
        $this->get('/profile')->assertNotFound();
        $this->patch('/profile', ['name' => 'OTRO', 'email' => 'otro@prueba.test'])->assertNotFound();
        $this->delete('/profile', ['password' => 'clave-de-prueba'])->assertNotFound();

        $this->assertTrue(User::where('IdUsuario', $this->usuario->IdUsuario)->where('email', $this->usuario->email)->exists());
    }
}
