<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mantenimiento › Apariencia: el administrador elige el color del sistema, el fondo y el tamaño de letra de su empresa.
 * El color principal del sistema es el "indigo" de Tailwind: en tailwind.config.js cada tono lee una variable (--ap-50…
 * --ap-950) con el índigo original de respaldo. Aquí se ponen esas variables con la paleta elegida, así cambian a la vez
 * todos los botones, el menú y los resaltados, sin tocar cada pantalla. Solo dentro del sistema de la empresa
 * (layouts.app): el panel, el login y las páginas públicas siguen igual.
 */
class Apariencia
{
    private const TONOS = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

    /** nombre => [etiqueta, tonos 50…950 en hex] (paletas de Tailwind) */
    public const PALETAS = [
        'indigo' => ['Índigo (el de siempre)', ['eef2ff', 'e0e7ff', 'c7d2fe', 'a5b4fc', '818cf8', '6366f1', '4f46e5', '4338ca', '3730a3', '312e81', '1e1b4b']],
        'azul' => ['Azul', ['eff6ff', 'dbeafe', 'bfdbfe', '93c5fd', '60a5fa', '3b82f6', '2563eb', '1d4ed8', '1e40af', '1e3a8a', '172554']],
        'celeste' => ['Celeste', ['f0f9ff', 'e0f2fe', 'bae6fd', '7dd3fc', '38bdf8', '0ea5e9', '0284c7', '0369a1', '075985', '0c4a6e', '082f49']],
        'turquesa' => ['Turquesa', ['f0fdfa', 'ccfbf1', '99f6e4', '5eead4', '2dd4bf', '14b8a6', '0d9488', '0f766e', '115e59', '134e4a', '042f2e']],
        'esmeralda' => ['Esmeralda', ['ecfdf5', 'd1fae5', 'a7f3d0', '6ee7b7', '34d399', '10b981', '059669', '047857', '065f46', '064e3b', '022c22']],
        'verde' => ['Verde', ['f0fdf4', 'dcfce7', 'bbf7d0', '86efac', '4ade80', '22c55e', '16a34a', '15803d', '166534', '14532d', '052e16']],
        'naranja' => ['Naranja', ['fff7ed', 'ffedd5', 'fed7aa', 'fdba74', 'fb923c', 'f97316', 'ea580c', 'c2410c', '9a3412', '7c2d12', '431407']],
        'dorado' => ['Dorado', ['fffbeb', 'fef3c7', 'fde68a', 'fcd34d', 'fbbf24', 'f59e0b', 'd97706', 'b45309', '92400e', '78350f', '451a03']],
        'rojo' => ['Rojo', ['fef2f2', 'fee2e2', 'fecaca', 'fca5a5', 'f87171', 'ef4444', 'dc2626', 'b91c1c', '991b1b', '7f1d1d', '450a0a']],
        'rosado' => ['Rosado', ['fff1f2', 'ffe4e6', 'fecdd3', 'fda4af', 'fb7185', 'f43f5e', 'e11d48', 'be123c', '9f1239', '881337', '4c0519']],
        'fucsia' => ['Fucsia', ['fdf4ff', 'fae8ff', 'f5d0fe', 'f0abfc', 'e879f9', 'd946ef', 'c026d3', 'a21caf', '86198f', '701a75', '4a044e']],
        'morado' => ['Morado', ['f5f3ff', 'ede9fe', 'ddd6fe', 'c4b5fd', 'a78bfa', '8b5cf6', '7c3aed', '6d28d9', '5b21b6', '4c1d95', '2e1065']],
        'grafito' => ['Grafito', ['f8fafc', 'f1f5f9', 'e2e8f0', 'cbd5e1', '94a3b8', '64748b', '475569', '334155', '1e293b', '0f172a', '020617']],
    ];

    /** nombre => [etiqueta, color de fondo de las pantallas] */
    public const FONDOS = [
        'gris' => ['Gris claro (el de siempre)', '#f3f4f6'],
        'blanco' => ['Blanco', '#fbfbfc'],
        'calido' => ['Cálido (crema)', '#f7f3ec'],
        'frio' => ['Frío (celeste)', '#eef4fb'],
    ];

    /** nombre => [etiqueta, tamaño base de la letra] (todo el sistema usa rem: crece parejo) */
    public const LETRAS = [
        'normal' => ['Normal', '100%'],
        'grande' => ['Grande', '112.5%'],
        'muy_grande' => ['Muy grande', '125%'],
    ];

    public const PREDETERMINADA = ['color' => 'indigo', 'fondo' => 'gris', 'letra' => 'normal'];

    private static array $cache = [];

    public static function disponible(): bool
    {
        return Schema::hasTable('empresa') && Schema::hasColumn('empresa', 'apariencia');
    }

    /** @return array{color: string, fondo: string, letra: string} la elegida por la empresa (o la de siempre) */
    public static function de(?string $idEmpresa): array
    {
        if (! $idEmpresa || ! self::disponible()) {
            return self::PREDETERMINADA;
        }
        if (! isset(self::$cache[$idEmpresa])) {
            $guardada = json_decode((string) DB::table('empresa')->where('IdEmpresa', $idEmpresa)->value('apariencia'), true);
            self::$cache[$idEmpresa] = self::limpiar(is_array($guardada) ? $guardada : []);
        }

        return self::$cache[$idEmpresa];
    }

    /**
     * La apariencia del login (antes de entrar no hay usuario): la de la empresa de esta base, si es una sola.
     *
     * @return array{color: string, fondo: string, letra: string}
     */
    public static function deLaBase(): array
    {
        if (! self::disponible()) {
            return self::PREDETERMINADA;
        }
        $empresas = DB::table('empresa')->limit(2)->pluck('IdEmpresa');

        return $empresas->count() === 1 ? self::de((string) $empresas->first()) : self::PREDETERMINADA;
    }

    /**
     * El nombre de la empresa para reconocerla al vuelo (login y pestaña del navegador): el nombre comercial de la
     * sucursal del usuario o, antes de entrar, el de la empresa de esta base. null si no se sabe.
     * Corto para la pestaña del navegador; $completo para el login (ahí se lee entero).
     */
    public static function nombre(bool $completo = false): ?string
    {
        try {
            if (Auth::check()) {
                $nombre = DB::table('empresa_negocios')->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->value('nombre_comercial');
            } else {
                $empresas = DB::table('empresa')->limit(2)->get(['IdEmpresa', 'NomEmpresa']);
                $nombre = $empresas->count() === 1
                    ? (DB::table('empresa_negocios')->where('IdEmpresa', $empresas->first()->IdEmpresa)->orderBy('id_empresa_negocio')->value('nombre_comercial') ?: $empresas->first()->NomEmpresa)
                    : null;
            }
        } catch (\Throwable) {
            return null;
        }
        $nombre = trim((string) $nombre);

        return $nombre !== '' ? ($completo ? $nombre : mb_strimwidth($nombre, 0, 28, '…')) : null;
    }

    /** Solo valores conocidos; lo demás vuelve a lo de siempre */
    public static function limpiar(array $a): array
    {
        return [
            'color' => isset(self::PALETAS[$a['color'] ?? '']) ? $a['color'] : self::PREDETERMINADA['color'],
            'fondo' => isset(self::FONDOS[$a['fondo'] ?? '']) ? $a['fondo'] : self::PREDETERMINADA['fondo'],
            'letra' => isset(self::LETRAS[$a['letra'] ?? '']) ? $a['letra'] : self::PREDETERMINADA['letra'],
        ];
    }

    public static function guardar(string $idEmpresa, array $a): void
    {
        DB::table('empresa')->where('IdEmpresa', $idEmpresa)->update(['apariencia' => json_encode(self::limpiar($a))]);
        unset(self::$cache[$idEmpresa]);
    }

    /** "79 70 229": un tono en el formato que lee Tailwind (rgb(var(--ap-600) / opacidad)) */
    private static function rgb(string $hex): string
    {
        return implode(' ', array_map('hexdec', str_split($hex, 2)));
    }

    /** Las variables de una paleta: ['--ap-50' => '238 242 255', …] (para la vista previa) */
    public static function variables(string $color): array
    {
        $tonos = self::PALETAS[$color][1] ?? self::PALETAS['indigo'][1];

        return array_combine(array_map(fn ($t) => '--ap-'.$t, self::TONOS), array_map(fn ($h) => self::rgb($h), $tonos));
    }

    /** El <style> que va en el sistema de la empresa ('' si usa lo de siempre) */
    public static function estilo(?array $a = null): string
    {
        $a ??= Auth::check() ? self::de((string) Auth::user()->IdEmpresa) : self::PREDETERMINADA;
        if ($a === self::PREDETERMINADA) {
            return '';
        }
        $vars = collect(self::variables($a['color']))->map(fn ($v, $k) => "{$k}:{$v}")->implode(';');

        return '<style id="apariencia">:root{'.$vars.'}'
            .'html{font-size:'.self::LETRAS[$a['letra']][1].'}'
            .'body.bg-gray-100{background-color:'.self::FONDOS[$a['fondo']][1].'}</style>';
    }
}
