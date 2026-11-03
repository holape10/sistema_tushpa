<?php

namespace Tests\Unit;

use App\Support\Clinica;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClinicaPagoAnteriorTest extends TestCase
{
    /** @return array<string, array{array<string, mixed>, bool}> */
    public static function pagos(): array
    {
        // Las fechas se arman al correr la prueba: otra prueba puede haber fijado "hoy"
        $ayer = 'AYER';

        return [
            'pago de hoy va a caja' => [['adelanto' => 50], true],
            'pago de hoy marcado como cobrado igual va a caja' => [['adelanto' => 50, 'fecha_hora' => 'HOY', 'ya_cobrado' => true], true],
            'pago de una fecha pasada sin marcar va a caja' => [['adelanto' => 50, 'fecha_hora' => $ayer, 'ya_cobrado' => false], true],
            'pago de una fecha pasada ya cobrado no va a caja' => [['adelanto' => 50, 'fecha_hora' => $ayer, 'ya_cobrado' => true], false],
            'sesión sin pago no va a caja' => [['adelanto' => 0, 'detalle' => 'Curación'], false],
        ];
    }

    #[DataProvider('pagos')]
    public function test_decide_si_el_pago_va_a_caja(array $datos, bool $esperado): void
    {
        $fechas = ['HOY' => Carbon::today()->format('Y-m-d 08:00'), 'AYER' => Carbon::yesterday()->format('Y-m-d 10:00')];
        if (isset($datos['fecha_hora'])) {
            $datos['fecha_hora'] = $fechas[$datos['fecha_hora']];
        }
        $this->assertSame($esperado, Clinica::vaACaja($datos));
    }
}
