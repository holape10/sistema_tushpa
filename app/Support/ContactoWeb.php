<?php

namespace App\Support;

/**
 * Contacto que muestra la web pública (tushpa.app): el de un partner de ciudad (config/ciudades.php › contacto) o el
 * nuestro. Sale el del partner en la página de su ciudad y para quien entra desde su región (por la IP, aproximado).
 * La página de reseller siempre lleva nuestro contacto (es para trabajar con nosotros).
 */
class ContactoWeb
{
    /** @var array<string, mixed>|false|null false = ya se calculó y no hay partner */
    private static array|false|null $actual = null;

    /** @return array{slug: string, nombre: string, whatsapp: string, telefonos: array<int, string>, direccion: string, lugar: string}|null */
    public static function partner(): ?array
    {
        if (self::$actual === null) {
            self::$actual = self::calcular() ?? false;
        }

        return self::$actual ?: null;
    }

    private static function calcular(): ?array
    {
        $r = request();
        if ($r->routeIs('sitio.reseller')) {
            return null;
        }
        // En la página de una ciudad manda esa ciudad (con o sin partner)
        if ($r->routeIs('sitio.ciudad')) {
            $slug = (string) $r->route('ciudad');

            return self::deCiudad($slug);
        }
        $region = GeoIp::region($r->ip());
        foreach ((array) config('ciudades', []) as $slug => $c) {
            if (! empty($c['contacto']) && $region && strcasecmp($c['region'] ?? '', $region) === 0) {
                return self::deCiudad($slug);
            }
        }

        return null;
    }

    private static function deCiudad(string $slug): ?array
    {
        $c = config('ciudades.'.$slug.'.contacto');

        return is_array($c) ? $c + ['slug' => $slug] : null;
    }

    /**
     * Con partner, los enlaces de WhatsApp y el teléfono de la web pasan a ser los suyos (las vistas leen config('soporte.*')).
     * Lo llama el compositor de las vistas del sitio.
     */
    public static function aplicar(): ?array
    {
        $p = self::partner();
        if ($p) {
            config(['soporte.whatsapp' => $p['whatsapp'], 'soporte.telefono' => str_replace(' ', '', $p['telefonos'][0])]
                + (empty($p['correo']) ? [] : ['soporte.correo' => $p['correo']]));
        }

        return $p;
    }

    /** Para las pruebas: vuelve a calcular en la siguiente petición */
    public static function olvidar(): void
    {
        self::$actual = null;
    }
}
