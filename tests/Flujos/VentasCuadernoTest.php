<?php

namespace Tests\Flujos;

use App\Support\VentasCuaderno;
use Illuminate\Support\Facades\DB;

/** Pasar las ventas de un cuaderno (una venta por hoja) con la fecha del cuaderno, de una sola vez */
class VentasCuadernoTest extends FlujoTestCase
{
    private function cuaderno(float $total = 22.0): array
    {
        return ['fecha' => now()->subDay()->toDateString(), 'hojas' => [
            ['nombre' => 'Hoja 1', 'total' => $total, 'lineas' => [['ARROZ', 2, 3.50], ['JUEGO CARRITO', 1, 7.00], ['JUEGO CARRITO', 1, 8.00]]],
            ['nombre' => 'Hoja 2', 'total' => 1.75, 'lineas' => [['arroz ', 0.5, 3.50]]],
        ]];
    }

    private function cabeceras()
    {
        return DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->where('tdocod', '13');
    }

    public function test_revisa_sin_grabar_y_luego_graba_una_venta_por_hoja_con_su_fecha(): void
    {
        $this->producto('ARROZ', 3.50, 50);
        $this->producto('JUEGO CARRITO', 8.00, 50);
        $antes = $this->cabeceras()->count();

        $revision = VentasCuaderno::cargar($this->usuario, $this->cuaderno(), '13', false);
        $this->assertTrue($revision['ok'], implode("\n", $revision['mensajes']));
        $this->assertSame($antes, $this->cabeceras()->count(), 'La revisión no deja nada.');

        $r = VentasCuaderno::cargar($this->usuario, $this->cuaderno(), '13', true);
        $this->assertTrue($r['ok'], implode("\n", $r['mensajes']));
        $this->assertCount(2, $r['ventas']);
        $hoja1 = $this->cabecera($r['ventas'][0]);
        $this->assertSame(now()->subDay()->toDateString(), substr((string) $hoja1->ccafem, 0, 10));
        $this->assertEqualsWithDelta(22.0, (float) $hoja1->totalcontado, 0.01);
    }

    public function test_no_graba_nada_si_falta_un_producto_o_el_total_no_cuadra(): void
    {
        $this->producto('ARROZ', 3.50, 50);
        $antes = $this->cabeceras()->count();

        $r = VentasCuaderno::cargar($this->usuario, $this->cuaderno(), '13', true);
        $this->assertFalse($r['ok']);
        $this->assertContains('  - JUEGO CARRITO', $r['mensajes']);

        $this->producto('JUEGO CARRITO', 8.00, 50);
        $r = VentasCuaderno::cargar($this->usuario, $this->cuaderno(99), '13', true);
        $this->assertFalse($r['ok']);
        $this->assertSame($antes, $this->cabeceras()->count());
    }
}
