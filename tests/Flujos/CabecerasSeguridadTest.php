<?php

namespace Tests\Flujos;

/** Cabeceras de seguridad: el sistema no se puede mostrar dentro de otra web, la carta pública sí */
class CabecerasSeguridadTest extends FlujoTestCase
{
    public function test_las_pantallas_del_sistema_no_se_pueden_incrustar_en_otra_web(): void
    {
        $this->get(route('comandas.seleccion'))->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_la_carta_publica_si_se_puede_incrustar(): void
    {
        $this->get(route('carta.ver', $this->sucursal))->assertHeaderMissing('X-Frame-Options');
    }
}
