<?php

namespace Tests\Flujos;

use App\Support\Bancos;
use App\Support\Conciliacion;
use App\Support\Contabilidad\Contabilidad;
use App\Support\Excel;
use App\Support\VentaDirecta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Bancos y conciliación: cuenta con su 104, libro bancos, movimientos propios con asiento, extracto y conciliación */
class ConciliacionBancariaTest extends FlujoTestCase
{
    private object $cuenta;

    private int $yape;

    protected function setUp(): void
    {
        parent::setUp();
        $this->yape = (int) DB::table('medios_pagos')->insertGetId(['nom_med_pag' => 'YAPE', 'IdEmpresa' => $this->usuario->IdEmpresa,
            'id_empresa_negocio' => $this->sucursal, 'predeterminado' => '0', 'tipo_medio' => 'BILLETERA']);
        $id = Bancos::guardarCuenta($this->usuario, ['banco' => 'BCP', 'numero' => '191-1234567-0-12', 'cuenta_contable' => '104101',
            'saldo_inicial' => 1000, 'fecha_inicio' => '2026-10-01', 'medios' => [$this->yape]]);
        $this->cuenta = Bancos::cuenta((string) $this->usuario->IdEmpresa, $id);
    }

    /** Venta al contado cobrada con Yape */
    private function ventaYape(float $precio, string $fecha, ?string $operacion = null, string $tdocod = '13'): int
    {
        $plato = $this->producto('PLATO '.$precio.uniqid(), $precio);
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]], $tdocod, ['id_med_pag' => [$this->yape], 'mon_med_pag' => [$precio]]), 'PV');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->update(['ccafem' => $fecha]);
        if ($operacion) {
            DB::table('venta_medio_pago')->where('IdCpe_cabecera', $id)->update(['num_operacion' => $operacion]);
        }

        return $id;
    }

    /** Sube un extracto con estas líneas [fecha, descripción, operación, cargo, abono, saldo] */
    private function extracto(array $lineas): array
    {
        $ruta = (new Excel)->hoja('Movimientos', ['Fecha', 'Descripción', 'N° Operación', 'Cargo', 'Abono', 'Saldo'], $lineas)->guardar();

        return Conciliacion::importar($this->usuario, $this->cuenta, $ruta, 'xlsx');
    }

    private function asientoDe(int $movimiento): array
    {
        $a = DB::table('bancos_movimientos')->where('id', $movimiento)->value('asiento_id');

        return DB::table('conta_asiento_detalle')->where('asiento_id', $a)->orderBy('id')->get()
            ->map(fn ($d) => [(string) $d->cuenta, (float) $d->debe, (float) $d->haber])->all();
    }

    public function test_el_medio_de_pago_pasa_a_la_cuenta_contable_del_banco_y_su_cobro_entra_al_libro_bancos(): void
    {
        $this->assertSame('104101', DB::table('medios_pagos')->where('id_med_pag', $this->yape)->value('cuenta_contable'));
        $this->ventaYape(45, '2026-10-05', '889911');

        $libro = Bancos::movimientos($this->cuenta, '2026-10-01', '2026-10-31');

        $this->assertCount(1, $libro);
        $this->assertSame(['venta', '2026-10-05', '889911', 45.0], [$libro[0]['origen'], $libro[0]['fecha'], $libro[0]['operacion'], $libro[0]['monto']]);
        $this->assertSame(1045.0, Bancos::saldoLibros($this->cuenta, '2026-10-31'));
    }

    public function test_la_centralizacion_lleva_el_cobro_con_yape_a_la_cuenta_del_banco(): void
    {
        $this->ventaYape(45, '2026-10-05', null, '03');   // la nota de venta no se contabiliza; la boleta sí

        Contabilidad::centralizarVentas((string) $this->usuario->IdEmpresa, '202610');

        $this->assertSame(45.0, Bancos::saldoMayor((string) $this->usuario->IdEmpresa, '104101', '2026-10-31'));
    }

    public function test_una_comision_lleva_su_asiento_y_al_anularla_se_borra(): void
    {
        $id = Bancos::registrarMovimiento($this->usuario, $this->cuenta, ['tipo' => 'COMISION', 'fecha' => '2026-10-10', 'monto' => 12.5, 'descripcion' => 'MANTENIMIENTO']);

        $this->assertSame([['6791', 12.5, 0.0], ['104101', 0.0, 12.5]], $this->asientoDe($id));
        $this->assertSame(987.5, Bancos::saldoLibros($this->cuenta, '2026-10-31'));

        $asiento = DB::table('bancos_movimientos')->where('id', $id)->value('asiento_id');
        Bancos::anularMovimiento($this->usuario, $id);
        $this->assertFalse(DB::table('conta_asientos')->where('id', $asiento)->exists());
        $this->assertSame(1000.0, Bancos::saldoLibros($this->cuenta, '2026-10-31'));
    }

    public function test_una_transferencia_entre_cuentas_propias_mueve_las_dos_con_un_asiento(): void
    {
        $otra = Bancos::cuenta((string) $this->usuario->IdEmpresa, Bancos::guardarCuenta($this->usuario, ['banco' => 'BBVA', 'numero' => '0011-22',
            'cuenta_contable' => '104102', 'saldo_inicial' => 0, 'fecha_inicio' => '2026-10-01']));

        $id = Bancos::registrarMovimiento($this->usuario, $this->cuenta, ['tipo' => 'TRANSFERENCIA', 'fecha' => '2026-10-12', 'monto' => 300, 'cuenta_destino_id' => $otra->id]);

        $this->assertSame([['104102', 300.0, 0.0], ['104101', 0.0, 300.0]], $this->asientoDe($id));
        $this->assertSame([700.0, 300.0], [Bancos::saldoLibros($this->cuenta, '2026-10-31'), Bancos::saldoLibros($otra, '2026-10-31')]);
        $this->assertSame(1, DB::table('conta_asientos')->where('origen', 'BANCOS')->count());
    }

    public function test_el_extracto_se_importa_una_sola_vez(): void
    {
        $lineas = [['05/10/2026', 'ABONO YAPE', '889911', '', '45.00', '1045.00'], ['06/10/2026', 'COMISION', '', '12.50', '', '1032.50'],
            ['', 'TOTAL', '', '12.50', '45.00', '']];

        $this->assertSame(['nuevas' => 2, 'repetidas' => 0, 'ignoradas' => 1], $this->extracto($lineas));
        $this->assertSame(['nuevas' => 0, 'repetidas' => 2, 'ignoradas' => 1], $this->extracto($lineas));
        $this->assertSame([45.0, -12.5], DB::table('bancos_extracto')->orderBy('id')->pluck('monto')->map(fn ($m) => (float) $m)->all());
    }

    public function test_conciliacion_automatica_por_operacion_por_monto_y_por_el_deposito_del_dia(): void
    {
        $this->ventaYape(45, '2026-10-05', '889911');      // por número de operación (el banco lo registra al día siguiente)
        $this->ventaYape(80, '2026-10-07');                // por monto y fecha cercana
        $this->ventaYape(30, '2026-10-09');                // estos dos llegan juntos en un solo abono
        $this->ventaYape(20, '2026-10-09');
        $this->extracto([['06/10/2026', 'ABONO YAPE', '00889911', '', '45.00', ''], ['08/10/2026', 'ABONO', '', '', '80.00', ''],
            ['10/10/2026', 'ABONO POS DEL DIA', '', '', '50.00', ''], ['11/10/2026', 'COMISION', '', '7.00', '', '']]);

        $this->assertSame(3, Conciliacion::automatico($this->usuario, $this->cuenta, '2026-10-01', '2026-10-31'));

        $p = Conciliacion::pendientes($this->cuenta, '2026-10-01', '2026-10-31');
        $this->assertSame(['COMISION'], $p['banco']->pluck('descripcion')->all(), 'Solo la comisión queda por conciliar.');
        $this->assertCount(0, $p['sistema']);
    }

    public function test_lo_que_solo_esta_en_el_banco_se_registra_y_queda_conciliado(): void
    {
        $this->extracto([['11/10/2026', 'COMISION', '', '7.00', '', '']]);
        $linea = DB::table('bancos_extracto')->first();

        $id = Conciliacion::registrarDesdeBanco($this->usuario, $this->cuenta, $linea->id, 'COMISION');

        $this->assertSame([['6791', 7.0, 0.0], ['104101', 0.0, 7.0]], $this->asientoDe($id));
        $this->assertCount(0, Conciliacion::pendientes($this->cuenta, '2026-10-01', '2026-10-31')['banco']);
        $this->expectExceptionMessage('ya está conciliada');
        Conciliacion::registrarDesdeBanco($this->usuario, $this->cuenta, $linea->id, 'COMISION');
    }

    public function test_a_mano_solo_se_concilia_lo_que_suma_igual(): void
    {
        $this->ventaYape(45, '2026-10-05');
        $this->extracto([['20/10/2026', 'DEPOSITO', '', '', '44.00', '']]);
        $linea = (int) DB::table('bancos_extracto')->value('id');
        $mov = Bancos::movimientos($this->cuenta, '2026-10-01', '2026-10-31')->first();

        try {
            Conciliacion::manual($this->usuario, $this->cuenta, [$linea], ['venta:'.$mov['id']]);
            $this->fail('Concilió montos distintos.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('diferencia -1.00', $e->getMessage());
        }

        DB::table('bancos_extracto')->where('id', $linea)->update(['monto' => 45]);
        Conciliacion::manual($this->usuario, $this->cuenta, [$linea], ['venta:'.$mov['id']]);
        $this->assertCount(0, Conciliacion::pendientes($this->cuenta, '2026-10-01', '2026-10-31')['sistema']);
    }

    public function test_desde_la_pantalla_se_sube_el_extracto_se_concilia_y_se_registra_lo_del_banco(): void
    {
        $this->ventaYape(45, '2026-10-05', '889911');
        $ruta = (new Excel)->hoja('Movimientos', ['Fecha', 'Descripción', 'N° Operación', 'Cargo', 'Abono', 'Saldo'], [
            ['05/10/2026', 'ABONO YAPE', '889911', '', '45.00', '1045.00'], ['06/10/2026', 'ITF', '', '0.05', '', '1044.95']])->guardar();
        $archivo = new UploadedFile($ruta, 'extracto_bcp.xlsx', null, null, true);
        $url = fn (string $vista) => route('bancos.show', ['id' => $this->cuenta->id, 'mes' => '2026-10', 'vista' => $vista]);

        $this->post(route('bancos.extracto', $this->cuenta->id), ['archivo' => $archivo])->assertSessionHas('success');
        $this->post(route('bancos.automatico', $this->cuenta->id), ['mes' => '2026-10'])->assertSessionHas('success', '✔ 1 línea(s) del banco conciliadas automáticamente.');
        $itf = (int) DB::table('bancos_extracto')->where('descripcion', 'ITF')->value('id');
        $this->post(route('bancos.registrar', $this->cuenta->id), ['extracto_id' => $itf, 'tipo' => 'ITF'])->assertSessionHas('success');

        $this->get($url('conciliar'))->assertOk()->assertSee('Todo lo del banco de este mes está conciliado');
        $this->get($url('libro'))->assertOk()->assertSee('Venta ')->assertSee('ITF');
        $this->get($url('reporte'))->assertOk()->assertSee('✔ Conciliado');
        $this->get(route('bancos.index'))->assertOk()->assertSee('191-1234567-0-12');
        $this->get(route('bancos.edit', $this->cuenta->id))->assertOk();
        $this->get(route('bancos.reporte.excel', ['id' => $this->cuenta->id, 'mes' => '2026-10']))->assertOk()->assertDownload();
    }

    public function test_sin_la_opcion_en_su_menu_no_entra_a_bancos(): void
    {
        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);

        $this->actingAs($cajero)->get(route('bancos.index'))->assertForbidden();
        $this->actingAs($cajero)->post(route('bancos.movimiento', $this->cuenta->id), ['tipo' => 'RETIRO', 'fecha' => '2026-10-10', 'monto' => 500])->assertForbidden();
    }

    public function test_el_reporte_cuadra_con_lo_pendiente_de_cada_lado(): void
    {
        $this->ventaYape(45, '2026-10-05', '889911');     // el banco ya lo tiene
        $this->ventaYape(60, '2026-10-31');               // el banco lo refleja en noviembre: pendiente del sistema
        $this->extracto([['06/10/2026', 'ABONO YAPE', '889911', '', '45.00', '1045.00'], ['15/10/2026', 'ITF', '', '0.05', '', '1044.95']]);
        Conciliacion::automatico($this->usuario, $this->cuenta, '2026-10-01', '2026-10-31');

        $r = Conciliacion::reporte($this->cuenta, '202610');

        $this->assertSame([1105.0, 1044.95, 60.0, -0.05], [$r['saldo_libros'], $r['saldo_banco'], $r['total_sistema_pendiente'], $r['total_banco_pendiente']]);
        $this->assertSame(0.0, $r['diferencia'], 'Libros 1105 − 60 en tránsito − 0.05 de ITF = 1044.95 del banco.');
    }
}
