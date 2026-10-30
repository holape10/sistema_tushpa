<?php

namespace Tests\Unit;

use App\Support\CartaMesa;
use PHPUnit\Framework\TestCase;

class CartaMesaAjusteTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function lineas(): array
    {
        return [
            ['IdProducto' => 1, 'nombre' => 'CEVICHE CLÁSICO', 'cantidad' => 1, 'precio' => 25.0],
            ['IdProducto' => 2, 'nombre' => 'TACACHO CON CECINA', 'cantidad' => 1, 'precio' => 22.0],
            ['IdProducto' => 3, 'nombre' => 'CERVEZA CUSQUEÑA', 'cantidad' => 3, 'precio' => 10.0],
        ];
    }

    public function test_el_mozo_baja_una_cantidad_y_quita_un_producto(): void
    {
        $quedan = CartaMesa::ajustarLineas($this->lineas(), [1, 0, 2]);

        $this->assertSame(['CEVICHE CLÁSICO', 'CERVEZA CUSQUEÑA'], array_column($quedan, 'nombre'));
        $this->assertSame([1.0, 2.0], array_column($quedan, 'cantidad'));
        $this->assertSame(10.0, $quedan[1]['precio']);
    }

    public function test_sin_cambios_quedan_todas_las_lineas(): void
    {
        $this->assertCount(3, CartaMesa::ajustarLineas($this->lineas(), []));
    }

    public function test_no_puede_aumentar_lo_que_pidio_el_cliente(): void
    {
        $this->expectExceptionMessage('no aumentar');
        CartaMesa::ajustarLineas($this->lineas(), [2 => 4]);
    }

    public function test_quitar_todo_es_un_rechazo(): void
    {
        $this->expectExceptionMessage('Rechazar');
        CartaMesa::ajustarLineas($this->lineas(), [0, 0, 0]);
    }
}
