<?php

namespace Tests\Flujos;

use App\Support\Estudio;
use App\Support\EstudioDescargas;
use App\Support\Sunat\Sire;
use Illuminate\Http\Client\Request as Peticion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Unit\CompraXmlTest;

/** Descarga automática del estudio: el día programado trae solo el SIRE y los XML del mes anterior de cada empresa */
class EstudioDescargasTest extends FlujoTestCase
{
    private const CON_CLAVES = '20553510661';

    private const SIN_CLAVES = '20100070970';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        DB::connection('central')->beginTransaction();
        // Sin multi-empresa: el comando trabaja con esta base (la de pruebas), no con los clientes del panel
        config(['services.jsonpe.token' => 'token-de-prueba', 'tenancy.dominio' => null]);
        Estudio::activar((string) $this->usuario->IdEmpresa);
        foreach ([self::CON_CLAVES => 'LLAMA PE SA', self::SIN_CLAVES => 'SUPERMERCADOS PERUANOS SA'] as $ruc => $nombre) {
            $this->post(route('estudio.guardar'), ['ruc' => $ruc, 'razon_social' => $nombre])->assertSessionHasNoErrors();
        }
        $this->post(route('estudio.configurar', self::CON_CLAVES), ['client_id' => 'ID', 'client_secret' => 'SECRETO', 'sire_usuario' => 'SIRE01', 'sire_clave' => 'CLAVE',
            'sol_usuario' => 'SOLA', 'sol_clave' => 'clave-sol'])->assertSessionHas('success');
        $this->sunat();
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    /** SUNAT (SIRE) y json.pe de mentira: la propuesta de compras trae una factura de LLAMA PE y json.pe entrega su XML */
    private function sunat(): void
    {
        $cab = 'RUC|Apellidos y Nombres o Razón social|Periodo|CAR SUNAT|Fecha de emisión|Fecha Vcto/Pago|Tipo CP/Doc.|Serie del CDP|Año|Nro CP o Doc. Nro Inicial (Rango)|Nro Final (Rango)|Tipo Doc Identidad|Nro Doc Identidad|Apellidos Nombres/ Razón  Social|BI Gravado DG|IGV / IPM DG|BI Gravado DGNG|IGV / IPM DGNG|BI Gravado DNG|IGV / IPM DNG|Valor Adq. NG|ISC|ICBPER|Otros Trib/ Cargos|Total CP|Moneda|Tipo de Cambio|Fecha Emisión Doc Modificado|Tipo CP Modificado|Serie CP Modificado|COD. DAM O DSI|Nro CP Modificado';
        $fila = self::CON_CLAVES.'|LLAMA|202610|CAR|22/10/2026|22/10/2026|01|F001||134277||6|20553510661|LLAMA PE SA|0.00|0.00|0.00|0.00|150.00|27.00|0.00|0.00|0.00|0.00|177.00|PEN|1.000||||||';
        $propuesta = tempnam(sys_get_temp_dir(), 'rce');
        $z = new \ZipArchive;
        $z->open($propuesta, \ZipArchive::OVERWRITE);
        $z->addFromString('propuesta.txt', "\xEF\xBB\xBF{$cab}\n{$fila}");
        $z->close();
        $xml = tempnam(sys_get_temp_dir(), 'xml');
        $z = new \ZipArchive;
        $z->open($xml, \ZipArchive::OVERWRITE);
        $z->addFromString('F001-134277.xml', CompraXmlTest::factura(self::CON_CLAVES));
        $z->close();
        [$propuesta, $xml] = [file_get_contents($propuesta), file_get_contents($xml)];

        Http::fake(function (Peticion $p) use ($propuesta, $xml) {
            return match (true) {
                str_contains($p->url(), 'sunat/xml') => Http::response(['success' => true, 'data' => ['xml_base64' => base64_encode($xml)]]),
                str_contains($p->url(), 'oauth2/token') => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
                str_contains($p->url(), 'exporta') => Http::response(['numTicket' => 'T1']),
                str_contains($p->url(), 'consultaestadotickets') => Http::response(['registros' => [['numTicket' => 'T1', 'codEstadoProceso' => '06',
                    'desEstadoProceso' => 'Terminado', 'perTributario' => '202610', 'codProceso' => '10',
                    'archivoReporte' => [['nomArchivoReporte' => 'p.zip', 'codTipoAchivoReporte' => '01']]]]]),
                str_contains($p->url(), 'archivoreporte') => Http::response($propuesta),
                default => Http::response([], 404),
            };
        });
    }

    public function test_el_dia_programado_trae_solo_el_sire_y_los_xml_del_mes_anterior(): void
    {
        $this->post(route('estudio.descargas.programacion'), ['activo' => 1, 'dia' => 5, 'hora' => 1, 'sire_compras' => 1, 'sire_ventas' => 1, 'xml' => 1])
            ->assertSessionHas('success');

        // El 4 todavía no toca; el 5 a la 1 a. m. arranca solo (el programador de tareas, cada 10 minutos)
        $this->travelTo('2026-11-04 23:50');
        Artisan::call('estudio:descargas');
        $this->assertSame(0, DB::table('estudio_descargas')->count());
        $this->travelTo('2026-11-05 01:05');
        Artisan::call('estudio:descargas');

        $estado = fn (string $ruc, string $tarea) => DB::table('estudio_descargas')->where(['periodo' => '202610', 'IdEmpresa' => $ruc, 'tarea' => $tarea])->value('estado');
        $this->assertSame(['PROCESO', 'PROCESO', 'PENDIENTE'], [$estado(self::CON_CLAVES, 'sire_compras'), $estado(self::CON_CLAVES, 'sire_ventas'), $estado(self::CON_CLAVES, 'xml')]);
        $this->assertSame('OMITIDO', $estado(self::SIN_CLAVES, 'sire_compras'), 'Sin credenciales se salta y queda anotada.');

        // Unos minutos después SUNAT ya tiene las propuestas: se descargan y se traen los XML
        $this->travel(11)->minutes();
        Artisan::call('estudio:descargas');
        $this->assertSame(['LISTO', 'LISTO', 'LISTO'], [$estado(self::CON_CLAVES, 'sire_compras'), $estado(self::CON_CLAVES, 'sire_ventas'), $estado(self::CON_CLAVES, 'xml')]);
        $this->assertTrue(DB::table('compras_xml')->where('IdEmpresa', self::CON_CLAVES)->where('serie', 'F001')->where('numero', '134277')->exists());
        $this->assertSame(1, DB::table('sire_solicitudes')->where('IdEmpresa', self::CON_CLAVES)->where('libro', Sire::COMPRAS)->count());
        $this->assertSame('202610', DB::table('estudio_programacion')->value('avisado_periodo'), 'Al terminar avisa una sola vez.');

        // Al día siguiente el contador lo ve al entrar; y ese mes ya no se vuelve a descargar sola
        $this->get(route('estudio.elegir'))->assertOk()->assertSee('Descarga de Octubre 2026');
        $this->get(route('estudio.descargas'))->assertOk()->assertSee('LLAMA PE SA')->assertSee('sin credenciales');
        $this->assertFalse(EstudioDescargas::toca());

        // Desde ahí entra a esa empresa directo a su DBF de compras del mes, para revisarlo y descargarlo
        $this->get(route('estudio.descargas', ['periodo' => '202610']))->assertSee('DBF Compras');
        $this->post(route('estudio.cambiar'), ['ruc' => self::CON_CLAVES, 'ir' => '/sire/dbf?libro=compras&periodo=202610'])
            ->assertRedirect(url('/sire/dbf?libro=compras&periodo=202610'));
        $this->assertSame(self::CON_CLAVES, (string) $this->usuario->fresh()->IdEmpresa);
        $this->get('/sire/dbf?libro=compras&periodo=202610')->assertOk()->assertSee('134277');
    }

    public function test_reintentar_vuelve_a_pedir_lo_que_fallo_y_solo_el_administrador_programa(): void
    {
        $this->post(route('estudio.descargas.ahora'), ['periodo' => '202610'])->assertSessionHas('success');
        $this->assertSame(9, DB::table('estudio_descargas')->where('periodo', '202610')->count(), '3 empresas (con la del estudio) x 3 tareas.');

        // Le ponen las credenciales a la que faltaba y se reintenta: solo esa vuelve a la cola
        $this->post(route('estudio.configurar', self::SIN_CLAVES), ['client_id' => 'ID', 'client_secret' => 'S', 'sire_usuario' => 'SIRE02', 'sire_clave' => 'C']);
        $this->post(route('estudio.descargas.ahora'), ['periodo' => '202610']);
        $this->assertSame('PENDIENTE', DB::table('estudio_descargas')->where(['IdEmpresa' => self::SIN_CLAVES, 'tarea' => 'sire_compras'])->value('estado'));

        $contador = $this->usuario->replicate();
        $contador->email = 'contador'.uniqid().'@prueba.test';
        $contador->save();
        DB::table('role_user')->insert(['role_id' => 15, 'user_IdUsuario' => $contador->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        $this->post(route('estudio.accesos', self::CON_CLAVES), ['usuarios' => [$contador->IdUsuario]]);
        $this->actingAs($contador)->post(route('estudio.descargas.programacion'), ['activo' => 1, 'dia' => 5, 'hora' => 1])->assertForbidden();
    }
}
