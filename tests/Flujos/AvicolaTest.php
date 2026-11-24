<?php

namespace Tests\Flujos;

use App\Models\Producto;
use App\Support\Avicola;
use App\Support\AvicolaGranja;
use App\Support\AvicolaReportes;
use Illuminate\Support\Facades\DB;

/**
 * Avícola: venta por peso (pesadas − tara − aves muertas, precio del día), crédito y abonos, jabas, ingreso de pollo
 * comprado y de la granja, beneficio con rendimiento, lotes de granja, mermas y reportes.
 */
class AvicolaTest extends FlujoTestCase
{
    private Producto $vivo;

    protected function setUp(): void
    {
        parent::setUp();
        Avicola::crearTipicos($this->usuario);
        $this->vivo = Producto::where('id_empresa_negocio', $this->sucursal)->where('pronom', 'POLLO VIVO')->firstOrFail();
        DB::table('productos')->where('IdProducto', $this->vivo->IdProducto)->update(['costo' => 6]);
        DB::table('producto_stock')->insert(['IdProducto' => $this->vivo->IdProducto, 'id_almacen' => $this->almacen, 'id_empresa_negocio' => $this->sucursal,
            'stock' => 500, 'stock_inicial' => 500]);
        $this->vivo->refresh();
    }

    private function parte(string $nombre): Producto
    {
        return Producto::where('id_empresa_negocio', $this->sucursal)->where('pronom', $nombre)->firstOrFail();
    }

    /** 3 jabas (30 aves) que pesan 75 kg; con tara de 6.5 kg por jaba */
    private function pesaje(array $extra = []): array
    {
        return $extra + ['IdProducto' => $this->vivo->IdProducto, 'tdocod' => '13', 'tdicod' => '1', 'clinum' => '45678912', 'clinom' => 'PUESTO DOÑA ROSA',
            'pesadas' => [['jabas' => 2, 'aves' => 20, 'peso' => 50], ['jabas' => 1, 'aves' => 10, 'peso' => 25]], 'precio' => 8.5];
    }

    public function test_el_precio_del_dia_queda_como_precio_de_venta(): void
    {
        $this->post(route('avicola.precios'), ['precios' => [$this->vivo->IdProducto => '8.50']])->assertSessionHas('success');

        $this->assertSame('8.50', (string) DB::table('productos')->where('IdProducto', $this->vivo->IdProducto)->value('propun'));
        $this->assertSame(1, DB::table('avi_precios')->where('IdProducto', $this->vivo->IdProducto)->count());
    }

    public function test_la_venta_por_peso_cobra_el_neto_y_las_aves_muertas_son_merma(): void
    {
        $this->post(route('avicola.precios'), ['precios' => [$this->vivo->IdProducto => '8.50']]);

        $r = $this->postJson(route('avicola.vender'), $this->pesaje(['aves_muertas' => 1, 'peso_muertas' => 2.5, 'jabas_devueltas' => 0]))->assertJson(['ok' => true]);

        // 75 kg bruto − 3 jabas × 6.5 kg − 2.5 kg de la muerta = 53 kg × S/ 8.50
        $venta = DB::table('avi_ventas')->where('id', $r->json('venta'))->first();
        $this->assertSame(['75.000', '19.500', '53.000', '450.50'], [(string) $venta->peso_bruto, (string) $venta->tara, (string) $venta->peso_neto, (string) $venta->total]);
        $this->assertSame('450.50', (string) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $r->json('id'))->value('ccaitv'));
        $this->assertSame(53.0, (float) DB::table('cpe_detalle')->where('IdCpe_cabecera', $r->json('id'))->value('cdecan'));
        $this->assertEqualsWithDelta(500 - 53 - 2.5, $this->stock($this->vivo), 0.001, 'Sale lo vendido y la muerta.');
        $this->assertSame(1, DB::table('avi_mermas')->where('tipo', 'MORTALIDAD')->where('origen', 'VENTA')->where('sale_stock', 1)->count());
        $clicod = (int) $venta->clicod;
        $this->assertSame([$clicod => 3], Avicola::saldoJabas($this->sucursal, $clicod), 'Se lleva 3 jabas.');
    }

    public function test_se_paga_con_varios_medios_y_se_guarda_como_proforma(): void
    {
        $yape = DB::table('medios_pagos')->insertGetId(['nom_med_pag' => 'YAPE', 'IdEmpresa' => $this->usuario->IdEmpresa,
            'id_empresa_negocio' => $this->sucursal, 'predeterminado' => '0']);

        // 55.5 kg × 8.50 = 471.75: 300 en efectivo y 171.75 por Yape
        $r = $this->postJson(route('avicola.vender'), $this->pesaje(['id_med_pag' => [$this->efectivo, $yape], 'mon_med_pag' => [300, 171.75],
            'op_med_pag' => [null, '889911']]))->assertJson(['ok' => true]);
        $medios = DB::table('venta_medio_pago')->where('IdCpe_cabecera', $r->json('id'))->orderBy('monto', 'desc')->get();
        $this->assertSame([['300.00', null], ['171.75', '889911']], $medios->map(fn ($m) => [(string) $m->monto, $m->num_operacion])->all());
        $this->postJson(route('avicola.vender'), $this->pesaje(['id_med_pag' => [$this->efectivo, $yape], 'mon_med_pag' => [300, 100]]))
            ->assertJson(['ok' => false]);

        // La proforma no emite ni descuenta stock
        $stock = $this->stock($this->vivo);
        $p = $this->postJson(route('avicola.proforma'), $this->pesaje())->assertJson(['ok' => true]);
        $proforma = DB::table('proformas')->where('id_proforma', $p->json('id'))->first();
        $this->assertSame(['AVICOLA', '471.75', 'PENDIENTE'], [$proforma->origen, (string) $proforma->total, $proforma->estado]);
        $this->assertStringContainsString('3 JABAS · 30 AVES', (string) DB::table('proforma_detalle')->where('id_proforma', $proforma->id_proforma)->value('descripcion'));
        $this->assertEqualsWithDelta($stock, $this->stock($this->vivo), 0.001);
    }

    public function test_al_credito_va_a_su_cuenta_y_el_abono_paga_primero_lo_mas_antiguo(): void
    {
        $this->postJson(route('avicola.vender'), $this->pesaje(['credito' => true]))->assertJson(['ok' => true]);                          // 55.5 kg × 8.50 = 471.75
        $this->postJson(route('avicola.vender'), $this->pesaje(['credito' => true, 'jabas_devueltas' => 2]))->assertJson(['ok' => true]); // 471.75
        $clicod = (int) DB::table('avi_ventas')->value('clicod');
        $this->assertSame('943.50', number_format((float) Avicola::cuentaCorriente($this->sucursal, $clicod)->first()->saldo, 2, '.', ''));
        $this->assertSame([$clicod => 4], Avicola::saldoJabas($this->sucursal, $clicod), '3 + 3 − 2 devueltas.');

        $this->post(route('avicola.abonar', $clicod), ['monto' => 500, 'medio' => $this->efectivo])->assertSessionHas('success');

        $saldos = DB::table('cuentas_cobrar')->where('clicod', $clicod)->orderBy('cue_cob_id')->pluck('saldo')->map(fn ($s) => (string) $s)->all();
        $this->assertSame(['0.00', '443.50'], $saldos, 'La primera queda pagada y a la segunda le quedan 443.50.');
        $this->postJson(route('avicola.vender'), $this->pesaje(['credito' => true, 'clinum' => '00000000', 'clinom' => 'VENTA AL PORTADOR']))
            ->assertJson(['ok' => false, 'mensaje' => 'Para vender al crédito elige al cliente (DNI o RUC).']);
        $this->post(route('avicola.abonar', $clicod), ['monto' => 1000, 'medio' => $this->efectivo])->assertSessionHas('error');
    }

    public function test_el_pollo_comprado_entra_por_su_peso_neto_y_las_muertas_no_se_pagan(): void
    {
        $this->post(route('avicola.ingresar'), ['IdProducto' => $this->vivo->IdProducto, 'prov_num' => '20100070970', 'prov_nom' => 'GRANJA EL GALPÓN SAC',
            'tdocod' => '01', 'serie' => 'F001', 'numero' => '345', 'costo_kg' => 6.2, 'tip_igv' => '20',
            'pesadas' => [['jabas' => 10, 'aves' => 100, 'peso' => 315]], 'aves_muertas' => 2, 'peso_muertas' => 5])->assertSessionHas('success');

        // 315 − 65 de tara − 5 de las muertas = 245 kg × 6.20
        $i = DB::table('avi_ingresos')->first();
        $this->assertSame(['245.000', '1519.00'], [(string) $i->peso_neto, (string) $i->total]);
        $this->assertSame('1519.00', (string) DB::table('compras_cabecera')->where('com_cab_id', $i->com_cab_id)->value('total_com'));
        $this->assertEqualsWithDelta(745, $this->stock($this->vivo), 0.001);
        $this->assertSame(1, DB::table('avi_mermas')->where('origen', 'INGRESO')->where('aves', 2)->where('sale_stock', 0)->count());
    }

    public function test_el_beneficio_saca_el_vivo_mete_las_partes_y_calcula_el_rendimiento(): void
    {
        $pelado = $this->parte('POLLO BENEFICIADO');
        $menudencia = $this->parte('MENUDENCIA (HÍGADO Y MOLLEJA)');

        $this->post(route('avicola.beneficiar'), ['IdProducto' => $this->vivo->IdProducto, 'aves' => 40, 'kg_vivo' => 100,
            'partes' => [['IdProducto' => $pelado->IdProducto, 'kg' => 72], ['IdProducto' => $menudencia->IdProducto, 'kg' => 6]]])->assertSessionHas('success');

        $b = DB::table('avi_beneficios')->first();
        $this->assertSame(['78.00', '600.00'], [(string) $b->rendimiento, (string) $b->costo_total]);
        $this->assertEqualsWithDelta(400, $this->stock($this->vivo), 0.001);
        $this->assertEqualsWithDelta(72, $this->stock($pelado), 0.001);
        // S/ 600 del vivo repartidos en 78 kg: el costo del kilo de las partes
        $this->assertEqualsWithDelta(7.69, (float) DB::table('productos')->where('IdProducto', $pelado->IdProducto)->value('costo'), 0.005);
        $this->assertSame('22.000', (string) DB::table('avi_mermas')->where('tipo', 'BENEFICIO')->value('kg'));

        $this->post(route('avicola.beneficiar'), ['IdProducto' => $this->vivo->IdProducto, 'kg_vivo' => 10,
            'partes' => [['IdProducto' => $pelado->IdProducto, 'kg' => 12]]])->assertSessionHas('error');
    }

    public function test_el_lote_de_granja_lleva_su_mortalidad_conversion_y_costo_y_entra_a_la_venta(): void
    {
        $lote = AvicolaGranja::crear($this->usuario, ['codigo' => 'L1', 'tipo' => 'ENGORDE', 'fecha_ingreso' => '2026-09-20', 'aves_iniciales' => 1000, 'costo_ave' => 2.5]);
        $this->post(route('avicola.lote.registro', $lote), ['fecha' => '2026-10-10', 'mortalidad' => 20, 'alimento_kg' => 2000, 'costo_alimento' => 3600])->assertSessionHas('success');
        $this->post(route('avicola.lote.registro', $lote), ['fecha' => '2026-10-29', 'mortalidad' => 10, 'alimento_kg' => 1500, 'costo_alimento' => 2700,
            'otros_gastos' => 200, 'peso_promedio' => 2.5])->assertSessionHas('success');

        $r = AvicolaGranja::resumen(DB::table('avi_lotes')->find($lote));
        $this->assertSame([40, 970, 3.0, 9000.0], [$r['edad_dias'], $r['aves_vivas'], $r['mortalidad_pct'], $r['costo_total']]);
        // 3500 kg de alimento para 970 × 2.5 = 2425 kg en pie
        $this->assertSame(1.44, $r['conversion']);
        $this->assertSame(2, DB::table('avi_mermas')->where('origen', 'GRANJA')->count());

        // Salen 100 aves a la venta: entran al stock con el costo del kilo del lote
        $this->post(route('avicola.ingresar'), ['IdProducto' => $this->vivo->IdProducto, 'lote_id' => $lote,
            'pesadas' => [['jabas' => 10, 'aves' => 100, 'peso' => 315]]])->assertSessionHas('success');
        $this->assertEqualsWithDelta(750, $this->stock($this->vivo), 0.001);
        $this->assertSame(870, AvicolaGranja::resumen(DB::table('avi_lotes')->find($lote))['aves_vivas']);
        $this->assertNull(DB::table('avi_ingresos')->value('com_cab_id'), 'De la granja propia no es una compra.');
    }

    public function test_la_merma_manual_saca_del_stock_y_al_anularla_vuelve(): void
    {
        $this->post(route('avicola.mermas.guardar'), ['tipo' => 'DESHIDRATACION', 'IdProducto' => $this->vivo->IdProducto, 'kg' => 12.5])->assertSessionHas('success');
        $this->assertEqualsWithDelta(487.5, $this->stock($this->vivo), 0.001);
        $this->assertSame('75.00', (string) DB::table('avi_mermas')->value('costo'));

        $this->post(route('avicola.mermas.anular', DB::table('avi_mermas')->value('id')))->assertSessionHas('success');
        $this->assertEqualsWithDelta(500, $this->stock($this->vivo), 0.001);
    }

    public function test_los_reportes_cuentan_las_ventas_y_no_las_anuladas(): void
    {
        $uno = $this->postJson(route('avicola.vender'), $this->pesaje())->json();
        $this->postJson(route('avicola.vender'), $this->pesaje(['credito' => true]))->assertJson(['ok' => true]);
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $uno['id'])->update(['ccabaj' => 'ANULADA']);
        $this->post(route('avicola.ingresar'), ['IdProducto' => $this->vivo->IdProducto, 'prov_num' => '20100070970', 'prov_nom' => 'GRANJA EL GALPÓN SAC',
            'costo_kg' => 6, 'pesadas' => [['jabas' => 10, 'aves' => 100, 'peso' => 315]]]);

        $r = AvicolaReportes::generar($this->sucursal, now()->subDay(), now());

        $this->assertSame(1, (int) $r['totVentas']->ventas);
        $this->assertSame(['8.50', '6.00', '2.50'], [number_format($r['precioProm'], 2), number_format($r['costoProm'], 2), number_format($r['margenKg'], 2)]);
        $this->assertSame('471.75', number_format((float) $r['saldos']->first()->saldo, 2, '.', ''));
        $this->get(route('avicola.reportes.excel'))->assertOk();
    }

    public function test_todas_las_pantallas_abren(): void
    {
        $this->postJson(route('avicola.vender'), $this->pesaje(['credito' => true]))->assertJson(['ok' => true]);
        $lote = AvicolaGranja::crear($this->usuario, ['codigo' => 'P1', 'tipo' => 'POSTURA', 'aves_iniciales' => 500]);
        $clicod = (int) DB::table('avi_ventas')->value('clicod');

        foreach ([route('avicola.index'), route('avicola.clientes'), route('avicola.cliente', $clicod), route('avicola.ingresos'), route('avicola.beneficio'),
            route('avicola.granja'), route('avicola.lote', $lote), route('avicola.mermas'), route('avicola.reportes'), route('avicola.configuracion')] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('avicola.index'))->assertSee('PUESTO DOÑA ROSA')->assertSee('Precio del día');
    }
}
