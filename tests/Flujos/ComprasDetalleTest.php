<?php

namespace Tests\Flujos;

use App\Support\ComprasDetalle;
use App\Support\Concar;
use App\Support\Sunat\Sire;
use App\Support\Sunat\SireCuadre;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Unit\CompraXmlTest;

/** El contador sube los XML de las compras, pone la cuenta a cada línea (el sistema aprende) y saca el CONCAR con detalle */
class ComprasDetalleTest extends FlujoTestCase
{
    private function zip(array $archivos): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new \ZipArchive;
        $zip->open($ruta, \ZipArchive::OVERWRITE);
        foreach ($archivos as $nombre => $contenido) {
            $zip->addFromString($nombre, $contenido);
        }
        $zip->close();

        return new UploadedFile($ruta, 'compras.zip', 'application/zip', null, true);
    }

    private function subir(array $archivos): void
    {
        $this->from(route('sire.detalle', ['periodo' => '202607']))
            ->post(route('sire.detalle.subir'), ['archivos' => [$this->zip($archivos)]])->assertSessionHas('success');
    }

    /** La propuesta del RCE como la baja SUNAT (columnas reales del .txt) */
    private function propuesta(): void
    {
        Storage::fake();
        $ruc = $this->usuario->IdEmpresa;
        $cab = 'RUC|Apellidos y Nombres o Razón social|Periodo|CAR SUNAT|Fecha de emisión|Fecha Vcto/Pago|Tipo CP/Doc.|Serie del CDP|Año|Nro CP o Doc. Nro Inicial (Rango)|Nro Final (Rango)|Tipo Doc Identidad|Nro Doc Identidad|Apellidos Nombres/ Razón  Social|BI Gravado DG|IGV / IPM DG|BI Gravado DGNG|IGV / IPM DGNG|BI Gravado DNG|IGV / IPM DNG|Valor Adq. NG|ISC|ICBPER|Otros Trib/ Cargos|Total CP|Moneda|Tipo de Cambio|Fecha Emisión Doc Modificado|Tipo CP Modificado|Serie CP Modificado|COD. DAM O DSI|Nro CP Modificado';
        $fila = fn (string $fecha, string $serie, string $num, string $rucProv, string $prov, string $igv, string $total) => "{$ruc}|EMPRESA|202607|CAR|{$fecha}|{$fecha}|01|{$serie}||{$num}||6|{$rucProv}|{$prov}|0.00|0.00|0.00|0.00|".round($total - $igv, 2)."|{$igv}|0.00|0.00|0.00|0.00|{$total}|PEN|1.000||||||";
        $txt = implode("\n", [$cab,
            $fila('22/07/2026', 'F001', '134277', '20553510661', 'LLAMA PE SA', '27.00', '177.00'),
            $fila('25/07/2026', 'E001', '512', '20100070970', 'SUPERMERCADOS SA', '18.00', '118.00')]);
        $ruta = tempnam(sys_get_temp_dir(), 'rce');
        $zip = new \ZipArchive;
        $zip->open($ruta, \ZipArchive::OVERWRITE);
        $zip->addFromString('propuesta.txt', "\xEF\xBB\xBF".$txt);
        $zip->close();
        Storage::put("sire/{$ruc}/080000/202607-1.zip", file_get_contents($ruta));
        DB::table('sire_solicitudes')->insert(['IdEmpresa' => $ruc, 'libro' => Sire::COMPRAS, 'periodo' => '202607', 'num_ticket' => '1',
            'cod_estado' => '06', 'archivo' => "sire/{$ruc}/080000/202607-1.zip", 'filas' => 2, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_sube_los_xml_aprende_las_cuentas_y_el_concar_sale_por_cuenta(): void
    {
        $ruc = $this->usuario->IdEmpresa;
        // Un .zip con la factura, su constancia CDR y una factura de otra empresa
        $this->subir([
            'F001-134277.xml' => CompraXmlTest::factura($ruc),
            'R-F001-134277.xml' => '<?xml version="1.0"?><ApplicationResponse xmlns="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2"/>',
            'otra.xml' => CompraXmlTest::factura('20100070970', 'F001-00000999'),
        ]);
        $this->assertSame(1, DB::table('compras_xml')->where('IdEmpresa', $ruc)->count(), 'Solo la compra de esta empresa.');
        $lineas = DB::table('compras_xml_lineas')->orderBy('orden')->pluck('id');
        $this->assertCount(2, $lineas);

        // El contador pone las cuentas: internet a gasto, el router a activo
        $this->postJson(route('sire.detalle.cuenta'), ['lineas' => [$lineas[0]], 'cuenta' => '6365'])->assertOk();
        $this->postJson(route('sire.detalle.cuenta'), ['lineas' => [$lineas[1]], 'cuenta' => '3361'])->assertOk();
        $this->postJson(route('sire.detalle.cuenta'), ['lineas' => [$lineas[1]], 'cuenta' => '33.61'])->assertStatus(422);

        // El mes siguiente llega otra factura igual: las cuentas se ponen solas
        $this->subir(['F001-134475.xml' => CompraXmlTest::factura($ruc, 'F001-00134475', 'INTERNET DEDICADO AGOSTO 2026')]);
        $nueva = DB::table('compras_xml')->where('numero', '134475')->value('id');
        $this->assertSame(['6365', '3361'], DB::table('compras_xml_lineas')->where('compra_xml_id', $nueva)->orderBy('orden')->pluck('cuenta')->all());

        // Con la propuesta de SUNAT: se ve qué factura tiene detalle y cuál falta
        $this->propuesta();
        $this->get(route('sire.detalle', ['periodo' => '202607']))->assertOk()
            ->assertSee('INTERNET DEDICADO JULIO 2026')->assertSee('ROUTER WIFI')->assertSee('Falta XML')->assertSee('SUPERMERCADOS SA');

        $propuesta = SireCuadre::comprasPropuesta(Sire::leerZip(Storage::path("sire/{$ruc}/080000/202607-1.zip")));
        $periodo = ComprasDetalle::delPeriodo($ruc, '202607', $propuesta);
        $this->assertSame(1, $periodo['conDetalle']);
        $this->assertCount(1, $periodo['sinPropuesta'], 'La factura de agosto no está en la propuesta de julio.');

        $concar = Concar::comprasConDetalle($ruc, '202607', $periodo['comprobantes']);
        $porCuenta = fn (string $numero) => collect($concar['filas'])->filter(fn ($f) => $f[18] === $numero)->map(fn ($f) => [$f[10], $f[13], $f[14]])->values()->all();
        $this->assertSame([['6365', 'D', 100.0], ['3361', 'D', 50.0], ['401111', 'D', 27.0], ['421201', 'H', 177.0]], $porCuenta('F001-00134277'),
            'Una línea por cuenta según el detalle, más IGV y por pagar.');
        $this->assertSame([['601111', 'D', 100.0], ['401111', 'D', 18.0], ['421201', 'H', 118.0]], $porCuenta('E001-00000512'),
            'Sin XML va a la cuenta de compras.');

        $this->get(route('sire.detalle.excel', ['periodo' => '202607']))->assertOk()->assertDownload('COMPRAS_DETALLE_'.$ruc.'_202607.xlsx');
        $this->get(route('sire.detalle.concar', ['periodo' => '202607']))->assertOk()->assertDownload('CONCAR_COMPRAS_DETALLE_'.$ruc.'_202607.xlsx');
    }
}
