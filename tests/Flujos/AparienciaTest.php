<?php

namespace Tests\Flujos;

use Illuminate\Support\Facades\DB;

/** Mantenimiento › Apariencia: el administrador elige color, fondo y letra, y todo el sistema de su empresa se ve así */
class AparienciaTest extends FlujoTestCase
{
    public function test_el_administrador_elige_el_color_y_todo_el_sistema_lo_usa(): void
    {
        $this->get('/inicio')->assertOk()->assertDontSee('id="apariencia"', false);

        $this->get(route('apariencia.index'))->assertOk()->assertSeeText('Apariencia del sistema');
        $this->post(route('apariencia.guardar'), ['color' => 'esmeralda', 'fondo' => 'calido', 'letra' => 'grande'])
            ->assertRedirect(route('apariencia.index'));

        $this->assertSame(['color' => 'esmeralda', 'fondo' => 'calido', 'letra' => 'grande'],
            json_decode(DB::table('empresa')->where('IdEmpresa', $this->usuario->IdEmpresa)->value('apariencia'), true));
        // El verde esmeralda (5 150 105) reemplaza al índigo en todas las pantallas, con el fondo y la letra elegidos
        $this->get('/inicio')->assertOk()->assertSee('--ap-600:5 150 105', false)
            ->assertSee('html{font-size:112.5%}', false)->assertSee('background-color:#f7f3ec', false);
    }

    public function test_el_login_y_la_pestana_dicen_en_que_empresa_estas(): void
    {
        $this->post(route('apariencia.guardar'), ['color' => 'naranja', 'fondo' => 'gris', 'letra' => 'normal']);
        $nombre = (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->value('nombre_comercial');

        $this->get('/inicio')->assertOk()->assertSee('<title>'.e($nombre).' · ', false);

        // Antes de entrar: si la base es de una sola empresa, el login lleva su color y su nombre
        $this->post(route('logout'));
        if (DB::table('empresa')->count() === 1) {
            $this->get(route('login'))->assertOk()->assertSee('--ap-600:234 88 12', false)->assertSeeText($nombre);
        }
    }

    public function test_en_una_marca_el_login_lleva_su_icono_y_no_el_de_tushpa(): void
    {
        $this->post(route('logout'));
        config(['marca.es_tushpa' => false, 'marca.icono' => 'imagenes/marcas/wilcat-icono.png']);

        $this->get(route('login'))->assertOk()
            ->assertSee(asset('imagenes/marcas/wilcat-icono.png'), false)
            ->assertDontSee(asset('favicon.ico'), false);
    }

    public function test_solo_acepta_opciones_conocidas_y_solo_el_administrador(): void
    {
        $this->post(route('apariencia.guardar'), ['color' => 'arcoiris', 'fondo' => 'gris', 'letra' => 'normal'])->assertSessionHasErrors('color');

        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 3, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);

        $this->actingAs($cajero)->get(route('apariencia.index'))->assertForbidden();
    }
}
