<?php

namespace App\Support;

use App\Support\Contabilidad\Contabilidad;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Centros de costo: dónde se gana o se gasta (un local, un área o un proyecto).
 *  - Un centro LOCAL está amarrado a una sucursal: sus ventas y su costo de ventas son de ese centro, y los gastos que
 *    se registran en ese local van a él por defecto.
 *  - Los gastos (y la depreciación de los activos) llevan su centro. Contablemente, cada centro tiene su cuenta de destino
 *    de la clase 9 (94 administración, 95 ventas, 90 producción, 97 financieros): con el asiento de destino activado,
 *    el gasto se lleva a 9x (D) contra 791 cargas imputables (H).
 */
class CentrosCosto
{
    public const TIPOS = ['LOCAL' => 'Local (sucursal)', 'AREA' => 'Área', 'PROYECTO' => 'Proyecto'];

    public const DESTINOS = ['94' => 'Gastos administrativos', '95' => 'Gastos de ventas', '90' => 'Costos de producción', '97' => 'Gastos financieros'];

    /** Las ventas: valor de venta sin IGV (cdepve); las notas de crédito restan */
    private const VENTAS = "SUM(CASE WHEN c.tdocod = '07' THEN -1 ELSE 1 END * d.cdepve)";

    /** El costo de lo vendido; solo las notas de crédito por devolución o anulación lo devuelven */
    private const COSTO = "SUM(CASE WHEN c.tdocod = '07' AND c.tipnot IN ('01','02','06','07') THEN -1 WHEN c.tdocod IN ('07','08') THEN 0 ELSE 1 END * d.costo * d.cdecan)";

    public static function lista(string $ruc, bool $soloActivos = false): Collection
    {
        return DB::table('centros_costo')->where('IdEmpresa', $ruc)->when($soloActivos, fn ($q) => $q->where('activo', true))
            ->orderBy('codigo')->get();
    }

    /** El centro del local (si lo tiene) */
    public static function deSucursal(string $ruc, int $sucursal): ?int
    {
        $id = DB::table('centros_costo')->where('IdEmpresa', $ruc)->where('tipo', 'LOCAL')->where('id_empresa_negocio', $sucursal)
            ->where('activo', true)->value('id');

        return $id ? (int) $id : null;
    }

    /** ¿Ese centro es de la empresa y está activo? */
    public static function valido(string $ruc, ?int $id): bool
    {
        return $id !== null && DB::table('centros_costo')->where('id', $id)->where('IdEmpresa', $ruc)->where('activo', true)->exists();
    }

    /**
     * @param  array{codigo: string, nombre: string, tipo: string, id_empresa_negocio?: ?int, cuenta_destino: string, activo?: bool}  $d
     */
    public static function guardar(string $ruc, array $d, ?int $id = null): int
    {
        $codigo = mb_strtoupper(trim($d['codigo']));
        if (DB::table('centros_costo')->where('IdEmpresa', $ruc)->where('codigo', $codigo)->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
            throw new \RuntimeException("Ya hay un centro de costo con el código {$codigo}.");
        }
        $local = $d['tipo'] === 'LOCAL' ? (int) ($d['id_empresa_negocio'] ?? 0) : null;
        if ($d['tipo'] === 'LOCAL') {
            if (! DB::table('empresa_negocios')->where('id_empresa_negocio', $local)->where('IdEmpresa', $ruc)->exists()) {
                throw new \RuntimeException('Elige el local (sucursal) de este centro.');
            }
            if (DB::table('centros_costo')->where('IdEmpresa', $ruc)->where('tipo', 'LOCAL')->where('id_empresa_negocio', $local)
                ->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                throw new \RuntimeException('Ese local ya tiene su centro de costo.');
            }
        }
        if (! array_key_exists($d['cuenta_destino'], self::DESTINOS)) {
            throw new \RuntimeException('Cuenta de destino no válida.');
        }
        $datos = ['codigo' => $codigo, 'nombre' => mb_strtoupper(trim($d['nombre'])), 'tipo' => $d['tipo'], 'id_empresa_negocio' => $local,
            'cuenta_destino' => $d['cuenta_destino'], 'activo' => (bool) ($d['activo'] ?? true), 'updated_at' => now()];
        if ($id) {
            DB::table('centros_costo')->where('id', $id)->where('IdEmpresa', $ruc)->update($datos);

            return $id;
        }

        return DB::table('centros_costo')->insertGetId($datos + ['IdEmpresa' => $ruc, 'created_at' => now()]);
    }

    /** Un centro LOCAL por cada sucursal que aún no tiene (para empezar rápido). Devuelve cuántos creó. */
    public static function crearDeLocales(string $ruc): int
    {
        $n = 0;
        foreach (DB::table('empresa_negocios')->where('IdEmpresa', $ruc)->orderBy('id_empresa_negocio')->get(['id_empresa_negocio', 'nombre_comercial']) as $s) {
            if (self::deSucursal($ruc, (int) $s->id_empresa_negocio) || DB::table('centros_costo')->where('IdEmpresa', $ruc)->where('id_empresa_negocio', $s->id_empresa_negocio)->exists()) {
                continue;
            }
            $codigo = 'L'.str_pad((string) $s->id_empresa_negocio, 3, '0', STR_PAD_LEFT);
            if (DB::table('centros_costo')->where('IdEmpresa', $ruc)->where('codigo', $codigo)->exists()) {
                continue;
            }
            self::guardar($ruc, ['codigo' => $codigo, 'nombre' => $s->nombre_comercial ?: 'LOCAL '.$s->id_empresa_negocio, 'tipo' => 'LOCAL',
                'id_empresa_negocio' => (int) $s->id_empresa_negocio, 'cuenta_destino' => '95']);
            $n++;
        }

        return $n;
    }

    /**
     * Resultado de cada centro en el periodo: ventas y costo de ventas (los locales), gastos y depreciación.
     *
     * @return Collection<int, object{id: ?int, codigo: string, nombre: string, tipo: string, ventas: float, costo: float, gastos: float, depreciacion: float, resultado: float, margen: ?float, porCategoria: array<string, float>}>
     */
    public static function resultados(string $ruc, string $desde, string $hasta): Collection
    {
        $centros = self::lista($ruc)->keyBy('id');
        $locales = $centros->where('tipo', 'LOCAL')->pluck('id', 'id_empresa_negocio');

        $ventas = DB::table('cpe_detalle as d')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
            ->where('c.IdEmpresa', $ruc)->whereBetween('c.ccafem', [$desde, $hasta])->whereNull('c.ccabaj')
            ->groupBy('c.id_empresa_negocio')->select('c.id_empresa_negocio', DB::raw(self::VENTAS.' as ventas'), DB::raw(self::COSTO.' as costo'))
            ->get()->keyBy('id_empresa_negocio');
        // Gastos sin IGV cuando el IGV se usa como crédito fiscal; las notas de crédito restan
        $gastos = DB::table('gastos as g')->leftJoin('gasto_categorias as k', 'k.id', '=', 'g.categoria_id')
            ->where('g.IdEmpresa', $ruc)->where('g.estado', 'Registrado')->whereBetween('g.fecha', [$desde, $hasta])
            ->groupBy('g.centro_costo_id', 'k.nombre')
            ->select('g.centro_costo_id', DB::raw("COALESCE(k.nombre, 'SIN CATEGORÍA') as categoria"),
                DB::raw("SUM(CASE WHEN g.tdocod = '07' THEN -1 ELSE 1 END * (g.total - CASE WHEN g.credito_fiscal = 1 THEN g.igv ELSE 0 END)
                    * CASE WHEN g.moneda = 'USD' THEN g.tipo_cambio ELSE 1 END) as monto"))->get();
        $periodos = [str_replace('-', '', substr($desde, 0, 7)), str_replace('-', '', substr($hasta, 0, 7))];
        $depreciacion = DB::table('activos_depreciacion as d')->join('activos_fijos as a', 'a.id', '=', 'd.activo_id')
            ->where('d.IdEmpresa', $ruc)->whereBetween('d.periodo', $periodos)->groupBy('a.centro_costo_id')
            ->select('a.centro_costo_id', DB::raw('SUM(d.monto) as monto'))->pluck('monto', 'centro_costo_id');

        $filas = collect();
        foreach ($centros->concat([(object) ['id' => null, 'codigo' => '—', 'nombre' => 'SIN CENTRO DE COSTO', 'tipo' => '', 'id_empresa_negocio' => null]]) as $c) {
            $v = $c->tipo === 'LOCAL' ? ($ventas[$c->id_empresa_negocio] ?? null) : null;
            // Las ventas de los locales que no tienen centro van a "sin centro"
            if ($c->id === null) {
                $sinCentro = $ventas->reject(fn ($x, $suc) => isset($locales[$suc]));
                $v = (object) ['ventas' => $sinCentro->sum('ventas'), 'costo' => $sinCentro->sum('costo')];
            }
            $g = $gastos->filter(fn ($x) => (string) $x->centro_costo_id === (string) $c->id);
            $fila = (object) [
                'id' => $c->id, 'codigo' => $c->codigo, 'nombre' => $c->nombre, 'tipo' => $c->tipo,
                'ventas' => round((float) ($v->ventas ?? 0), 2), 'costo' => round((float) ($v->costo ?? 0), 2),
                'gastos' => round((float) $g->sum('monto'), 2), 'depreciacion' => round((float) ($depreciacion[$c->id ?? ''] ?? 0), 2),
                'porCategoria' => $g->mapWithKeys(fn ($x) => [$x->categoria => round((float) $x->monto, 2)])->sortDesc()->all(),
            ];
            $fila->resultado = round($fila->ventas - $fila->costo - $fila->gastos - $fila->depreciacion, 2);
            $fila->margen = $fila->ventas > 0 ? round($fila->resultado / $fila->ventas * 100, 1) : null;
            // "Sin centro" solo se muestra si tiene algo
            if ($c->id !== null || $fila->ventas || $fila->gastos || $fila->depreciacion) {
                $filas->push($fila);
            }
        }

        return $filas;
    }

    /**
     * Líneas del asiento de destino (9x D → 79 H) por centro, para un gasto o la depreciación. Sin centro o sin el asiento
     * de destino activado: ninguna línea.
     *
     * @param  array<int|string, float>  $montosPorCentro  centro_costo_id => monto
     * @return array<int, array{cuenta: string, debe?: float, haber?: float, glosa?: string}>
     */
    public static function lineasDestino(string $ruc, array $montosPorCentro, $imputables): array
    {
        $cfg = Contabilidad::config($ruc);
        if (! $cfg->asiento_destino) {
            return [];
        }
        $centros = DB::table('centros_costo')->where('IdEmpresa', $ruc)->whereIn('id', array_keys($montosPorCentro))->get()->keyBy('id');
        $cuenta79 = self::imputable($imputables, '79');
        $lineas = [];
        $total = 0.0;
        foreach ($montosPorCentro as $centro => $monto) {
            $monto = round((float) $monto, 2);
            if (! isset($centros[$centro]) || abs($monto) < 0.005 || ! $cuenta79) {
                continue;
            }
            $cuenta9 = self::imputable($imputables, (string) $centros[$centro]->cuenta_destino);
            if (! $cuenta9) {
                continue;
            }
            $lineas[] = ['cuenta' => $cuenta9, $monto > 0 ? 'debe' : 'haber' => abs($monto), 'glosa' => 'CENTRO '.$centros[$centro]->codigo.' '.$centros[$centro]->nombre];
            $total += $monto;
        }
        if ($lineas) {
            $lineas[] = ['cuenta' => $cuenta79, $total > 0 ? 'haber' : 'debe' => abs(round($total, 2)), 'glosa' => 'CARGAS IMPUTABLES A CENTROS DE COSTO'];
        }

        return $lineas;
    }

    /** La cuenta si recibe movimientos; si no, su primera subcuenta que sí (o null) */
    public static function imputable($imputables, string $cuenta): ?string
    {
        if (isset($imputables[$cuenta])) {
            return $cuenta;
        }

        return collect($imputables)->keys()->map(fn ($c) => (string) $c)->filter(fn ($c) => str_starts_with($c, $cuenta))->sort()->first();
    }
}
