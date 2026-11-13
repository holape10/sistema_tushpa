<?php

namespace Tests\Unit;

use App\Support\Contabilidad\CuentasCompras;
use Tests\TestCase;

/** El centro de costo de las compras está apagado por defecto: sin él, nada se bloquea */
class CentroCostoApagadoTest extends TestCase
{
    public function test_apagado_ninguna_cuenta_de_gasto_pide_centro_de_costo(): void
    {
        config(['services.contabilidad.centro_costo' => false]);
        $this->assertFalse(CuentasCompras::requiereCentro('63691'));

        config(['services.contabilidad.centro_costo' => true]);
        $this->assertTrue(CuentasCompras::requiereCentro('63691'));
        $this->assertFalse(CuentasCompras::requiereCentro('601111'), 'Las 60 no lo llevan.');
    }
}
