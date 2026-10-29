<?php

namespace Tests\Unit;

use App\Support\KardexSunat;
use PHPUnit\Framework\TestCase;

class KardexSunatTest extends TestCase
{
    public function test_promedio_ponderado_recalcula_el_costo_con_cada_compra(): void
    {
        $s = KardexSunat::saldoVacio();
        KardexSunat::entrar($s, 10, 5, '1');
        KardexSunat::entrar($s, 10, 7, '1');

        $this->assertSame([20.0, 6.0, 120.0], KardexSunat::foto($s));
        $this->assertSame([6.0, 30.0], KardexSunat::salir($s, 5, '1'));
        $this->assertSame([15.0, 6.0, 90.0], KardexSunat::foto($s));
    }

    public function test_peps_saca_primero_lo_mas_antiguo(): void
    {
        $s = KardexSunat::saldoVacio();
        KardexSunat::entrar($s, 10, 5, '2');
        KardexSunat::entrar($s, 10, 7, '2');

        [$unitario, $total] = KardexSunat::salir($s, 15, '2');

        $this->assertSame(85.0, $total);               // 10 a S/ 5 + 5 a S/ 7
        $this->assertEqualsWithDelta(5.66666667, $unitario, 0.000001);
        $this->assertSame([5.0, 7.0, 35.0], KardexSunat::foto($s));   // quedan los de S/ 7
    }

    public function test_peps_con_stock_negativo_lo_cubre_la_siguiente_compra(): void
    {
        $s = KardexSunat::saldoVacio(4);
        $this->assertSame([4.0, 12.0], KardexSunat::salir($s, 3, '2'));   // sin stock: al último costo
        $this->assertSame([-3.0, 4.0, -12.0], KardexSunat::foto($s));

        KardexSunat::entrar($s, 5, 6, '2');

        $this->assertSame([2.0, 6.0, 12.0], KardexSunat::foto($s));
    }

    public function test_una_devolucion_sin_costo_entra_al_costo_del_momento(): void
    {
        $s = KardexSunat::saldoVacio();
        KardexSunat::entrar($s, 4, 10, '1');

        $this->assertSame([10.0, 20.0], KardexSunat::entrar($s, 2, 0, '1'));
    }

    public function test_en_peps_la_devolucion_vuelve_al_costo_con_que_salio(): void
    {
        $s = KardexSunat::saldoVacio();
        KardexSunat::entrar($s, 5, 1, '2');
        KardexSunat::entrar($s, 5, 2, '2');
        KardexSunat::salir($s, 3, '2');                               // salen 3 a S/ 1

        $this->assertSame([1.0, 1.0], KardexSunat::entrar($s, 1, 0, '2'));
    }

    public function test_numeros_del_ple_sin_comas_y_con_dos_decimales_como_minimo(): void
    {
        $this->assertSame('1234.50', KardexSunat::numero(1234.5));
        $this->assertSame('12.50', KardexSunat::numero(12.5, 8));
        $this->assertSame('0.125', KardexSunat::numero(0.125, 8));
        $this->assertSame('-3.00', KardexSunat::numero(-3));
        $this->assertSame('0.00', KardexSunat::numero(-0.0));
    }

    public function test_nombre_oficial_del_archivo_del_ple(): void
    {
        $this->assertSame('LE2060580056520260900130100001111.txt', KardexSunat::nombreArchivo('20605800565', '2026-09', '131', true));
        $this->assertSame('LE2060580056520260900120100000011.txt', KardexSunat::nombreArchivo('20605800565', '2026-09', '121', false));
    }

    public function test_codigo_de_existencia_para_el_campo_9_del_ple(): void
    {
        $this->assertSame('5020230100000000', KardexSunat::codigoExistencia('50202301'));        // UNSPSC + 8 ceros
        $this->assertSame('1010150700278175', KardexSunat::codigoExistencia('1010150700278175')); // ítem CUBSO tal cual
        $this->assertSame('', KardexSunat::codigoExistencia('123'));
        $this->assertSame('', KardexSunat::codigoExistencia(null));
    }

    public function test_tipo_de_existencia_segun_el_producto(): void
    {
        $this->assertSame('01', KardexSunat::tipoExistencia((object) ['tipo_existencia' => null, 'promocion' => 0]));
        $this->assertSame('03', KardexSunat::tipoExistencia((object) ['tipo_existencia' => null, 'promocion' => 4]));
        $this->assertSame('07', KardexSunat::tipoExistencia((object) ['tipo_existencia' => '07', 'promocion' => 0]));
    }
}
