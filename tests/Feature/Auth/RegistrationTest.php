<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Flujos\FlujoTestCase;

/** Nadie se registra solo: los usuarios los crea el administrador */
class RegistrationTest extends FlujoTestCase
{
    public function test_no_hay_registro_publico_de_usuarios(): void
    {
        Auth::logout();

        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'Intruso', 'email' => 'intruso@prueba.test', 'password' => 'clave-intruso',
            'password_confirmation' => 'clave-intruso'])->assertNotFound();

        $this->assertFalse(User::where('email', 'intruso@prueba.test')->exists());
    }
}
