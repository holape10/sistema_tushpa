<?php

namespace Tests\Flujos;

use Illuminate\Support\Facades\DB;

/** La proforma del Punto de Venta sale sola en la impresora de caja (como los comprobantes) y se ve en pantalla */
class ProformaImpresionTest extends FlujoTestCase
{
    private function impresora(string $conexion = 'COMPARTIDO'): int
    {
        return DB::table('configuracion_impresoras')->insertGetId(['descripcion' => 'CAJA', 'ruta' => 'CAJA', 'IdEmpresa' => $this->usuario->IdEmpresa,
            'id_empresa_negocio' => $this->sucursal, 'tip_conex_imp' => $conexion, 'columnas' => 42, 'diseno' => 'CLASICO', 'activo' => 1, 'predeterminado' => 1]);
    }

    private function guardar(bool $imprimir): array
    {
        $cemento = $this->producto('CEMENTO SOL 42.5 KG', 32, 50);

        return $this->postJson(route('proformas.guardar'), ['origen' => 'PV', 'tdicod' => '1', 'clinum' => '00000000', 'clinom' => 'JUAN PEREZ',
            'items' => [['id' => $cemento->IdProducto, 'cantidad' => 3, 'precio' => 32]], 'imprimir' => $imprimir])
            ->assertJson(['estado' => 'success'])->json();
    }

    public function test_al_guardar_sale_en_la_ticketera_de_caja(): void
    {
        $imp = $this->impresora();

        $r = $this->guardar(true);

        $this->assertSame('CAJA', $r['impresora']);
        $cola = DB::table('cola_impresion')->where('id_impresora', $imp)->where('tipo', 'PROFORMA')->first();
        $this->assertNotNull($cola, 'La proforma no se mandó a la impresora.');
        $bytes = base64_decode($cola->contenido);
        $this->assertStringContainsString('PROFORMA', $bytes);
        $this->assertStringContainsString($r['numero'], $bytes);
        $this->assertStringContainsString('CEMENTO SOL 42.5 KG', $bytes);
        $this->assertStringContainsString('96.00', $bytes);

        // En pantalla (dentro de la ventana): sin los botones de la página suelta
        $this->get($r['imprimir'].'?embebida=1')->assertOk()->assertSee('CEMENTO SOL 42.5 KG')->assertDontSee('VER EN A4');
    }

    public function test_en_proformas_se_descarga_en_pdf_y_se_imprime_directo_como_en_el_panel_de_ventas(): void
    {
        $r = $this->guardar(false);
        $this->get(route('proformas.index'))->assertOk()->assertSee('📄 PDF')->assertSee('🧾 Ticket');

        $pdf = $this->get(route('proformas.pdf', $r['id']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // Sin impresora de caja: avisa que use Ticket o PDF
        $this->postJson(route('proformas.directo', $r['id']))->assertOk()->assertJson(['directa' => false]);
        // Con impresora: sale directo
        $imp = $this->impresora();
        $this->postJson(route('proformas.directo', $r['id']))->assertOk()->assertJson(['directa' => true]);
        $this->assertSame(1, DB::table('cola_impresion')->where('id_impresora', $imp)->where('tipo', 'PROFORMA')->count());
    }

    public function test_el_vendedor_que_solo_toma_pedidos_no_imprime_y_en_impresora_windows_va_en_pdf(): void
    {
        $win = $this->impresora('WINDOWS');

        $sinImprimir = $this->guardar(false);
        $this->assertNull($sinImprimir['impresora']);
        $this->assertSame(0, DB::table('cola_impresion')->count());

        $this->guardar(true);
        $this->assertStringStartsWith('%PDF', base64_decode(DB::table('cola_impresion')->where('id_impresora', $win)->value('contenido')));
    }
}
