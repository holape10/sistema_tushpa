<?php

namespace Tests\Flujos;

use App\Models\Turno;
use App\Models\User;
use App\Support\ArqueoDiario;
use App\Support\Cuentas;
use App\Support\VentaDirecta;
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

    public function test_el_arqueo_cuenta_la_caja_sin_cerrar_el_turno_y_guarda_el_resumen_completo(): void
    {
        $plato = $this->producto('JUANE', 15, 10);
        VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 2]], '13'), 'PV');   // S/ 30 en efectivo
        DB::table('movimientoscaja')->insert(['tip_caj_id' => '003', 'mov_com' => 'COMPRA DE HIELO', 'importe' => 4, 'estado' => 'ACTIVO',
            'mov_fecha' => now()->toDateString(), 'registro' => now(), 'id_turno' => $this->turno->id_turno, 'IdUsuario' => $this->usuario->IdUsuario,
            'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal]);

        $this->get(route('arqueos.create', $this->turno->id_turno))->assertOk()->assertSee('Cómo está la caja ahora')->assertSee('COMPRA DE HIELO');
        $this->post(route('arqueos.store', $this->turno->id_turno), ['billetes' => ['cant_c_20_soles' => 1, 'cant_m_1_sol' => 1], 'observacion' => 'sorpresa'])
            ->assertRedirect();

        $a = DB::table('arqueos_caja')->where('id_turno', $this->turno->id_turno)->first();
        $this->assertEquals([26.0, 21.0, -5.0], [(float) $a->esperado, (float) $a->contado, (float) $a->diferencia], 'Debe haber 30 de ventas − 4 de egreso.');
        $this->assertSame('ABIERTO', $this->turno->fresh()->estado, 'El arqueo no cierra el turno.');
        $foto = json_decode($a->resumen, true);
        $this->assertSame([30.0, 4.0, 1], [(float) $foto['ventas_total'], (float) $foto['egresos'], $foto['operaciones']]);
        $this->assertSame('COMPRA DE HIELO', $foto['movimientos'][0]['detalle']);

        $this->get(route('arqueos.show', $a->id))->assertOk()->assertSee('Falta')->assertSee('5.00')->assertSee('Billete S/ 20')->assertSee('COMPRA DE HIELO')
            ->assertSee('Total vendido');
        $this->get(route('arqueos.index'))->assertOk()->assertSee('Falta S/ 5.00')->assertSee('Faltó en total');
    }

    public function test_un_turno_cerrado_ya_no_se_arquea(): void
    {
        $this->turno->update(['estado' => 'CERRADO']);

        $this->post(route('arqueos.store', $this->turno->id_turno), ['monto' => 10])->assertNotFound();
    }

    public function test_el_arqueo_diario_junta_ventas_credito_cobranzas_gastos_y_el_efectivo_del_dia(): void
    {
        $plato = $this->producto('JUANE', 15, 10);
        VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 2]], '03'), 'PV');
        VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1, 10]], '13'), 'PV');
        $alCredito = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1, 500]], '01', ['estadopago' => $this->credito,
            'fecVen' => now()->addDays(30)->toDateString(), 'id_med_pag' => [], 'mon_med_pag' => [], 'paga' => 0]), 'PV');
        Cuentas::registrarPago($this->usuario, 'cobrar', (int) DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $alCredito)->value('cue_cob_id'),
            ['fecha' => now()->toDateString(), 'medios' => [['id' => $this->efectivo, 'monto' => 200]]]);
        foreach ([['003', 'COMPRA DE HIELO', 4], ['008', 'DEVOLUCION DE PRESTAMO', 6]] as [$tipo, $detalle, $importe]) {
            DB::table('movimientoscaja')->insert(['tip_caj_id' => $tipo, 'mov_com' => $detalle, 'importe' => $importe, 'estado' => 'ACTIVO',
                'mov_fecha' => now()->toDateString(), 'registro' => now(), 'id_turno' => $this->turno->id_turno, 'IdUsuario' => $this->usuario->IdUsuario,
                'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal]);
        }

        $d = ArqueoDiario::datos($this->sucursal, now()->toDateString());
        $t = $d['totales'];
        $fondo = (float) DB::table('turnos')->where('id_empresa_negocio', $this->sucursal)->whereDate('apertura', now()->toDateString())->sum('monto');

        $this->assertEquals([540.0, 500.0, 200.0, 4.0, 6.0], [$t['ventas'], $t['credito'], $t['cobranzas'], $t['gastos'], $t['otros']]);
        $this->assertEquals([30.0, 10.0, 0.0, 200.0], [$t['ingresos']['boletas'], $t['ingresos']['notas'], $t['ingresos']['facturas'], $t['ingresos']['cobranzas']]);
        $this->assertEquals(round($fondo + 30 + 10 + 200 + 6 - 4, 2), $t['saldo'], 'Saldo = fondo + ventas y cobranzas en efectivo + otros ingresos - gastos.');
        $this->assertCount(1, $d['credito']);

        $this->get(route('arqueos.diario', ['fecha' => now()->toDateString()]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->ciego(true);
        $this->actingAs($this->cajero)->get(route('arqueos.diario'))->assertForbidden();
    }
}
