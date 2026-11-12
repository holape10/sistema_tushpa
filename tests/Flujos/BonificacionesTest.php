<?php

namespace Tests\Flujos;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Support\Descuentos;
use App\Support\Sunat\SunatService;
use Illuminate\Support\Facades\DB;

/** Bonificaciones del Punto de venta: la línea marcada va gratis, descuenta stock y ante SUNAT es operación gratuita */
class BonificacionesTest extends FlujoTestCase
{
    private function conBonificaciones(bool $si = true): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['permite_bonificaciones' => $si, 'permite_cortesias' => 0]);
    }

    /** 20 de canela se cobran y 2 de ajonjolí van bonificados */
    private function ventaConBonificacion(): array
    {
        $canela = $this->producto('CANELA CAJA X 10KG', 5.8, 50);
        $ajonjoli = $this->producto('AJONJOLI X 50KG', 6, 10);
        $datos = $this->venta([[$canela, 20], [$ajonjoli, 2]]);
        $datos['items'][1]['bonificacion'] = true;
        $datos['mon_med_pag'] = [116.0];
        $datos['paga'] = 116.0;

        return [$datos, $canela, $ajonjoli];
    }

    public function test_la_linea_bonificada_va_gratis_descuenta_stock_y_va_a_sunat_como_gratuita(): void
    {
        $this->afectacion('20');
        $this->conBonificaciones();
        [$datos, , $ajonjoli] = $this->ventaConBonificacion();

        $id = (int) $this->postJson(route('pv.registrar'), $datos)->assertOk()->assertJson(['estado' => 'success'])->json('id');

        $this->assertEquals(116.0, (float) $this->cabecera($id)->ccaitv, 'Solo se cobra la canela.');
        $bonif = DB::table('cpe_detalle')->where('IdCpe_cabecera', $id)->where('IdProducto', $ajonjoli->IdProducto)->first();
        $this->assertSame([1, Descuentos::MOTIVO_BONIFICACION, '21', 0.0, 6.0], [(int) $bonif->cortesia, $bonif->cortesia_motivo, $bonif->tigcod,
            (float) $bonif->cdevve, (float) $bonif->precio_lista]);
        $this->assertEquals(8.0, $this->stock($ajonjoli), 'La bonificación sale del almacén.');

        $sunat = new SunatService(Empresa::findOrFail($this->usuario->IdEmpresa), EmpresaNegocio::findOrFail($this->sucursal));
        $doc = (new \ReflectionMethod($sunat, 'construirComprobante'))->invoke($sunat, $this->cabecera($id));
        $this->assertEquals(12.0, $doc->getMtoOperGratuitas(), 'Valor referencial: 2 × S/ 6.');
        $this->assertEquals(116.0, $doc->getMtoImpVenta());

        $this->get(route('cobros.voucher', $id))->assertOk()->assertSee('BONIFICACIÓN')->assertDontSee('CORTESÍA');
        $this->get(route('cobros.voucher', ['id' => $id, 'formato' => 'A4']))->assertOk()->assertSee('BONIFICACIÓN');
    }

    public function test_sin_activar_no_se_bonifica_y_la_cortesia_no_puede_pasar_por_bonificacion(): void
    {
        $this->conBonificaciones(false);
        [$datos] = $this->ventaConBonificacion();
        $this->postJson(route('pv.registrar'), $datos)->assertJsonPath('mensaje', 'Esta sucursal no tiene activadas las bonificaciones.');

        // Con bonificaciones activas, una "cortesía" sigue necesitando su propio permiso
        $this->conBonificaciones();
        [$datos] = $this->ventaConBonificacion();
        unset($datos['items'][1]['bonificacion']);
        $datos['items'][1] += ['cortesia' => true, 'motivo_cortesia' => 'BONIFICACIÓN'];
        $this->postJson(route('pv.registrar'), $datos)->assertJsonPath('mensaje', 'Esta sucursal no tiene activadas las cortesías.');
    }

    public function test_la_opcion_se_activa_en_la_sucursal_y_sale_la_columna_en_el_punto_de_venta(): void
    {
        $this->conBonificaciones();

        $this->get(route('pv.index'))->assertOk()->assertSee('bonificaciones: true', false)->assertSee('Bonif.');
    }
}
