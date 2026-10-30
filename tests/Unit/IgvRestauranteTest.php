<?php

namespace Tests\Unit;

use App\Support\Comprobante;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IgvRestauranteTest extends TestCase
{
    /** @return array<string, array{string, float}> */
    public static function anios(): array
    {
        return [
            '2025: 8% + 2% de IPM' => ['2025-06-15', 1.10],
            '2026: 8% + 2.5% de IPM' => ['2026-10-30', 1.105],
            '2027: 12% + 3% de IPM' => ['2027-01-01', 1.15],
            'desde 2028 vuelve el 18%' => ['2028-01-01', 1.18],
        ];
    }

    #[DataProvider('anios')]
    public function test_la_tasa_especial_cambia_con_el_anio(string $fecha, float $factor): void
    {
        $this->assertSame($factor, Comprobante::factorPara('SALON', true, Carbon::parse($fecha)));
    }

    public function test_el_punto_de_venta_comun_siempre_es_18(): void
    {
        $this->assertSame(1.18, Comprobante::factorPara('PV', true, Carbon::parse('2026-10-30')));
    }

    public function test_sin_el_igv_especial_el_restaurante_vende_con_18(): void
    {
        $this->assertSame(1.18, Comprobante::factorPara('HOTEL', false, Carbon::parse('2026-10-30')));
    }
}
