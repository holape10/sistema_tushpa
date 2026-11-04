<?php

namespace Tests\Flujos;

use App\Support\ComprasDetalle;
use App\Support\Contabilidad\Dbf;
use App\Support\Contabilidad\SireDbf;
use App\Support\Sunat\CompraXml;
use App\Support\Sunat\Sire;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Unit\CompraXmlTest;

/** La propuesta del SIRE revisada por el contador y descargada para su sistema en FoxPro (almaingr / tienfact) */
class SireDbfTest extends FlujoTestCase
{
    private const RCE = 'RUC|Apellidos y Nombres o Razón social|Periodo|CAR SUNAT|Fecha de emisión|Fecha Vcto/Pago|Tipo CP/Doc.|Serie del CDP|Año|Nro CP o Doc. Nro Inicial (Rango)|Nro Final (Rango)|Tipo Doc Identidad|Nro Doc Identidad|Apellidos Nombres/ Razón  Social|BI Gravado DG|IGV / IPM DG|BI Gravado DGNG|IGV / IPM DGNG|BI Gravado DNG|IGV / IPM DNG|Valor Adq. NG|ISC|ICBPER|Otros Trib/ Cargos|Total CP|Moneda|Tipo de Cambio|Fecha Emisión Doc Modificado|Tipo CP Modificado|Serie CP Modificado|COD. DAM O DSI|Nro CP Modificado';

    private const RVIE = 'Ruc|Razon Social|Periodo|CAR SUNAT|Fecha de emisión|Fecha Vcto/Pago|Tipo CP/Doc.|Serie del CDP|Nro CP o Doc. Nro Inicial (Rango)|Nro Final (Rango)|Tipo Doc Identidad|Nro Doc Identidad|Apellidos Nombres/ Razón Social|Valor Facturado Exportación|BI Gravada|Dscto BI|IGV / IPM|Dscto IGV / IPM|Mto Exonerado|Mto Inafecto|ISC|BI Grav IVAP|IVAP|ICBPER|Otros Tributos|Total CP|Moneda|Tipo Cambio|Fecha Emisión Doc Modificado|Tipo CP Modificado|Serie CP Modificado|Nro CP Modificado|ID Proyecto Operadores Atribución|Tipo de Nota|Est. Comp';

    private function propuesta(string $libro, string $cabecera, array $filas, string $periodo = '202605'): void
    {
        $ruc = $this->usuario->IdEmpresa;
        $ruta = tempnam(sys_get_temp_dir(), 'sire');
        $zip = new \ZipArchive;
        $zip->open($ruta, \ZipArchive::OVERWRITE);
        $zip->addFromString('propuesta.txt', "\xEF\xBB\xBF".implode("\n", array_merge([$cabecera], $filas)));
        $zip->close();
        $archivo = "sire/{$ruc}/{$libro}/{$periodo}-1.zip";
        Storage::put($archivo, file_get_contents($ruta));
        DB::table('sire_solicitudes')->insert(['IdEmpresa' => $ruc, 'libro' => $libro, 'periodo' => $periodo, 'num_ticket' => '1',
            'cod_estado' => '06', 'archivo' => $archivo, 'filas' => count($filas), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Fila del RCE: [fecha, tipo, serie, número, RUC, proveedor, bases y montos DG/DGNG/DNG/NG..., total, ref] */
    private function compra(string $fecha, string $tipo, string $serie, string $num, string $rucProv, string $prov, array $m, array $ref = ['', '', '', '']): string
    {
        $v = fn ($k) => number_format($m[$k] ?? 0, 2, '.', '');
        $ruc = $this->usuario->IdEmpresa;

        return "{$ruc}|WILCAT|202605|CAR|{$fecha}|{$fecha}|{$tipo}|{$serie}||{$num}||6|{$rucProv}|{$prov}|{$v('base_dg')}|{$v('igv_dg')}|0.00|0.00|{$v('base_dng')}|{$v('igv_dng')}|{$v('ng')}|0.00|0.00|0.00|{$v('total')}|PEN|1.000|{$ref[0]}|{$ref[1]}|{$ref[2]}||{$ref[3]}";
    }

    private function dbf(string $libro): array
    {
        $r = $this->get(route('sire.dbf.descargar', [$libro, 'periodo' => '202605']))->assertOk();
        $this->assertStringContainsString($libro === 'ventas' ? 'tienfact_' : 'almaingr_', $r->headers->get('Content-Disposition'));

        return [$r->getContent(), Dbf::leer($r->getContent())];
    }

    public function test_compras_revisadas_y_descargadas_en_almaingr(): void
    {
        Storage::fake();
        $ruc = $this->usuario->IdEmpresa;
        $this->propuesta(Sire::COMPRAS, self::RCE, [
            $this->compra('22/05/2026', '01', 'F001', '134277', '20553510661', 'LLAMA PE SA', ['base_dg' => 150, 'igv_dg' => 27, 'total' => 177]),
            $this->compra('13/05/2026', '07', 'FC02', '152', '20601312710', 'CONSORCIO FLUVIAL DEL AMAZONAS', ['ng' => -160, 'total' => -160], ['22/04/2026', '01', 'FE02', '5907']),
            $this->compra('26/05/2026', '01', 'FX02', '3444', '20603067534', 'INVERSIONES FU YUAN S.A.C.', ['ng' => 42, 'total' => 42]),
            // El XML del proveedor dice 40.10 pero sus líneas suman 40.09: SUNAT lo copia así
            $this->compra('27/05/2026', '01', 'F001', '47676', '20603837739', 'COMERCIAL KARINA HYM E.I.R.L.', ['ng' => 40.09, 'total' => 40.10]),
        ]);
        // El detalle de la factura de internet ya tiene su cuenta: se propone como cuenta de cargo
        ComprasDetalle::guardar($ruc, [CompraXml::leer(CompraXmlTest::factura($ruc))]);
        ComprasDetalle::asignar($ruc, DB::table('compras_xml_lineas')->pluck('id')->all(), '63691');

        $this->get(route('sire.dbf', ['libro' => 'compras', 'periodo' => '202605']))->assertOk()
            ->assertSee('LLAMA PE SA')->assertSee('INTERNET DEDICADO JULIO 2026')->assertSee('Descargar almaingr.dbf');

        $guardar = fn (string $clave, array $datos) => $this->postJson(route('sire.dbf.guardar'), ['libro' => 'compras', 'periodo' => '202605', 'clave' => $clave] + $datos)->assertOk();
        // Corrige el IGV sin cuadrar: no deja descargar
        $r = $guardar('20553510661|01|F001|134277', ['montos' => ['base_dg' => 150, 'igv_dg' => 20, 'total' => 177]]);
        $this->assertEquals(7, $r->json('diferencia'));
        $this->assertSame(1, $r->json('totales.descuadrados'));
        $this->from(route('sire.dbf', ['libro' => 'compras', 'periodo' => '202605']))
            ->get(route('sire.dbf.descargar', ['compras', 'periodo' => '202605']))->assertRedirect()->assertSessionHas('error');
        // El IGV va a la segunda columna (IGV 2): ahora cuadra; cambia la cuenta y la tercera compra no se lleva
        $guardar('20553510661|01|F001|134277', ['montos' => ['base_dg' => 150, 'igv_dgng' => 27, 'total' => 177], 'ctaabono' => '42121']);
        $guardar('20603067534|01|FX02|3444', ['excluido' => true]);
        $this->postJson(route('sire.dbf.guardar'), ['libro' => 'compras', 'periodo' => '202605', 'clave' => '1|01|X|1', 'excluido' => true])->assertNotFound();

        [$bytes, $filas] = $this->dbf('compras');
        $plantilla = file_get_contents(resource_path('dbf/almaingr.dbf'));
        $this->assertSame(substr($plantilla, 32, 4776), substr($bytes, 32, 4776), 'La misma estructura de la tabla del sistema contable.');
        $this->assertCount(3, $filas, 'La excluida no va.');
        [$nota, $factura, $redondeo] = $filas;
        $this->assertSame(['SIRE', 'F001', '000000134277', '20553510661', '01', 'FACTURA', '2026-05-22', 150.0, null, 27.0, null, 177.0, '63691', '42121', '050002', '0002', '052026', 'S/'],
            [$factura['ORIGEN'], $factura['SERIEDOC'], $factura['NUMDOC'], $factura['RUC_DNI'], $factura['CODDOC'], $factura['DESDOC'], $factura['FECHADOC'],
                $factura['OGYE'], $factura['IGV_1'], $factura['IGV_2'], $factura['IGV_3'], $factura['TOTAL'], $factura['CTACARGO'], $factura['CTAABONO'], $factura['NORDEN'], $factura['NUM1'], $factura['FCON'], $factura['MONEDA']]);
        $this->assertSame([40.10, 40.10], [$redondeo['NG'], $redondeo['TOTAL']], 'El céntimo de redondeo del proveedor se corrige solo.');
        $this->get(route('sire.dbf.excel', ['compras', 'periodo' => '202605']))->assertOk()->assertDownload("almaingr_{$ruc}_202605.xlsx");
        $this->assertSame(['07', 'NOTA CRED', -160.0, -160.0, '01', 'FE02', '000000005907', '2026-04-22', '601111', '421201'],
            [$nota['CODDOC'], $nota['DESDOC'], $nota['NG'], $nota['TOTAL'], $nota['FCODDOC'], $nota['FSERIE'], $nota['FNUMERO'], $nota['FECHANC'], $nota['CTACARGO'], $nota['CTAABONO']],
            'La nota de crédito en negativo como la da SUNAT; sin cuentas propias, las de CONCAR.');

        // El mes siguiente el mismo proveedor ya trae las cuentas que se le pusieron
        $this->propuesta(Sire::COMPRAS, self::RCE, [$this->compra('20/06/2026', '01', 'F001', '135001', '20553510661', 'LLAMA PE SA', ['base_dg' => 150, 'igv_dg' => 27, 'total' => 177])], '202606');
        $this->get(route('sire.dbf', ['libro' => 'compras', 'periodo' => '202606']))->assertOk();
        $junio = SireDbf::delPeriodo($ruc, 'compras', '202606')['comprobantes'][0];
        $this->assertSame(['63691', '42121'], [$junio['ctacargo'], $junio['ctaabono']]);
    }

    public function test_ventas_con_sus_cuentas_en_tienfact(): void
    {
        Storage::fake();
        $ruc = $this->usuario->IdEmpresa;
        $venta = fn (string $tipo, string $serie, string $num, string $doc, string $cliente, string $exo, string $grav, string $igv, string $total, string $estado = '1') => "{$ruc}|WILCAT|202605|CAR|30/05/2026|30/05/2026|{$tipo}|{$serie}|{$num}||6|{$doc}|{$cliente}|0.00|{$grav}|0.00|{$igv}|0.00|{$exo}|0.00|0.00|0.00|0.00|0.00|0.00|{$total}|PEN|1.000|||||||{$estado}";
        $this->propuesta(Sire::VENTAS, self::RVIE, [
            $venta('01', 'F001', '8066', '20607721956', 'SANTA MARIA CAPITAL S.A.C.', '50.00', '0.00', '0.00', '50.00'),
            $venta('01', 'F001', '8067', '20100070970', 'CLIENTE LIMA SAC', '0.00', '100.00', '18.00', '118.00'),
        ]);
        $this->from(route('sire.dbf', ['libro' => 'ventas', 'periodo' => '202605']))
            ->post(route('sire.dbf.aplicar'), ['libro' => 'ventas', 'periodo' => '202605', 'ctacargo' => '1212', 'ctaabono' => '70001'])->assertSessionHas('success');

        [, $filas] = $this->dbf('ventas');
        $this->assertCount(2, $filas);
        $f = $filas[0];
        $this->assertSame([substr($ruc.'01F0', 0, 15), 5.0, '1', '01', 'F001', '000000008066', '20607721956', 'FACTURA', 'SANTA MARIA CAPITAL S.A.C.', 'SANTA MARI', 50.0, 50.0, 'PEN', '1212', '70001'],
            [$f['CODMOV'], $f['NUM_MES'], $f['ID'], $f['COD_DOC'], $f['SERIEDOC'], $f['NUM_DOC'], $f['RUC'], $f['DES_DOC'], $f['NOTA'], $f['NOMBRE'], $f['EXONERADA'], $f['TOTAL'], $f['MONEDA'], $f['CTACARGO'], $f['CTAABONO']]);
        $this->assertSame([100.0, 18.0, 118.0], [$filas[1]['GRAVADA'], $filas[1]['IGV'], $filas[1]['TOTAL']]);
    }
}
