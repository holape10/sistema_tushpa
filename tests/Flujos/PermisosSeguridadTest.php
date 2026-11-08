<?php

namespace Tests\Flujos;

use App\Http\Middleware\EmpresaDelEstudio;
use App\Http\Middleware\PermisoDelMenu;
use App\Models\Central\Superadmin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Lo delicado (precios, stock, medios de pago, pisos) solo para quien tiene la opción en su menú y de su sucursal */
class PermisosSeguridadTest extends FlujoTestCase
{
    private function cajero(array $menu = []): User
    {
        $u = $this->usuario->replicate();
        $u->email = 'cajero'.uniqid().'@prueba.test';
        $u->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $u->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        foreach ($menu as $url) {
            $mod = DB::table('modulos')->where('mod_url', $url)->value('mod_id');
            DB::table('modulos_usuario')->insert(['user_IdUsuario' => $u->IdUsuario, 'mod_id' => $mod]);
        }

        return $u;
    }

    public function test_sin_la_opcion_en_su_menu_no_cambia_precios_ni_stock(): void
    {
        $lomo = $this->producto('LOMO SALTADO', 30, 5);
        $cajero = $this->cajero();

        $this->actingAs($cajero)->get(route('productos.edit', $lomo->IdProducto))->assertForbidden();
        $this->actingAs($cajero)->put(route('productos.update', $lomo->IdProducto), ['pronom' => 'LOMO', 'propun' => 1])->assertForbidden();
        $this->actingAs($cajero)->delete(route('productos.destroy', $lomo->IdProducto))->assertForbidden();
        $this->actingAs($cajero)->post(route('kardex.guardar_movimiento'), [])->assertForbidden();
        $this->actingAs($cajero)->get(route('mediospagos.index'))->assertForbidden();
        $this->assertEquals(30, (float) DB::table('productos')->where('IdProducto', $lomo->IdProducto)->value('propun'), 'El precio no cambió.');

        // Con la opción en su menú sí entra (y el administrador siempre)
        $this->actingAs($this->cajero(['/productos']))->get(route('productos.edit', $lomo->IdProducto))->assertOk();
        $this->actingAs($this->usuario)->get(route('productos.edit', $lomo->IdProducto))->assertOk();
    }

    public function test_un_doble_envio_al_crear_usuario_no_revienta(): void
    {
        $datos = ['id_empresa_negocio' => $this->sucursal, 'tdicod' => '1', 'nombre_completo' => 'rojas vasquez elisa', 'estusu' => '1',
            'asistencia' => '0', 'email' => 'ELISA'.$this->sucursal, 'role_id' => '4', 'password' => 'clave-inicial', 'password_confirmation' => 'clave-inicial'];
        // El otro envío (doble clic) guarda el mismo usuario justo después de que este pasó la validación
        User::creating(function (User $u) use ($datos) {
            if ($u->email === $datos['email'] && ! User::where('email', $datos['email'])->exists()) {
                $otro = $this->usuario->replicate();
                $otro->email = $datos['email'];
                $otro->saveQuietly();
            }
        });

        $this->from(route('usuarios.create'))->post(route('usuarios.store'), $datos)
            ->assertRedirect(route('usuarios.create'))->assertSessionHasErrors('email');
        // El envío que chocó no deja nada a medias (ni un empleado sin usuario)
        $this->assertSame(0, DB::table('empleado')->where('emp_nom', 'ROJAS VASQUEZ ELISA')->count());
        $this->get(route('usuarios.create'))->assertOk()->assertSee('data-enviar', false);
    }

    public function test_no_se_tocan_medios_de_pago_ni_pisos_de_otra_sucursal(): void
    {
        $otra = DB::table('empresa_negocios')->insertGetId(['IdEmpresa' => $this->usuario->IdEmpresa, 'nombre_comercial' => 'OTRA SUCURSAL', 'estado' => 'Activo']);
        $medio = DB::table('medios_pagos')->insertGetId(['nom_med_pag' => 'YAPE OTRA', 'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $otra]);
        $piso = DB::table('pisos')->insertGetId(['pis_nom' => 'TERRAZA OTRA', 'id_empresa_negocio' => $otra]);

        $this->get(route('mediospagos.edit', $medio))->assertNotFound();
        $this->delete(route('mediospagos.destroy', $medio))->assertNotFound();
        $this->delete(route('pisos.destroy', $piso))->assertNotFound();
        $this->assertTrue(DB::table('medios_pagos')->where('id_med_pag', $medio)->exists());
        $this->assertTrue(DB::table('pisos')->where('pis_id', $piso)->exists());
    }

    public function test_config_solo_sirve_para_instalar_la_primera_vez(): void
    {
        $antes = DB::table('empresa')->count();
        // Con empresas ya registradas /config no existe: ni el administrador ni un cajero crean ahí otra empresa con su administrador
        foreach ([$this->usuario, $this->cajero()] as $quien) {
            $this->actingAs($quien)->get(route('empresa.config'))->assertNotFound();
            $this->actingAs($quien)->post(route('empresa.store'), ['rucEmpresa' => '20553510661', 'nomEmpresa' => 'COLADA', 'dirEmpresa' => 'X',
                'usuario' => 'colado@prueba.test', 'password' => 'clave-colada-123'])->assertNotFound();
        }
        $this->assertSame($antes, DB::table('empresa')->count());
        $this->assertFalse(DB::table('users')->where('email', 'colado@prueba.test')->exists());
        $this->get(route('empresas.index'))->assertOk()->assertDontSee(route('empresa.config'));

        // La búsqueda de RUC sigue para los usuarios logueados (la usa "Nueva empresa" del estudio)
        config(['services.apiperu.token' => null]); // sin gastar consultas de apiperu.dev
        $this->assertContains($this->actingAs($this->usuario)->get(route('api.ruc', '20553510661'))->status(), [200, 404]);
    }

    public function test_los_guardias_dejan_pasar_al_superadmin_del_panel(): void
    {
        // En admin.tushpa.app el que entra es un superadmin (no tiene roles ni menú): antes daba error 500
        Auth::setUser((new Superadmin)->forceFill(['id' => 1, 'nombre' => 'DUEÑO']));
        $peticion = Request::create('/productos', 'GET');
        $peticion->setRouteResolver(fn () => (new Route('GET', '/productos', []))->name('productos.index'));
        $siguiente = fn () => response('ok');

        $this->assertSame('ok', (new PermisoDelMenu)->handle($peticion, $siguiente)->getContent());
        $this->assertSame('ok', (new EmpresaDelEstudio)->handle($peticion, $siguiente)->getContent());
    }
}
