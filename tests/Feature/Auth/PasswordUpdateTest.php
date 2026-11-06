<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Hash;
use Tests\Flujos\FlujoTestCase;

/** Cada usuario puede cambiar su propia contraseña sabiendo la actual */
class PasswordUpdateTest extends FlujoTestCase
{
    public function test_cambia_su_contrasena_con_la_actual(): void
    {
        $this->from(route('inicio'))->put('/password', ['current_password' => 'clave-de-prueba', 'password' => 'clave-nueva-2026',
            'password_confirmation' => 'clave-nueva-2026'])->assertSessionHasNoErrors()->assertRedirect(route('inicio'));

        $this->assertTrue(Hash::check('clave-nueva-2026', $this->usuario->fresh()->password));
    }

    public function test_sin_la_contrasena_actual_correcta_no_la_cambia(): void
    {
        $this->from(route('inicio'))->put('/password', ['current_password' => 'no-es-la-actual', 'password' => 'clave-nueva-2026',
            'password_confirmation' => 'clave-nueva-2026'])->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check('clave-de-prueba', $this->usuario->fresh()->password));
    }
}
