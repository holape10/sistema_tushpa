<?php

namespace App\Support;

use App\Support\Asistente\ConsultaSegura;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Edición directa en la tabla del explorador de bases (solo el dueño, con su segunda clave): cambiar celdas, borrar
 * filas (➖) y agregar filas (➕), y aplicarlo todo junto con un botón. Las reglas de un buen DBA:
 * - solo tablas con llave primaria, y cada cambio va a UNA fila por su llave (nunca un WHERE abierto);
 * - control de choques: se compara el valor que se vio con el que hay ahora; si alguien lo cambió, no se pisa nada;
 * - todo en una transacción: o se aplican todos los cambios o ninguno;
 * - no se editan claves (ocultas), textos que llegaron cortados, columnas calculadas ni la llave;
 * - como mucho MAX_CAMBIOS por vez, y todo queda registrado con el valor de antes (la fila borrada completa).
 */
class EdicionTabla
{
    public const MAX_CAMBIOS = 50;

    /** Largo con que el explorador muestra un texto (ConsultaSegura::tapar): uno más largo llega cortado y no se edita */
    public const LARGO_VISIBLE = 400;

    /** Tablas de plata y stock: se pueden editar, pero el panel avisa que el sistema no se entera (caja, kardex, SUNAT) */
    private const DELICADAS = '/^(cpe_|venta_|movimientos|producto_stock|producto_lotes|lotes|turno|compras_|cuenta|resumenes|kardex|caja|pagos|gre_)/i';

    public static function delicada(string $tabla): bool
    {
        return (bool) preg_match(self::DELICADAS, $tabla);
    }

    /**
     * ¿Se puede editar la columna? (una columna de Schema::getColumns)
     *
     * @param  array<string, mixed>  $columna
     * @param  list<string>  $llave
     */
    public static function columnaEditable(array $columna, array $llave): bool
    {
        return ! in_array($columna['name'], $llave, true)
            && ! ConsultaSegura::esSensible((string) $columna['name'])
            && empty($columna['generation'])
            && ! preg_match('/blob|binary|geometry|point|polygon/i', (string) $columna['type']);
    }

    /**
     * Columnas de una fila cuyo texto llega cortado a la pantalla (no se editan: se perdería lo que no se ve).
     *
     * @param  array<string, mixed>  $fila
     * @return list<string>
     */
    public static function cortados(array $fila): array
    {
        return array_keys(array_filter($fila, fn ($v) => is_string($v) && mb_strlen($v) > self::LARGO_VISIBLE));
    }

    /**
     * Aplica los cambios de una tabla, todos juntos o ninguno.
     *
     * @param  array{editar?: list<array{llave: array<string, mixed>, antes: array<string, mixed>, valores: array<string, mixed>}>, borrar?: list<array{llave: array<string, mixed>}>, agregar?: list<array<string, mixed>>}  $cambios
     * @return array{editadas: int, borradas: int, agregadas: int}
     *
     * @throws \InvalidArgumentException con el motivo, para mostrarlo tal cual (y sin haber cambiado nada)
     */
    public static function aplicar(string $base, string $tabla, array $cambios, object $superadmin, ?string $ip = null): array
    {
        $editar = array_values($cambios['editar'] ?? []);
        $borrar = array_values($cambios['borrar'] ?? []);
        $agregar = array_values($cambios['agregar'] ?? []);
        $total = count($editar) + count($borrar) + count($agregar);
        if ($total === 0) {
            throw new \InvalidArgumentException('No hay cambios para aplicar.');
        }
        if ($total > self::MAX_CAMBIOS) {
            throw new \InvalidArgumentException('Son '.$total.' cambios: como mucho '.self::MAX_CAMBIOS.' por vez. Aplica una parte y luego el resto.');
        }
        if (! preg_match('/^\w{1,64}$/', $tabla) || ! ExploradorBd::tablas($base)->contains('tabla', $tabla)) {
            throw new \InvalidArgumentException("No existe la tabla {$tabla} en {$base}.");
        }

        [$resultado, $registro] = Tenancy::en($base, function () use ($tabla, $editar, $borrar, $agregar) {
            $columnas = collect(Schema::getColumns($tabla))->keyBy('name');
            $llave = collect(Schema::getIndexes($tabla))->firstWhere('primary', true)['columns'] ?? [];
            if (! $llave) {
                throw new \InvalidArgumentException("La tabla {$tabla} no tiene llave primaria: edítala con una orden SQL.");
            }
            if (collect($llave)->contains(fn ($c) => ConsultaSegura::esSensible($c))) {
                throw new \InvalidArgumentException("La llave de {$tabla} es una columna oculta: edítala con una orden SQL.");
            }

            $registro = [];
            $hechas = ['editadas' => 0, 'borradas' => 0, 'agregadas' => 0];
            DB::beginTransaction();
            try {
                foreach ($editar as $e) {
                    $donde = self::donde($llave, $e['llave'] ?? []);
                    $fila = (array) (DB::table($tabla)->where($donde)->lockForUpdate()->first() ?? []);
                    if (! $fila) {
                        throw new \InvalidArgumentException('La fila '.self::texto($donde).' ya no existe (alguien la borró). Recarga la tabla.');
                    }
                    $nuevos = [];
                    foreach ((array) ($e['valores'] ?? []) as $col => $valor) {
                        $col = (string) $col;
                        self::exigirEditable($columnas, $llave, $col);
                        if (in_array($col, self::cortados($fila), true)) {
                            throw new \InvalidArgumentException("El texto de {$col} es muy largo para editarlo aquí: usa una orden SQL.");
                        }
                        if (! array_key_exists($col, (array) ($e['antes'] ?? [])) || ! self::igual($fila[$col], $e['antes'][$col], self::numerica($columnas[$col]))) {
                            throw new \InvalidArgumentException("Alguien cambió {$col} de la fila ".self::texto($donde).' mientras la editabas (ahora dice «'
                                .Bitacora::mostrar($fila[$col]).'»). Recarga la tabla: no se cambió nada.');
                        }
                        $valor = self::valor($columnas[$col], $valor);
                        if (! self::igual($fila[$col], $valor, self::numerica($columnas[$col]))) {
                            $nuevos[$col] = $valor;
                        }
                    }
                    if ($nuevos) {
                        DB::table($tabla)->where($donde)->limit(1)->update($nuevos);
                        $hechas['editadas']++;
                        $registro[] = ['accion' => 'editar', 'llave' => $donde,
                            'cambios' => array_map(fn ($c) => ['antes' => $fila[$c], 'despues' => $nuevos[$c]], array_combine(array_keys($nuevos), array_keys($nuevos)))];
                    }
                }

                foreach ($borrar as $b) {
                    $donde = self::donde($llave, $b['llave'] ?? []);
                    $fila = (array) (DB::table($tabla)->where($donde)->lockForUpdate()->first() ?? []);
                    if (! $fila) {
                        throw new \InvalidArgumentException('La fila '.self::texto($donde).' ya no existe (alguien la borró). Recarga la tabla.');
                    }
                    DB::table($tabla)->where($donde)->limit(1)->delete();
                    $hechas['borradas']++;
                    // La fila completa que se borró (sin las claves), por si hay que devolverla
                    $registro[] = ['accion' => 'borrar', 'llave' => $donde, 'fila' => ConsultaSegura::tapar($fila)];
                }

                foreach ($agregar as $nueva) {
                    $datos = [];
                    foreach ((array) $nueva as $col => $valor) {
                        $col = (string) $col;
                        $deLlave = in_array($col, $llave, true);
                        if ($deLlave && ($columnas[$col]['auto_increment'] ?? false) && ($valor === null || $valor === '')) {
                            continue; // la llave autonumérica la pone MySQL
                        }
                        if (! $deLlave) {
                            self::exigirEditable($columnas, $llave, $col);
                        } elseif (! isset($columnas[$col])) {
                            throw new \InvalidArgumentException("La columna {$col} no existe en {$tabla}.");
                        }
                        if ($valor === null || $valor === '') {
                            continue; // vacía: toma su valor por defecto (o MySQL avisa si es obligatoria)
                        }
                        $datos[$col] = self::valor($columnas[$col], $valor);
                    }
                    if (! $datos) {
                        throw new \InvalidArgumentException('Hay una fila nueva vacía: llénala o quítala.');
                    }
                    DB::table($tabla)->insert($datos);
                    $id = count($llave) === 1 && ($columnas[$llave[0]]['auto_increment'] ?? false) ? DB::getPdo()->lastInsertId() : null;
                    $hechas['agregadas']++;
                    $registro[] = ['accion' => 'agregar', 'llave' => $id ? [$llave[0] => $id] : array_intersect_key($datos, array_flip($llave)), 'fila' => ConsultaSegura::tapar($datos)];
                }
                DB::commit();
            } catch (QueryException $e) {
                DB::rollBack();
                throw new \InvalidArgumentException(self::errorMysql($e).' No se cambió nada.');
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            return [$hechas, $registro];
        });

        $filas = array_sum($resultado);
        DB::connection('central')->table('panel_bd_cambios')->insert([
            'superadmin_id' => $superadmin->id, 'usuario' => mb_substr((string) $superadmin->email, 0, 120), 'base' => $base,
            'sql' => mb_substr('-- Edición en la tabla '.$tabla."\n".json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 0, 60000),
            'filas' => $filas, 'ip' => $ip, 'created_at' => now(),
        ]);
        Log::warning('Edición en tabla desde el panel', ['usuario' => $superadmin->email, 'base' => $base, 'tabla' => $tabla] + $resultado);

        return $resultado;
    }

    /**
     * El WHERE de una fila por su llave completa (todas las columnas de la llave, con valor).
     *
     * @param  list<string>  $llave
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private static function donde(array $llave, array $valores): array
    {
        $donde = [];
        foreach ($llave as $col) {
            if (! array_key_exists($col, $valores) || $valores[$col] === null || $valores[$col] === '' || ! is_scalar($valores[$col])) {
                throw new \InvalidArgumentException("Falta la llave ({$col}) de una fila: recarga la tabla.");
            }
            $donde[$col] = $valores[$col];
        }

        return $donde;
    }

    /** @param  Collection<string, array<string, mixed>>  $columnas */
    private static function exigirEditable($columnas, array $llave, string $columna): void
    {
        if (! isset($columnas[$columna])) {
            throw new \InvalidArgumentException("La columna {$columna} no existe en la tabla.");
        }
        if (! self::columnaEditable($columnas[$columna], $llave)) {
            throw new \InvalidArgumentException("La columna {$columna} no se edita aquí (es la llave, una clave oculta o una columna calculada).");
        }
    }

    /**
     * El valor a guardar: vacío en una columna que no es de texto y acepta NULL = NULL; lo demás tal cual (MySQL en modo
     * estricto rechaza lo que no calza, y ese error se muestra).
     *
     * @param  array<string, mixed>  $columna
     */
    private static function valor(array $columna, mixed $valor): mixed
    {
        if (is_bool($valor)) {
            return $valor ? 1 : 0;
        }
        if ($valor === '' && $columna['nullable'] && ! preg_match('/char|text|enum|set/i', (string) $columna['type'])) {
            return null;
        }
        if (! is_scalar($valor) && $valor !== null) {
            throw new \InvalidArgumentException("Valor no válido para {$columna['name']}.");
        }

        return $valor;
    }

    /**
     * El mismo valor: en columnas numéricas como números (3.50 = 3.5); en las de texto, texto exacto ("007" no es "7");
     * NULL solo es igual a NULL.
     */
    private static function igual(mixed $a, mixed $b, bool $numerico = false): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }
        if ($numerico && is_numeric($a) && is_numeric($b)) {
            return self::numero($a) === self::numero($b);
        }

        return (string) $a === (string) $b;
    }

    /** Un número como texto normalizado, sin perder precisión en los grandes: "003.500" => "3.5" */
    private static function numero(mixed $v): string
    {
        $s = trim((string) $v);
        if (! preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return (string) (float) $s;
        }
        if (str_contains($s, '.')) {
            $s = rtrim(rtrim($s, '0'), '.');
        }
        $s = preg_replace('/^(-?)0+(?=\d)/', '$1', $s);

        return $s === '-0' ? '0' : $s;
    }

    /** @param array<string, mixed> $columna */
    private static function numerica(array $columna): bool
    {
        return (bool) preg_match('/int|decimal|numeric|float|double|real|bit/i', (string) $columna['type']);
    }

    /** @param array<string, mixed> $donde */
    private static function texto(array $donde): string
    {
        return implode(', ', array_map(fn ($c, $v) => "{$c} = {$v}", array_keys($donde), $donde));
    }

    /** El error de MySQL en palabras (los más comunes) */
    private static function errorMysql(QueryException $e): string
    {
        $codigo = (int) ($e->errorInfo[1] ?? 0);
        $mensaje = (string) ($e->errorInfo[2] ?? $e->getMessage());
        preg_match("/column '([^']+)'/i", $mensaje, $c);
        $columna = $c[1] ?? null;

        return match ($codigo) {
            1062 => 'Ya existe otra fila con ese valor (la columna no acepta repetidos).',
            1451 => 'No se puede borrar: otra tabla usa esa fila.',
            1452 => 'Ese valor no existe en la tabla relacionada.',
            1048, 1364 => 'Falta llenar la columna obligatoria'.($columna ? " {$columna}" : '').'.',
            1264 => 'El número es demasiado grande para la columna'.($columna ? " {$columna}" : '').'.',
            1366, 1292, 1265 => 'Valor no válido para la columna'.($columna ? " {$columna}" : '').'.',
            1406 => 'El texto es muy largo para la columna'.($columna ? " {$columna}" : '').'.',
            default => 'MySQL respondió: '.mb_substr($mensaje, 0, 250),
        };
    }
}
