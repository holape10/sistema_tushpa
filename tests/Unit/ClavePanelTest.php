<?php

namespace Tests\Unit;

use App\Support\Tenancy\ClavePanel;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ClavePanelTest extends TestCase
{
    public function test_quien_nunca_la_cambio_puede_cambiarla(): void
    {
        $this->assertTrue(ClavePanel::puedeCambiar(null, Carbon::parse('2027-01-10 09:00')));
        $this->assertNull(ClavePanel::proximoCambio(null, Carbon::parse('2027-01-10 09:00')));
    }

    public function test_dentro_de_la_semana_no_puede_y_sabe_desde_cuando(): void
    {
        $ultimo = Carbon::parse('2027-01-10 09:00');

        $this->assertFalse(ClavePanel::puedeCambiar($ultimo, Carbon::parse('2027-01-16 23:59')));
        $this->assertSame('2027-01-17 09:00', ClavePanel::proximoCambio($ultimo, Carbon::parse('2027-01-12 10:00'))->format('Y-m-d H:i'));
    }

    public function test_pasada_la_semana_puede_otra_vez(): void
    {
        $this->assertTrue(ClavePanel::puedeCambiar(Carbon::parse('2027-01-10 09:00'), Carbon::parse('2027-01-17 09:00')));
    }
}
