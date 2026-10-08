<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Catálogo de ubigeos del INEI (1873 distritos), tomado de cat_ubigeo del sistema antiguo.
 * Permite buscar por el nombre del distrito, la provincia o el departamento (sin importar tildes) y obtener el código.
 */
class Ubigeo
{
    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> código => [código, departamento, provincia, distrito] */
    private static function todos(): array
    {
        return Cache::rememberForever('ubigeos_v1', function () {
            $filas = json_decode(file_get_contents(resource_path('data/ubigeos.json')), true) ?: [];

            return collect($filas)->keyBy(0)->all();
        });
    }

    private static function normalizar(string $texto): string
    {
        return Str::of(Str::ascii($texto))->lower()->replaceMatches('/[^a-z0-9 ]+/', ' ')->squish()->toString();
    }

    /** "Iquitos - Maynas - Loreto" */
    public static function nombre(?string $codigo): ?string
    {
        $u = $codigo ? (self::todos()[$codigo] ?? null) : null;

        return $u ? "{$u[3]} - {$u[2]} - {$u[1]}" : null;
    }

    /** @return array{departamento: string, provincia: string, distrito: string}|null en mayúsculas, como los guarda la sucursal */
    public static function partes(?string $codigo): ?array
    {
        $u = $codigo ? (self::todos()[$codigo] ?? null) : null;

        return $u ? ['departamento' => mb_strtoupper($u[1]), 'provincia' => mb_strtoupper($u[2]), 'distrito' => mb_strtoupper($u[3])] : null;
    }

    /**
     * Distritos cuyo nombre (o provincia/departamento) contiene todas las palabras buscadas.
     * El distrito que coincide al inicio va primero (busca "iquitos" → Iquitos antes que otros de Maynas).
     *
     * @return array<int, array{ubigeo: string, nombre: string}>
     */
    public static function buscar(string $q, int $limite = 15): array
    {
        $q = self::normalizar($q);
        if ($q === '') {
            return [];
        }
        if (preg_match('/^\d{2,6}$/', $q)) {
            return collect(self::todos())->filter(fn ($u) => str_starts_with($u[0], $q))->take($limite)
                ->map(fn ($u) => ['ubigeo' => $u[0], 'nombre' => "{$u[3]} - {$u[2]} - {$u[1]}"])->values()->all();
        }
        $palabras = explode(' ', $q);

        return collect(self::todos())
            ->map(fn ($u) => ['u' => $u, 'dist' => self::normalizar($u[3]), 'todo' => self::normalizar("{$u[3]} {$u[2]} {$u[1]}")])
            ->filter(fn ($x) => collect($palabras)->every(fn ($p) => str_contains($x['todo'], $p)))
            ->sortBy(fn ($x) => [str_starts_with($x['dist'], $q) ? 0 : (str_contains($x['dist'], $palabras[0]) ? 1 : 2), $x['todo']])
            ->take($limite)
            ->map(fn ($x) => ['ubigeo' => $x['u'][0], 'nombre' => "{$x['u'][3]} - {$x['u'][2]} - {$x['u'][1]}"])
            ->values()->all();
    }
}
