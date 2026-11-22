<?php

namespace Tests\Flujos;

use App\Support\Impresion\CodigoBarras;
use Illuminate\Support\Facades\DB;

/** Productos › Etiquetas: reimprimir el código de barras registrado en la ticketera, en una impresora de etiquetas o en PDF */
class EtiquetasProductoTest extends FlujoTestCase
{
    private function impresora(string $conexion = 'COMPARTIDO'): int
    {
        return DB::table('configuracion_impresoras')->insertGetId(['descripcion' => 'ETIQUETAS', 'ruta' => 'ETIQUETAS', 'IdEmpresa' => $this->usuario->IdEmpresa,
            'id_empresa_negocio' => $this->sucursal, 'tip_conex_imp' => $conexion, 'columnas' => 42, 'diseno' => 'CLASICO', 'activo' => 1]);
    }

    private function conCodigo(string $nombre, string $codigo): int
    {
        $p = $this->producto($nombre, 12.5);
        DB::table('productos')->where('IdProducto', $p->IdProducto)->update(['codigo_barra' => $codigo]);

        return (int) $p->IdProducto;
    }

    public function test_la_ticketera_recibe_el_codigo_de_barras_por_cada_copia(): void
    {
        $imp = $this->impresora();
        $id = $this->conCodigo('GASEOSA 500ML', '7751234567890');

        $this->get(route('productos.etiquetas'))->assertOk()->assertSee('Imprimir etiquetas');
        $this->getJson(route('productos.etiquetas.buscar', ['q' => '7751234567890']))->assertOk()->assertJsonPath('0.id', $id);

        $this->postJson(route('productos.etiquetas.imprimir'), ['items' => [['id' => $id, 'cantidad' => 3]], 'destino' => (string) $imp])
            ->assertOk()->assertJsonPath('ok', true);

        $bytes = base64_decode(DB::table('cola_impresion')->where('id_impresora', $imp)->value('contenido'));
        $codigo = CodigoBarras::datosEscpos('7751234567890');
        $this->assertSame(3, substr_count($bytes, "\x1Dk\x49".chr(strlen($codigo)).$codigo), 'Una etiqueta con el código de barras por cada copia.');
        $this->assertStringContainsString('GASEOSA 500ML', $bytes);
        $this->assertStringContainsString('S/ 12.50', $bytes);
    }

    public function test_en_pdf_sale_una_hoja_por_etiqueta_y_la_impresora_windows_recibe_el_pdf(): void
    {
        $id = $this->conCodigo('ARROZ 1KG', 'ABC-123');

        $pdf = $this->post(route('productos.etiquetas.imprimir'), ['items' => [['id' => $id, 'cantidad' => 2]], 'destino' => 'pdf', 'tamano' => '40x30'])
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(2, preg_match_all('#/Type\s*/Page[^s]#', $pdf));

        $win = $this->impresora('WINDOWS');
        $this->postJson(route('productos.etiquetas.imprimir'), ['items' => [['id' => $id, 'cantidad' => 1]], 'destino' => (string) $win])->assertOk();
        $this->assertStringStartsWith('%PDF', base64_decode(DB::table('cola_impresion')->where('id_impresora', $win)->value('contenido')));
    }

    public function test_sin_codigo_de_barras_solo_sale_si_se_pide_el_codigo_interno(): void
    {
        $imp = $this->impresora();
        $producto = $this->producto('SIN CODIGO', 5);

        $this->postJson(route('productos.etiquetas.imprimir'), ['items' => [['id' => $producto->IdProducto, 'cantidad' => 1]], 'destino' => (string) $imp])
            ->assertStatus(422);
        $this->assertSame(0, DB::table('cola_impresion')->count());

        $this->postJson(route('productos.etiquetas.imprimir'), ['items' => [['id' => $producto->IdProducto, 'cantidad' => 1]], 'destino' => (string) $imp, 'usar_interno' => true])
            ->assertOk();
        $this->assertStringContainsString(CodigoBarras::datosEscpos($producto->procod), base64_decode(DB::table('cola_impresion')->value('contenido')));
    }

    public function test_no_imprime_productos_de_otra_sucursal(): void
    {
        $imp = $this->impresora();
        $id = $this->conCodigo('AJENO', '123456');
        DB::table('productos')->where('IdProducto', $id)->update(['id_empresa_negocio' => $this->sucursal + 100000]);

        $this->postJson(route('productos.etiquetas.imprimir'), ['items' => [['id' => $id, 'cantidad' => 1]], 'destino' => (string) $imp])->assertStatus(422);
        $this->assertSame(0, DB::table('cola_impresion')->count());
    }
}
