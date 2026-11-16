<?php

namespace Tests\Flujos;

use App\Support\Tenancy\CorreccionProductos;
use Illuminate\Support\Facades\DB;

/** Corregir productos de una empresa con un Excel desde el panel: solo los que ya existen, por su código */
class CorreccionProductosTest extends FlujoTestCase
{
    private function csv(string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'corr').'.csv';
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    public function test_revisa_y_luego_corrige_precio_nombre_costo_estado_y_stock(): void
    {
        $base = DB::getDatabaseName();
        $a = $this->producto('CASE A', 10);
        $b = $this->producto('CASE B', 20, 5);
        DB::table('productos')->where('IdProducto', $a->IdProducto)->update(['procod' => 'COD-A']);
        DB::table('productos')->where('IdProducto', $b->IdProducto)->update(['procod' => 'COD-B']);
        $leido = CorreccionProductos::leer($this->csv("Código,Nombre,Costo,Precio venta,Estado,Stock\n"
            ."cod-a,,,15,,\n"
            ."COD-B,CASE B ORIGINAL,3,,Inactivo,0\n"
            ."NO-EXISTE,,,9,,\n"
            ."COD-A,,,-1,,\n"), 'csv');

        $this->assertSame(['nombre', 'costo', 'precio', 'estado', 'stock'], $leido['columnas']);
        $this->assertCount(1, $leido['errores'], 'El precio negativo se omite.');
        // Revisar no cambia nada
        $rev = CorreccionProductos::revisar($base, $leido['filas']);
        $this->assertSame([2, 1, 1, 1, 1, 1], [$rev['resumen']['encontrados'], $rev['resumen']['precio'], $rev['resumen']['nombre'], $rev['resumen']['costo'],
            $rev['resumen']['estado'], $rev['resumen']['stock']]);
        $this->assertSame(['NO-EXISTE'], $rev['no_encontrados']);
        $this->assertEquals(10, DB::table('productos')->where('IdProducto', $a->IdProducto)->value('propun'));

        $total = DB::table('productos')->count();
        CorreccionProductos::aplicar($base, $leido['filas'], 'dueno@prueba.test', 'correccion.csv');

        $this->assertEquals(15, DB::table('productos')->where('IdProducto', $a->IdProducto)->value('propun'));
        $fb = DB::table('productos')->where('IdProducto', $b->IdProducto)->first();
        $this->assertSame(['CASE B ORIGINAL', 3.0, 20.0, 'Inactivo'], [$fb->pronom, (float) $fb->costo, (float) $fb->propun, $fb->proest], 'El precio vacío no se toca.');
        $this->assertEquals(0, DB::table('producto_stock')->where('IdProducto', $b->IdProducto)->sum('stock'), 'El stock se corrige con un inventario.');
        $this->assertSame('PANEL', DB::table('inventario_cabecera')->latest('inv_cab_id')->value('origen'));
        $this->assertSame($total, DB::table('productos')->count(), 'No crea productos.');
        $this->assertStringContainsString('PANEL TUSHPA', (string) DB::table('bitacora')->where('accion', 'producto.corregir_panel')->value('usuario'));
        // Aplicado, una segunda revisión ya no encuentra nada que cambiar
        $this->assertSame(0, array_sum(array_intersect_key(CorreccionProductos::revisar($base, $leido['filas'])['resumen'], array_flip(['precio', 'nombre', 'costo', 'estado', 'stock']))));
    }

    public function test_avisa_si_pondria_el_mismo_precio_a_muchos_y_exige_la_columna_codigo(): void
    {
        $csv = "Código,Precio venta\n";
        foreach (range(1, 60) as $n) {
            $p = $this->producto("MICA {$n}", 5 + $n);
            DB::table('productos')->where('IdProducto', $p->IdProducto)->update(['procod' => "M{$n}"]);
            $csv .= "M{$n},352.36\n";
        }

        $rev = CorreccionProductos::revisar(DB::getDatabaseName(), CorreccionProductos::leer($this->csv($csv), 'csv')['filas']);

        $this->assertStringContainsString('mismo precio S/ 352.36 a 60 productos', (string) $rev['aviso']);
        $this->expectExceptionMessage('columna «Código»');
        CorreccionProductos::leer($this->csv("Nombre,Precio venta\nX,10\n"), 'csv');
    }
}
