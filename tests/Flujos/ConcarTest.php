<?php

namespace Tests\Flujos;

use App\Support\Concar;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Exportación a CONCAR: las ventas salen con las cuentas de sus productos, cada asiento cuadra y lo mal escrito se avisa */
class ConcarTest extends FlujoTestCase
{
    private function vender(object $producto, float $cantidad = 1): int
    {
        return VentaDirecta::registrar($this->usuario, $this->venta([[$producto, $cantidad]]), 'PV');
    }

    /** @return array<string, array{D: float, H: float}> sumas por cuenta */
    private function porCuenta(array $filas): array
    {
        $r = [];
        foreach ($filas as $f) {
            $r[$f[10]][$f[13]] = round(($r[$f[10]][$f[13]] ?? 0) + $f[14], 2);
        }

        return $r;
    }

    public function test_las_ventas_usan_las_cuentas_de_sus_productos_y_cada_asiento_cuadra(): void
    {
        $this->afectacion('20');   // exonerada (Amazonía): sin IGV
        $cuota = $this->producto('CUOTA ORDINARIA', 50);
        $sauna = $this->producto('SAUNA', 20);
        DB::table('productos')->where('IdProducto', $cuota->IdProducto)->update(['debe' => '145101', 'haber' => '759901']);
        DB::table('productos')->where('IdProducto', $sauna->IdProducto)->update(['debe' => '121201', 'haber' => '704110']);
        $this->vender($cuota, 2);
        $this->vender($sauna);

        $v = Concar::ventas($this->usuario->IdEmpresa, now()->format('Ym'));
        $this->assertSame(2, $v['asientos']);
        $cuentas = $this->porCuenta($v['filas']);
        $this->assertEquals(['D' => 100.0], $cuentas['145101']);
        $this->assertEquals(['H' => 100.0], $cuentas['759901']);
        $this->assertEquals(['D' => 20.0], $cuentas['121201']);
        $this->assertEquals(['H' => 20.0], $cuentas['704110']);
        $this->assertSame([], $v['revisar']);
    }

    public function test_una_cuenta_incompleta_no_sale_y_se_avisa(): void
    {
        $this->afectacion('20');
        $moto = $this->producto('MOTOCICLETA', 70);
        $otro = $this->producto('CUOTA', 30);
        DB::table('productos')->where('IdProducto', $moto->IdProducto)->update(['debe' => '121201', 'haber' => '70']);
        DB::table('productos')->where('IdProducto', $otro->IdProducto)->update(['debe' => '121201', 'haber' => '7599901']);
        $id = $this->vender($moto);
        DB::table('cpe_detalle')->where('IdCpe_cabecera', $id)->update(['debe' => '121201', 'haber' => '70']);   // así quedó guardada la venta
        $this->vender($otro);

        $v = Concar::ventas($this->usuario->IdEmpresa, now()->format('Ym'));
        $cuentas = $this->porCuenta($v['filas']);
        $this->assertArrayNotHasKey('70', $cuentas, 'La cuenta "70" no existe en el plan: CONCAR rechazaría el archivo.');
        $this->assertEquals(['H' => 70.0], $cuentas[Concar::DEFECTO['cta_ventas_exo']], 'Va a la cuenta general hasta corregir el producto.');
        $this->assertSame(['7599901'], array_column($v['revisar'], 'cuenta'), 'La cuenta con un dígito de más se avisa.');
        $this->assertSame(['MOTOCICLETA'], Concar::productosConCuentaIncompleta($this->usuario->IdEmpresa)->pluck('pronom')->all());

        $this->get(route('concar.index', ['periodo' => now()->format('Ym')]))->assertOk()
            ->assertSee('con la cuenta contable incompleta')->assertSee('Revisa estas cuentas antes de importar')->assertSee('7599901');
        $this->get(route('concar.excel', ['libro' => 'ventas', 'periodo' => now()->format('Ym')]))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_el_producto_no_acepta_una_cuenta_incompleta(): void
    {
        $p = $this->producto('MOTOCICLETA', 70);
        $this->put(route('productos.update', $p->IdProducto), ['haber' => '70'])->assertSessionHasErrors('haber');
    }
}
