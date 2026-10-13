<?php

namespace Tests\Feature;

use App\Support\SeoSitio;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

/**
 * SEO local de la web pública: páginas por ciudad, sitemap y datos estructurados.
 * La portada no se prueba aquí porque lee los planes de la base central.
 */
class SitioSeoTest extends TestCase
{
    /**
     * El multi-empresa se decide al arrancar la app (las rutas de la web pública solo existen con dominio).
     * Se activa por configuración y no por variable de entorno, porque Laravel vuelve a leer el .env en cada arranque
     * y pisaría el valor; así tampoco afecta a los demás tests.
     */
    public function createApplication()
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, fn ($app) => $app['config']->set('tenancy.dominio', 'tushpa.app'));
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_cada_ciudad_tiene_su_pagina_con_canonical_y_datos_estructurados(): void
    {
        foreach (SeoSitio::slugsCiudades() as $slug) {
            $ciudad = SeoSitio::ciudad($slug);
            $respuesta = $this->get('https://tushpa.app'.$ciudad['ruta']);

            $respuesta->assertOk();
            $respuesta->assertSee('<link rel="canonical" href="https://tushpa.app'.$ciudad['ruta'].'">', false);
            $respuesta->assertSee('<title>'.e($ciudad['titulo']).'</title>', false);
            $respuesta->assertSee($ciudad['h1']);

            preg_match('~<script type="application/ld\+json">(.+?)</script>~s', $respuesta->getContent(), $coincidencia);
            $grafo = json_decode($coincidencia[1] ?? '', true, flags: JSON_THROW_ON_ERROR);
            $tipos = collect($grafo['@graph'])->map(fn (array $nodo) => $nodo['@type'])->flatten()->all();

            $this->assertContains('Service', $tipos, "Falta el servicio en {$slug}");
            $this->assertContains('FAQPage', $tipos, "Faltan las preguntas en {$slug}");
            $this->assertContains('BreadcrumbList', $tipos, "Falta el recorrido en {$slug}");
        }
    }

    public function test_el_sitemap_lista_la_portada_y_todas_las_ciudades(): void
    {
        $respuesta = $this->get('https://tushpa.app/sitemap.xml');

        $respuesta->assertOk();
        $this->assertStringContainsString('application/xml', $respuesta->headers->get('Content-Type'));

        $xml = simplexml_load_string($respuesta->getContent());
        $this->assertNotFalse($xml, 'El sitemap no es un XML válido');
        $direcciones = array_map(fn ($url) => (string) $url->loc, iterator_to_array($xml->url, false));

        $this->assertContains('https://tushpa.app/', $direcciones);
        foreach (SeoSitio::slugsCiudades() as $slug) {
            $this->assertContains('https://tushpa.app'.SeoSitio::rutaCiudad($slug), $direcciones);
        }
    }

    public function test_los_titulos_y_descripciones_caben_en_los_resultados_de_google(): void
    {
        foreach (SeoSitio::slugsCiudades() as $slug) {
            $ciudad = SeoSitio::ciudad($slug);

            $this->assertLessThanOrEqual(60, mb_strlen($ciudad['titulo']), "Título muy largo en {$slug}");
            $this->assertLessThanOrEqual(160, mb_strlen($ciudad['descripcion']), "Descripción muy larga en {$slug}");
            $this->assertStringNotContainsString(':dias', $ciudad['descripcion']);
        }
    }

    public function test_los_textos_de_cada_ciudad_no_se_repiten(): void
    {
        $ciudades = array_map(fn (string $slug) => SeoSitio::ciudad($slug), SeoSitio::slugsCiudades());

        foreach (['titulo', 'descripcion', 'bajada'] as $campo) {
            $valores = array_column($ciudades, $campo);
            $this->assertSame(count($valores), count(array_unique($valores)), "El campo {$campo} se repite entre ciudades");
        }
        $parrafos = array_merge(...array_column($ciudades, 'parrafos'));
        $this->assertSame(count($parrafos), count(array_unique($parrafos)), 'Hay párrafos repetidos entre ciudades');
    }

    public function test_una_ciudad_que_no_existe_no_tiene_pagina(): void
    {
        $this->get('https://tushpa.app/sistema-de-facturacion-electronica-lima')->assertRedirect('/ingresar');
    }

    public function test_la_pagina_de_ingresar_no_se_indexa(): void
    {
        $this->get('https://tushpa.app/ingresar')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
    }
}
