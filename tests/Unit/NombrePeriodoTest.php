<?php

namespace Tests\Unit;

use App\Support\Contabilidad\Contabilidad;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/** El periodo AAAAMM es siempre ese mes, aunque hoy sea 31 (antes un 31 de octubre convertía septiembre en octubre) */
class NombrePeriodoTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[TestWith(['2026-10-31', '202609', 'Septiembre 2026'])]
    #[TestWith(['2026-03-30', '202602', 'Febrero 2026'])]
    #[TestWith(['2026-01-31', '202511', 'Noviembre 2025'])]
    public function test_el_nombre_del_mes_no_depende_del_dia_de_hoy(string $hoy, string $periodo, string $esperado): void
    {
        Carbon::setTestNow($hoy.' 10:00:00');

        $this->assertSame($esperado, Contabilidad::nombrePeriodo($periodo));
    }
}
