<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\ClienteController;
use Illuminate\Http\Request;
use Tests\TestCase;

/** Panel: al crear la empresa, la ciudad o el distrito se busca por su nombre (la persona natural no trae ubigeo de SUNAT) */
class PanelUbigeosTest extends TestCase
{
    public function test_busca_el_ubigeo_por_el_nombre_del_distrito(): void
    {
        $r = app(ClienteController::class)->ubigeos(Request::create('/', 'GET', ['q' => 'iquitos']))->getData(true);

        $this->assertSame('160101', $r[0]['ubigeo'] ?? null, 'Iquitos (Maynas, Loreto) sale primero.');
    }

    public function test_el_formulario_del_panel_usa_el_buscador_de_ubigeo(): void
    {
        $vista = file_get_contents(resource_path('views/admin/clientes/form.blade.php'));

        $this->assertStringContainsString("'url' => route('admin.ubigeos')", $vista);
        $this->assertStringNotContainsString('name="ubigeo" x-ref="ubigeo"', $vista, 'Ya no es un campo libre de 6 dígitos.');
    }
}
