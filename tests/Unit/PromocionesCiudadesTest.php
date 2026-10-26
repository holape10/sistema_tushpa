<?php

namespace Tests\Unit;

use App\Support\Promociones;
use PHPUnit\Framework\TestCase;

class PromocionesCiudadesTest extends TestCase
{
    public function test_limpia_las_ciudades_y_pone_el_codigo_de_peru(): void
    {
        $this->assertSame([
            ['nombre' => 'Piura', 'whatsapp' => '51965123456'],
            ['nombre' => 'Iquitos', 'whatsapp' => null],
        ], Promociones::limpiarCiudades([
            ['nombre' => ' piura ', 'whatsapp' => '965 123 456'],
            ['nombre' => 'IQUITOS', 'whatsapp' => ''],
            ['nombre' => 'Piura', 'whatsapp' => '999999999'],
            ['nombre' => '', 'whatsapp' => '911111111'],
        ]));
    }

    public function test_reconoce_la_ciudad_de_la_pagina(): void
    {
        $this->assertSame('piura', Promociones::ciudadDeRuta('/sistema-de-facturacion-electronica-piura'));
        $this->assertNull(Promociones::ciudadDeRuta('registro'));
    }

    public function test_enlace_de_whatsapp_con_mensaje(): void
    {
        $enlace = Promociones::whatsapp((object) ['titulo' => '50% el primer mes'], ['nombre' => 'Piura', 'whatsapp' => '51965123456']);

        $this->assertStringStartsWith('https://wa.me/51965123456?text=', $enlace);
        $this->assertStringContainsString(rawurlencode('para Piura'), $enlace);
    }
}
