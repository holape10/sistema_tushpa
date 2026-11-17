<?php

namespace Tests\Flujos;

use App\Http\Controllers\ComandasController;
use App\Http\Controllers\SucursalController;
use App\Support\LogoNegocio;
use App\Support\VentaDirecta;
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

    public function test_la_foto_para_redes_guarda_su_marco_y_usa_el_logo_a_color_de_la_empresa(): void
    {
        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario(['foto_redes' => 1, 'foto_marco_color' => '#ZZZZZZ']))
            ->assertSessionHasErrors('foto_marco_color');

        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario(['foto_redes' => 1, 'foto_marco_estilo' => 'BORDE',
            'foto_marco_color' => '#DB2777', 'foto_texto' => '@local.prueba']))->assertSessionHasNoErrors();
        // La sucursal con su logo en blanco y negro (ticketera); la empresa con el logo a color
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['logo_suc' => 'imagenes/icono.png']);
        DB::table('empresa')->where('IdEmpresa', $this->usuario->IdEmpresa)->update(['LogEmpresa' => 'imagenes/192.png']);

        $marco = ComandasController::fotoRedes($this->sucursal);
        $this->assertSame(['BORDE', '#db2777', '@local.prueba'], [$marco['estilo'], $marco['color'], $marco['texto']]);
        $this->assertStringEndsWith('imagenes/192.png', $marco['logo'], 'La foto usa el logo a color de la empresa.');

        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario())->assertSessionHasNoErrors();
        $this->assertNull(ComandasController::fotoRedes($this->sucursal), 'Sin marcar, la foto para redes queda apagada.');
    }

    public function test_el_correlativo_no_baja_de_lo_emitido_y_avisa_si_ese_ultimo_aun_no_se_envio(): void
    {
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('JUANE', 15, 10), 1]], '03'), 'PV');
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first(['serdoc', 'numdoc']);
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->update(['est_sunat' => 'PENDIENTE', 'enviado' => 0]);

        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario(['BnuEmpresa' => $cab->numdoc - 1]))
            ->assertSessionHasErrors(['BnuEmpresa' => "Boleta {$cab->serdoc}: el {$cab->serdoc}-{$cab->numdoc} ya existe; el correlativo no puede ser menor (se repetirían números). Lo último emitido es el {$cab->numdoc}. El {$cab->serdoc}-{$cab->numdoc} aún no se envía a SUNAT: si su número salió por error, no lo envíes y pide a soporte que lo corrija."]);

        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->update(['est_sunat' => 'ACEPTADO', 'enviado' => 1]);
        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario(['BnuEmpresa' => $cab->numdoc - 1]))
            ->assertSessionHasErrors(['BnuEmpresa' => "Boleta {$cab->serdoc}: el {$cab->serdoc}-{$cab->numdoc} ya existe; el correlativo no puede ser menor (se repetirían números). Lo último emitido es el {$cab->numdoc}."]);
        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario(['BnuEmpresa' => $cab->numdoc]))->assertSessionHasNoErrors();
    }

    public function test_con_un_tramo_antiguo_mas_adelante_el_correlativo_baja_y_al_emitir_se_salta_lo_que_ya_existe(): void
    {
        // La serie volvió a empezar: hay boletas nuevas (1, 2…) y un tramo de antes más adelante (695-700)
        $juane = $this->producto('JUANE', 15, 50);
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$juane, 1]], '03'), 'PV');
        $cab = (array) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first();
        foreach (range(695, 700) as $n) {
            DB::table('cpe_cabecera')->insert(array_merge(array_diff_key($cab, ['IdCpe_cabecera' => 0]), ['numdoc' => $n, 'ccafem' => '2026-06-25', 'est_sunat' => 'ACEPTADO', 'enviado' => 1]));
        }
        $actual = (int) $cab['numdoc'];

        // Se puede dejar en el último del tramo nuevo (los 100 siguientes están libres)…
        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario(['BnuEmpresa' => $actual]))->assertSessionHasNoErrors();
        // …pero no tan cerca del tramo antiguo
        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario(['BnuEmpresa' => 650]))
            ->assertSessionHasErrors(['BnuEmpresa' => "Boleta {$cab['serdoc']}: el {$cab['serdoc']}-695 ya existe; el correlativo no puede ser menor (se repetirían números). Lo último emitido es el 700."]);

        // Al llegar al tramo antiguo, la venta salta al siguiente número libre (nunca repite)
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['BnuEmpresa' => 694]);
        $nueva = VentaDirecta::registrar($this->usuario, $this->venta([[$juane, 1]], '03'), 'PV');
        $this->assertSame(701, (int) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $nueva)->value('numdoc'));
        $this->assertSame(701, (int) DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->value('BnuEmpresa'));
    }

    public function test_las_pantallas_de_empresa_y_sucursal_abren_dentro_del_menu(): void
    {
        $this->get(route('empresas.edit', $this->usuario->IdEmpresa))->assertOk()->assertSee('Logo de la empresa')
            ->assertDontSee('IMP. PEDIDOS')->assertDontSee('Correo de Envío');
        $this->get(route('sucursales.edit', $this->sucursal))->assertOk()->assertSee('Logo de esta sucursal')->assertSee('Notas de crédito y de débito');
    }

    public function test_cambiar_a_una_serie_nueva_empieza_en_1_aunque_la_anterior_vaya_mas_adelante(): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['BseEmpresa' => 'B001', 'BnuEmpresa' => 54326]);
        DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->delete();
        VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('JUANE', 15, 10), 1]], '03'), 'PV');

        $this->get(route('sucursales.edit', $this->sucursal))->assertOk()->assertSee('Serie nueva: pon 0');
        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario(['BseEmpresa' => 'B001', 'BnuEmpresa' => 54300]))
            ->assertSessionHasErrors('BnuEmpresa');
        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario(['BseEmpresa' => 'B002', 'BnuEmpresa' => 0]))->assertSessionHasNoErrors();

        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('TACACHO', 12, 10), 1]], '03'), 'PV');
        $this->assertSame(['B002', 1], [($c = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first())->serdoc, (int) $c->numdoc]);
    }

    public function test_el_cancelado_grande_del_a4_simple_solo_si_la_sucursal_lo_activa(): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['diseno_a4' => 'SIMPLE']);
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('PASAJE FLUVIAL', 200, 10), 1]], '01'), 'PV');
        $a4 = fn () => $this->get(route('cobros.voucher', [$id, 'formato' => 'a4']))->assertOk();

        $a4()->assertSee('font-size:10px; margin-top:6px">CANCELADO', false)->assertSee('____de___', false);

        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario(['diseno_a4' => 'SIMPLE', 'a4_cancelado_grande' => 1]))->assertSessionHasNoErrors();

        $a4()->assertSee('font-size:24px; letter-spacing:1px; margin-top:8px">CANCELADO', false)->assertDontSee('____de___', false);
    }

    public function test_el_redondeo_automatico_se_elige_por_sucursal_y_hacia_abajo_se_acepta(): void
    {
        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario(['redondeo_linea' => 'ABAJO']))->assertSessionHasNoErrors();
        $this->assertSame('ABAJO', DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->value('redondeo_linea'));
        $this->get(route('pv.index'))->assertOk()->assertSee('redondeo: "ABAJO"', false);

        // 2.99 × 9 = 26.91 → hacia abajo 26.90; 2.81 × 9.85 = 27.68 → 27.60 (9 céntimos menos)
        $p = $this->producto('ARROZ A GRANEL', 9.85, 50);
        $datos = $this->venta([], '13');
        $datos['items'] = [['id' => $p->IdProducto, 'cantidad' => 2.81, 'precio' => 9.85, 'importe' => 27.60]];
        $datos['mon_med_pag'] = [27.60];
        $datos['paga'] = 27.60;
        $id = (int) $this->postJson(route('pv.registrar'), $datos)->assertJson(['estado' => 'success'])->json('id');
        $this->assertEquals(27.60, (float) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->value('ccaitv'));

        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario(['redondeo_linea' => '']))->assertSessionHasNoErrors();
        $this->assertNull(DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->value('redondeo_linea'));
    }

    public function test_con_un_ubigeo_que_no_esta_en_el_catalogo_se_guarda_igual_el_nombre(): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['departamento' => 'LORETO', 'provincia' => 'MAYNAS', 'distrito' => 'IQUITOS']);

        $this->patch(route('sucursales.update', $this->sucursal), $this->formulario(['ubigeo' => '999999', 'nombre_comercial' => 'HOTEL EL PARAISO']))
            ->assertSessionHasNoErrors()->assertRedirect(route('sucursales.index'));

        $s = DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->first();
        $this->assertSame(['HOTEL EL PARAISO', 'LORETO', 'MAYNAS', 'IQUITOS'], [$s->nombre_comercial, $s->departamento, $s->provincia, $s->distrito]);
    }
}
