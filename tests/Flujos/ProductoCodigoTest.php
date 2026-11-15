<?php

namespace Tests\Flujos;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** El código interno de un producto no se repite en la sucursal; el nombre sí puede repetirse */
class ProductoCodigoTest extends FlujoTestCase
{
    private function crear(string $nombre, string $codigo, array $extra = [])
    {
        return $this->post(route('productos.store'), $extra + ['promocion' => 0, 'pronom' => $nombre, 'procod' => $codigo, 'umecod' => 'NIU', 'propun' => 10]);
    }

    public function test_no_deja_crear_un_producto_con_un_codigo_que_ya_existe(): void
    {
        $this->crear('GASEOSA 500 ML', 'GAS-500')->assertSessionHasNoErrors();

        // El mismo código (aunque cambien mayúsculas o espacios) no pasa
        $this->crear('GASEOSA 1 LITRO', ' gas-500 ')->assertSessionHasErrors(['procod' => 'Ya existe un producto con el código gas-500: GASEOSA 500 ML. Usa otro código.']);
        $this->assertSame(1, DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('pronom', 'like', 'GASEOSA%')->count());

        // El nombre sí puede repetirse con otro código; y vacío, el código se genera solo
        $this->crear('GASEOSA 500 ML', 'GAS-501')->assertSessionHasNoErrors();
        $this->crear('GASEOSA 500 ML', '')->assertSessionHasNoErrors();
        $this->assertSame(3, DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('pronom', 'GASEOSA 500 ML')->count());

        // Tampoco el código de barras de otro producto (las cajas buscan por los dos)
        $this->crear('AGUA', 'AGUA-1', ['codigo_barra' => '7750001112223'])->assertSessionHasNoErrors();
        $this->crear('AGUA CON GAS', '7750001112223')->assertSessionHasErrors('procod');
    }

    /** Un Excel con la celda del precio arrastrada hacia abajo no le pone el mismo precio a todos sin confirmar */
    public function test_la_importacion_se_frena_si_pone_el_mismo_precio_a_muchos(): void
    {
        $csv = "Codigo,Nombre,Precio venta\n";
        foreach (range(1, 60) as $n) {
            $this->producto("CASE {$n}", 10 + $n);
            DB::table('productos')->where('pronom', "CASE {$n}")->update(['procod' => "C{$n}"]);
            $csv .= "C{$n},CASE {$n},352.36\n";
        }
        $subir = function (array $extra = []) use ($csv) {
            $ruta = tempnam(sys_get_temp_dir(), 'prod').'.csv';
            file_put_contents($ruta, $csv);

            return $this->post(route('productos.importar'), $extra + ['archivo' => new UploadedFile($ruta, 'productos.csv', 'text/csv', null, true)]);
        };

        $subir()->assertSessionHasErrors(['archivo' => 'No se importó nada: este Excel le pondría el mismo precio S/ 352.36 a 60 productos. Revisa la columna Precio (¿se arrastró una celda hacia abajo?). Si de verdad quieres ese precio para todos, vuelve a subirlo marcando «Sí, quiero poner el mismo precio a muchos productos».']);
        $this->assertSame(0, DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('propun', 352.36)->count(), 'Ningún precio cambió.');

        // Confirmado a propósito, sí se aplica
        $subir(['mismo_precio' => 1])->assertSessionHasNoErrors();
        $this->assertSame(60, DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('propun', 352.36)->count());
    }

    public function test_los_repetidos_que_ya_existian_se_siguen_editando(): void
    {
        $a = $this->producto('ARROZ', 4);
        $b = $this->producto('ARROZ EXTRA', 5);
        DB::table('productos')->whereIn('IdProducto', [$a->IdProducto, $b->IdProducto])->update(['procod' => 'ARR-1']);

        $this->put(route('productos.update', $b->IdProducto), ['pronom' => 'ARROZ EXTRA', 'propun' => 6])->assertSessionHasNoErrors();
        $this->assertEquals(6, DB::table('productos')->where('IdProducto', $b->IdProducto)->value('propun'));
    }
}
