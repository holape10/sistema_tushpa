<?php

namespace Tests\Unit;

use App\Support\Recetas;
use PHPUnit\Framework\TestCase;

class RecetasUnidadesTest extends TestCase
{
    public function test_el_kilo_se_escribe_en_gramos(): void
    {
        $this->assertEqualsWithDelta(0.22, Recetas::enUnidadDelInsumo(220, 'GRM', 'KGM'), 1e-9);
    }

    public function test_la_unidad_equivalente_del_insumo_se_puede_usar_en_la_receta(): void
    {
        // Huevos comprados por caja de 30: 2 huevos = 2/30 de caja
        $this->assertArrayHasKey('NIU', Recetas::unidadesPara('BX', 'NIU', 30));
        $this->assertEqualsWithDelta(2 / 30, Recetas::enUnidadDelInsumo(2, 'NIU', 'BX', 'NIU', 30), 1e-9);
    }

    public function test_saco_de_50_kilos_se_escribe_en_kilos_y_gramos(): void
    {
        $unidades = Recetas::unidadesPara('SA', 'KGM', 50);
        $this->assertEqualsWithDelta(1 / 50, $unidades['KGM'], 1e-9);
        $this->assertEqualsWithDelta(0.001 / 50, $unidades['GRM'], 1e-12);
    }

    public function test_sin_equivalencia_solo_su_unidad(): void
    {
        $this->assertSame(['BX' => 1], Recetas::unidadesPara('BX'));
        $this->assertSame(['BX' => 1], Recetas::unidadesPara('BX', 'NIU', 0));
    }
}
