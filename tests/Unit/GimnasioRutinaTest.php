<?php

namespace Tests\Unit;

use App\Support\GimnasioRutina;
use PHPUnit\Framework\TestCase;

class GimnasioRutinaTest extends TestCase
{
    public function test_quita_los_dias_vacios_y_los_ejercicios_sin_nombre(): void
    {
        $dias = GimnasioRutina::limpiarDias([
            ['dia' => 'LUNES', 'grupo' => 'pecho', 'ejercicios' => [['nombre' => 'press de banca', 'series' => '4', 'reps' => '12'], ['nombre' => '  ']]],
            ['dia' => 'MARTES', 'grupo' => '', 'ejercicios' => [['nombre' => '']]],
            ['dia' => 'CUALQUIERA', 'grupo' => '', 'ejercicios' => [['nombre' => 'sentadilla']]],
        ]);

        $this->assertCount(2, $dias);
        $this->assertSame('PECHO', $dias[0]['grupo']);
        $this->assertSame(['PRESS DE BANCA'], array_column($dias[0]['ejercicios'], 'nombre'));
        $this->assertSame('DÍA 2', $dias[1]['dia']);
    }

    public function test_calentamiento_solo_guarda_maquinas_con_minutos(): void
    {
        $this->assertSame(['CAMINADORA' => 10, 'BICICLETA' => 5],
            GimnasioRutina::limpiarCalentamiento(['CAMINADORA' => '10', 'ELIPTICA' => 0, 'BICICLETA' => 5, 'ESCALADORA' => 500, 'OTRA' => 3]));
    }

    public function test_la_rutina_cubre_el_mes_completo(): void
    {
        $this->assertSame(['2026-02-01', '2026-02-28'], GimnasioRutina::mesDe('2026-02-15'));
    }
}
