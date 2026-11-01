<?php

namespace Tests\Unit;

use App\Support\MonitorErrores;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class MonitorErroresTest extends TestCase
{
    private function falla(string $mensaje): \RuntimeException
    {
        return new \RuntimeException($mensaje);   // misma línea: mismo lugar del código
    }

    public function test_el_mismo_error_con_otros_numeros_es_uno_solo(): void
    {
        $this->assertSame(MonitorErrores::huella($this->falla('Venta 1520 de S/ 35.50 falló')), MonitorErrores::huella($this->falla('Venta 98 de S/ 12.00 falló')));
        $this->assertNotSame(MonitorErrores::huella($this->falla('Venta 1 falló')), MonitorErrores::huella($this->falla('Stock 1 falló')));
    }

    public function test_no_se_avisa_lo_que_no_es_una_falla_del_sistema(): void
    {
        $this->assertTrue(MonitorErrores::ignorar(new NotFoundHttpException));
        $this->assertTrue(MonitorErrores::ignorar(new HttpException(403)));
        $this->assertTrue(MonitorErrores::ignorar(ValidationException::withMessages(['dni' => 'falta'])));
        $this->assertFalse(MonitorErrores::ignorar(new HttpException(500)));
        $this->assertFalse(MonitorErrores::ignorar(new \ErrorException('Undefined variable $total')));
    }
}
