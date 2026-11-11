<?php

namespace Tests\Flujos;

use App\Support\Contingencia;
use App\Support\Sunat\SunatService;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Contingencia: la boleta o factura hecha en papel (sin luz ni internet) se pasa al sistema con su serie y número */
class ContingenciaTest extends FlujoTestCase
{
    private function papel(string $serie, int $numero, string $fecha, string $tdocod = '03'): array
    {
        return $this->venta([[$this->producto('ARENA '.uniqid(), 40, 10), 2]], $tdocod, ['fecEmi' => $fecha, 'contingencia' => ['serie' => $serie, 'numero' => $numero]]);
    }

    public function test_el_papel_entra_con_su_serie_numero_y_fecha_sin_tocar_el_correlativo_ni_ir_a_sunat(): void
    {
        $id = VentaDirecta::registrar($this->usuario, $this->papel('1', 125, '2026-10-25'), 'PV');
        $cab = $this->cabecera($id);

        $this->assertSame(['0001', 125, '2026-10-25', Contingencia::ESTADO], [$cab->serdoc, (int) $cab->numdoc, $cab->ccafem, $cab->est_sunat]);
        $this->assertSame(0, (int) DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->value('BnuEmpresa'), 'El correlativo electrónico no se mueve.');
        $this->assertEquals(80.0, (float) DB::table('venta_medio_pago')->where('IdCpe_cabecera', $id)->sum('monto'), 'Entra a la caja como cualquier venta.');
        $this->assertCount(0, SunatService::paraUsuario($this->usuario)->pendientesResumen('2026-10-25'), 'No va en el resumen diario.');
    }

    public function test_no_se_repite_ni_acepta_series_de_letras_ni_fechas_fuera_de_plazo(): void
    {
        VentaDirecta::registrar($this->usuario, $this->papel('0001', 7, '2026-10-30'), 'PV');

        foreach ([['0001', 7, '2026-10-30', 'ya está registrado'], ['B001', 8, '2026-10-30', 'serie de contingencia'], ['0001', 9, '2026-08-01', 'días atrás']] as [$serie, $num, $fecha, $error]) {
            try {
                VentaDirecta::registrar($this->usuario, $this->papel($serie, $num, $fecha), 'PV');
                $this->fail("Aceptó {$serie}-{$num} del {$fecha}.");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString($error, $e->getMessage());
            }
        }
    }

    public function test_un_papel_equivocado_se_anula_aqui_sin_comunicacion_de_baja(): void
    {
        $id = VentaDirecta::registrar($this->usuario, $this->papel('0001', 30, '2026-10-30'), 'PV');

        $this->postJson(route('ventas.anular', $id), ['motivo' => 'ERROR AL PASAR EL PAPEL'])->assertJson(['success' => true]);
        $this->assertNotNull($this->cabecera($id)->ccabaj);
    }

    public function test_la_lista_del_mes_y_su_excel(): void
    {
        VentaDirecta::registrar($this->usuario, $this->papel('0002', 15, '2026-10-20', '01'), 'PV');

        $this->get(route('contingencia.index', ['mes' => '2026-10']))->assertOk()->assertSee('0002-00000015');
        $this->get(route('contingencia.excel', ['mes' => '2026-10']))->assertOk()->assertDownload('contingencia_202610.xlsx');
    }
}
