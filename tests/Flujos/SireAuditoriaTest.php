<?php

namespace Tests\Flujos;

use App\Support\Asistente\Asistente;
use App\Support\Sunat\Sire;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** «Compras con detalle»: sumas por tipo de documento contra SUNAT y los XML, por qué no cuadra, y el asistente IA del mes */
class SireAuditoriaTest extends FlujoTestCase
{
    private const RCE = 'RUC|Apellidos y Nombres o Razón social|Periodo|CAR SUNAT|Fecha de emisión|Fecha Vcto/Pago|Tipo CP/Doc.|Serie del CDP|Año|Nro CP o Doc. Nro Inicial (Rango)|Nro Final (Rango)|Tipo Doc Identidad|Nro Doc Identidad|Apellidos Nombres/ Razón  Social|BI Gravado DG|IGV / IPM DG|BI Gravado DGNG|IGV / IPM DGNG|BI Gravado DNG|IGV / IPM DNG|Valor Adq. NG|ISC|ICBPER|Otros Trib/ Cargos|Total CP|Moneda|Tipo de Cambio|Fecha Emisión Doc Modificado|Tipo CP Modificado|Serie CP Modificado|COD. DAM O DSI|Nro CP Modificado';

    protected function tearDown(): void
    {
        Asistente::$falso = null;
        parent::tearDown();
    }

    /** Propuesta del mes: 2 facturas, 1 nota de crédito (en negativo, como la da SUNAT) y 1 boleta; con sus XML */
    private function mes(): void
    {
        Storage::fake();
        $ruc = $this->usuario->IdEmpresa;
        $fila = fn (string $tipo, string $serie, string $num, string $prov, float $ng, array $ref = ['', '', '', '']) => "{$ruc}|WILCAT|202605|CAR|10/05/2026|10/05/2026|{$tipo}|{$serie}||{$num}||6|{$prov}|PROVEEDOR {$serie}|0.00|0.00|0.00|0.00|0.00|0.00|"
            .number_format($ng, 2, '.', '').'|0.00|0.00|0.00|'.number_format($ng, 2, '.', '')."|PEN|1.000|{$ref[0]}|{$ref[1]}|{$ref[2]}||{$ref[3]}";
        $filas = [$fila('01', 'F001', '10', '20100000001', 1000), $fila('01', 'F002', '20', '20100000002', 500),
            $fila('07', 'FC02', '168', '20100000003', -446, ['01/05/2026', '01', 'FT02', '27408']), $fila('03', 'B001', '5', '10400000004', 50)];
        $zip = new \ZipArchive;
        $ruta = tempnam(sys_get_temp_dir(), 'sire');
        $zip->open($ruta, \ZipArchive::OVERWRITE);
        $zip->addFromString('propuesta.txt', implode("\n", [self::RCE, ...$filas]));
        $zip->close();
        Storage::put("sire/{$ruc}/".Sire::COMPRAS.'/202605-1.zip', file_get_contents($ruta));
        DB::table('sire_solicitudes')->insert(['IdEmpresa' => $ruc, 'libro' => Sire::COMPRAS, 'periodo' => '202605', 'num_ticket' => '1',
            'cod_estado' => '06', 'archivo' => "sire/{$ruc}/".Sire::COMPRAS.'/202605-1.zip', 'filas' => 4, 'created_at' => now(), 'updated_at' => now()]);
        // XML: la F002 dice 510 (el proveedor cobró otra cosa); la nota de crédito, en positivo
        foreach ([['20100000001', '01', 'F001', '10', 1000], ['20100000002', '01', 'F002', '20', 510], ['20100000003', '07', 'FC02', '168', 446]] as [$emisor, $tipo, $serie, $numero, $total]) {
            DB::table('compras_xml')->insert(['IdEmpresa' => $ruc, 'ruc_emisor' => $emisor, 'tipo' => $tipo, 'serie' => $serie, 'numero' => $numero,
                'fecha' => '2026-05-10', 'moneda' => 'PEN', 'total' => $total, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function guardar(string $clave, array $datos): void
    {
        $this->postJson(route('sire.dbf.guardar'), ['libro' => 'compras', 'periodo' => '202605', 'clave' => $clave] + $datos)->assertOk();
    }

    public function test_suma_por_tipo_de_documento_y_dice_por_que_no_cuadra(): void
    {
        $this->mes();
        // La nota de crédito la ponen en positivo (así suma en vez de restar) y la boleta no se lleva
        $this->guardar('20100000003|07|FC02|168', ['montos' => ['no_gravado' => 446, 'total' => 446]]);
        $this->guardar('10400000004|03|B001|5', ['excluido' => true]);

        $a = $this->get(route('sire.detalle', ['periodo' => '202605']))->assertOk()
            ->assertSee('Sumas y cuadre (auditoría)')->assertSee('¿Por qué no cuadra?')->viewData('auditoria');

        // Por documento: lo que se lleva, SUNAT y XML (la nota de crédito resta en SUNAT y en el XML)
        $this->assertEquals([1500.0, 1500.0, 0.0, 1510.0, -10.0], [$a['filas']['01']['total'], $a['filas']['01']['sunat'], $a['filas']['01']['dif_sunat'], $a['filas']['01']['xml_de_llevan'], $a['filas']['01']['dif_xml']]);
        $this->assertEquals([446.0, -446.0, 892.0, -446.0, 892.0], [$a['filas']['07']['total'], $a['filas']['07']['sunat'], $a['filas']['07']['dif_sunat'], $a['filas']['07']['xml_de_llevan'], $a['filas']['07']['dif_xml']]);
        $this->assertEquals([0.0, 50.0, 1, 1], [$a['filas']['03']['total'], $a['filas']['03']['sunat'], $a['filas']['03']['excluidos'], $a['filas']['03']['cantidad']]);
        $this->assertEquals([1946.0, 1104.0, 842.0, 4, 3], [$a['todos']['total'], $a['todos']['sunat'], $a['todos']['dif_sunat'], $a['todos']['cantidad'], $a['todos']['llevan']]);

        // Por qué: la nota en positivo, la F002 distinta a su XML y la boleta que no se lleva
        $textos = collect($a['motivos'])->map(fn ($m) => $m['comprobante'].': '.$m['texto'])->implode("\n");
        $this->assertStringContainsString('FC02-168: Nota de crédito en positivo', $textos);
        $this->assertStringContainsString('F002-20: Distinto a su XML: el XML dice S/ 510.00', $textos);
        $this->assertStringContainsString('B001-5: No se lleva al .dbf', $textos);
        $this->assertTrue(collect($a['motivos'])->firstWhere('comprobante', 'FC02-168')['grave']);

        // Con la nota de vuelta en negativo, las notas cuadran con SUNAT y con su XML
        $this->guardar('20100000003|07|FC02|168', ['montos' => null]);
        $a = $this->get(route('sire.dbf', ['libro' => 'compras', 'periodo' => '202605']))->assertOk()->assertSee('Sumas y cuadre (auditoría)')->viewData('auditoria');
        $this->assertEquals([-446.0, 0.0, 0.0], [$a['filas']['07']['total'], $a['filas']['07']['dif_sunat'], $a['filas']['07']['dif_xml']]);
    }

    public function test_el_asistente_responde_con_los_datos_del_mes(): void
    {
        $this->mes();
        $enviado = null;
        Asistente::$falso = function (array $p) use (&$enviado) {
            $enviado = $p;

            return (object) ['stopReason' => 'end_turn', 'content' => [(object) ['type' => 'text', 'text' => 'La **FC02-168** resta S/ 446.00.']],
                'usage' => (object) ['inputTokens' => 900, 'outputTokens' => 40, 'cacheReadInputTokens' => 0, 'cacheCreationInputTokens' => 0]];
        };
        DB::connection('central')->beginTransaction();
        try {
            $this->get(route('sire.detalle', ['periodo' => '202605']))->assertOk()->assertSee('Pregúntale al asistente');
            $r = $this->postJson(route('sire.detalle.preguntar'), ['periodo' => '202605', 'pregunta' => '¿Cuánto resta la nota de crédito?'])->assertOk();
            $this->assertStringContainsString('<strong>FC02-168</strong>', $r->json('html'));
            // Lee el mes: cada comprobante y la auditoría
            $datos = $enviado['system'][1]['text'];
            $this->assertStringContainsString('07|FC02-168|2026-05-10|20100000003', $datos);
            $this->assertStringContainsString('AUDITORÍA POR TIPO', $datos);
            $this->assertSame('¿Cuánto resta la nota de crédito?', end($enviado['messages'])['content']);
        } finally {
            DB::connection('central')->rollBack();
        }
    }
}
