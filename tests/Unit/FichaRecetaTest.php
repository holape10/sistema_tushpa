<?php

namespace Tests\Unit;

use App\Support\FichaReceta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FichaRecetaTest extends TestCase
{
    /** @return array<string, array{float, string, float, string}> */
    public static function casos(): array
    {
        return [
            'menos de un kilo va en gramos' => [0.15, 'KGM', 150.0, 'GRM'],
            'menos de un litro va en mililitros' => [0.04, 'LTR', 40.0, 'MLT'],
            'un kilo o más se queda en kilos' => [1.5, 'KGM', 1.5, 'KGM'],
            'onzas no cambian' => [3.0, 'ONZ', 3.0, 'ONZ'],
            'media unidad no cambia' => [0.5, 'NIU', 0.5, 'NIU'],
        ];
    }

    #[DataProvider('casos')]
    public function test_cantidad_legible_para_la_cocina(float $cantidad, string $ume, float $esperada, string $umeEsperada): void
    {
        $this->assertSame([$esperada, $umeEsperada], FichaReceta::legible($cantidad, $ume));
    }
}
