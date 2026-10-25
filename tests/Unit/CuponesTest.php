<?php

namespace Tests\Unit;

use App\Support\Cupones;
use PHPUnit\Framework\TestCase;

class CuponesTest extends TestCase
{
    public function test_el_codigo_se_guarda_en_mayusculas_y_sin_espacios(): void
    {
        $this->assertSame('VERANO10', Cupones::normalizar('  verano 10 '));
    }

    public function test_el_porcentaje_se_calcula_sobre_la_cuenta(): void
    {
        $this->assertSame(4.5, Cupones::monto((object) ['tipo' => 'P', 'valor' => 10], 45.0));
        $this->assertSame(3.33, Cupones::monto((object) ['tipo' => 'P', 'valor' => 10], 33.33));
    }

    public function test_el_cupon_en_soles_descuenta_su_valor(): void
    {
        $this->assertSame(5.0, Cupones::monto((object) ['tipo' => 'S', 'valor' => '5.00'], 80.0));
    }

    public function test_texto_del_descuento(): void
    {
        $this->assertSame('10%', Cupones::texto((object) ['tipo' => 'P', 'valor' => '10.00']));
        $this->assertSame('12.5%', Cupones::texto((object) ['tipo' => 'P', 'valor' => '12.50']));
        $this->assertSame('S/ 5.00', Cupones::texto((object) ['tipo' => 'S', 'valor' => '5']));
    }
}
