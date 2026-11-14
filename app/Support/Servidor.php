<?php

namespace App\Support;

use App\Models\Central\Cliente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cómo está el servidor, para el dueño del sistema: memoria y disco del VPS, cómo va MySQL y cuánto pesa la base de
 * cada empresa (la RAM de MySQL es una sola caché compartida: lo que se puede medir por base es su tamaño). Solo lee.
 */
class Servidor
{
    /** Bases del propio MySQL que no son de ninguna empresa */
    private const DEL_MOTOR = ['information_schema', 'performance_schema', 'mysql', 'sys'];

    /**
     * Memoria del VPS (Linux, /proc/meminfo) en bytes; null si no se puede leer (por ejemplo, en Windows).
     *
     * @return array{total: int, disponible: int, usada: int, swap_total: int, swap_usada: int}|null
     */
    public static function memoria(?string $meminfo = null): ?array
    {
        $meminfo ??= is_readable('/proc/meminfo') ? (string) @file_get_contents('/proc/meminfo') : '';
        preg_match_all('/^(\w+):\s+(\d+)\s*kB/m', $meminfo, $m);
        $kb = array_combine($m[1], array_map('intval', $m[2]));
        if (empty($kb['MemTotal']) || ! isset($kb['MemAvailable'])) {
            return null;
        }
        $b = fn (string $k) => ($kb[$k] ?? 0) * 1024;

        return ['total' => $b('MemTotal'), 'disponible' => $b('MemAvailable'), 'usada' => $b('MemTotal') - $b('MemAvailable'),
            'swap_total' => $b('SwapTotal'), 'swap_usada' => $b('SwapTotal') - $b('SwapFree')];
    }

    /** @return array{total: int, libre: int}|null disco donde está el sistema */
    public static function disco(): ?array
    {
        $total = @disk_total_space(base_path());
        $libre = @disk_free_space(base_path());

        return $total ? ['total' => (int) $total, 'libre' => (int) $libre] : null;
    }

    /** @return array{conexiones: int, maximo_usado: int, max_conexiones: int, cache: int, encendido: int, version: string} */
    public static function mysql(): array
    {
        $db = DB::connection('central');
        $estado = collect($db->select("SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected', 'Max_used_connections', 'Uptime')"))
            ->pluck('Value', 'Variable_name');
        $variables = $db->selectOne('SELECT @@max_connections AS max_conexiones, @@innodb_buffer_pool_size AS cache, VERSION() AS version');

        return ['conexiones' => (int) ($estado['Threads_connected'] ?? 0), 'maximo_usado' => (int) ($estado['Max_used_connections'] ?? 0),
            'max_conexiones' => (int) $variables->max_conexiones, 'cache' => (int) $variables->cache, 'encendido' => (int) ($estado['Uptime'] ?? 0),
            'version' => (string) $variables->version];
    }

    /**
     * Las bases de MySQL de mayor a menor (datos + índices), con la empresa a la que pertenecen (por el RUC en el nombre).
     *
     * @return Collection<int, object{base: string, datos: int, indices: int, total: int, tablas: int, filas: int, empresa: ?string, ruc: ?string}>
     */
    public static function bases(): Collection
    {
        $filas = collect(DB::connection('central')->select(
            'SELECT TABLE_SCHEMA AS base, COALESCE(SUM(DATA_LENGTH), 0) AS datos, COALESCE(SUM(INDEX_LENGTH), 0) AS indices,
                COUNT(*) AS tablas, COALESCE(SUM(TABLE_ROWS), 0) AS filas
             FROM information_schema.TABLES WHERE TABLE_SCHEMA NOT IN ('.implode(',', array_fill(0, count(self::DEL_MOTOR), '?')).')
             GROUP BY TABLE_SCHEMA', self::DEL_MOTOR));
        $rucs = $filas->map(fn ($f) => preg_match('/(\d{11})/', $f->base, $m) ? $m[1] : null)->filter()->unique()->values();
        $empresas = $rucs->isEmpty() ? collect() : Cliente::whereIn('ruc', $rucs->all())->pluck('razon_social', 'ruc');

        return $filas->map(function ($f) use ($empresas) {
            $ruc = preg_match('/(\d{11})/', $f->base, $m) ? $m[1] : null;

            return (object) ['base' => $f->base, 'datos' => (int) $f->datos, 'indices' => (int) $f->indices, 'total' => (int) $f->datos + (int) $f->indices,
                'tablas' => (int) $f->tablas, 'filas' => (int) $f->filas, 'ruc' => $ruc, 'empresa' => $ruc ? ($empresas[$ruc] ?? null) : null];
        })->sortByDesc('total')->values();
    }

    /**
     * Las tablas más pesadas de una base.
     *
     * @return Collection<int, object{tabla: string, total: int, filas: int}>
     */
    public static function tablasDe(string $base, int $cuantas = 15): Collection
    {
        if (in_array($base, self::DEL_MOTOR, true) || ! preg_match('/^[a-z0-9_]{1,64}$/i', $base)) {
            return collect();
        }

        return collect(DB::connection('central')->select(
            'SELECT TABLE_NAME AS tabla, COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0) AS total, COALESCE(TABLE_ROWS, 0) AS filas
             FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY total DESC LIMIT '.max(1, min(50, $cuantas)), [$base]))
            ->map(fn ($t) => (object) ['tabla' => $t->tabla, 'total' => (int) $t->total, 'filas' => (int) $t->filas]);
    }

    /** 1536 → "1.5 KB"; 5 400 000 000 → "5.03 GB" */
    public static function tamano(int|float $bytes): string
    {
        $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while (abs($bytes) >= 1024 && $i < count($unidades) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $bytes : number_format($bytes, $bytes >= 100 ? 0 : ($bytes >= 10 ? 1 : 2))).' '.$unidades[$i];
    }
}
