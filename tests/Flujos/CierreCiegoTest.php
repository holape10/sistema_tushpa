<?php

namespace Tests\Flujos;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Cierre de caja ciego: el cajero cuenta la caja sin ver cuánto debería haber; la diferencia se ve al cerrar */
class CierreCiegoTest extends FlujoTestCase
{
    private User $cajero;

    private Turno $turnoCajero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cajero = $this->usuario->replicate();
        $this->cajero->email = 'caja'.$this->usuario->IdUsuario.'@prueba.test';
        $this->cajero->apeusu = 'CAJERO DE PRUEBA';
        $this->cajero->save();
        DB::table('role_user')->insert(['user_IdUsuario' => $this->cajero->IdUsuario, 'role_id' => 4]);
        $this->turnoCajero = Turno::create(['turno' => 2, 'IdUsuario' => $this->cajero->IdUsuario, 'apertura' => now(), 'IdEmpresa' => $this->usuario->IdEmpresa,
            'id_empresa_negocio' => $this->sucursal, 'estado' => 'ABIERTO', 'monto' => 100]);
    }

    private function ciego(bool $si): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['cierre_ciego' => $si]);
    }

    public function test_sin_cierre_ciego_el_cajero_ve_lo_esperado(): void
    {
        $this->actingAs($this->cajero)->get(route('turnos.index'))->assertOk()->assertSee('Efectivo esperado')->assertDontSee('Cierre de caja ciego');
        $this->get(route('turnos.show', $this->turnoCajero->id_turno))->assertOk();
    }

    public function test_con_cierre_ciego_el_cajero_cuenta_sin_ver_lo_esperado(): void
    {
        $this->ciego(true);
        $this->actingAs($this->cajero)->get(route('turnos.index'))->assertOk()
            ->assertSee('Cierre de caja ciego')->assertDontSee('Efectivo esperado')->assertDontSee('Ventas del turno');
        $this->get(route('turnos.show', $this->turnoCajero->id_turno))->assertRedirect(route('turnos.index'));

        // Sin contar no se cierra
        $this->post(route('turnos.cerrar'))->assertSessionHasErrors('montocierre');
        $this->assertSame('ABIERTO', $this->turnoCajero->fresh()->estado);

        // Contó S/ 90 de los S/ 100 del fondo: cierra y recién ahí ve la diferencia
        $this->post(route('turnos.cerrar'), ['montocierre' => 90])->assertRedirect(route('turnos.show', $this->turnoCajero->id_turno));
        $cerrado = $this->turnoCajero->fresh();
        $this->assertSame('CERRADO', $cerrado->estado);
        $this->assertEquals(90, $cerrado->montocierre);
        $this->get(route('turnos.show', $this->turnoCajero->id_turno))->assertOk();
    }

    public function test_contar_billete_por_billete_tambien_vale(): void
    {
        $this->ciego(true);
        $this->actingAs($this->cajero)->post(route('turnos.cerrar'), ['cierre_cant_c_50_soles' => 2])->assertSessionHasNoErrors();
        $this->assertEquals(100, $this->turnoCajero->fresh()->montocierre);
    }

    public function test_el_administrador_siempre_ve_todo(): void
    {
        $this->ciego(true);
        $this->actingAs($this->usuario)->get(route('turnos.index'))->assertOk()->assertSee('Efectivo esperado');
        $this->get(route('turnos.show', $this->turno->id_turno))->assertOk();
    }
}
