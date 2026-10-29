<?php

namespace Tests\Unit;

use App\Support\Courier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CourierCotizacionTest extends TestCase
{
    /** Base S/ 10 con 1 kg incluido, S/ 2 el kilo adicional, sobre S/ 8, domicilio S/ 5 */
    private const TARIFA = ['base' => 10.0, 'kg_incluidos' => 1.0, 'por_kg' => 2.0, 'sobre' => 8.0, 'domicilio' => 5.0];

    /** @return array<string, array{array<int, array<string, mixed>>, float}> */
    public static function fletes(): array
    {
        return [
            'paquete dentro de los kilos incluidos paga la base' => [[['peso' => 0.8]], 10.0],
            'cada kilo adicional se suma' => [[['peso' => 3]], 14.0],
            'la fracción de kilo cuenta como kilo entero' => [[['peso' => 2.2]], 14.0],
            'varios iguales suman su peso' => [[['peso' => 1, 'cantidad' => 3]], 14.0],
            'un sobre paga su precio fijo' => [[['tipo' => 'SOBRE']], 8.0],
            'sobres y paquete se suman' => [[['tipo' => 'SOBRE', 'cantidad' => 2], ['peso' => 1]], 26.0],
            'caja liviana pero grande paga por su volumen' => [[['peso' => 1, 'largo' => 50, 'ancho' => 40, 'alto' => 30]], 32.0],
        ];
    }

    /** @param  array<int, array<string, mixed>>  $paquetes */
    #[DataProvider('fletes')]
    public function test_flete(array $paquetes, float $flete): void
    {
        $this->assertSame($flete, Courier::cotizar(self::TARIFA, $paquetes, 0, false)['flete']);
    }

    public function test_peso_volumetrico_se_cobra_solo_si_supera_al_real(): void
    {
        $precio = Courier::cotizar(self::TARIFA, [['peso' => 15, 'largo' => 50, 'ancho' => 40, 'alto' => 30]], 0, false);

        $this->assertSame(15.0, $precio['peso_real']);
        $this->assertSame(15.0, $precio['peso_cobrado']);
    }

    public function test_seguro_y_domicilio_se_suman_al_total(): void
    {
        $precio = Courier::cotizar(self::TARIFA, [['peso' => 1]], 500, true, 5000, 1, 3);

        $this->assertSame(5.0, $precio['seguro']);
        $this->assertSame(5.0, $precio['domicilio']);
        $this->assertSame(20.0, $precio['total']);
    }

    public function test_el_seguro_minimo_aplica_a_valores_pequenos(): void
    {
        $this->assertSame(3.0, Courier::cotizar(self::TARIFA, [['peso' => 1]], 50, false, 5000, 1, 3)['seguro']);
        $this->assertSame(0.0, Courier::cotizar(self::TARIFA, [['peso' => 1]], 0, false, 5000, 1, 3)['seguro']);
    }
}
