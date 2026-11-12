<?php

namespace Tests\Flujos;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Support\AnulacionVenta;
use App\Support\Notas;
use App\Support\Sunat\SunatService;
use App\Support\VentaDirecta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/** Vender, cobrar, el stock, la numeración, el IGV y anular: lo que nunca debe romperse */
class VentaTest extends FlujoTestCase
{
    public function test_una_boleta_al_contado_cobra_el_total_descuenta_stock_y_avanza_la_numeracion(): void
    {
        $lomo = $this->producto('LOMO SALTADO', 25, 10);
        $chicha = $this->producto('CHICHA MORADA', 6, 20);

        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$lomo, 2], [$chicha, 3]]), 'PV');
        $cab = $this->cabecera($id);

        $this->assertSame('B001', $cab->serdoc);
        $this->assertSame(1, (int) $cab->numdoc);
        $this->assertEquals(68.00, (float) $cab->ccaitv);
        $this->assertSame('PENDIENTE', $cab->est_sunat);
        $this->assertEquals(68.00, (float) DB::table('venta_medio_pago')->where('IdCpe_cabecera', $id)->sum('monto'));
        $this->assertEquals(8.0, $this->stock($lomo));
        $this->assertEquals(17.0, $this->stock($chicha));

        $id2 = VentaDirecta::registrar($this->usuario, $this->venta([[$lomo, 1]]), 'PV');
        $this->assertSame(2, (int) $this->cabecera($id2)->numdoc);
    }

    public function test_el_igv_de_una_factura_gravada_cuadra_y_el_exonerado_no_cobra_igv(): void
    {
        $plato = $this->producto('CEVICHE', 118, 5);

        $factura = $this->cabecera(VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]], '01'), 'PV'));
        $this->assertSame('F001', $factura->serdoc);
        $this->assertEquals(100.00, (float) $factura->ccatvg);
        $this->assertEquals(18.00, (float) $factura->ccaigv);
        $this->assertEquals(118.00, (float) $factura->ccaitv);
        $this->assertEquals(18.0, (float) $factura->por_igv);

        $this->afectacion('20');
        $boleta = $this->cabecera(VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]]), 'PV'));
        $this->assertEquals(0.0, (float) $boleta->ccaigv);
        $this->assertEquals(118.00, (float) $boleta->ccatexo);
        $this->assertEquals(118.00, (float) $boleta->ccaitv);
    }

    public function test_el_restaurante_mype_cobra_10_5_por_ciento_en_2026_y_15_por_ciento_en_2027(): void
    {
        $plato = $this->producto('ARROZ CON MARISCOS', 110.5, 5);

        $en2026 = $this->cabecera(VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]]), 'PVCOMANDA'));
        $this->assertEquals(10.5, (float) $en2026->por_igv);
        $this->assertEquals(100.00, (float) $en2026->ccatvg);
        $this->assertEquals(10.50, (float) $en2026->ccaigv);

        Carbon::setTestNow(Carbon::parse('2027-01-02 12:00:00'));
        $en2027 = $this->cabecera(VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]]), 'PVCOMANDA'));
        $this->assertEquals(15.0, (float) $en2027->por_igv);

        // Sin el IGV especial (no cumple los requisitos de MYPE), el restaurante vende con 18%
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['igv_restaurante' => 0]);
        $sinEspecial = $this->cabecera(VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]]), 'PVCOMANDA'));
        $this->assertEquals(18.0, (float) $sinEspecial->por_igv);
    }

    public function test_sin_turno_abierto_no_se_puede_vender(): void
    {
        $plato = $this->producto('POLLO A LA BRASA', 60, 5);
        $this->turno->update(['estado' => 'CERRADO']);

        $this->expectExceptionMessage('turno');
        VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]]), 'PV');
    }

    public function test_si_los_pagos_no_cuadran_con_el_total_la_venta_no_se_registra(): void
    {
        $plato = $this->producto('TACACHO', 30, 5);
        $datos = $this->venta([[$plato, 1]]);
        $datos['mon_med_pag'] = [20];

        try {
            VentaDirecta::registrar($this->usuario, $datos, 'PV');
            $this->fail('Se registró una venta con pagos que no cuadran.');
        } catch (\RuntimeException $e) {
            $this->assertSame(0, DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->count());
            $this->assertEquals(5.0, $this->stock($plato));
        }
    }

    public function test_anular_una_venta_devuelve_el_stock_y_no_se_anula_dos_veces(): void
    {
        $plato = $this->producto('JUANE', 15, 10);
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 4]]), 'PV');
        $this->assertEquals(6.0, $this->stock($plato));

        AnulacionVenta::anular($id, 'ERROR DEL CAJERO', $this->usuario->IdUsuario);

        $this->assertNotNull($this->cabecera($id)->ccabaj);
        $this->assertEquals(10.0, $this->stock($plato));
        $this->assertNull(AnulacionVenta::anular($id, 'OTRA VEZ', $this->usuario->IdUsuario));
        $this->assertEquals(10.0, $this->stock($plato));
    }

    public function test_la_nota_de_credito_por_anulacion_devuelve_todo_y_lleva_el_mismo_igv(): void
    {
        $plato = $this->producto('PATARASHCA', 59, 10);
        $factura = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 2]], '01'), 'PV');

        // Mientras SUNAT no la acepta, no lleva nota de crédito (se anula con baja o en el sistema)
        try {
            Notas::emitir($this->usuario, $factura, ['tdocod' => '07', 'tipnot' => '01', 'motivo' => 'ANULACION']);
            $this->fail('Se emitió una nota de crédito de una factura que SUNAT aún no aceptó.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ACEPTADO', $e->getMessage());
        }
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $factura)->update(['est_sunat' => 'ACEPTADO']);

        $nota = $this->cabecera(Notas::emitir($this->usuario, $factura, ['tdocod' => '07', 'tipnot' => '01', 'motivo' => 'ANULACION DE LA OPERACION']));

        $this->assertSame('07', $nota->tdocod);
        $this->assertSame('FC01', $nota->serdoc);
        $this->assertEquals(118.00, (float) $nota->ccaitv);
        $this->assertEquals(18.0, (float) $nota->por_igv);
        $this->assertEquals(10.0, $this->stock($plato));
    }

    public function test_la_venta_al_credito_no_registra_pago_y_queda_por_cobrar(): void
    {
        $plato = $this->producto('MENU EJECUTIVO', 15, 10);
        $datos = $this->venta([[$plato, 4]], '01', ['fecVen' => now()->addDays(15)->toDateString()]);
        $datos['estadopago'] = $this->credito;
        $datos['id_med_pag'] = $datos['mon_med_pag'] = [];

        $id = VentaDirecta::registrar($this->usuario, $datos, 'PV');

        $this->assertSame(0, DB::table('venta_medio_pago')->where('IdCpe_cabecera', $id)->count());
        $deuda = DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $id)->first();
        $this->assertNotNull($deuda);
        $this->assertEquals(60.00, (float) $deuda->saldo);
    }

    public function test_la_linea_libre_se_vende_con_la_cantidad_y_la_unidad_que_elige_la_caja(): void
    {
        $datos = $this->venta([], '13');
        $datos['items'] = [['id' => null, 'descripcion' => 'ARENA FINA', 'cantidad' => 5, 'precio' => 40, 'umecod' => 'MTQ'],
            ['id' => null, 'descripcion' => 'FLETE', 'cantidad' => 1, 'precio' => 30, 'umecod' => 'XXX']];
        $datos['mon_med_pag'] = [230];
        $datos['paga'] = 230;

        $id = VentaDirecta::registrar($this->usuario, $datos, 'PV');

        $this->assertSame(['ARENA FINA' => 'MTQ', 'FLETE' => 'NIU'],
            DB::table('cpe_detalle')->where('IdCpe_cabecera', $id)->orderBy('cdedes')->pluck('umecod', 'cdedes')->all(), 'Una unidad que no existe queda como Unidad.');
        $this->assertEquals(5.0, (float) DB::table('cpe_detalle')->where('IdCpe_cabecera', $id)->where('cdedes', 'ARENA FINA')->value('cdecan'));
    }

    public function test_las_lineas_libres_en_cero_se_imprimen_con_su_servicio_y_no_van_a_sunat(): void
    {
        $datos = $this->venta([], '01');
        $datos['items'] = [['id' => null, 'descripcion' => 'POR EL SERVICIO DE LIMPIEZA DE LA OFICINA', 'cantidad' => 1, 'precio' => 0],
            ['id' => null, 'descripcion' => 'CORRESPONDIENTE AL PERIODO DE SETIEMBRE', 'cantidad' => 1, 'precio' => 2039.32],
            ['id' => null, 'descripcion' => 'SEGUN CONTRATO 045', 'cantidad' => 1, 'precio' => 0]];
        $datos['mon_med_pag'] = [2039.32];
        $datos['paga'] = 2039.32;

        $r = $this->postJson(route('pv.registrar'), $datos)->assertOk()->assertJson(['estado' => 'success']);
        $id = (int) $r->json('id');

        $detalle = DB::table('cpe_detalle')->where('IdCpe_cabecera', $id)->get();
        $this->assertCount(1, $detalle, 'Las líneas en S/ 0 no son ítems.');
        $this->assertSame("POR EL SERVICIO DE LIMPIEZA DE LA OFICINA\nCORRESPONDIENTE AL PERIODO DE SETIEMBRE\nSEGUN CONTRATO 045", $detalle[0]->descripcion_impresa);
        $this->assertEquals(2039.32, (float) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->value('ccaitv'));

        $sunat = new SunatService(Empresa::findOrFail($this->usuario->IdEmpresa), EmpresaNegocio::findOrFail($this->sucursal));
        $detalles = (new \ReflectionMethod($sunat, 'construirComprobante'))->invoke($sunat, $this->cabecera($id))->getDetails();
        $this->assertCount(1, $detalles);
        $this->assertSame('CORRESPONDIENTE AL PERIODO DE SETIEMBRE', $detalles[0]->getDescripcion());

        $this->get(route('cobros.voucher', $id))->assertOk()->assertSeeInOrder(['POR EL SERVICIO DE LIMPIEZA', 'CORRESPONDIENTE AL PERIODO', 'SEGUN CONTRATO 045']);
    }

    public function test_solo_texto_o_un_producto_en_cero_no_se_vende(): void
    {
        $datos = $this->venta([], '13');
        $datos['items'] = [['id' => null, 'descripcion' => 'SOLO UNA NOTA', 'cantidad' => 1, 'precio' => 0]];
        $this->postJson(route('pv.registrar'), $datos)->assertJsonMissing(['estado' => 'success']);

        $datos = $this->venta([[$this->producto('ARENA', 40, 10), 1, 0]], '13');
        $this->postJson(route('pv.registrar'), $datos)->assertJsonMissing(['estado' => 'success']);
        $this->assertSame(0, DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->count());
    }

    /** Factura al crédito por S/ 900 (3 x S/ 300) */
    private function alCreditoEnCuotas(array $cuotas): TestResponse
    {
        return $this->postJson(route('pv.registrar'), $this->venta([[$this->producto('MOCHILA', 300, 10), 3]], '01', ['estadopago' => $this->credito,
            'fecVen' => now()->addDays(30)->toDateString(), 'cuotas' => $cuotas, 'id_med_pag' => [], 'mon_med_pag' => [], 'paga' => 0]));
    }

    public function test_el_credito_en_varias_cuotas_va_a_sunat_y_sale_impreso(): void
    {
        $fechas = [now()->addDays(30)->toDateString(), now()->addDays(60)->toDateString(), now()->addDays(90)->toDateString()];
        $id = (int) $this->alCreditoEnCuotas(array_map(fn ($f) => ['fecha' => $f, 'monto' => 300], $fechas))->assertJson(['estado' => 'success'])->json('id');

        $this->assertSame($fechas, DB::table('cpe_cuotas')->where('IdCpe_cabecera', $id)->orderBy('numero')->pluck('fecha')->map(fn ($f) => (string) $f)->all());
        $this->assertSame($fechas[2], (string) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->value('ccafve'), 'Vence con la última cuota.');
        $this->assertSame($fechas[2], (string) DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $id)->value('fec_ven'));

        $sunat = new SunatService(Empresa::findOrFail($this->usuario->IdEmpresa), EmpresaNegocio::findOrFail($this->sucursal));
        $factura = (new \ReflectionMethod($sunat, 'construirComprobante'))->invoke($sunat, $this->cabecera($id));
        $this->assertSame([300.0, 300.0, 300.0], array_map(fn ($c) => (float) $c->getMonto(), $factura->getCuotas()));
        $this->assertSame($fechas[1], $factura->getCuotas()[1]->getFechaPago()->format('Y-m-d'));

        $this->get(route('cobros.voucher', [$id, 'formato' => 'a4']))->assertOk()->assertSeeInOrder(['CUOTAS', $fechas[0], $fechas[1], $fechas[2]]);
        $this->get(route('cobros.voucher', $id))->assertOk()->assertSee('Cuota 3:');
    }

    public function test_las_cuotas_deben_sumar_el_total_y_sin_cuotas_es_una_sola(): void
    {
        $this->alCreditoEnCuotas([['fecha' => now()->addDays(30)->toDateString(), 'monto' => 300], ['fecha' => now()->addDays(60)->toDateString(), 'monto' => 300]])
            ->assertJsonMissing(['estado' => 'success']);

        $id = (int) $this->alCreditoEnCuotas([])->assertJson(['estado' => 'success'])->json('id');
        $this->assertSame([[1, 900.0]], DB::table('cpe_cuotas')->where('IdCpe_cabecera', $id)->get()->map(fn ($c) => [(int) $c->numero, (float) $c->monto])->all());
    }

    public function test_el_total_de_la_linea_se_redondea_hasta_5_centimos(): void
    {
        $arroz = $this->producto('ARROZ A GRANEL', 11, 50);
        $datos = $this->venta([], '03');
        $datos['items'] = [['id' => $arroz->IdProducto, 'cantidad' => 2.35, 'precio' => 11, 'importe' => 25.90]];
        $datos['mon_med_pag'] = [25.90];
        $datos['paga'] = 25.90;

        $id = (int) $this->postJson(route('pv.registrar'), $datos)->assertJson(['estado' => 'success'])->json('id');

        $this->assertEquals(25.90, (float) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->value('ccaitv'));
        $linea = DB::table('cpe_detalle')->where('IdCpe_cabecera', $id)->first();
        $this->assertEquals([2.35, 11.0, 25.90], [(float) $linea->cdecan, (float) $linea->cdepuni, (float) $linea->cdevve], 'La cantidad y el precio quedan como se escribieron.');

        $datos['items'][0]['importe'] = 26.00; // 15 céntimos: ya no es redondeo
        $datos['mon_med_pag'] = [26.00];
        $datos['paga'] = 26.00;
        $this->postJson(route('pv.registrar'), $datos)->assertJsonMissing(['estado' => 'success']);
    }

    public function test_el_pv_tactil_vende_cantidades_con_decimales(): void
    {
        $albaca = $this->producto('ALBACA', 10, 50);
        $datos = $this->venta([[$albaca, 1.35]], '13');

        $id = (int) $this->postJson(route('pv.tactil.registrar'), $datos)->assertJson(['estado' => 'success'])->json('id');

        $this->assertEquals([1.35, 13.50], [(float) DB::table('cpe_detalle')->where('IdCpe_cabecera', $id)->value('cdecan'),
            (float) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->value('ccaitv')]);
    }

    public function test_sunat_recibe_el_distrito_oficial_del_ubigeo_aunque_la_sucursal_lo_tenga_mal_escrito(): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['ubigeo' => '160113',
            'departamento' => 'LORETO', 'provincia' => 'MAYNAS', 'distrito' => 'SAN JUAN BAUTIZTA']);
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('ARENA', 40, 10), 1]]), 'PV');

        $sunat = new SunatService(Empresa::findOrFail($this->usuario->IdEmpresa), EmpresaNegocio::findOrFail($this->sucursal));
        $direccion = (new \ReflectionMethod($sunat, 'construirComprobante'))->invoke($sunat, $this->cabecera($id))->getCompany()->getAddress();

        $this->assertSame(['LORETO', 'MAYNAS', 'SAN JUAN BAUTISTA'], [$direccion->getDepartamento(), $direccion->getProvincia(), $direccion->getDistrito()]);
    }
}
