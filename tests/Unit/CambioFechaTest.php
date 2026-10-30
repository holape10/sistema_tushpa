<?php

namespace Tests\Unit;

use App\Support\Sunat\CambioFecha;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CambioFechaTest extends TestCase
{
    /** @return array<string, array{string, string, string, bool}> */
    public static function comprobantes(): array
    {
        // Hoy es 30/10. El plazo empieza al día siguiente de la emisión: factura 3 días (la del 27 vale hasta hoy), boleta 7 (la del 23)
        return [
            'factura de ayer, a tiempo' => ['01', 'F001', '2026-10-29', false],
            'factura del 27: hoy es su último día' => ['01', 'F001', '2026-10-27', false],
            'factura del 26, fuera de plazo' => ['01', 'F001', '2026-10-26', true],
            'nota de crédito de factura fuera de plazo' => ['07', 'FC01', '2026-10-25', true],
            'boleta del 23: hoy es su último día' => ['03', 'B001', '2026-10-23', false],
            'boleta del 22, fuera de plazo' => ['03', 'B001', '2026-10-22', true],
        ];
    }

    #[DataProvider('comprobantes')]
    public function test_sabe_si_se_paso_el_plazo_para_enviarlo(string $tdocod, string $serie, string $fecha, bool $vencido): void
    {
        $cab = (object) ['tdocod' => $tdocod, 'serdoc' => $serie, 'ccafem' => $fecha];

        $this->assertSame($vencido, CambioFecha::vencido($cab, Carbon::parse('2026-10-30 10:00')));
    }

    public function test_la_fecha_corregida_es_el_dia_mas_antiguo_que_sunat_acepta(): void
    {
        $hoy = Carbon::parse('2026-10-30 10:00');

        // Una factura del 20: no pasa a hoy (30) sino al 27, antes de las facturas del 28 y 29
        $this->assertSame('2026-10-27', CambioFecha::fechaValida((object) ['tdocod' => '01', 'serdoc' => 'F001', 'ccafem' => '2026-10-20'], $hoy)->toDateString());
        $this->assertSame('2026-10-23', CambioFecha::fechaValida((object) ['tdocod' => '03', 'serdoc' => 'B001', 'ccafem' => '2026-10-15'], $hoy)->toDateString());
    }
}
