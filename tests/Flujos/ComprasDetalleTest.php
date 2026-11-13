<?php

namespace Tests\Flujos;

use App\Models\Central\Cliente;
use App\Support\ComprasDetalle;
use App\Support\Concar;
use App\Support\Sunat\CompraXml;
use App\Support\Sunat\Sire;
use App\Support\Sunat\SireCuadre;
use Illuminate\Http\Client\Request as Peticion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    public function test_el_pdf_de_una_compra_sale_de_su_xml_y_solo_para_esta_empresa(): void
    {
        $ruc = $this->usuario->IdEmpresa;
        ComprasDetalle::guardar($ruc, [CompraXml::leer(CompraXmlTest::factura($ruc))]);
        $id = DB::table('compras_xml')->where('IdEmpresa', $ruc)->value('id');

        $r = $this->get(route('sire.detalle.pdf', $id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $r->getContent());

        $ajena = DB::table('compras_xml')->insertGetId(['IdEmpresa' => '20999999990', 'ruc_emisor' => '20553510661', 'tipo' => '01', 'serie' => 'F001', 'numero' => '9']);
        $this->get(route('sire.detalle.pdf', $ajena))->assertNotFound();
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
            ->assertSee('INTERNET DEDICADO JULIO 2026')->assertSee('ROUTER WIFI')->assertSee('sin XML')->assertSee('SUPERMERCADOS SA')->assertSee('Descargar almaingr.dbf');

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
        // La 6365 es de gasto: sin centro de costo CONCAR no sale; con el centro (94) sale en su columna
        $this->get(route('sire.detalle.concar', ['periodo' => '202607']))->assertRedirect()->assertSessionHas('error', fn ($m) => str_contains($m, 'centro de costo'));
        $this->postJson(route('sire.detalle.centro'), ['lineas' => DB::table('compras_xml_lineas')->where('cuenta', '6365')->pluck('id')->all(), 'centro' => '94'])->assertOk();
        $conCentro = Concar::comprasConDetalle($ruc, '202607', ComprasDetalle::delPeriodo($ruc, '202607', $propuesta)['comprobantes']);
        $this->assertSame('94', collect($conCentro['filas'])->first(fn ($f) => $f[10] === '6365')[12]);
        $this->get(route('sire.detalle.concar', ['periodo' => '202607']))->assertOk()->assertDownload('CONCAR_COMPRAS_DETALLE_'.$ruc.'_202607.xlsx');
        // El subdiario de compras se cambia junto al botón y queda guardado para la empresa (11 → 14)
        $this->assertSame('11', $concar['filas'][0][1]);
        $this->get(route('sire.detalle', ['periodo' => '202607']))->assertSee('name="subdiario" value="11"', false);
        $this->get(route('sire.detalle.concar', ['periodo' => '202607', 'subdiario' => '14']))->assertOk();
        $this->assertSame('14', Concar::comprasConDetalle($ruc, '202607', $periodo['comprobantes'])['filas'][0][1]);
        $this->get(route('sire.detalle', ['periodo' => '202607']))->assertSee('name="subdiario" value="14"', false);
    }

    public function test_trae_de_sunat_por_json_pe_los_xml_que_faltan(): void
    {
        $ruc = $this->usuario->IdEmpresa;
        $this->propuesta();
        config(['services.jsonpe.token' => 'token-de-prueba']);
        $this->get(route('sire.detalle', ['periodo' => '202607']))->assertSee('Falta la Clave SOL principal');

        app()->instance('tenancy.cliente', (new Cliente)->forceFill(['ruc' => $ruc, 'sol_usuario' => 'USUARIO1', 'sol_clave' => 'clave-de-prueba']));
        $zip = tempnam(sys_get_temp_dir(), 'zip');
        $z = new \ZipArchive;
        $z->open($zip, \ZipArchive::OVERWRITE);
        $z->addFromString('20553510661-01-F001-134277.xml', CompraXmlTest::factura($ruc));
        $z->close();
        Http::fake(fn (Peticion $p) => $p['serie'] === 'F001'
            ? Http::response(['success' => true, 'message' => 'ok', 'data' => ['xml_base64' => base64_encode(file_get_contents($zip))]])
            : Http::response(['success' => false, 'message' => 'Comprobante no encontrado']));

        $this->postJson(route('sire.detalle.traer', ['periodo' => '202607']))->assertOk()
            ->assertJson(['ok' => true, 'traidos' => 1, 'quedan' => 0])->assertJsonCount(1, 'errores');
        Http::assertSent(fn (Peticion $p) => $p['proveedor'] === '20553510661' && $p['usuario'] === 'USUARIO1' && $p['correlativo'] === '134277'
            && $p->hasHeader('Authorization', 'Bearer token-de-prueba'));
        $this->assertSame(2, DB::table('compras_xml_lineas')->count(), 'Llegó la factura con sus líneas.');

        // La que SUNAT no tiene no se vuelve a pedir (cada consulta cuesta), pero se dice cuántas y por qué
        $this->postJson(route('sire.detalle.traer', ['periodo' => '202607']))->assertJson(['ok' => true, 'traidos' => 0, 'quedan' => 0, 'saltados' => 1])
            ->assertJsonPath('motivos.0', 'Comprobante no encontrado (1)');
        Http::assertSentCount(2);

        // Reintentar (a propósito): se vuelve a pedir
        $this->postJson(route('sire.detalle.traer', ['periodo' => '202607', 'reintentar' => 1]))->assertJson(['ok' => true, 'traidos' => 0])->assertJsonCount(1, 'errores');
        Http::assertSentCount(3);
    }

    public function test_si_la_clave_sol_esta_mal_se_detiene_al_primer_intento(): void
    {
        $this->propuesta();
        config(['services.jsonpe.token' => 'token-de-prueba']);
        app()->instance('tenancy.cliente', (new Cliente)->forceFill(['ruc' => $this->usuario->IdEmpresa, 'sol_usuario' => 'USUARIO1', 'sol_clave' => 'mala']));
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Usuario o clave SOL incorrectos'], 400)]);

        $this->postJson(route('sire.detalle.traer', ['periodo' => '202607']))->assertJson(['ok' => false])
            ->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'clave SOL'));
        Http::assertSentCount(1);
    }

    public function test_si_json_pe_solo_devuelve_la_constancia_cdr_no_gasta_mas_creditos(): void
    {
        $this->propuesta();
        config(['services.jsonpe.token' => 'token-de-prueba']);
        app()->instance('tenancy.cliente', (new Cliente)->forceFill(['ruc' => $this->usuario->IdEmpresa, 'sol_usuario' => 'USUARIO1', 'sol_clave' => 'clave']));
        $zip = tempnam(sys_get_temp_dir(), 'zip');
        $z = new \ZipArchive;
        $z->open($zip, \ZipArchive::OVERWRITE);
        $z->addFromString('R-20553510661-01-F001-134277.xml', '<?xml version="1.0"?><ar:ApplicationResponse xmlns:ar="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2"/>');
        $z->close();
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'exito', 'data' => ['xml_base64' => base64_encode(file_get_contents($zip))]])]);

        $this->postJson(route('sire.detalle.traer', ['periodo' => '202607']))->assertJson(['ok' => false])
            ->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'constancia'));
        Http::assertSentCount(1);
    }
}
