<?php

namespace Tests\Unit;

use App\Support\MetasMozo;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class MetasMozoTest extends TestCase
{
    public function test_el_mozo_sin_meta_propia_usa_la_de_todos(): void
    {
        $metas = collect([0 => (object) ['diaria' => '500.00', 'semanal' => '3000.00', 'mensual' => null]]);

        $this->assertSame(['dia' => 500.0, 'semana' => 3000.0, 'mes' => null], MetasMozo::metaDe($metas, 7));
    }

    public function test_la_meta_propia_manda_y_lo_vacio_toma_la_general(): void
    {
        $metas = collect([
            0 => (object) ['diaria' => '500.00', 'semanal' => '3000.00', 'mensual' => '12000.00'],
            7 => (object) ['diaria' => '800.00', 'semanal' => null, 'mensual' => null],
        ]);

        $this->assertSame(['dia' => 800.0, 'semana' => 3000.0, 'mes' => 12000.0], MetasMozo::metaDe($metas, 7));
    }

    public function test_avance_y_lo_que_falta(): void
    {
        $this->assertSame(['vendido' => 350.0, 'meta' => 1000.0, 'pct' => 35.0, 'falta' => 650.0], MetasMozo::avance(350.0, 1000.0));
        $this->assertSame(0.0, MetasMozo::avance(1200.0, 1000.0)['falta']);
        $this->assertNull(MetasMozo::avance(300.0, null)['pct']);
    }

    public function test_la_semana_va_de_lunes_a_domingo(): void
    {
        $rangos = MetasMozo::rangos(Carbon::parse('2026-10-25 15:00')); // domingo

        $this->assertSame('2026-10-19', $rangos['semana'][0]->toDateString());
        $this->assertSame('2026-10-25', $rangos['semana'][1]->toDateString());
        $this->assertSame('2026-10-01', $rangos['mes'][0]->toDateString());
    }
}
