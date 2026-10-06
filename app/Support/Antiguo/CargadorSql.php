<?php
namespace App\Support\Antiguo;

use Illuminate\Support\Facades\DB;

/**
 * Carga un respaldo .sql (o .sql.gz) del sistema antiguo en una base temporal, leyéndolo línea por línea.
 * Solo ejecuta las tablas que el importador usa (productos, clientes, ventas, compras, cuentas…): se saltan kardex,
 * logs y demás tablas que no se importan. Procedimientos, vistas y triggers se ignoran.
 * Soporta los formatos de mysqldump (con DELIMITER) y de HeidiSQL/SQLyog (procedimientos sin DELIMITER).
 */
class CargadorSql
{
    public const TABLAS = [
        'empresa', 'empresa_negocios', 'almacenes', 'unidad_medida',
        'categorias', 'subcategorias', 'productos', 'presentaciones', 'producto_codigo', 'precios_dia_semana',
        'producto_stock', 'combos', 'cliente', 'proveedor', 'medios_pagos', 'credito_dias', 'pisos', 'mesas',
        'users', 'empleado', 'role_user',
        // Historial
        'cpe_cabecera', 'cpe_detalle', 'venta_medio_pago', 'compras_cabecera', 'compras_detalle',
        'cuentas_cobrar', 'cuentas_cobrar_detalle', 'cuentas_pagar', 'cuentas_pagar_detalle',
    ];

    private const RUTINA = '/^CREATE\s+(?:OR\s+REPLACE\s+)?(?:DEFINER\s*=\s*\S+\s+)?(?:SQL\s+SECURITY\s+\w+\s+)?(?:PROCEDURE|FUNCTION|TRIGGER|EVENT)\b/i';
    private const TABLA = '/^(?:INSERT\s+(?:IGNORE\s+)?INTO|REPLACE\s+INTO|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|DROP\s+TABLE(?:\s+IF\s+EXISTS)?|ALTER\s+TABLE)\s+`?([A-Za-z0-9_]+)`?/i';

    /** @return array{ejecutadas: int, omitidas: int, errores: string[], tablas: string[]} */
    public static function cargar(string $archivo, string $bd): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $bd)) {
            throw new \RuntimeException('Nombre de base de datos no válido.');
        }
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$bd}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        Importador::conectar($bd);
        $pdo = DB::connection(Importador::CONEXION)->getPdo();
        $pdo->exec("SET NAMES utf8mb4, FOREIGN_KEY_CHECKS = 0, UNIQUE_CHECKS = 0, SQL_MODE = ''");

        $gz = str_ends_with(strtolower($archivo), '.gz');
        $f = $gz ? gzopen($archivo, 'rb') : fopen($archivo, 'rb');
        if (!$f) {
            throw new \RuntimeException('No se pudo abrir el archivo.');
        }

        $res = ['ejecutadas' => 0, 'omitidas' => 0, 'errores' => [], 'tablas' => []];
        $buffer = '';
        $delim = ';';
        $enRutina = false;

        while (($linea = $gz ? gzgets($f) : fgets($f)) !== false) {
            $limpia = rtrim($linea, "\r\n");
            $t = trim($limpia);

            // Procedimiento sin DELIMITER (HeidiSQL): se salta hasta su END
            if ($enRutina) {
                if (preg_match('/^END\s*;?\s*$/i', $t)) {
                    $enRutina = false;
                }
                continue;
            }

            if ($buffer === '') {
                if ($t === '' || str_starts_with($t, '--') || str_starts_with($t, '#') || str_starts_with($t, '/*')) {
                    continue;
                }
                if (preg_match('/^DELIMITER\s+(\S+)/i', $t, $m)) {
                    $delim = $m[1];
                    continue;
                }
                if ($delim === ';' && preg_match(self::RUTINA, $t)) {
                    $res['omitidas']++;
                    $enRutina = true;
                    continue;
                }
            }

            $buffer .= $limpia . "\n";
            if (!str_ends_with($t, $delim)) {
                continue;
            }

            $sentencia = substr(rtrim($buffer), 0, -strlen($delim));
            $buffer = '';
            self::ejecutar($pdo, trim($sentencia), $res);
        }
        $gz ? gzclose($f) : fclose($f);

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1, UNIQUE_CHECKS = 1');
        $res['tablas'] = array_values(array_unique($res['tablas']));
        return $res;
    }

    private static function ejecutar(\PDO $pdo, string $sql, array &$res): void
    {
        if ($sql === '' || preg_match(self::RUTINA, $sql) || !preg_match(self::TABLA, $sql, $m)
            || !in_array(strtolower($m[1]), self::TABLAS, true)) {
            $res['omitidas']++;
            return;
        }
        try {
            $pdo->exec($sql);
            $res['ejecutadas']++;
            $res['tablas'][] = strtolower($m[1]);
        } catch (\Throwable $e) {
            if (count($res['errores']) < 20) {
                $res['errores'][] = $m[1] . ': ' . mb_substr($e->getMessage(), 0, 200);
            }
        }
    }
}
