<?php

namespace Tests\Flujos;

use App\Http\Controllers\SucursalController;
use App\Support\LogoNegocio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Cada sucursal puede tener su logo; la que no tiene usa el de la empresa */
class LogoSucursalTest extends FlujoTestCase
{
    /** @var array<int, string> logos creados en la prueba (se borran al final) */
    private array $creados = [];

    protected function tearDown(): void
    {
        foreach ($this->creados as $ruta) {
            @unlink(public_path($ruta));
        }
        parent::tearDown();
    }

    /** El formulario de la sucursal tal como está guardada */
    private function formulario(array $extra = []): array
    {
        $s = DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->first();
        $datos = ['nombre_comercial' => $s->nombre_comercial, 'estado' => 'Activo', 'direccion' => $s->direccion ?: 'JR. PRUEBA 123', 'ubigeo' => '160101',
            'tip_igv_pred' => '10', 'tdocod_pred' => '03', 'formato_impresion' => 'TICKET', 'control_stock' => 'libre'];
        foreach (SucursalController::SERIES as $c) {
            $datos[$c['serie']] = $s->{$c['serie']};
            $datos[$c['numero']] = (int) $s->{$c['numero']};
        }

        return $extra + $datos;
    }

    public function test_la_sucursal_guarda_su_logo_y_al_quitarlo_vuelve_el_de_la_empresa(): void
    {
        DB::table('empresa')->where('IdEmpresa', $this->usuario->IdEmpresa)->update(['LogEmpresa' => 'imagenes/192.png']);

        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario(['logo_suc' => UploadedFile::fake()->image('logo.png', 200, 200)]))
            ->assertSessionHasNoErrors()->assertRedirect(route('sucursales.index'));
        $logo = (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->value('logo_suc');
        $this->creados[] = $logo;
        $this->assertStringStartsWith('imagenes/logos/', $logo);
        $this->assertFileExists(public_path($logo));
        $this->assertSame($logo, LogoNegocio::de($logo, 'imagenes/192.png'), 'La sucursal usa su propio logo.');

        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario(['quitar_logo_suc' => 1]))->assertSessionHasNoErrors();
        $this->assertNull(DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->value('logo_suc'));
        $this->assertFileDoesNotExist(public_path($logo));
        $this->assertSame('imagenes/192.png', LogoNegocio::de(null, 'imagenes/192.png'), 'Sin logo propio usa el de la empresa.');
    }

    public function test_las_pantallas_de_empresa_y_sucursal_abren_dentro_del_menu(): void
    {
        $this->get(route('empresas.edit', $this->usuario->IdEmpresa))->assertOk()->assertSee('Logo de la empresa')
            ->assertDontSee('IMP. PEDIDOS')->assertDontSee('Correo de Envío');
        $this->get(route('sucursales.edit', $this->sucursal))->assertOk()->assertSee('Logo de esta sucursal')->assertSee('Notas de crédito y de débito');
    }
}
