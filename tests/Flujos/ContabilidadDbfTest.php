<?php

namespace Tests\Flujos;

use App\Support\Contabilidad\Dbf;
use App\Support\Notas;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Otros › Contabilidad DBF: el tienfact.dbf de ventas (estructura del SIRE) armado con los comprobantes del sistema */
class ContabilidadDbfTest extends FlujoTestCase
{
    public function test_el_tienfact_lleva_las_ventas_del_mes_como_el_del_sire(): void
    {
        $plato = $this->producto('POLLO A LA BRASA', 59, 50);
        $boleta = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 2]]), 'PV');
        $factura = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]], '01'), 'PV');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $factura)->update(['est_sunat' => 'ACEPTADO']);
        $nota = Notas::emitir($this->usuario, $factura, ['tdocod' => '07', 'tipnot' => '01', 'motivo' => 'ANULACION DE LA OPERACION']);
        $anulada = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]]), 'PV');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $anulada)->update(['ccabaj' => now()->toDateTimeString()]);
        $rechazada = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]]), 'PV');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $rechazada)->update(['est_sunat' => 'RECHAZADO']);
        VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]], '13'), 'PV');   // nota de venta: no va

        $r = $this->get(route('contabilidad_dbf.descargar', ['mes' => now()->format('Y-m')]))->assertOk();
        $this->assertStringContainsString('filename="tienfact.dbf"', $r->headers->get('Content-Disposition'));
        $filas = collect(Dbf::leer($r->getContent()))->keyBy(fn ($f) => trim($f['SERIEDOC']).'-'.(int) $f['NUM_DOC']);
        $clave = fn (int $id) => ($c = $this->cabecera($id))->serdoc.'-'.(int) $c->numdoc;

        $this->assertEqualsCanonicalizing([$clave($boleta), $clave($factura), $clave($nota), $clave($anulada)], $filas->keys()->all());
        $this->assertCount(4, $filas, 'Boleta, factura, nota de crédito y la anulada; ni la rechazada ni la nota de venta');

        $b = $filas[$clave($boleta)];
        $cab = $this->cabecera($boleta);
        $this->assertSame(['03', (float) $cab->ccaitv, (float) $cab->ccatvg, (float) $cab->ccaigv, '121201'],
            [trim($b['COD_DOC']), (float) $b['TOTAL'], (float) $b['GRAVADA'], (float) $b['IGV'], trim($b['CTACARGO'])]);
        $n = $filas[$clave($nota)];
        $this->assertSame(['07', -(float) $this->cabecera($nota)->ccaitv, trim($this->cabecera($factura)->serdoc)],
            [trim($n['COD_DOC']), (float) $n['TOTAL'], trim($n['SER_DOC_C'])], 'La nota de crédito va en negativo, con su factura');
        $a = $filas[$clave($anulada)];
        $this->assertSame([1, 0.0], [(int) $a['ANULADA'], (float) $a['TOTAL']], 'La anulada va marcada y en cero');
    }

    public function test_la_pantalla_muestra_el_mes_y_solo_el_administrador_descarga(): void
    {
        VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('GASEOSA', 3), 1]]), 'PV');
        $this->get(route('contabilidad_dbf.index', ['mes' => now()->format('Y-m')]))->assertOk()->assertSee('Descargar tienfact.dbf')->assertSee('VENTA AL PORTADOR');

        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        $this->actingAs($cajero)->get(route('contabilidad_dbf.descargar'))->assertForbidden();
    }
}
