<?php

namespace App\Support\Asistente;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Consultas SQL de SOLO LECTURA para el asistente de soporte, sobre la base de la empresa elegida. Varias barreras:
 * - solo una sentencia SELECT / SHOW / DESCRIBE / EXPLAIN (sin ";" intermedios ni INTO OUTFILE, FOR UPDATE, SLEEP…);
 * - nunca otra base de datos (ni la central ni la de otra empresa) ni las del motor (mysql, information_schema…);
 * - corre en una transacción READ ONLY que se deshace al final (MySQL rechaza cualquier escritura);
 * - tiempo máximo y pocas filas;
 * - las claves, contraseñas, tokens y certificados salen tapados.
 */
class ConsultaSegura
{
    public const FILAS = 100;

    private const MILISEGUNDOS = 8000;

    /** Columnas que nunca se muestran (claves SOL, certificado, tokens, contraseñas…) */
    private const SENSIBLE = '/pass|clave|secret|token|certificado|contrasena|password|api_?key|remember|cvv|pin\b/i';

    /** Empieza con SELECT y es una sola sentencia: lo que queda por cerrar es escribir archivos o variables, bloquear filas y frenar el servidor */
    private const PROHIBIDO = '/into\s+(outfile|dumpfile|@)|for\s+update|lock\s+in\s+share|\b(sleep|benchmark|get_lock|release_lock|load_file|sys_exec|sys_eval)\s*\(|show\s+databases|show\s+grants|show\s+processlist/i';

    /** Por qué no se puede correr (null = se puede) */
    public static function problema(string $sql): ?string
    {
        $limpio = trim(rtrim(trim($sql), ';'));
        if ($limpio === '') {
            return 'La consulta está vacía.';
        }
        if (str_contains($limpio, ';')) {
            return 'Solo una sentencia por consulta (sin ";").';
        }
        if (! preg_match('/^(select|with|show|describe|desc|explain)\b/i', $limpio)) {
            return 'Solo se permiten consultas de lectura: SELECT, SHOW, DESCRIBE o EXPLAIN.';
        }
        // Las palabras prohibidas se buscan fuera de los textos entre comillas (un nombre de cliente puede decir "UPDATE")
        $sinTextos = preg_replace("/'(?:[^'\\\\]|\\\\.)*'|\"(?:[^\"\\\\]|\\\\.)*\"/s", "''", $limpio);
        if (preg_match(self::PROHIBIDO, $sinTextos, $m)) {
            return 'No se permite "'.trim($m[0]).'": el asistente solo consulta.';
        }
        if (str_contains($sinTextos, '/*') || str_contains($sinTextos, '--') || str_contains($sinTextos, '#')) {
            return 'Sin comentarios dentro de la consulta.';
        }
        $actual = mb_strtolower((string) DB::getDatabaseName());
        foreach (DB::select('SHOW DATABASES') as $fila) {
            $base = mb_strtolower((string) (array_values((array) $fila)[0] ?? ''));
            if ($base !== '' && $base !== $actual && preg_match('/`?\b'.preg_quote($base, '/').'\b`?\s*\./i', $sinTextos)) {
                return "Solo se consulta la base de esta empresa (no {$base}).";
            }
        }
        if (preg_match('/\b(information_schema|performance_schema|mysql|sys)\s*\./i', $sinTextos) || preg_match('/\bfrom\s+`?(information_schema|performance_schema|mysql|sys)\b/i', $sinTextos)) {
            return 'Para ver tablas y columnas usa la herramienta tablas.';
        }

        return null;
    }

    /**
     * @param  int  $maximo  filas que devuelve (el explorador del panel pide más para el Excel)
     * @return array{filas: list<array<string, mixed>>, total: int, cortado: bool}
     */
    public static function ejecutar(string $sql, int $maximo = self::FILAS): array
    {
        if ($motivo = self::problema($sql)) {
            throw new \InvalidArgumentException($motivo);
        }
        $sql = trim(rtrim(trim($sql), ';'));
        $conexion = DB::connection();
        $conexion->statement('SET SESSION TRANSACTION READ ONLY');
        $conexion->beginTransaction();
        try {
            if (preg_match('/^(select|with)\b/i', $sql)) {
                $sql = preg_replace('/^(select)\b/i', '$1 /*+ MAX_EXECUTION_TIME('.self::MILISEGUNDOS.') */', $sql, 1);
            }
            $filas = $conexion->select($sql);
        } catch (QueryException $e) {
            throw new \InvalidArgumentException('MySQL respondió: '.mb_substr($e->getPrevious()?->getMessage() ?? $e->getMessage(), 0, 300));
        } finally {
            $conexion->rollBack();
            $conexion->statement('SET SESSION TRANSACTION READ WRITE');
        }
        $total = count($filas);

        return ['filas' => array_map(fn ($f) => self::tapar((array) $f), array_slice($filas, 0, $maximo)), 'total' => $total, 'cortado' => $total > $maximo];
    }

    /** Tablas de la base (con un filtro opcional) y, si se pide una, sus columnas */
    public static function tablas(?string $tabla, ?string $buscar): array
    {
        if ($tabla) {
            if (! preg_match('/^\w+$/', $tabla) || ! DB::getSchemaBuilder()->hasTable($tabla)) {
                throw new \InvalidArgumentException("No existe la tabla {$tabla}.");
            }

            return ['tabla' => $tabla, 'columnas' => collect(DB::getSchemaBuilder()->getColumns($tabla))
                ->map(fn ($c) => ['columna' => $c['name'], 'tipo' => $c['type'], 'nulo' => $c['nullable'], 'comentario' => $c['comment'] ?: null])->all()];
        }
        $todas = collect(DB::getSchemaBuilder()->getTableListing(schemaQualified: false))
            ->when($buscar, fn ($c) => $c->filter(fn ($t) => str_contains(mb_strtolower($t), mb_strtolower((string) $buscar))))->values();

        return ['tablas_en_total' => $todas->count(), 'tablas' => $todas->take(300)->all()];
    }

    /**
     * Claves, contraseñas, tokens y certificados tapados; textos muy largos, recortados.
     *
     * @param  array<string, mixed>  $fila
     */
    public static function tapar(array $fila): array
    {
        foreach ($fila as $col => $valor) {
            if ($valor !== null && $valor !== '' && preg_match(self::SENSIBLE, (string) $col)) {
                $fila[$col] = '•••• (oculto)';
            } elseif (is_string($valor) && mb_strlen($valor) > 400) {
                $fila[$col] = mb_substr($valor, 0, 400).'…';
            }
        }

        return $fila;
    }
}
