<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Support\Sunat\Certificado;
use Carbon\Carbon;
use RuntimeException;
use Tests\TestCase;

/** Certificado digital vencido: en producción no se firma ni se envía nada a SUNAT */
class CertificadoVigenteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function empresa(?string $vence, string $produccion = '1'): Empresa
    {
        return (new Empresa)->forceFill(['fec_fin_cer' => $vence, 'produccion' => $produccion]);
    }

    public function test_cuenta_los_dias_que_le_quedan(): void
    {
        $this->assertSame(30, Certificado::diasRestantes('2026-12-02'));
        $this->assertSame(0, Certificado::diasRestantes('2026-11-02'));
        $this->assertSame(-1, Certificado::diasRestantes('2026-11-01'));
        $this->assertNull(Certificado::diasRestantes(null));
    }

    public function test_el_ultimo_dia_todavia_se_puede_enviar(): void
    {
        Certificado::exigirVigente($this->empresa('2026-11-02'));
        Certificado::exigirVigente($this->empresa(null));

        $this->addToAssertionCount(1);
    }

    public function test_vencido_en_produccion_no_deja_enviar(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('venció el 01/11/2026');

        Certificado::exigirVigente($this->empresa('2026-11-01'));
    }

    public function test_en_beta_no_se_bloquea(): void
    {
        Certificado::exigirVigente($this->empresa('2026-11-01', '0'));

        $this->addToAssertionCount(1);
    }
}
