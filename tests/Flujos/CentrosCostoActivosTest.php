<?php

namespace Tests\Flujos;

use App\Support\ActivosFijos;
use App\Support\CentrosCosto;
use App\Support\Contabilidad\Contabilidad;
use App\Support\Gastos;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Centros de costo (resultados por centro y destino a la clase 9) y activos fijos (depreciación, alta y baja) */
class CentrosCostoActivosTest extends FlujoTestCase
{
    private string $ruc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ruc = (string) $this->usuario->IdEmpresa;
        Contabilidad::asegurar($this->ruc);
    }

    private function categoriaGasto(): int
    {
        return (int) DB::table('gasto_categorias')->insertGetId(['IdEmpresa' => $this->ruc, 'nombre' => 'ALQUILER', 'cuenta_contable' => '659']);
    }

    private function gasto(float $base, ?int $centro = null, string $fecha = '2026-10-10'): int
    {
        return Gastos::guardar($this->usuario, ['fecha' => $fecha, 'tdocod' => '01', 'serie' => 'E001', 'numero' => (string) random_int(1, 99999),
            'prov_doc' => '20100070970', 'prov_nombre' => 'PROVEEDOR SAC', 'categoria_id' => $this->categoriaGasto(), 'moneda' => 'PEN',
            'base' => $base, 'igv' => round($base * 0.18, 2), 'forma_pago' => 'CREDITO', 'credito_fiscal' => true, 'centro_costo_id' => $centro]);
    }

    private function laptop(array $mas = []): int
    {
        return ActivosFijos::guardar($this->usuario, $mas + ['codigo' => 'AF-001', 'descripcion' => 'Laptop Lenovo', 'categoria' => 'COMPUTO',
            'fecha_adquisicion' => '2026-08-10', 'fecha_uso' => '2026-08-15', 'costo' => 3000]);
    }

    private function asiento(int $id): array
    {
        return DB::table('conta_asiento_detalle')->where('asiento_id', $id)->orderBy('id')->get()
            ->map(fn ($d) => [(string) $d->cuenta, (float) $d->debe, (float) $d->haber])->all();
    }

    // ------------------------------------------------------------------ centros de costo

    public function test_cada_local_tiene_su_centro_y_el_gasto_va_ahi_por_defecto(): void
    {
        $this->assertSame(1, CentrosCosto::crearDeLocales($this->ruc));
        $local = CentrosCosto::deSucursal($this->ruc, $this->sucursal);

        $id = $this->gasto(100);

        $this->assertSame($local, (int) DB::table('gastos')->where('id', $id)->value('centro_costo_id'));
        $this->assertSame(0, CentrosCosto::crearDeLocales($this->ruc), 'No se repite.');
    }

    public function test_el_resultado_de_cada_centro_con_ventas_costo_y_gastos(): void
    {
        CentrosCosto::crearDeLocales($this->ruc);
        $marketing = CentrosCosto::guardar($this->ruc, ['codigo' => 'MKT', 'nombre' => 'Marketing', 'tipo' => 'AREA', 'cuenta_destino' => '95']);
        $plato = $this->producto('LOMO', 59, 10);
        DB::table('productos')->where('IdProducto', $plato->IdProducto)->update(['costo' => 20]);
        VentaDirecta::registrar($this->usuario, $this->venta([[$plato->fresh(), 2]], '03'), 'PV');
        $this->gasto(100);                 // al local
        $this->gasto(50, $marketing);      // a marketing

        $r = CentrosCosto::resultados($this->ruc, '2026-10-01', '2026-10-31')->keyBy('codigo');

        $local = $r[DB::table('centros_costo')->where('tipo', 'LOCAL')->value('codigo')];
        $this->assertSame([100.0, 40.0, 100.0, -40.0], [$local->ventas, $local->costo, $local->gastos, $local->resultado], '2 lomos de 59 = 100 sin IGV; costo 2 × 20.');
        $this->assertSame([0.0, 50.0, -50.0], [$r['MKT']->ventas, $r['MKT']->gastos, $r['MKT']->resultado]);
    }

    public function test_con_el_destino_activado_el_gasto_va_a_su_cuenta_9_contra_la_79(): void
    {
        DB::table('conta_config')->where('IdEmpresa', $this->ruc)->update(['asiento_destino' => 1]);
        $admin = CentrosCosto::guardar($this->ruc, ['codigo' => 'ADM', 'nombre' => 'Administración', 'tipo' => 'AREA', 'cuenta_destino' => '94']);
        $ventas = CentrosCosto::guardar($this->ruc, ['codigo' => 'VTA', 'nombre' => 'Ventas', 'tipo' => 'AREA', 'cuenta_destino' => '95']);
        $this->gasto(100, $admin);
        $this->gasto(60, $ventas);

        Contabilidad::centralizarCompras($this->ruc, '202610');

        $destino = DB::table('conta_asientos')->where('IdEmpresa', $this->ruc)->where('glosa', 'like', 'DESTINO DE GASTOS%')->value('id');
        $this->assertSame([['94', 100.0, 0.0], ['95', 60.0, 0.0], ['79', 0.0, 160.0]], $this->asiento($destino));
    }

    public function test_un_local_tiene_un_solo_centro(): void
    {
        CentrosCosto::crearDeLocales($this->ruc);

        $this->expectExceptionMessage('Ese local ya tiene su centro de costo.');
        CentrosCosto::guardar($this->ruc, ['codigo' => 'OTRO', 'nombre' => 'Otro', 'tipo' => 'LOCAL', 'id_empresa_negocio' => $this->sucursal, 'cuenta_destino' => '95']);
    }

    public function test_las_pantallas_de_centros_de_costo(): void
    {
        $this->post(route('centros_costo.locales'))->assertSessionHas('success');
        $this->post(route('centros_costo.store'), ['codigo' => 'MKT', 'nombre' => 'Marketing', 'tipo' => 'AREA', 'cuenta_destino' => '95'])
            ->assertRedirect(route('centros_costo.index'));
        $this->post(route('centros_costo.store'), ['codigo' => 'MKT', 'nombre' => 'Otro', 'tipo' => 'AREA', 'cuenta_destino' => '95'])
            ->assertSessionHas('error', 'Ya hay un centro de costo con el código MKT.');

        $this->get(route('centros_costo.index'))->assertOk()->assertSee('MARKETING');
        $this->get(route('centros_costo.reporte'))->assertOk()->assertSee('Resultado por centro de costo');
        $this->get(route('centros_costo.excel'))->assertOk()->assertDownload();
        $this->get(route('gastos.index'))->assertOk()->assertSee('Centro de costo');
    }

    // ------------------------------------------------------------------ activos fijos

    public function test_las_pantallas_de_activos_fijos(): void
    {
        $this->post(route('activos.store'), ['codigo' => 'af-010', 'descripcion' => 'Refrigeradora', 'categoria' => 'MAQUINARIA', 'fecha_adquisicion' => '2026-09-01',
            'fecha_uso' => '2026-09-01', 'costo' => 2400, 'tasa' => 10])->assertSessionHasNoErrors()->assertSessionHas('success');
        $id = (int) DB::table('activos_fijos')->where('codigo', 'AF-010')->value('id');
        $this->assertSame('333', DB::table('activos_fijos')->where('id', $id)->value('cuenta_activo'), 'La cuenta sale de la categoría.');

        $this->post(route('activos.depreciar'), ['mes' => '2026-10'])->assertSessionHas('success');
        $this->assertSame(40.0, (float) DB::table('activos_depreciacion')->where('activo_id', $id)->value('monto'), 'Setiembre y octubre: 2 × 20.');

        $this->get(route('activos.index'))->assertOk()->assertSee('REFRIGERADORA');
        $this->get(route('activos.show', $id))->assertOk()->assertSee('Octubre 2026');
        $this->get(route('activos.create'))->assertOk();
        $this->get(route('activos.edit', $id))->assertOk()->assertSee('ya no se cambian');
        $this->get(route('activos.excel'))->assertOk()->assertDownload();

        $this->post(route('activos.baja', $id), ['fecha' => '2026-11-05', 'motivo' => 'SE VENDIÓ'])->assertSessionHasErrors('fecha');
        $this->post(route('activos.baja', $id), ['fecha' => '2026-10-30', 'motivo' => 'SE VENDIÓ'])->assertSessionHas('error');
        $this->post(route('activos.deshacer'), ['periodo' => '202610'])->assertSessionHas('success');
        $this->post(route('activos.baja', $id), ['fecha' => '2026-10-30', 'motivo' => 'SE VENDIÓ'])->assertSessionHas('success');
        $this->assertSame('BAJA', DB::table('activos_fijos')->where('id', $id)->value('estado'));
    }

    public function test_sin_la_opcion_no_entra_a_activos_ni_centros(): void
    {
        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);

        $this->actingAs($cajero)->get(route('activos.index'))->assertForbidden();
        $this->actingAs($cajero)->get(route('centros_costo.index'))->assertForbidden();
        $this->actingAs($cajero)->post(route('activos.depreciar'), ['mes' => '2026-10'])->assertForbidden();
    }

    public function test_la_depreciacion_mensual_lleva_su_asiento_y_su_destino(): void
    {
        DB::table('conta_config')->where('IdEmpresa', $this->ruc)->update(['asiento_destino' => 1]);
        $admin = CentrosCosto::guardar($this->ruc, ['codigo' => 'ADM', 'nombre' => 'Administración', 'tipo' => 'AREA', 'cuenta_destino' => '94']);
        $id = $this->laptop(['centro_costo_id' => $admin]);

        $r = ActivosFijos::depreciar($this->usuario, '202608');

        $this->assertSame(62.5, $r['total'], '3000 × 25% ÷ 12; desde el mes en que se empieza a usar.');
        $this->assertSame([['681', 62.5, 0.0], ['3913', 0.0, 62.5], ['94', 62.5, 0.0], ['79', 0.0, 62.5]], $this->asiento($r['asiento']));
        $this->assertSame(62.5, ActivosFijos::acumulado(ActivosFijos::activo($this->ruc, $id)));
    }

    public function test_un_activo_registrado_tarde_se_pone_al_dia_y_nunca_pasa_del_costo(): void
    {
        $this->laptop();
        $chico = ActivosFijos::guardar($this->usuario, ['codigo' => 'AF-002', 'descripcion' => 'Impresora', 'categoria' => 'COMPUTO',
            'fecha_adquisicion' => '2026-01-05', 'fecha_uso' => '2026-01-05', 'costo' => 100, 'depreciacion_inicial' => 99]);

        $r = ActivosFijos::depreciar($this->usuario, '202610');

        $this->assertSame(187.5, (float) DB::table('activos_depreciacion')->where('periodo', '202610')->where('activo_id', '!=', $chico)->value('monto'), 'Agosto, setiembre y octubre juntos.');
        $this->assertSame(1.0, (float) DB::table('activos_depreciacion')->where('activo_id', $chico)->value('monto'), 'Solo le faltaba 1 sol para llegar a su costo.');
        $this->assertSame(188.5, $r['total']);

        // Rehacer el último mes da lo mismo: no duplica
        $otraVez = ActivosFijos::depreciar($this->usuario, '202610');
        $this->assertSame(188.5, $otraVez['total']);
        $this->assertSame(2, DB::table('activos_depreciacion')->where('periodo', '202610')->count());
        $this->assertFalse(DB::table('conta_asientos')->where('id', $r['asiento'])->exists(), 'El asiento anterior se reemplazó.');
    }

    public function test_solo_se_rehace_o_deshace_el_ultimo_mes(): void
    {
        $this->laptop();
        ActivosFijos::depreciar($this->usuario, '202609');
        $octubre = ActivosFijos::depreciar($this->usuario, '202610');
        $this->assertSame(62.5, $octubre['total']);

        try {
            ActivosFijos::depreciar($this->usuario, '202609');
            $this->fail('Rehizo un mes que no es el último.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('solo se rehace el último mes', $e->getMessage());
        }
        ActivosFijos::deshacerMes($this->usuario, '202610');
        $this->assertFalse(DB::table('conta_asientos')->where('id', $octubre['asiento'])->exists());
        $this->assertSame(0, DB::table('activos_depreciacion')->where('periodo', '202610')->count());
    }

    public function test_la_baja_cancela_lo_depreciado_y_manda_el_resto_a_gasto(): void
    {
        $id = $this->laptop();
        ActivosFijos::depreciar($this->usuario, '202609');      // agosto + setiembre = 125

        ActivosFijos::darDeBaja($this->usuario, $id, '2026-10-20', 'SE MALOGRÓ');

        $a = ActivosFijos::activo($this->ruc, $id);
        $this->assertSame([['3913', 125.0, 0.0], ['659', 2875.0, 0.0], ['3361', 0.0, 3000.0]], $this->asiento((int) $a->asiento_baja_id));
        $this->assertSame(0.0, ActivosFijos::depreciar($this->usuario, '202610')['total'], 'Dado de baja ya no se deprecia.');
    }

    public function test_alta_con_asiento_y_tasa_maxima_de_sunat(): void
    {
        $id = $this->laptop(['contrapartida_alta' => '469']);

        $this->assertSame([['3361', 3000.0, 0.0], ['469', 0.0, 3000.0]], $this->asiento((int) ActivosFijos::activo($this->ruc, $id)->asiento_alta_id));

        $this->expectExceptionMessage('va de 0% a 25%');
        $this->laptop(['codigo' => 'AF-009', 'tasa' => 30]);
    }

    public function test_con_depreciacion_procesada_ya_no_se_cambia_el_costo(): void
    {
        $id = $this->laptop();
        ActivosFijos::depreciar($this->usuario, '202608');

        $this->expectExceptionMessage('ya no se cambian');
        ActivosFijos::guardar($this->usuario, ['codigo' => 'AF-001', 'descripcion' => 'Laptop Lenovo', 'categoria' => 'COMPUTO',
            'fecha_adquisicion' => '2026-08-10', 'fecha_uso' => '2026-08-15', 'costo' => 3500], $id);
    }
}
