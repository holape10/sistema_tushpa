<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use Illuminate\Support\Facades\DB;

/**
 * Inventario de las empresas y las bases del servidor, para limpiar lo de prueba con cuidado: cada empresa con su
 * tamaño y su actividad, y las bases "huérfanas" (que ninguna empresa usa: importaciones del sistema antiguo, bases de
 * empresas eliminadas que quedaron). Borrar una huérfana siempre saca su respaldo antes y nunca toca las del sistema.
 */
class Inventario
{
    /** Bases propias de MySQL (nunca son del sistema) */
    private const DEL_MOTOR = ['mysql', 'information_schema', 'performance_schema', 'sys'];

    /** @return array<string, float> base => MB que ocupa */
    public static function tamanos(): array
    {
        return collect(DB::connection('central')->select(
            'SELECT table_schema AS base, ROUND(SUM(data_length + index_length) / 1048576, 1) AS mb FROM information_schema.TABLES GROUP BY table_schema'
        ))->mapWithKeys(fn ($f) => [(string) $f->base => (float) $f->mb])->all();
    }

    /** @return array<int, string> todas las bases del servidor */
    public static function basesDelServidor(): array
    {
        return collect(DB::connection('central')->select('SHOW DATABASES'))->map(fn ($r) => (string) array_values((array) $r)[0])->all();
    }

    /** Las que nunca se borran: el sistema principal, la central del panel y las de las empresas que siguen activas o suspendidas */
    public static function protegidas(): array
    {
        return array_values(array_filter(array_unique(array_merge(
            [(string) config('tenancy.base_sistema'), (string) config('database.connections.central.database')],
            Cliente::where('estado', '!=', 'ELIMINADO')->pluck('base_datos')->map(fn ($b) => (string) $b)->all()
        ))));
    }

    /**
     * Las bases del servidor que no usa ninguna empresa (ni el sistema ni el panel).
     *
     * @param  array<int, string>  $todas
     * @param  array<int, string>  $protegidas
     * @return array<int, string>
     */
    public static function filtrarHuerfanas(array $todas, array $protegidas): array
    {
        return array_values(array_filter($todas, fn ($b) => ! in_array(strtolower($b), self::DEL_MOTOR, true) && ! in_array($b, $protegidas, true)));
    }

    /** @return array<int, string> */
    public static function huerfanas(): array
    {
        return self::filtrarHuerfanas(self::basesDelServidor(), self::protegidas());
    }

    /**
     * Las empresas con lo necesario para decidir si son de prueba.
     *
     * @return array<int, array{id: int, ruc: string, nombre: string, estado: string, marca: string, base: string, existe: bool, mb: float,
     *     comprobantes: ?int, ultimo: ?string, creada: string}>
     */
    public static function empresas(): array
    {
        $tamanos = self::tamanos();
        $existen = array_flip(self::basesDelServidor());

        return Cliente::with('marca')->orderBy('estado')->orderBy('ruc')->get()->map(function (Cliente $c) use ($tamanos, $existen) {
            $base = (string) $c->base_datos;
            $existe = isset($existen[$base]);
            [$comprobantes, $ultimo] = [null, null];
            if ($existe) {
                try {
                    [$comprobantes, $ultimo] = Tenancy::en($base, fn () => DB::getSchemaBuilder()->hasTable('cpe_cabecera')
                        ? [DB::table('cpe_cabecera')->count(), DB::table('cpe_cabecera')->max('ccafem')] : [0, null]);
                } catch (\Throwable) {
                    // Una base dañada o a medias: se ve igual en la lista
                }
            }

            return [
                'id' => (int) $c->id, 'ruc' => (string) $c->ruc, 'nombre' => (string) ($c->nombre_comercial ?: $c->razon_social),
                'estado' => (string) $c->estado, 'marca' => $c->marca_id ? (string) $c->marca?->nombre : 'TUSHPA', 'base' => $base,
                'existe' => $existe, 'mb' => $tamanos[$base] ?? 0.0, 'comprobantes' => $comprobantes,
                'ultimo' => $ultimo ? substr((string) $ultimo, 0, 10) : null, 'creada' => (string) $c->created_at?->format('Y-m-d'),
            ];
        })->all();
    }

    /** Por qué no se puede borrar esa base (null = sí se puede) */
    public static function motivoParaNoBorrar(string $base, array $protegidas, array $delServidor): ?string
    {
        if (! preg_match('/^[A-Za-z0-9_]{1,64}$/', $base)) {
            return 'Ese nombre de base no es válido.';
        }
        if (in_array(strtolower($base), self::DEL_MOTOR, true)) {
            return 'Es una base del propio MySQL.';
        }
        if (in_array($base, $protegidas, true)) {
            return 'La usa el sistema, el panel o una empresa que no está eliminada: esa se elimina desde el panel (suspender → Eliminar).';
        }
        if (! in_array($base, $delServidor, true)) {
            return 'Esa base no existe en el servidor.';
        }

        return null;
    }

    /**
     * La borra; con $guardarCopia saca antes su respaldo (si el respaldo falla, no se borra nada).
     *
     * @return string|null la copia guardada
     */
    public static function eliminarHuerfana(string $base, bool $guardarCopia = true): ?string
    {
        if ($motivo = self::motivoParaNoBorrar($base, self::protegidas(), self::basesDelServidor())) {
            throw new \RuntimeException($motivo);
        }
        $respaldo = $guardarCopia ? Respaldos::crear($base, 'huerfana-eliminada') : null;
        DB::connection('central')->statement("DROP DATABASE `{$base}`");

        return $respaldo;
    }
}
