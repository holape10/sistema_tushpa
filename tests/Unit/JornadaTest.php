<?php

namespace Tests\Unit;

use App\Support\PanelDueno;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class JornadaTest extends TestCase
{
    public function test_de_madrugada_sigue_siendo_la_noche_anterior(): void
    {
        [$desde, $hasta] = PanelDueno::jornada(0, Carbon::parse('2026-10-26 06:30'));

        $this->assertSame('2026-10-25 12:00:00', $desde->toDateTimeString());
        $this->assertSame('2026-10-26 06:30:00', $hasta->toDateTimeString());
    }

    public function test_pasado_el_mediodia_empieza_una_jornada_nueva(): void
    {
        [$desde] = PanelDueno::jornada(0, Carbon::parse('2026-10-26 15:00'));

        $this->assertSame('2026-10-26 12:00:00', $desde->toDateTimeString());
    }

    public function test_la_jornada_anterior_es_completa(): void
    {
        [$desde, $hasta] = PanelDueno::jornada(1, Carbon::parse('2026-10-26 15:00'));

        $this->assertSame('2026-10-25 12:00:00', $desde->toDateTimeString());
        $this->assertSame('2026-10-26 11:59:59', $hasta->toDateTimeString());
    }
}
