<?php

namespace Tests\Flujos;

use App\Models\Central\Cliente;
use Illuminate\Support\Facades\Auth;

/** El login móvil (mozos y cobradores con código) solo en los rubros que lo usan */
class LoginRubroTest extends FlujoTestCase
{
    private const CELULAR = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        Auth::logout();
    }

    private function rubro(string $rubro): void
    {
        app()->instance('tenancy.cliente', new Cliente(['rubro' => $rubro]));
    }

    public function test_un_restobar_ve_el_login_movil_y_el_celular_va_directo_ahi(): void
    {
        $this->rubro('RESTOBAR');

        $this->get('/login?escritorio=1')->assertOk()->assertSee('Login para tablet / celular (mozos)');
        $this->withHeader('User-Agent', self::CELULAR)->get('/login')->assertRedirect(route('login.movil'));
        $this->get(route('login.movil'))->assertOk();
    }

    public function test_un_estudio_contable_no_ve_el_login_movil_ni_desde_el_celular(): void
    {
        $this->rubro('ESTUDIO');

        $this->get('/login')->assertOk()->assertDontSee('Login para tablet');
        $this->withHeader('User-Agent', self::CELULAR)->get('/login')->assertOk()->assertDontSee('Login para tablet');
        $this->get(route('login.movil'))->assertRedirect(route('login'));
        $this->post(route('login.movil'), ['usuario' => 1, 'codigo' => '1234'])->assertNotFound();
    }

    public function test_prestamos_si_lo_usa_para_sus_cobradores(): void
    {
        $this->rubro('PRESTAMOS');

        $this->get('/login')->assertOk()->assertSee('Login para tablet / celular (cobradores)');
    }
}
