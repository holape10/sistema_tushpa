<?php

namespace Tests\Flujos;

use App\Support\Asistente\Asistente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Unit\CompraXmlTest;

/** Leer el comprobante de compra (XML, foto o PDF) para llenar el formulario; el usuario revisa y guarda */
class LecturaComprobanteTest extends FlujoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('central')->beginTransaction();
        config(['services.anthropic.limite_empresa' => 100, 'services.anthropic.tope_mensual_usd' => 20, 'services.anthropic.modelo' => 'claude-haiku-5-5']);
    }

    protected function tearDown(): void
    {
        Asistente::$falso = null;
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    /** La "IA" responde con estos datos leídos */
    private function iaLee(array $datos): void
    {
        Asistente::$falso = fn (array $p) => (object) ['stopReason' => 'end_turn',
            'content' => [(object) ['type' => 'text', 'text' => json_encode($datos)]],
            'usage' => (object) ['inputTokens' => 1800, 'outputTokens' => 400, 'cacheReadInputTokens' => 0, 'cacheCreationInputTokens' => 0]];
    }

    private function leido(): array
    {
        return session('compra_lectura');
    }

    public function test_el_xml_de_la_factura_se_lee_exacto_y_sin_ia(): void
    {
        $router = $this->producto('ROUTER WIFI', 0);
        $xml = UploadedFile::fake()->createWithContent('F001-77.xml', CompraXmlTest::factura((string) $this->usuario->IdEmpresa, 'F001-00000077', 'INTERNET DEDICADO JULIO 2026'));

        $this->post(route('compras.leer'), ['archivo' => $xml])->assertRedirect(route('compras.create', ['lectura' => 1]));
        $l = $this->leido();

        $this->assertSame(['01', 'F001', '77', '20553510661', 'LLAMA PE SA', 177.0], [$l['tdocod'], $l['serie'], $l['numero'], $l['proveedor']['num'], $l['proveedor']['nom'], $l['total_leido']]);
        // El router existe (2 a S/ 29.50 con IGV); el internet no es un producto: queda para que el usuario decida
        $this->assertSame([[$router->IdProducto, 2.0, 29.5]], array_map(fn ($i) => [$i['id'], $i['cantidad'], $i['costo']], $l['items']));
        $this->assertSame('INTERNET DEDICADO JULIO 2026', $l['pendientes'][0]['descripcion']);
        $this->assertSame([], $l['avisos']);
        $this->assertSame(0, DB::connection('central')->table('asistente_uso')->count(), 'El XML no usa la IA ni cuenta como pregunta.');
        $this->get(route('compras.create', ['lectura' => 1]))->assertOk();
    }

    public function test_la_foto_la_lee_la_ia_cuadra_con_el_total_y_cuenta_como_pregunta(): void
    {
        $arroz = $this->producto('ARROZ COSTEÑO 5KG', 0);
        $this->iaLee(['es_comprobante' => true, 'tipo' => '01', 'serie' => 'F002', 'numero' => '000123', 'fecha' => '2026-10-28', 'ruc_emisor' => '20100070970',
            'razon_emisor' => 'Supermercados Peruanos SA', 'moneda' => 'PEN', 'total' => 118.0, 'lineas' => [
                ['descripcion' => 'ARROZ COSTENO 5 KG', 'cantidad' => 4, 'unidad' => 'UND', 'importe' => 80.0],
                ['descripcion' => 'BOLSA PAPEL KRAFT', 'cantidad' => 10, 'unidad' => 'UND', 'importe' => 20.0]]]);

        $this->post(route('compras.leer'), ['archivo' => UploadedFile::fake()->image('factura.jpg', 1200, 1600)])->assertRedirect();
        $l = $this->leido();

        // Las líneas venían sin IGV (suman 100) y el total es 118: se ajustan al total
        $this->assertSame([[$arroz->IdProducto, 4.0, 23.6]], array_map(fn ($i) => [$i['id'], $i['cantidad'], $i['costo']], $l['items']));
        $this->assertSame(['BOLSA PAPEL KRAFT', 2.36], [$l['pendientes'][0]['descripcion'], $l['pendientes'][0]['costo']]);
        $this->assertSame(['123', '2026-10-28', '6', 'SUPERMERCADOS PERUANOS SA'], [$l['numero'], $l['fecha'], $l['proveedor']['tdicod'], $l['proveedor']['nom']]);
        $this->assertStringContainsString('revisa los números', $l['avisos'][0]);
        $uso = DB::connection('central')->table('asistente_uso')->latest('id')->first();
        $this->assertSame(['Lectura de comprobante (foto)', 'claude-haiku-5-5'], [$uso->pregunta, $uso->modelo]);
        $this->assertNull($uso->error);
    }

    public function test_aprende_que_producto_es_cada_linea_de_ese_proveedor(): void
    {
        $bolsa = $this->producto('BOLSA DE PAPEL MEDIANA', 0);
        $datos = ['es_comprobante' => true, 'tipo' => '12', 'serie' => 'T001', 'numero' => '9', 'fecha' => '2026-10-28', 'ruc_emisor' => '10456789012',
            'razon_emisor' => 'BAZAR LUCHO', 'moneda' => 'PEN', 'total' => 25.0, 'lineas' => [['descripcion' => 'BOLSA KRAFT N°8', 'cantidad' => 50, 'unidad' => '', 'importe' => 25.0]]];
        $this->iaLee($datos);
        $this->post(route('compras.leer'), ['archivo' => UploadedFile::fake()->image('t.jpg')]);
        $this->assertSame([], $this->leido()['items'], 'La primera vez no la reconoce.');

        // El usuario la buscó, eligió su producto y guardó la compra
        $this->postJson(route('compras.store'), ['tdocod' => '12', 'serie' => 'T001', 'numero' => '9', 'fecEmi' => '2026-10-28', 'fecIng' => '2026-10-28',
            'estadopago' => $this->contado, 'moneda' => 'PEN', 'id_almacen' => $this->almacen, 'prov_tdicod' => '1', 'prov_num' => '10456789012', 'prov_nom' => 'BAZAR LUCHO',
            'items' => [['id' => $bolsa->IdProducto, 'tip_igv' => '10', 'cantidad' => 50, 'costo' => 0.5]],
            'aprendidos' => [['descripcion' => 'BOLSA KRAFT N°8', 'id' => $bolsa->IdProducto]]])->assertJson(['estado' => 'success']);

        // La siguiente compra a ese proveedor ya la llena sola
        $this->iaLee(['numero' => '10'] + $datos);
        $this->post(route('compras.leer'), ['archivo' => UploadedFile::fake()->image('t2.jpg')]);
        $this->assertSame([$bolsa->IdProducto], array_column($this->leido()['items'], 'id'));
    }

    public function test_si_la_foto_no_se_entiende_avisa(): void
    {
        $this->iaLee(['es_comprobante' => false, 'tipo' => '00', 'serie' => '', 'numero' => '', 'fecha' => '', 'ruc_emisor' => '', 'razon_emisor' => '',
            'moneda' => 'PEN', 'total' => 0, 'lineas' => []]);

        $this->from(route('compras.create'))->post(route('compras.leer'), ['archivo' => UploadedFile::fake()->image('borrosa.jpg')])
            ->assertRedirect(route('compras.create'))->assertSessionHas('error');
        $this->assertNotNull(DB::connection('central')->table('asistente_uso')->latest('id')->value('error'));
        $this->post(route('compras.leer'), ['archivo' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('archivo');
    }
}
