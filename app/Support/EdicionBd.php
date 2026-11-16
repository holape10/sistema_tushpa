<?php

namespace App\Support;

use App\Support\Tenancy\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cambios de datos en la base de una empresa desde el panel (solo el dueño, con su segunda clave). Frenos:
 * - una sola orden de datos: INSERT, UPDATE, DELETE o REPLACE (nunca DROP, TRUNCATE, ALTER, CREATE, GRANT…);
 * - UPDATE y DELETE siempre con WHERE (y no uno que lo abarque todo, como WHERE 1=1);
 * - solo en la base elegida (nunca otra empresa ni las del motor);
 * - primero se PRUEBA (corre y se deshace, para ver cuántas filas cambiaría) y recién después se ejecuta;
 * - como mucho FILAS_MAXIMAS filas por orden, y cada cambio queda registrado (quién, base, orden y filas).
 */
class EdicionBd
{
    public const FILAS_MAXIMAS = 20000;

    /** Minutos que dura el modo edición después de poner la segunda clave */
    public const MINUTOS = 15;

    private const PROHIBIDO = '/\b(drop|truncate|alter|create|grant|revoke|rename|shutdown|kill|load\s+data|lock\s+tables|unlock|handler|prepare|execute|deallocate|call|set\s+global|set\s+persist|install|uninstall)\b|into\s+(outfile|dumpfile)|\b(sleep|benchmark|load_file|sys_exec|sys_eval)\s*\(/i';

    /** Por qué no se puede ejecutar (null = se puede) */
    public static function problema(string $sql): ?string
    {
        $limpio = trim(rtrim(trim($sql), ';'));
        if ($limpio === '') {
            return 'La orden está vacía.';
        }
        // Las palabras se buscan fuera de los textos entre comillas (un nombre puede decir "DROP")
        $sinTextos = preg_replace("/'(?:[^'\\\\]|\\\\.)*'|\"(?:[^\"\\\\]|\\\\.)*\"/s", "''", $limpio);
        if (str_contains($sinTextos, ';')) {
            return 'Solo una orden a la vez (sin ";").';
        }
        if (str_contains($sinTextos, '/*') || str_contains($sinTextos, '--') || str_contains($sinTextos, '#')) {
            return 'Sin comentarios dentro de la orden.';
        }
        if (! preg_match('/^(update|delete|insert|replace)\b/i', $limpio, $m)) {
            return 'En modo edición solo se permite INSERT, UPDATE, DELETE o REPLACE. Para consultar usa el cuadro de consultas.';
        }
        if (preg_match(self::PROHIBIDO, $sinTextos, $p)) {
            return 'No se permite «'.trim($p[0]).'»: solo se cambian datos, nunca la estructura de la base.';
        }
        $tipo = strtolower($m[1]);
        if (in_array($tipo, ['update', 'delete'], true)) {
            if (! preg_match('/\bwhere\b(.+)$/is', $sinTextos, $w)) {
                return 'Un '.strtoupper($tipo).' sin WHERE cambiaría TODA la tabla (como pasó con los precios). Di a qué filas: WHERE IdProducto = …';
            }
            if (preg_match('/^\s*(1|true|1\s*=\s*1|0\s*=\s*0|\'\'\s*=\s*\'\')\s*(limit\s+\d+)?\s*$/i', $w[1])) {
                return 'Ese WHERE abarca todas las filas: di exactamente cuáles cambiar.';
            }
        }
        if (preg_match('/\b(information_schema|performance_schema|mysql|sys)\s*\./i', $sinTextos)) {
            return 'No se tocan las tablas internas de MySQL.';
        }
        $actual = mb_strtolower((string) DB::getDatabaseName());
        foreach (DB::select('SHOW DATABASES') as $fila) {
            $base = mb_strtolower((string) (array_values((array) $fila)[0] ?? ''));
            if ($base !== '' && $base !== $actual && preg_match('/`?\b'.preg_quote($base, '/').'\b`?\s*\./i', $sinTextos)) {
                return "Solo se cambia la base elegida (no {$base}).";
            }
        }

        return null;
    }

    /**
     * Corre la orden y la deshace: cuántas filas cambiaría.
     *
     * @throws \InvalidArgumentException con el motivo
     */
    public static function probar(string $base, string $sql): int
    {
        return Tenancy::en($base, function () use ($sql) {
            if ($motivo = self::problema($sql)) {
                throw new \InvalidArgumentException($motivo);
            }
            DB::beginTransaction();
            try {
                return DB::affectingStatement(trim(rtrim(trim($sql), ';')));
            } catch (QueryException $e) {
                throw new \InvalidArgumentException('MySQL respondió: '.mb_substr($e->getPrevious()?->getMessage() ?? $e->getMessage(), 0, 300));
            } finally {
                DB::rollBack();
            }
        });
    }

    /**
     * Ejecuta la orden de verdad y la registra. Si cambiaría más filas que el máximo, no se hace nada.
     *
     * @throws \InvalidArgumentException con el motivo
     */
    public static function ejecutar(string $base, string $sql, object $superadmin, ?string $ip = null): int
    {
        $filas = Tenancy::en($base, function () use ($sql) {
            if ($motivo = self::problema($sql)) {
                throw new \InvalidArgumentException($motivo);
            }
            DB::beginTransaction();
            try {
                $n = DB::affectingStatement(trim(rtrim(trim($sql), ';')));
                if ($n > self::FILAS_MAXIMAS) {
                    DB::rollBack();
                    throw new \InvalidArgumentException('Esa orden cambiaría '.number_format($n).' filas (más de '.number_format(self::FILAS_MAXIMAS).'): no se hizo nada.');
                }
                DB::commit();

                return $n;
            } catch (QueryException $e) {
                DB::rollBack();
                throw new \InvalidArgumentException('MySQL respondió: '.mb_substr($e->getPrevious()?->getMessage() ?? $e->getMessage(), 0, 300));
            }
        });
        DB::connection('central')->table('panel_bd_cambios')->insert([
            'superadmin_id' => $superadmin->id, 'usuario' => mb_substr((string) $superadmin->email, 0, 120), 'base' => $base,
            'sql' => mb_substr($sql, 0, 60000), 'filas' => $filas, 'ip' => $ip, 'created_at' => now(),
        ]);
        Log::warning('Cambio en base desde el panel', ['usuario' => $superadmin->email, 'base' => $base, 'filas' => $filas, 'sql' => mb_substr($sql, 0, 2000)]);

        return $filas;
    }
}
