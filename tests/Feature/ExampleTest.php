<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Tests\Flujos\FlujoTestCase;

/** La dirección principal lleva a ingresar o, con la sesión abierta, al inicio del usuario */
class ExampleTest extends FlujoTestCase
{
    public function test_sin_sesion_la_raiz_lleva_a_ingresar(): void
    {
        Auth::logout();

        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_con_sesion_la_raiz_lleva_a_su_inicio(): void
    {
        $this->get('/')->assertRedirect(route('home.usuario'));
    }
}
