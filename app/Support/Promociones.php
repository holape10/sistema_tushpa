<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Promociones de la web pública (tushpa.app) que se administran desde el panel */
class Promociones
{
    /** Lado mayor de las imágenes de promociones: la ventana mide 440 px (el doble para pantallas nítidas) */
    private const LADO_IMAGEN = 900;

    /**
     * Achica la imagen de una promoción y la pasa a WEBP para que la web cargue rápido (un afiche de 1 MB pasa a ~60 KB).
     * Los GIF se dejan igual (pueden ser animados). Si no se puede convertir, queda la original.
     */
    public static function optimizarImagen(string $ruta): string
    {
        $origen = public_path($ruta);
        if (! is_file($origen) || str_ends_with(strtolower($ruta), '.gif')) {
            return $ruta;
        }
        $nueva = preg_replace('/\.[a-z0-9]+$/i', '', $ruta).'-'.self::LADO_IMAGEN.'.webp';
        if (! ImagenProducto::redimensionar($origen, public_path($nueva), self::LADO_IMAGEN)) {
            return $ruta;
        }
        @unlink($origen);

        return $nueva;
    }

    public const PAGINAS = ['todas' => 'Todas las páginas', 'inicio' => 'Página principal', 'registro' => 'Registro (prueba gratis)',
        'carta' => 'Crea tu carta', 'tutorial' => 'Tutoriales', 'ciudades' => 'Páginas de ciudades', 'ingresar' => 'Ingresar'];

    public const FRECUENCIAS = ['SIEMPRE' => 'Cada vez que entra', 'SESION' => 'Una vez por visita', 'DIA' => 'Una vez al día', 'UNA_VEZ' => 'Solo una vez'];

    public static function db(): Builder
    {
        return DB::connection('central')->table('web_promociones');
    }

    /** Qué página de la web es esta ruta */
    public static function pagina(string $ruta): ?string
    {
        $ruta = trim($ruta, '/');

        return match (true) {
            $ruta === '' => 'inicio',
            str_starts_with($ruta, 'registro') => 'registro',
            str_starts_with($ruta, 'crea-tu-carta') => 'carta',
            str_starts_with($ruta, 'tutorial') => 'tutorial',
            str_starts_with($ruta, 'sistema-de-facturacion-electronica-') => 'ciudades',
            str_starts_with($ruta, 'ingresar') => 'ingresar',
            default => null,
        };
    }

    /** Promociones vigentes para la página actual (la carta pública de un negocio y su lista no llevan publicidad) */
    public static function para(string $ruta): Collection
    {
        $pagina = self::pagina($ruta);
        try {
            if (! $pagina || ! Schema::connection('central')->hasTable('web_promociones')) {
                return collect();
            }
            $hoy = now()->toDateString();

            $ciudad = self::ciudadDeRuta($ruta);

            return self::db()->where('activo', true)
                ->where(fn ($q) => $q->whereNull('desde')->orWhere('desde', '<=', $hoy))
                ->where(fn ($q) => $q->whereNull('hasta')->orWhere('hasta', '>=', $hoy))
                ->orderBy('orden')->orderByDesc('id')->get()
                ->filter(function ($p) use ($pagina) {
                    $paginas = json_decode((string) $p->paginas, true) ?: ['todas'];

                    return in_array('todas', $paginas, true) || in_array($pagina, $paginas, true);
                })
                // Por ciudad: en la página de una ciudad solo salen las que valen ahí (y solo con el WhatsApp de esa ciudad)
                ->map(function ($p) use ($ciudad) {
                    $p->ciudades_lista = self::ciudades($p);
                    if ($ciudad && $p->ciudades_lista) {
                        $p->ciudades_lista = array_values(array_filter($p->ciudades_lista, fn ($c) => self::slug($c['nombre']) === $ciudad));
                        $p->fuera_de_ciudad = ! $p->ciudades_lista;
                    }

                    return $p;
                })
                ->reject(fn ($p) => ! empty($p->fuera_de_ciudad))
                // Una ventana y una barra a la vez como máximo
                ->groupBy('tipo')->map(fn ($g) => $g->first())->values();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Ciudades donde vale la promoción, con su WhatsApp.
     *
     * @return array<int, array{nombre: string, whatsapp: ?string}>
     */
    public static function ciudades(object $p): array
    {
        return json_decode((string) ($p->ciudades ?? ''), true) ?: [];
    }

    /**
     * Ciudades que llegan del formulario: con nombre, sin repetir, y el WhatsApp con el código de Perú.
     *
     * @return array<int, array{nombre: string, whatsapp: ?string}>
     */
    public static function limpiarCiudades(array $filas): array
    {
        return collect($filas)->map(function ($c) {
            $n = preg_replace('/\D/', '', (string) ($c['whatsapp'] ?? ''));

            return ['nombre' => mb_convert_case(trim((string) ($c['nombre'] ?? '')), MB_CASE_TITLE),
                'whatsapp' => $n === '' ? null : (strlen($n) === 9 ? '51'.$n : $n)];
        })->filter(fn ($c) => $c['nombre'] !== '')->unique(fn ($c) => self::slug($c['nombre']))->values()->all();
    }

    /** La ciudad de la página de SEO local (tushpa.app/sistema-de-facturacion-electronica-piura → piura) */
    public static function ciudadDeRuta(string $ruta): ?string
    {
        return preg_match('/^sistema-de-facturacion-electronica-([a-z0-9-]+)$/', trim($ruta, '/'), $m) ? $m[1] : null;
    }

    public static function slug(string $nombre): string
    {
        return Str::slug($nombre);
    }

    /** Enlace de WhatsApp de una ciudad, con el mensaje listo */
    public static function whatsapp(object $p, array $ciudad): ?string
    {
        return $ciudad['whatsapp'] ? 'https://wa.me/'.$ciudad['whatsapp'].'?text='.rawurlencode('Hola, vi la promoción "'.$p->titulo.'" para '.$ciudad['nombre'].' y quiero más información.') : null;
    }

    /** A dónde lleva el botón: su enlace, o su propia página de oferta */
    /** ¿La imagen se muestra entera (afiche) en vez de recortada? */
    public static function imagenCompleta(object $p): bool
    {
        return ($p->imagen_ajuste ?? 'RECORTAR') === 'COMPLETA';
    }

    /** Estilo de la imagen: entera, o recortada mostrando la parte elegida en el panel */
    public static function estiloImagen(object $p): string
    {
        if (self::imagenCompleta($p)) {
            return 'object-fit: contain; background: #0f172a;';
        }
        [$x, $y] = array_map('intval', explode(' ', (string) ($p->imagen_pos ?? '50 50')) + [50, 50]);

        return 'object-fit: cover; object-position: '.max(0, min(100, $x)).'% '.max(0, min(100, $y)).'%;';
    }

    public static function destino(object $p): ?string
    {
        return $p->boton_url ?: ($p->con_pagina && $p->slug ? route('sitio.oferta', $p->slug) : null);
    }
}
