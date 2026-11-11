<?php

namespace Tests\Flujos;

/** Guía de remisión: además de buscar productos, se agregan líneas escritas a mano */
class GuiaLineaLibreTest extends FlujoTestCase
{
    public function test_la_guia_tiene_el_boton_de_linea_libre(): void
    {
        $this->get(route('guias.crear'))->assertOk()->assertSee('+ Línea libre')->assertSee('lineaLibre()', false);
    }
}
