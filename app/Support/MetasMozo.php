<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Metas de venta de los mozos: el administrador pone una meta diaria, semanal y mensual para todos (y, si quiere,
 * una distinta para alguno). Cuenta lo cobrado de las mesas que atendió cada mozo (cpe_cabecera.mozo), sin anuladas.
 * La semana va de lunes a domingo.
 */
class MetasMozo
{
    public const PERIODOS = ['dia' => 'diaria', 'semana' => 'semanal', 'mes' => 'mensual'];

    /** @return Collection<int, object> metas guardadas, por IdUsuario (0 = la de todos) */
    public static function metas(int $sucursal): Collection
    {
        return DB::table('metas_mozo')->where('id_empresa_negocio', $sucursal)->get()->keyBy(fn ($m) => (int) $m->IdUsuario);
    }

    /**
     * Meta de un mozo: la suya si la tiene; si no (o si ese periodo está vacío), la de todos.
     *
     * @return array{dia: ?float, semana: ?float, mes: ?float}
     */
    public static function metaDe(Collection $metas, int $usuario): array
    {
        $propia = $metas[$usuario] ?? null;
        $general = $metas[0] ?? null;
        $valor = fn (string $col) => ($v = $propia?->$col ?? $general?->$col) !== null && (float) $v > 0 ? (float) $v : null;

        return ['dia' => $valor('diaria'), 'semana' => $valor('semanal'), 'mes' => $valor('mensual')];
    }

    /** Mozos activos de la sucursal (rol mozo) */
    public static function mozos(int $sucursal): Collection
    {
        return DB::table('users as u')->join('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')
            ->where('r.role_id', 8)->where('u.estusu', 1)->where('u.id_empresa_negocio', $sucursal)
            ->orderBy('u.apeusu')->get(['u.IdUsuario', DB::raw("COALESCE(NULLIF(u.apeusu, ''), u.name) as nombre")])
            ->unique('IdUsuario')->values();
    }

    /** @return array{dia: array{Carbon, Carbon}, semana: array{Carbon, Carbon}, mes: array{Carbon, Carbon}} */
    public static function rangos(?Carbon $hoy = null): array
    {
        $hoy ??= now();

        return [
            'dia' => [$hoy->copy()->startOfDay(), $hoy->copy()->endOfDay()],
            'semana' => [$hoy->copy()->startOfWeek(Carbon::MONDAY), $hoy->copy()->endOfWeek(Carbon::SUNDAY)],
            'mes' => [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()],
        ];
    }

    /** @return Collection<int, object{total: float, ventas: int}> lo vendido por cada mozo entre dos fechas */
    public static function ventas(int $sucursal, Carbon $desde, Carbon $hasta, ?int $mozo = null): Collection
    {
        return DB::table('cpe_cabecera')->where('id_empresa_negocio', $sucursal)->whereNull('ccabaj')
            ->whereIn('tdocod', ['01', '03', '13'])->whereNotNull('mozo')
            ->when($mozo, fn ($q) => $q->where('mozo', $mozo))
            ->whereBetween('fecha_hora', [$desde, $hasta])
            ->groupBy('mozo')->get(['mozo', DB::raw('SUM(ccaitv) as total'), DB::raw('COUNT(*) as ventas')])
            ->keyBy(fn ($v) => (int) $v->mozo)
            ->map(fn ($v) => (object) ['total' => round((float) $v->total, 2), 'ventas' => (int) $v->ventas]);
    }

    /** Avance de una meta: % logrado y cuánto falta */
    public static function avance(float $vendido, ?float $meta): array
    {
        return ['vendido' => $vendido, 'meta' => $meta,
            'pct' => $meta ? round($vendido / $meta * 100, 1) : null,
            'falta' => $meta ? round(max(0, $meta - $vendido), 2) : null];
    }

    /**
     * Avance de cada mozo en el día, la semana y el mes, con su ticket promedio del mes. Ordenado por lo vendido en el mes.
     *
     * @return Collection<int, object>
     */
    public static function tablero(int $sucursal, ?Carbon $hoy = null): Collection
    {
        $metas = self::metas($sucursal);
        $rangos = self::rangos($hoy);
        $ventas = collect($rangos)->map(fn ($r) => self::ventas($sucursal, $r[0], $r[1]));
        // Mozos con rol y también quien atendió mesas este mes sin tener el rol (un cajero que hizo de mozo)
        $mozos = self::mozos($sucursal);
        $otros = $ventas['mes']->keys()->diff($mozos->pluck('IdUsuario'));
        if ($otros->isNotEmpty()) {
            $mozos = $mozos->concat(DB::table('users')->whereIn('IdUsuario', $otros)
                ->get(['IdUsuario', DB::raw("COALESCE(NULLIF(apeusu, ''), name) as nombre")]));
        }

        return $mozos->map(function ($m) use ($metas, $ventas) {
            $id = (int) $m->IdUsuario;
            $meta = self::metaDe($metas, $id);
            $fila = (object) ['id' => $id, 'nombre' => $m->nombre, 'propia' => isset($metas[$id])];
            foreach (array_keys(self::PERIODOS) as $p) {
                $fila->$p = self::avance((float) ($ventas[$p][$id]->total ?? 0), $meta[$p]);
            }
            $mes = $ventas['mes'][$id] ?? null;
            $fila->ventasMes = (int) ($mes->ventas ?? 0);
            $fila->ticket = $mes && $mes->ventas ? round($mes->total / $mes->ventas, 2) : 0;

            return $fila;
        })->sortByDesc(fn ($f) => $f->mes['vendido'])->values();
    }

    /** Lo que ve el mozo en su pantalla de mesas: su avance de hoy, la semana y el mes (null si no hay metas) */
    public static function delMozo(int $sucursal, int $usuario): ?array
    {
        $metas = self::metas($sucursal);
        $meta = self::metaDe($metas, $usuario);
        if (! array_filter($meta)) {
            return null;
        }
        $avance = [];
        foreach (self::rangos() as $p => [$desde, $hasta]) {
            if ($meta[$p]) {
                $avance[$p] = self::avance((float) (self::ventas($sucursal, $desde, $hasta, $usuario)[$usuario]->total ?? 0), $meta[$p]);
            }
        }

        return $avance;
    }
}
