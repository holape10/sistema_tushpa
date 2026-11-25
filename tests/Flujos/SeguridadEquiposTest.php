<?php

namespace Tests\Flujos;

use App\Http\Controllers\Admin\AccesoController;
use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use App\Support\Seguridad\Dispositivos;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Seguridad de equipos: el primer equipo es de confianza, uno nuevo queda por revisar y se avisa; desde un equipo que no
 * es de confianza no se cambian precios en masa ni correlativos; "no fui yo" bloquea el equipo y lo saca.
 */
class SeguridadEquiposTest extends FlujoTestCase
{
    private string $miPc;

    private string $otro;

    protected function setUp(): void
    {
        parent::setUp();
        Dispositivos::$enPruebas = true;
        $this->miPc = str_repeat('A', 40);
        $this->otro = str_repeat('B', 40);
        DB::table('dispositivos')->where('IdUsuario', $this->usuario->IdUsuario)->delete();
    }

    protected function tearDown(): void
    {
        Dispositivos::$enPruebas = false;
        parent::tearDown();
    }

    private function desde(string $token)
    {
        return $this->withCookie(Dispositivos::COOKIE, $token);
    }

    /** Un ingreso nuevo con usuario y clave desde ese equipo */
    private function entraConClave(string $token)
    {
        return $this->desde($token)->withSession([Dispositivos::INGRESO => true]);
    }

    public function test_el_equipo_que_ya_estaba_conectado_sigue_trabajando_igual_sin_avisos(): void
    {
        $this->desde($this->miPc)->get(route('inicio'))->assertOk();
        // El celular ya tenía su sesión abierta antes de existir este control
        $this->desde($this->otro)->get(route('inicio'))->assertOk();

        $this->assertSame(2, DB::table('dispositivos')->where('IdUsuario', $this->usuario->IdUsuario)->where('confiable', true)->count());
        $this->assertSame(0, DB::table('bitacora')->where('accion', 'sesion.equipo_nuevo')->count());
        $this->desde($this->miPc)->get(route('productos.index'))->assertOk()->assertDontSee('ingreso desde un equipo nuevo');
    }

    public function test_el_primer_equipo_es_de_confianza_y_uno_nuevo_queda_por_revisar_y_se_avisa(): void
    {
        $this->desde($this->miPc)->get(route('inicio'))->assertOk();
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) Chrome/155.0 Mobile Safari/537.36')
            ->entraConClave($this->otro)->get(route('inicio'))->assertOk();

        $equipos = DB::table('dispositivos')->where('IdUsuario', $this->usuario->IdUsuario)->orderBy('id')->get();
        $this->assertCount(2, $equipos);
        $this->assertTrue((bool) $equipos[0]->confiable);
        $this->assertFalse((bool) $equipos[1]->confiable);
        $this->assertTrue((bool) $equipos[1]->por_revisar);
        $this->assertSame('Celular', $equipos[1]->tipo);
        $this->assertSame(1, DB::table('bitacora')->where('accion', 'sesion.equipo_nuevo')->where('alerta', 1)->count());

        // El administrador ve el aviso y la pantalla de equipos
        $this->desde($this->miPc)->get(route('productos.index'))->assertOk()->assertSee('ingreso desde un equipo nuevo');
        $this->desde($this->miPc)->get(route('seguridad.equipos'))->assertOk()->assertSee('Chrome en Android')->assertSee('Nuevo · por revisar');
    }

    public function test_desde_un_equipo_que_no_es_de_confianza_no_se_cambian_precios_en_masa_ni_correlativos(): void
    {
        $this->desde($this->miPc)->get(route('inicio'));
        $csv = "Nombre,Precio de venta\n";
        for ($i = 1; $i <= 12; $i++) {
            $this->producto("PRODUCTO {$i}", 10);
            $csv .= "PRODUCTO {$i},1.34\n";
        }
        $archivo = UploadedFile::fake()->createWithContent('precios.csv', $csv);

        $this->entraConClave($this->otro)->post(route('productos.importar'), ['archivo' => $archivo])->assertSessionHasErrors('archivo');
        $this->assertSame(0, DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('propun', 1.34)->count(), 'No se cambió ningún precio.');
        $this->assertSame(1, DB::table('bitacora')->where('accion', 'sesion.bloqueo_accion')->count(), 'Queda la alerta del intento.');

        // Desde el equipo de confianza sí
        $archivo = UploadedFile::fake()->createWithContent('precios.csv', $csv);
        $this->desde($this->miPc)->post(route('productos.importar'), ['archivo' => $archivo, 'mismo_precio' => 1])->assertSessionHasNoErrors();
        $this->assertSame(12, DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('propun', 1.34)->count());
    }

    public function test_no_fui_yo_bloquea_el_equipo_y_lo_saca(): void
    {
        $this->desde($this->miPc)->get(route('inicio'));
        $this->entraConClave($this->otro)->get(route('inicio'));
        $intruso = DB::table('dispositivos')->where('IdUsuario', $this->usuario->IdUsuario)->where('token', $this->otro)->first();

        // Desde el equipo nuevo no puede aprobarse a sí mismo
        $this->desde($this->otro)->post(route('seguridad.equipos.accion', $intruso->id), ['accion' => 'confiar'])->assertSessionHas('error');
        $this->assertFalse((bool) DB::table('dispositivos')->where('id', $intruso->id)->value('confiable'));

        // Desde el de confianza: "no fui yo"
        $tokenAntes = DB::table('users')->where('IdUsuario', $this->usuario->IdUsuario)->value('remember_token');
        $this->desde($this->miPc)->post(route('seguridad.equipos.accion', $intruso->id), ['accion' => 'bloquear'])->assertSessionHas('success');
        $this->assertNotNull(DB::table('dispositivos')->where('id', $intruso->id)->value('bloqueado_el'));
        $this->assertNotSame($tokenAntes, DB::table('users')->where('IdUsuario', $this->usuario->IdUsuario)->value('remember_token'), '"Recordarme" ya no sirve.');

        // El equipo bloqueado ya no entra
        $this->desde($this->otro)->get(route('inicio'))->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_sin_ningun_equipo_de_confianza_el_administrador_autoriza_ahi_mismo_y_queda_la_alerta(): void
    {
        $this->desde($this->miPc)->get(route('inicio'));
        // Cambió de PC o borró el navegador: ningún equipo suyo es de confianza
        DB::table('dispositivos')->where('IdUsuario', $this->usuario->IdUsuario)->update(['confiable' => false, 'por_revisar' => true]);
        $mio = DB::table('dispositivos')->where('IdUsuario', $this->usuario->IdUsuario)->where('token', $this->miPc)->first();

        $this->desde($this->miPc)->post(route('seguridad.equipos.accion', $mio->id), ['accion' => 'confiar'])->assertSessionHas('success');

        $this->assertTrue((bool) DB::table('dispositivos')->where('id', $mio->id)->value('confiable'));
        $this->assertSame(1, DB::table('bitacora')->where('accion', 'sesion.autorizo_sin_confianza')->where('alerta', 1)->count(), 'Queda la alerta para el panel.');

        // Ya tiene uno de confianza: desde otro equipo nuevo ya no se aprueba solo
        $this->entraConClave($this->otro)->get(route('inicio'));
        $otro = DB::table('dispositivos')->where('IdUsuario', $this->usuario->IdUsuario)->where('token', $this->otro)->first();
        $this->desde($this->otro)->post(route('seguridad.equipos.accion', $otro->id), ['accion' => 'confiar'])->assertSessionHas('error');
    }

    public function test_soporte_autoriza_desde_el_panel_los_equipos_por_revisar(): void
    {
        $this->desde($this->miPc)->get(route('inicio'));
        $this->entraConClave($this->otro)->get(route('inicio'));
        DB::connection('central')->beginTransaction();
        try {
            $cliente = Cliente::create(['ruc' => '20555555559', 'base_datos' => DB::connection()->getDatabaseName(), 'razon_social' => 'EMPRESA DE PRUEBA', 'estado' => 'ACTIVO']);
            $this->actingAs(Superadmin::create(['nombre' => 'SOPORTE', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => true]), 'superadmin');

            app(AccesoController::class)->equipo(Request::create('/', 'POST', ['accion' => 'confiar']), $cliente, 'todos');

            $otro = DB::table('dispositivos')->where('IdUsuario', $this->usuario->IdUsuario)->where('token', $this->otro)->first();
            $this->assertSame([1, 0], [(int) $otro->confiable, (int) $otro->por_revisar]);
            $this->assertSame('Panel: SOPORTE', (string) $otro->revisado_por);
        } finally {
            DB::connection('central')->rollBack();
        }
    }
}
