<?php

namespace Tests\Flujos;

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use App\Support\Seguridad\Dispositivos;
use App\Support\Tenancy\HerramientasSoporte;
use App\Support\Tenancy\ModoSoporte;
use App\Support\Tenancy\PartePanel;
use App\Support\Tenancy\SaludCliente;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/** Soporte desde el panel: diagnóstico con arreglos de un clic, ver como el cliente (modo soporte) y el parte de la mañana */
class SoporteClienteTest extends FlujoTestCase
{
    protected function tearDown(): void
    {
        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }
        parent::tearDown();
    }

    /** @return array{0: Cliente, 1: Superadmin} la empresa de la prueba como cliente del panel y alguien del panel */
    private function enElPanel(): array
    {
        DB::connection('central')->beginTransaction();
        URL::defaults(['panelDominio' => strtolower((string) config('tenancy.dominio'))]);
        $cliente = Cliente::create(['ruc' => (string) $this->usuario->IdEmpresa, 'base_datos' => DB::connection()->getDatabaseName(),
            'razon_social' => 'EMPRESA DE PRUEBA', 'estado' => 'ACTIVO']);
        $yo = Superadmin::create(['nombre' => 'GABY SOPORTE', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => true]);

        return [$cliente, $yo];
    }

    private function deMiSucursal(array $problemas, string $texto): array
    {
        return array_values(array_filter($problemas, fn ($p) => str_contains($p['titulo'].' '.$p['detalle'], $texto)));
    }

    public function test_un_correlativo_atrasado_sale_en_rojo_y_se_arregla_de_un_clic(): void
    {
        VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('GASEOSA', 3), 1]]), 'PV');
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['BnuEmpresa' => 0]);

        $atrasado = collect(SaludCliente::correlativosAtrasados())->firstWhere('sucursal', $this->sucursal);
        $this->assertSame(['03', 'B001', 0, 1], [$atrasado['clave'], $atrasado['serie'], $atrasado['correlativo'], $atrasado['emitido']]);
        $problema = collect(SaludCliente::revisarBase()['problemas'])->first(fn ($p) => ($p['arreglos'][0]['datos']['sucursal'] ?? null) === $this->sucursal);
        $this->assertSame(['rojo', 'igualar_correlativo'], [$problema['nivel'], $problema['arreglos'][0]['accion']]);

        HerramientasSoporte::ejecutar('igualar_correlativo', $problema['arreglos'][0]['datos'], 'Soporte: GABY');

        $this->assertSame(1, (int) DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->value('BnuEmpresa'));
        $this->assertNull(collect(SaludCliente::correlativosAtrasados())->firstWhere('sucursal', $this->sucursal));
        $this->assertSame('Soporte: GABY', DB::table('bitacora')->where('accion', 'config.correlativo_soporte')->orderByDesc('id')->value('usuario'));
        $this->expectExceptionMessage('ya está bien');
        HerramientasSoporte::igualarCorrelativo($this->sucursal, '03', 'Soporte: GABY');
    }

    public function test_una_caja_olvidada_abierta_se_cierra_desde_el_panel(): void
    {
        $olvidado = DB::table('turnos')->insertGetId(['turno' => 7, 'IdUsuario' => $this->usuario->IdUsuario, 'apertura' => now()->subDays(2),
            'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal, 'estado' => 'ABIERTO', 'monto' => 0]);
        $deHoy = DB::table('turnos')->insertGetId(['turno' => 8, 'IdUsuario' => $this->usuario->IdUsuario, 'apertura' => now()->subHours(3),
            'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal, 'estado' => 'ABIERTO', 'monto' => 0]);

        $turnos = collect(SaludCliente::revisarBase()['problemas'])->pluck('arreglos')->flatten(1)->where('accion', 'cerrar_turno')->pluck('datos.turno');
        $this->assertTrue($turnos->contains($olvidado));
        $this->assertFalse($turnos->contains($deHoy), 'Un turno abierto hoy no es un problema');

        HerramientasSoporte::ejecutar('cerrar_turno', ['turno' => $olvidado], 'Soporte: GABY');

        $this->assertSame('CERRADO', DB::table('turnos')->where('id_turno', $olvidado)->value('estado'));
        $this->assertSame('ABIERTO', DB::table('turnos')->where('id_turno', $deHoy)->value('estado'));
        $this->expectExceptionMessage('ya no está abierto');
        HerramientasSoporte::cerrarTurno($olvidado, 'Soporte: GABY');
    }

    public function test_las_boletas_por_vencer_el_plazo_salen_en_rojo(): void
    {
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('PAN', 1), 2]]), 'PV');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->update(['ccafem' => now()->subDays(6)->toDateString(), 'est_sunat' => 'PENDIENTE']);

        $boletas = $this->deMiSucursal(SaludCliente::revisarBase()['problemas'], 'boleta(s) sin resumen');
        $this->assertNotEmpty($boletas);
        $this->assertSame('rojo', $boletas[0]['nivel']);
    }

    public function test_ver_como_el_cliente_entra_una_sola_vez_y_en_modo_mirar_no_guarda_nada(): void
    {
        [$cliente, $yo] = $this->enElPanel();
        $enlace = ModoSoporte::enlace($cliente, $yo, $this->usuario, 'mirar');
        $ruta = parse_url($enlace, PHP_URL_PATH);
        Auth::logout();

        $this->get($ruta)->assertRedirect();
        $this->assertAuthenticatedAs($this->usuario);
        $this->assertSame('mirar', session(ModoSoporte::SESION)['modo']);
        $this->assertStringContainsString('Soporte (GABY SOPORTE) entró', DB::table('bitacora')->where('accion', 'sesion.soporte_entro')->orderByDesc('id')->value('descripcion'));

        // Solo para mirar: nada que se envíe se guarda
        $this->postJson(route('turnos.abrir'), ['monto' => 10])->assertForbidden()->assertJsonFragment(['ok' => false]);

        // Salir cierra la sesión y deja la hora de salida en el panel
        $this->post(route('soporte.salir'))->assertRedirect('/login');
        $this->assertGuest();
        $this->assertNotNull(DB::connection('central')->table('soporte_ingresos')->where('cliente_id', $cliente->id)->value('salio_at'));

        // El enlace ya se usó: no vuelve a servir
        $this->get($ruta)->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_soporte_con_permiso_de_cambios_cuenta_como_equipo_de_confianza(): void
    {
        Dispositivos::$enPruebas = true;
        try {
            $this->app['request']->setLaravelSession($sesion = $this->app['session.store']);
            $sesion->put(ModoSoporte::SESION, ['id' => 1, 'nombre' => 'GABY', 'usuario' => 'ADMIN', 'modo' => 'mirar', 'hasta' => now()->addHour()->timestamp]);
            $this->assertFalse(Dispositivos::deConfianza(), 'Solo para mirar: su PC no es de confianza');

            $sesion->put(ModoSoporte::SESION.'.modo', 'cambiar');
            $this->assertTrue(Dispositivos::deConfianza(), 'Con «Puede hacer cambios» soporte arregla credenciales y correlativos');
            Dispositivos::exigirConfianza('cambiar las credenciales de SUNAT');
        } finally {
            Dispositivos::$enPruebas = false;
        }
    }

    public function test_soporte_con_permiso_de_cambios_trabaja_como_administrador_aunque_entre_como_cajero(): void
    {
        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->acciones_bloqueadas = json_encode(['anular_ventas']);
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        $this->actingAs($cajero);
        $this->app['request']->setLaravelSession($sesion = $this->app['session.store']);
        $visita = fn (string $modo) => $sesion->put(ModoSoporte::SESION, ['id' => 1, 'nombre' => 'GABY', 'usuario' => 'CAJERO', 'modo' => $modo, 'hasta' => now()->addHour()->timestamp]);

        $visita('mirar');
        $comoMirar = $cajero->fresh();
        $this->assertFalse($comoMirar->esAdmin());
        $this->assertFalse($comoMirar->puede('anular_ventas'));

        $visita('cambiar');
        $comoSoporte = $cajero->fresh();
        $this->assertTrue($comoSoporte->esAdmin(), 'Con «Puede hacer cambios» trabaja como administrador');
        $this->assertTrue($comoSoporte->puede('anular_ventas'));
        $this->assertTrue($comoSoporte->tieneModulo('/configuracion/empresa'));
        $this->assertFalse($this->usuario->fresh()->tieneRol([4]), 'Los demás usuarios no cambian');

        // La pantalla sale con el menú completo de la empresa
        $this->withSession([ModoSoporte::SESION => ['id' => 1, 'nombre' => 'GABY', 'usuario' => 'CAJERO', 'modo' => 'cambiar', 'hasta' => now()->addHour()->timestamp]])
            ->get(route('turnos.index'))->assertOk()->assertSee('SOPORTE');
    }

    public function test_un_enlace_de_soporte_no_sirve_en_otra_empresa(): void
    {
        [$cliente, $yo] = $this->enElPanel();
        $cliente->update(['base_datos' => 'bd_otra_empresa']);
        $ruta = parse_url(ModoSoporte::enlace($cliente, $yo, $this->usuario, 'cambiar'), PHP_URL_PATH);
        Auth::logout();

        $this->get($ruta)->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_el_parte_de_la_manana_lista_solo_las_empresas_con_problemas(): void
    {
        [$cliente] = $this->enElPanel();
        $texto = PartePanel::mensaje([['cliente' => $cliente, 'problemas' => [
            ['nivel' => 'rojo', 'titulo' => 'Certificado digital vencido', 'detalle' => null, 'ir' => null, 'arreglos' => []],
            ['nivel' => 'naranja', 'titulo' => '2 ticket(s) de soporte abierto(s)', 'detalle' => null, 'ir' => 'tickets', 'arreglos' => []],
        ]]]);

        $this->assertStringContainsString('1 empresa(s) necesitan atención (1 urgente(s))', $texto);
        $this->assertStringContainsString('🔴 EMPRESA DE PRUEBA', $texto);
        $this->assertStringContainsString('• Certificado digital vencido', $texto);
        $this->assertStringContainsString('/clientes/'.$cliente->id.'/diagnostico', $texto);
        $this->assertNull(PartePanel::mensaje([]));
    }

    public function test_el_panel_muestra_el_diagnostico_y_la_linea_de_tiempo(): void
    {
        [$cliente, $yo] = $this->enElPanel();
        $this->actingAs($yo, 'superadmin');

        $this->get(route('admin.clientes.diagnostico', $cliente->id))->assertOk()->assertSeeText('Ver como el cliente')->assertSeeText('Línea de tiempo');
        $this->get(route('admin.clientes.linea_tiempo', [$cliente->id, 'fecha' => now()->toDateString()]))->assertOk()->assertSeeText('Línea de tiempo');
    }
}
