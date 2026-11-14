<?php

namespace Tests\Flujos;

use App\Support\ComprasDetalle;
use App\Support\Contabilidad\Dbf;
use App\Support\Contabilidad\SireDbf;
use App\Support\Sunat\CompraXml;
use App\Support\Sunat\Sire;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Unit\CompraXmlTest;

/** La propuesta del SIRE revisada por el contador y descargada para su sistema en FoxPro (almaingr / tienfact) */
class SireDbfTest extends FlujoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // El centro de costo está apagado por defecto: estas pruebas lo revisan encendido
        config(['services.contabilidad.centro_costo' => true]);
    }

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
        // El IGV en otra casilla (IGV 2, que no es el de su base): suma el total, pero no deja descargar
        $r = $guardar('20553510661|01|F001|134277', ['montos' => ['base_dg' => 150, 'igv_dgng' => 27, 'total' => 177]]);
        $this->assertEquals(0, $r->json('diferencia'));
        $this->assertStringContainsString('IGV 1', $r->json('alerta'));
        $this->assertSame(1, $r->json('totales.descuadrados'));
        // Lo mismo con el IGV escrito en la base y la base en el IGV
        $r = $guardar('20553510661|01|F001|134277', ['montos' => ['igv_dg' => 150, 'base_dgng' => 27, 'total' => 177]]);
        $this->assertNotNull($r->json('alerta'));
        // Cada IGV junto a su base: cuadra, pero la 63691 es de gasto y le falta su centro de costo
        $r = $guardar('20553510661|01|F001|134277', ['montos' => ['base_dg' => 150, 'igv_dg' => 27, 'total' => 177], 'ctaabono' => '42121']);
        $this->assertStringContainsString('centro de costo', $r->json('alerta'));
        // Con su centro de costo (94 gastos de administración) ya se puede; la tercera compra no se lleva
        $r = $guardar('20553510661|01|F001|134277', ['ccosto' => '94']);
        $this->assertNull($r->json('alerta'));
        $r = $guardar('20603067534|01|FX02|3444', ['excluido' => true]);
        $this->assertGreaterThan(0, $r->json('totales.no_llevan.total'), 'Lo que no se lleva se ve aparte (la diferencia con SUNAT).');
        $this->postJson(route('sire.dbf.guardar'), ['libro' => 'compras', 'periodo' => '202605', 'clave' => '1|01|X|1', 'excluido' => true])->assertNotFound();

        [$bytes, $filas] = $this->dbf('compras');
        $plantilla = file_get_contents(resource_path('dbf/almaingr.dbf'));
        $this->assertSame(substr($plantilla, 32, 4776), substr($bytes, 32, 4776), 'La misma estructura de la tabla del sistema contable.');
        $this->assertCount(3, $filas, 'La excluida no va.');
        [$nota, $factura, $redondeo] = $filas;
        $this->assertSame(['SIRE', 'F001', '000000134277', '20553510661', '01', 'FACTURA', '2026-05-22', 150.0, 27.0, null, null, 177.0, '63691', '42121', '050002', '0002', '052026', 'S/'],
            [$factura['ORIGEN'], $factura['SERIEDOC'], $factura['NUMDOC'], $factura['RUC_DNI'], $factura['CODDOC'], $factura['DESDOC'], $factura['FECHADOC'],
                $factura['OGYE'], $factura['IGV_1'], $factura['IGV_2'], $factura['IGV_3'], $factura['TOTAL'], $factura['CTACARGO'], $factura['CTAABONO'], $factura['NORDEN'], $factura['NUM1'], $factura['FCON'], $factura['MONEDA']]);
        $this->assertSame('94', $factura['CODDESTINO'], 'El centro de costo va en CODDESTINO.');
        // Apagado, el .dbf sale como antes aunque haya un centro guardado: su sistema contable no lo acepta en CODDESTINO
        config(['services.contabilidad.centro_costo' => false]);
        $this->assertSame('', $this->dbf('compras')[1][1]['CODDESTINO'], 'CODDESTINO en blanco, como antes.');
        config(['services.contabilidad.centro_costo' => true]);
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

        // El registro para imprimir: solo la cabecera con montos y cuentas
        $pdf = $this->get(route('sire.dbf.imprimir', ['ventas', 'periodo' => '202605']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->from(route('sire.dbf', ['libro' => 'ventas', 'periodo' => '202604']))
            ->get(route('sire.dbf.imprimir', ['ventas', 'periodo' => '202604']))->assertSessionHas('error');

        [, $filas] = $this->dbf('ventas');
        $this->assertCount(2, $filas);
        $f = $filas[0];
        $this->assertSame([substr($ruc.'01F0', 0, 15), 5.0, '1', '01', 'F001', '000000008066', '20607721956', 'FACTURA', 'SANTA MARIA CAPITAL S.A.C.', 'SANTA MARI', 50.0, 50.0, 'PEN', '1212', '70001'],
            [$f['CODMOV'], $f['NUM_MES'], $f['ID'], $f['COD_DOC'], $f['SERIEDOC'], $f['NUM_DOC'], $f['RUC'], $f['DES_DOC'], $f['NOTA'], $f['NOMBRE'], $f['EXONERADA'], $f['TOTAL'], $f['MONEDA'], $f['CTACARGO'], $f['CTAABONO']]);
        $this->assertSame([100.0, 18.0, 118.0], [$filas[1]['GRAVADA'], $filas[1]['IGV'], $filas[1]['TOTAL']]);
    }

    public function test_ventas_con_detalle_editables_y_tienfact_como_lo_guarda_el_sistema_contable(): void
    {
        Storage::fake();
        $ruc = $this->usuario->IdEmpresa;
        $venta = fn (string $num, string $doc, string $cliente, string $exo, string $grav, string $igv, string $total) => "{$ruc}|WILCAT|202605|CAR|30/05/2026|30/05/2026|01|F001|{$num}||6|{$doc}|{$cliente}|0.00|{$grav}|0.00|{$igv}|0.00|{$exo}|0.00|0.00|0.00|0.00|0.00|0.00|{$total}|PEN|1.000|||||||1";
        $this->propuesta(Sire::VENTAS, self::RVIE, [
            $venta('8066', '20607721956', 'SANTA MARIA CAPITAL S.A.C.', '50.00', '0.00', '0.00', '50.00'),
            $venta('8067', '20100070970', 'CLIENTE LIMA SAC', '0.00', '100.00', '18.00', '118.00'),
        ]);

        // El XML de una venta propia se guarda; el de una compra (lo emitió otro) no
        $propia = str_replace(['20553510661', 'LLAMA PE SA'], [$ruc, 'MI EMPRESA'], CompraXmlTest::factura('20100070970', 'F001-00008067', 'SERVICIO DE INSTALACION'));
        $this->from(route('sire.ventas.detalle', ['periodo' => '202605']))->post(route('sire.ventas.detalle.subir'), ['archivos' => [
            UploadedFile::fake()->createWithContent('venta.xml', $propia), UploadedFile::fake()->createWithContent('compra.xml', CompraXmlTest::factura($ruc)),
        ]])->assertSessionHas('success', fn ($m) => str_contains($m, '1 venta(s)') && str_contains($m, '1 no los emitió'));
        $this->get(route('sire.ventas.detalle', ['periodo' => '202605']))->assertOk()
            ->assertSee('SERVICIO DE INSTALACION')->assertSee('CLIENTE LIMA SAC')->assertSee('Descargar tienfact.dbf');
        $this->get(route('sire.ventas.pdf', DB::table('ventas_xml')->value('id')))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Los montos de una venta se editan; el IGV tiene que ir con su base gravada
        $guardar = fn (array $montos) => $this->postJson(route('sire.dbf.guardar'), ['libro' => 'ventas', 'periodo' => '202605', 'clave' => '20100070970|01|F001|8067', 'montos' => $montos])->assertOk();
        $this->assertNotNull($guardar(['exonerada' => 100, 'igv' => 18, 'total' => 118])->json('alerta'));
        $this->assertNull($guardar(['gravada' => 100, 'igv' => 18, 'total' => 118])->json('alerta'));

        // Como lo guarda el sistema contable: los montos en cero (no vacíos), ANULADA 0 y TIPO_10 vacío
        [, $filas] = $this->dbf('ventas');
        [$exonerada, $gravada] = $filas;
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 50.0, 0.0, 50.0, 0.0, ''],
            [$exonerada['GRAVADA'], $exonerada['IGV'], $exonerada['TBRUTO'], $exonerada['TNETO'], $exonerada['EXONERADA'], $exonerada['IBOLSAS'], $exonerada['TOTAL'], $exonerada['ANULADA'], $exonerada['TIPO_10']]);
        $this->assertSame([100.0, 18.0, 0.0, 118.0], [$gravada['GRAVADA'], $gravada['IGV'], $gravada['EXONERADA'], $gravada['TOTAL']]);
    }

    public function test_lo_revisado_pasa_al_registro_de_compras_y_ventas_con_su_asiento(): void
    {
        Storage::fake();
        $ruc = $this->usuario->IdEmpresa;
        $this->propuesta(Sire::COMPRAS, self::RCE, [
            $this->compra('22/05/2026', '01', 'F001', '134277', '20553510661', 'LLAMA PE SA', ['base_dg' => 150, 'igv_dg' => 27, 'total' => 177]),
            $this->compra('13/05/2026', '07', 'FC02', '152', '20601312710', 'CONSORCIO FLUVIAL DEL AMAZONAS', ['ng' => -160, 'total' => -160], ['22/04/2026', '01', 'FE02', '5907']),
            // Una factura de abril que el SIRE trae en mayo: se anota en mayo
            $this->compra('28/04/2026', '01', 'FX02', '3444', '20603067534', 'INVERSIONES FU YUAN S.A.C.', ['ng' => 42, 'total' => 42]),
        ]);
        $guardar = fn (string $clave, array $datos) => $this->postJson(route('sire.dbf.guardar'), ['libro' => 'compras', 'periodo' => '202605', 'clave' => $clave] + $datos)->assertOk();
        $guardar('20553510661|01|F001|134277', ['ctacargo' => '6365', 'ccosto' => '94']);
        $guardar('20603067534|01|FX02|3444', ['ctacargo' => '99999']);

        $this->from(route('sire.dbf', ['periodo' => '202605']))->post(route('sire.dbf.registrar', ['compras', 'periodo' => '202605']))->assertSessionHas('success');
        $reg = DB::table('conta_registros')->where('IdEmpresa', $ruc)->where('tipo', 'COMPRA')->get()->keyBy('numero');
        $this->assertCount(3, $reg);
        $this->assertSame(['6365', 150.0, 27.0, 177.0, '202605'], [$reg['134277']->cuenta, (float) $reg['134277']->base, (float) $reg['134277']->igv, (float) $reg['134277']->total, $reg['134277']->periodo]);
        $this->assertSame(['07', 160.0, 160.0], [$reg['152']->tdocod, (float) $reg['152']->no_gravado, (float) $reg['152']->total], 'La nota de crédito en positivo: el registro la asienta al revés.');
        $this->assertSame(['2026-04-28', '202605', '601111'], [substr($reg['3444']->fecha, 0, 10), $reg['3444']->periodo, $reg['3444']->cuenta],
            'Fecha del comprobante, anotada en el mes del SIRE; su cuenta no está en el plan: va la de compras.');

        // Cada comprobante con su asiento, que cuadra
        foreach ($reg as $r) {
            $asiento = DB::table('conta_asientos')->where('ref_tabla', 'conta_registros')->where('ref_id', $r->id)->where('subdiario', '11')->first();
            $this->assertNotNull($asiento);
            $sumas = DB::table('conta_asiento_detalle')->where('asiento_id', $asiento->id)->selectRaw('SUM(debe) d, SUM(haber) h')->first();
            $this->assertEquals((float) $sumas->d, (float) $sumas->h);
        }

        // Pasarlo otra vez no duplica nada
        $this->from(route('sire.dbf', ['periodo' => '202605']))->post(route('sire.dbf.registrar', ['compras', 'periodo' => '202605']))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '3 ya estaban registrados'));
        $this->assertSame(3, DB::table('conta_registros')->where('IdEmpresa', $ruc)->where('tipo', 'COMPRA')->count());
    }

    public function test_las_ventas_del_sire_pasan_al_registro_de_ventas(): void
    {
        Storage::fake();
        $ruc = $this->usuario->IdEmpresa;
        $venta = fn (string $num, string $exo, string $grav, string $igv, string $total, string $estado = '1') => "{$ruc}|WILCAT|202605|CAR|30/05/2026|30/05/2026|01|F001|{$num}||6|20607721956|SANTA MARIA CAPITAL S.A.C.|0.00|{$grav}|0.00|{$igv}|0.00|{$exo}|0.00|0.00|0.00|0.00|0.00|0.00|{$total}|PEN|1.000|||||||{$estado}";
        $this->propuesta(Sire::VENTAS, self::RVIE, [$venta('8066', '50.00', '0.00', '0.00', '50.00'), $venta('8067', '0.00', '100.00', '18.00', '118.00'),
            $venta('8068', '0.00', '0.00', '0.00', '0.00', '2')]);

        $this->from(route('sire.dbf', ['libro' => 'ventas', 'periodo' => '202605']))->post(route('sire.dbf.registrar', ['ventas', 'periodo' => '202605']))->assertSessionHas('success');
        $reg = DB::table('conta_registros')->where('IdEmpresa', $ruc)->where('tipo', 'VENTA')->get()->keyBy('numero');
        $this->assertCount(2, $reg, 'El anulado no se registra.');
        $this->assertSame([0.0, 50.0, '6', '20607721956'], [(float) $reg['8066']->base, (float) $reg['8066']->no_gravado, $reg['8066']->doc_tipo, $reg['8066']->doc_num]);
        $this->assertSame([100.0, 18.0, 118.0], [(float) $reg['8067']->base, (float) $reg['8067']->igv, (float) $reg['8067']->total]);
    }

    /** Una factura que SUNAT aún no tiene: se agrega a mano y va como las demás, sin repetirse nunca */
    public function test_agregar_a_mano_una_compra_que_sunat_aun_no_tiene(): void
    {
        Storage::fake();
        $ruc = $this->usuario->IdEmpresa;
        $this->propuesta(Sire::COMPRAS, self::RCE, [
            $this->compra('22/05/2026', '01', 'F001', '134277', '20553510661', 'LLAMA PE SA', ['base_dg' => 150, 'igv_dg' => 27, 'total' => 177]),
        ]);
        $factura = ['libro' => 'compras', 'periodo' => '202605', 'tipo' => '01', 'serie' => 'f005', 'numero' => '00099', 'fecha' => '2026-05-30',
            'ruc' => '20100070970', 'nombre' => 'supermercados peruanos', 'montos' => ['base_dg' => 100, 'igv_dg' => 18]];
        $agregar = fn (array $cambios = []) => $this->postJson(route('sire.dbf.agregar'), array_replace($factura, $cambios));

        $id = (int) $agregar()->assertOk()->json('id');
        $mayo = collect(SireDbf::delPeriodo($ruc, 'compras', '202605')['comprobantes'])->keyBy('clave');
        $manual = $mayo['20100070970|01|F005|99'];
        $this->assertSame([$id, 'SUPERMERCADOS PERUANOS', 118.0, false, 0.0, null], [$manual['manual'], $manual['nombre'], $manual['montos']['total'], $manual['excluido'], $manual['diferencia'], $manual['alerta']]);
        $this->get(route('sire.dbf', ['libro' => 'compras', 'periodo' => '202605']))->assertOk()->assertSee('MANUAL')->assertSee('SUPERMERCADOS PERUANOS');

        // Se le ponen cuentas como a cualquiera y va al .dbf y al CONCAR con detalle
        $this->postJson(route('sire.dbf.guardar'), ['libro' => 'compras', 'periodo' => '202605', 'clave' => $manual['clave'], 'ctacargo' => '6011'])->assertOk();
        [, $filas] = $this->dbf('compras');
        $fila = collect($filas)->firstWhere('SERIEDOC', 'F005');
        $this->assertSame(['000000000099', 100.0, 18.0, 118.0, '6011'], [$fila['NUMDOC'], $fila['OGYE'], $fila['IGV_1'], $fila['TOTAL'], $fila['CTACARGO']]);
        $this->assertArrayHasKey('20100070970|01|F005|99', collect(ComprasDetalle::conPropuesta($ruc, '202605')['comprobantes'])->keyBy(fn ($c) => "{$c['ruc']}|{$c['tipo']}|{$c['serie']}|{$c['numero']}"));

        // No se repite, no se agrega lo que ya está en la propuesta ni con fecha de un mes posterior, ni sin propuesta
        $agregar()->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'ya se agregó'));
        $agregar(['ruc' => '20553510661', 'serie' => 'F001', 'numero' => '134277'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'ya está en la propuesta'));
        $agregar(['numero' => '100', 'fecha' => '2026-06-01'])->assertStatus(422);
        $agregar(['numero' => '101', 'periodo' => '202604'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'propuesta'));
        $agregar(['numero' => '102', 'montos' => ['base_dg' => -5]])->assertStatus(422);

        // La nota de crédito se resta sola
        $agregar(['tipo' => '07', 'serie' => 'FC05', 'numero' => '7', 'montos' => ['no_gravado' => 20], 'ref_serie' => 'F005', 'ref_numero' => '99'])->assertOk();
        $nota = collect(SireDbf::delPeriodo($ruc, 'compras', '202605')['comprobantes'])->firstWhere('serie', 'FC05');
        $this->assertSame([-20.0, -20.0, 'F005'], [$nota['montos']['no_gravado'], $nota['montos']['total'], $nota['ref']['serie']]);

        // En junio SUNAT ya la trae: allí no se lleva (no entra dos veces)
        $this->propuesta(Sire::COMPRAS, self::RCE, [$this->compra('30/05/2026', '01', 'F005', '99', '20100070970', 'SUPERMERCADOS PERUANOS', ['base_dg' => 100, 'igv_dg' => 18, 'total' => 118])], '202606');
        $junio = collect(SireDbf::delPeriodo($ruc, 'compras', '202606')['comprobantes'])->firstWhere('serie', 'F005');
        $this->assertSame([true, '202605', null], [$junio['excluido'], $junio['yaManual'], $junio['manual']]);
        $this->get(route('sire.dbf', ['libro' => 'compras', 'periodo' => '202606']))->assertOk()->assertSee('Ya se agregó a mano en 05/2026');

        // Se borra con sus cuentas
        $this->postJson(route('sire.dbf.quitar_manual', $id))->assertOk();
        $this->assertNull(collect(SireDbf::delPeriodo($ruc, 'compras', '202605')['comprobantes'])->firstWhere('serie', 'F005'));
        $this->assertFalse(DB::table('sire_ajustes')->where('clave', '20100070970|01|F005|99')->where('periodo', '202605')->exists());
        $this->assertFalse(collect(SireDbf::delPeriodo($ruc, 'compras', '202606')['comprobantes'])->firstWhere('serie', 'F005')['excluido'], 'Borrado el manual, junio ya lo lleva.');
        $this->postJson(route('sire.dbf.quitar_manual', $id))->assertNotFound();
    }

    public function test_agregar_a_mano_una_venta_y_se_imprime(): void
    {
        Storage::fake();
        $ruc = $this->usuario->IdEmpresa;
        $this->propuesta(Sire::VENTAS, self::RVIE, []);
        $this->postJson(route('sire.dbf.agregar'), ['libro' => 'ventas', 'periodo' => '202605', 'tipo' => '03', 'serie' => 'B001', 'numero' => '45',
            'fecha' => '2026-05-10', 'ruc' => '0', 'nombre' => 'Clientes varios', 'montos' => ['gravada' => 50, 'igv' => 9]])->assertOk();
        $this->postJson(route('sire.dbf.agregar'), ['libro' => 'ventas', 'periodo' => '202605', 'tipo' => '12', 'serie' => 'T001', 'numero' => '1',
            'fecha' => '2026-05-10', 'ruc' => '0', 'nombre' => 'X', 'montos' => ['gravada' => 1]])->assertStatus(422);

        [, $filas] = $this->dbf('ventas');
        $this->assertSame(['B001', 50.0, 9.0, 59.0, false], [$filas[0]['SERIEDOC'], $filas[0]['GRAVADA'], $filas[0]['IGV'], $filas[0]['TOTAL'], (bool) $filas[0]['ANULADA']]);
        $this->get(route('sire.dbf.imprimir', ['ventas', 'periodo' => '202605']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('sire.ventas.detalle', ['periodo' => '202605']))->assertOk()->assertSee('MANUAL')->assertSee('Agregar comprobante');
    }

    public function test_sin_permiso_del_registro_no_aparece_ni_se_puede(): void
    {
        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        $sire = DB::table('modulos')->where('mod_url', '/sire/compras')->value('mod_id');
        DB::table('modulos_usuario')->insert(['user_IdUsuario' => $cajero->IdUsuario, 'mod_id' => $sire]);
        DB::table('role_user')->where('user_IdUsuario', $cajero->IdUsuario)->update(['role_id' => 15]);

        $this->actingAs($cajero)->post(route('sire.dbf.registrar', ['compras', 'periodo' => '202605']))->assertForbidden();
    }
}
