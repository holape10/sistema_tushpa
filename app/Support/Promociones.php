<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Promociones de la web pública (tushpa.app) que se administran desde el panel */
class Promociones
{
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

            return self::db()->where('activo', true)
                ->where(fn ($q) => $q->whereNull('desde')->orWhere('desde', '<=', $hoy))
                ->where(fn ($q) => $q->whereNull('hasta')->orWhere('hasta', '>=', $hoy))
                ->orderBy('orden')->orderByDesc('id')->get()
                ->filter(function ($p) use ($pagina) {
                    $paginas = json_decode((string) $p->paginas, true) ?: ['todas'];

                    return in_array('todas', $paginas, true) || in_array($pagina, $paginas, true);
                })
                // Una ventana y una barra a la vez como máximo
                ->groupBy('tipo')->map(fn ($g) => $g->first())->values();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** A dónde lleva el botón: su enlace, o su propia página de oferta */
    public static function destino(object $p): ?string
    {
        return $p->boton_url ?: ($p->con_pagina && $p->slug ? route('sitio.oferta', $p->slug) : null);
    }
}
