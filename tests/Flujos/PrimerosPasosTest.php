<?php

namespace Tests\Flujos;

use App\Models\Turno;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Guía de primeros pasos: solo para la empresa que aún no vendió (administrador) y la bienvenida del usuario nuevo */
class PrimerosPasosTest extends FlujoTestCase
{
    public function test_la_empresa_nueva_ve_la_guia_hasta_su_primera_venta_o_hasta_ocultarla(): void
    {
        DB::table('empresa')->where('IdEmpresa', $this->usuario->IdEmpresa)->update(['guia_oculta' => false]);
        DB::table('cpe_cabecera')->where('IdEmpresa', $this->usuario->IdEmpresa)->delete();

        $this->get(route('inicio'))->assertOk()->assertSee('Deja tu sistema listo en pocos pasos')->assertSee('Conexión con SUNAT');
        $this->get(route('productos.index'))->assertOk()->assertDontSee('Deja tu sistema listo', false);

        // Con la primera venta ya no sale
        VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('JABON', 3.5, 10), 1]], '13'), 'PV');
        $this->get(route('inicio'))->assertOk()->assertDontSee('Deja tu sistema listo');
    }

    public function test_el_administrador_puede_ocultarla(): void
    {
        DB::table('empresa')->where('IdEmpresa', $this->usuario->IdEmpresa)->update(['guia_oculta' => false]);
        DB::table('cpe_cabecera')->where('IdEmpresa', $this->usuario->IdEmpresa)->delete();

        $this->post(route('primeros-pasos.ocultar'))->assertRedirect();
        $this->get(route('inicio'))->assertOk()->assertDontSee('Deja tu sistema listo');
    }

    public function test_el_cajero_nuevo_ve_su_bienvenida_una_sola_vez(): void
    {
        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->apeusu = 'CAJERO NUEVO';
        $cajero->bienvenida_vista = false;
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        Turno::create(['turno' => 2, 'IdUsuario' => $cajero->IdUsuario, 'apertura' => now(), 'IdEmpresa' => $cajero->IdEmpresa,
            'id_empresa_negocio' => $this->sucursal, 'estado' => 'ABIERTO', 'monto' => 0]);

        $this->actingAs($cajero)->get(route('inicio'))->assertOk()->assertSee('¡Bienvenido, CAJERO NUEVO!')->assertSee('Abre tu caja');
        $this->post(route('primeros-pasos.bienvenida'))->assertRedirect();
        // En cada pantalla el usuario se lee de nuevo de la base (aquí se recarga a mano)
        $this->actingAs($cajero->fresh())->get(route('inicio'))->assertOk()->assertDontSee('¡Bienvenido, CAJERO NUEVO!');
    }
}
