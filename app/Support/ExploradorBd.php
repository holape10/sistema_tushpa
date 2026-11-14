<?php

namespace App\Support;

use App\Support\Asistente\ConsultaSegura;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Explorador de bases para el dueño del sistema, de SOLO LECTURA: ver las tablas de una base, sus columnas y filas
 * (con búsqueda), y correr consultas SELECT con las mismas barreras del asistente de soporte (ConsultaSegura): una sola
 * sentencia de lectura, transacción READ ONLY, tiempo máximo, sin tocar otras bases ni las del motor, claves tapadas.
 */
class ExploradorBd
{
    public const POR_PAGINA = 50;

    /** Filas como mucho en pantalla y en el Excel de una consulta */
    public const FILAS_PANTALLA = 200;

    public const FILAS_EXCEL = 5000;

    /** Milisegundos como mucho para ver o buscar en una tabla grande */
    private const MILISEGUNDOS = 8000;

    /** La base solo si es una de la lista (nunca las del motor de MySQL) */
    public static function base(?string $base): ?string
    {
        $base = (string) $base;

        return $base !== '' && Servidor::bases()->contains('base', $base) ? $base : null;
    }

    /**
     * Las tablas de la base con su tamaño y filas aproximadas.
     *
     * @return Collection<int, object{tabla: string, total: int, filas: int}>
     */
    public static function tablas(string $base): Collection
    {
        return Servidor::tablasDe($base, 1000)->sortBy('tabla')->values();
    }

    /**
     * Columnas y una página de filas de una tabla (las más nuevas primero si tiene llave), con búsqueda opcional en
     * sus columnas de texto.
     *
     * @return array{columnas: list<array{nombre: string, tipo: string, nulo: bool, llave: bool}>, filas: list<array<string, mixed>>, hay_mas: bool, buscadas: list<string>}
     */
    public static function ver(string $base, string $tabla, ?string $buscar = null, int $pagina = 1): array
    {
        if (! preg_match('/^\w{1,64}$/', $tabla) || ! self::tablas($base)->contains('tabla', $tabla)) {
            throw new \InvalidArgumentException("No existe la tabla {$tabla} en {$base}.");
        }

        return Tenancy::en($base, function () use ($tabla, $buscar, $pagina) {
            $llave = collect(Schema::getIndexes($tabla))->firstWhere('primary', true)['columns'] ?? [];
            $columnas = collect(Schema::getColumns($tabla))->map(fn ($c) => ['nombre' => $c['name'], 'tipo' => $c['type'], 'nulo' => (bool) $c['nullable'],
                'llave' => in_array($c['name'], $llave, true)])->values()->all();
            // Se busca en las columnas de texto y números (no en las de claves u ocultas)
            $buscadas = collect($columnas)->filter(fn ($c) => preg_match('/char|text|int|decimal|date/i', $c['tipo']) && ! preg_match('/pass|clave|secret|token|certificado/i', $c['nombre']))
                ->pluck('nombre')->take(20)->values()->all();
            $buscar = trim((string) $buscar);
            $conexion = DB::connection();
            $conexion->statement('SET SESSION MAX_EXECUTION_TIME = '.self::MILISEGUNDOS);
            try {
                $filas = DB::table($tabla)
                    ->when($buscar !== '' && $buscadas, fn ($q) => $q->where(function ($w) use ($buscadas, $buscar) {
                        foreach ($buscadas as $col) {
                            $w->orWhere($col, 'like', '%'.addcslashes($buscar, '%_\\').'%');
                        }
                    }))
                    ->when($llave, fn ($q) => $q->orderByDesc($llave[0]))
                    ->offset((max(1, $pagina) - 1) * self::POR_PAGINA)->limit(self::POR_PAGINA + 1)->get();
            } finally {
                $conexion->statement('SET SESSION MAX_EXECUTION_TIME = 0');
            }

            return ['columnas' => $columnas, 'filas' => $filas->take(self::POR_PAGINA)->map(fn ($f) => ConsultaSegura::tapar((array) $f))->all(),
                'hay_mas' => $filas->count() > self::POR_PAGINA, 'buscadas' => $buscadas];
        });
    }

    /**
     * Una consulta de solo lectura en la base elegida.
     *
     * @return array{filas: list<array<string, mixed>>, total: int, cortado: bool}
     *
     * @throws \InvalidArgumentException con el motivo, para mostrarlo tal cual
     */
    public static function consultar(string $base, string $sql, int $maximo = self::FILAS_PANTALLA): array
    {
        return Tenancy::en($base, fn () => ConsultaSegura::ejecutar($sql, $maximo));
    }
}
