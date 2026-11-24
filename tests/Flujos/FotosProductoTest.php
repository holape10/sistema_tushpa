<?php

namespace Tests\Flujos;

use App\Support\ImagenProducto;
use Illuminate\Support\Facades\DB;

/** Fotos de productos: las pesadas (sistema antiguo, subidas antes) se pasan a WEBP liviano; y salen en las ventanas del Punto de Venta */
class FotosProductoTest extends FlujoTestCase
{
    private string $carpeta = 'imagenes/productos/prueba_fotos';

    protected function tearDown(): void
    {
        foreach (glob(public_path($this->carpeta).'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir(public_path($this->carpeta));
        parent::tearDown();
    }

    public function test_una_foto_pesada_guardada_se_pasa_a_webp_liviano_con_miniatura(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('PHP sin GD/WEBP.');
        }
        @mkdir(public_path($this->carpeta), 0775, true);
        $png = $this->carpeta.'/23_original.png';
        $img = imagecreatetruecolor(1600, 1200);
        for ($i = 0; $i < 4000; $i++) {
            imagesetpixel($img, random_int(0, 1599), random_int(0, 1199), random_int(0, 0xFFFFFF));
        }
        imagepng($img, public_path($png));

        $nueva = ImagenProducto::optimizarGuardada($png);

        $this->assertStringEndsWith('.webp', $nueva);
        $this->assertFileDoesNotExist(public_path($png), 'La original pesada se borra.');
        [$ancho, $alto] = getimagesize(public_path($nueva));
        $this->assertLessThanOrEqual(ImagenProducto::LADO, max($ancho, $alto));
        $this->assertFileExists(public_path(ImagenProducto::rutaMini($nueva)));
        // Una que ya está liviana no se toca
        $this->assertSame($nueva, ImagenProducto::optimizarGuardada($nueva));
    }

    public function test_el_punto_de_venta_muestra_las_fotos_solo_si_la_sucursal_lo_activa(): void
    {
        $this->get(route('pv.index'))->assertOk()->assertSee('fotos: false', false);

        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['imagenes_venta' => 1]);
        $this->get(route('pv.index'))->assertOk()->assertSee('fotos: true', false);
    }
}
