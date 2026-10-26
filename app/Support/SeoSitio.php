<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * SEO de la web pública (tushpa.app): direcciones canónicas, páginas por ciudad, sitemap y datos estructurados (JSON-LD).
 * Las ciudades salen de config/ciudades.php.
 */
class SeoSitio
{
    /** Dirección del local: igual que en la portada y en el perfil de Google Business (nombre, dirección y teléfono deben coincidir) */
    private const DIRECCION = 'Jr. Raúl Pillco Pérez 285';

    private const DISTRITO = 'Iquitos';

    private const REGION = 'Loreto';

    /** Dirección absoluta (https) en el dominio principal; no depende de cómo llegó la visita */
    public static function url(string $ruta = '/'): string
    {
        $base = rtrim((string) config('tenancy.esquema', 'https').'://'.config('tenancy.dominio'), '/');

        return $base.'/'.ltrim($ruta, '/');
    }

    public static function rutaCiudad(string $slug): string
    {
        return '/sistema-de-facturacion-electronica-'.$slug;
    }

    /** @return array<int, string> */
    public static function slugsCiudades(): array
    {
        return array_keys((array) config('ciudades', []));
    }

    /**
     * Datos de una ciudad listos para la vista (con los días de prueba ya puestos), o null si no existe.
     *
     * @return array<string, mixed>|null
     */
    public static function ciudad(string $slug): ?array
    {
        $ciudad = config('ciudades.'.$slug);
        if (! is_array($ciudad)) {
            return null;
        }
        $ciudad['slug'] = $slug;
        $ciudad['ruta'] = self::rutaCiudad($slug);
        $ciudad['url'] = self::url($ciudad['ruta']);
        $ciudad['descripcion'] = self::conDias($ciudad['descripcion']);
        $ciudad['preguntas'] = array_map(fn (array $p): array => [$p[0], self::conDias($p[1])], $ciudad['preguntas']);

        return $ciudad;
    }

    /**
     * Páginas para el sitemap.xml.
     * La fecha de modificación sale de los archivos que definen cada página.
     *
     * @return array<int, array{loc: string, lastmod: string}>
     */
    public static function paginasSitemap(): array
    {
        $fecha = fn (string ...$archivos): string => date('Y-m-d', max(array_map(fn (string $a): int => (int) @filemtime($a), $archivos)) ?: time());
        $vista = fn (string $nombre): string => resource_path('views/sitio/'.$nombre.'.blade.php');

        $paginas = [
            ['loc' => self::url('/'), 'lastmod' => $fecha($vista('inicio'), $vista('partials/pie'), $vista('partials/menu'))],
            ['loc' => self::url('/tutorial'), 'lastmod' => $fecha($vista('tutorial'))],
        ];
        foreach (self::slugsCiudades() as $slug) {
            $paginas[] = ['loc' => self::url(self::rutaCiudad($slug)), 'lastmod' => $fecha($vista('ciudad'), config_path('ciudades.php'))];
        }
        // Cada tutorial (se crean en el panel)
        foreach (Tutoriales::activos() as $t) {
            $paginas[] = ['loc' => self::url('/tutorial/'.$t->slug), 'lastmod' => date('Y-m-d', strtotime((string) $t->updated_at) ?: time())];
        }

        return $paginas;
    }

    /** sitemap.xml (protocolo sitemaps.org) con las páginas de paginasSitemap() */
    public static function sitemapXml(): string
    {
        $filas = array_map(
            fn (array $p): string => '  <url><loc>'.htmlspecialchars($p['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc><lastmod>'.$p['lastmod'].'</lastmod></url>',
            self::paginasSitemap()
        );

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode("\n", $filas)."\n</urlset>\n";
    }

    /** @return array<string, mixed> */
    public static function organizacion(): array
    {
        $ciudades = array_map(fn (array $c): array => ['@type' => 'City', 'name' => $c['nombre']], array_values((array) config('ciudades', [])));

        return [
            '@type' => ['Organization', 'LocalBusiness'],
            '@id' => self::url('/').'#organizacion',
            'name' => 'TUSHPA',
            'legalName' => config('soporte.nombre'),
            'url' => self::url('/'),
            'logo' => asset('web/img/logo.webp'),
            'image' => asset('web/img/logo.webp'),
            'telephone' => '+'.config('soporte.whatsapp'),
            'email' => config('soporte.correo'),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => self::DIRECCION,
                'addressLocality' => self::DISTRITO,
                'addressRegion' => self::REGION,
                'addressCountry' => 'PE',
            ],
            'areaServed' => [...$ciudades, ['@type' => 'Country', 'name' => 'Perú']],
        ];
    }

    /** @return array<string, mixed> */
    public static function sitioWeb(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => self::url('/').'#sitio',
            'name' => 'TUSHPA',
            'url' => self::url('/'),
            'inLanguage' => 'es-PE',
            'publisher' => ['@id' => self::url('/').'#organizacion'],
        ];
    }

    /**
     * El sistema como aplicación. Los precios se publican solo si hay planes activos; nunca se inventan valoraciones.
     *
     * @param  iterable<int, object{precio: float|int|string}>  $planes
     * @return array<string, mixed>
     */
    public static function aplicacion(iterable $planes = [], string $descripcion = ''): array
    {
        $precios = (new Collection($planes))->pluck('precio')->map(fn ($p): float => (float) $p)->filter(fn (float $p): bool => $p > 0);

        $aplicacion = [
            '@type' => 'SoftwareApplication',
            '@id' => self::url('/').'#software',
            'name' => 'TUSHPA',
            'url' => self::url('/'),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'inLanguage' => 'es-PE',
            'publisher' => ['@id' => self::url('/').'#organizacion'],
        ];
        if ($descripcion !== '') {
            $aplicacion['description'] = $descripcion;
        }
        if ($precios->isNotEmpty()) {
            $aplicacion['offers'] = [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'PEN',
                'lowPrice' => $precios->min(),
                'highPrice' => $precios->max(),
                'offerCount' => $precios->count(),
            ];
        }

        return $aplicacion;
    }

    /**
     * El servicio ofrecido en una ciudad (datos de SeoSitio::ciudad()): le dice a Google dónde se atiende.
     *
     * @param  array<string, mixed>  $ciudad
     * @return array<string, mixed>
     */
    public static function servicio(array $ciudad): array
    {
        return [
            '@type' => 'Service',
            'name' => $ciudad['h1'],
            'serviceType' => 'Sistema de facturación electrónica',
            'url' => $ciudad['url'],
            'description' => $ciudad['descripcion'],
            'provider' => ['@id' => self::url('/').'#organizacion'],
            'areaServed' => [
                '@type' => 'City',
                'name' => $ciudad['nombre'],
                'containedInPlace' => ['@type' => 'AdministrativeArea', 'name' => $ciudad['region']],
            ],
        ];
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $preguntas  pares [pregunta, respuesta] en texto plano
     * @return array<string, mixed>
     */
    public static function preguntas(array $preguntas): array
    {
        return [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $p): array => [
                '@type' => 'Question',
                'name' => $p[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $p[1]],
            ], $preguntas),
        ];
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $migas  pares [nombre, dirección absoluta]
     * @return array<string, mixed>
     */
    public static function migas(array $migas): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $m, int $i): array => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $m[0],
                'item' => $m[1],
            ], $migas, array_keys($migas)),
        ];
    }

    /**
     * JSON-LD listo para pegar dentro de <script type="application/ld+json">.
     * Los < y > van escapados para que ningún texto pueda cerrar la etiqueta.
     *
     * @param  array<int, array<string, mixed>>  $nodos
     */
    public static function json(array $nodos): string
    {
        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => array_values($nodos)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );
    }

    private static function conDias(string $texto): string
    {
        return str_replace(':dias', (string) config('sitio.dias_prueba'), $texto);
    }
}
