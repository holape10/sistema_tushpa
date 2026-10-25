<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consumo por caja y barra: qué se vendió, qué se regaló (cortesías) y qué insumos y productos salieron del almacén
 * por las ventas cobradas en cada PC de caja. La barra de una caja es su almacén (o, si no tiene, su impresora de barra).
 */
class ConsumoCajas
{
    public const SIN_CAJA = 'SIN CAJA';

    /**
     * @return array{cajas: Collection, barras: Collection, productos: Collection, insumos: Collection}
     */
    public static function datos(int $suc, Carbon $desde, Carbon $hasta, ?string $ver = null): array
    {
        $agentes = DB::table('impresion_agentes as a')->leftJoin('almacenes as al', 'al.id_almacen', '=', 'a.id_almacen')
            ->leftJoin('configuracion_impresoras as i', 'i.Id', '=', 'a.impresora_barra')
            ->where('a.id_empresa_negocio', $suc)->get(['a.pc', 'al.descripcion as almacen', 'i.descripcion as barra'])->keyBy('pc');
        $barraDe = fn (string $caja) => $agentes[$caja]->almacen ?? $agentes[$caja]->barra ?? 'SIN BARRA';

        $cpe = DB::table('cpe_cabecera')->where('id_empresa_negocio', $suc)->whereNull('ccabaj')->whereIn('tdocod', ['01', '03', '13'])
            ->whereBetween('fecha_hora', [$desde, $hasta])->get(['IdCpe_cabecera', 'equipo', 'ccaitv'])
            ->map(function ($c) use ($barraDe) {
                $c->caja = $c->equipo ?: self::SIN_CAJA;
                $c->barra = $c->equipo ? $barraDe($c->equipo) : 'SIN BARRA';

                return $c;
            });

        // Resumen de cada caja (ventas, cortesías, comprobantes) y de cada barra
        $cortesiasPorCpe = DB::table('cpe_detalle')->whereIn('IdCpe_cabecera', $cpe->pluck('IdCpe_cabecera')->all() ?: [0])->where('cortesia', 1)
            ->groupBy('IdCpe_cabecera')->selectRaw('IdCpe_cabecera, SUM(cdecan * COALESCE(precio_lista, 0)) as valor')->pluck('valor', 'IdCpe_cabecera');
        $cajas = $cpe->groupBy('caja')->map(fn ($g, $caja) => (object) [
            'caja' => $caja, 'barra' => $g->first()->barra, 'comprobantes' => $g->count(),
            'ventas' => round($g->sum('ccaitv'), 2), 'cortesias' => round($g->sum(fn ($c) => (float) ($cortesiasPorCpe[$c->IdCpe_cabecera] ?? 0)), 2),
        ])->sortBy('caja')->values();
        $barras = $cajas->groupBy('barra')->map(fn ($g, $barra) => (object) [
            'barra' => $barra, 'cajas' => $g->pluck('caja')->all(), 'comprobantes' => $g->sum('comprobantes'),
            'ventas' => round($g->sum('ventas'), 2), 'cortesias' => round($g->sum('cortesias'), 2),
        ])->sortBy('barra')->values();

        // Lo elegido: todo, una barra o una caja
        $elegidos = $cpe->filter(fn ($c) => ! $ver || $ver === 'caja:'.$c->caja || $ver === 'barra:'.$c->barra)->pluck('IdCpe_cabecera')->all() ?: [0];

        $productos = DB::table('cpe_detalle as d')->whereIn('d.IdCpe_cabecera', $elegidos)
            ->groupBy('d.cdedes')->orderBy('d.cdedes')
            ->get(['d.cdedes', DB::raw('SUM(CASE WHEN d.cortesia = 0 THEN d.cdecan ELSE 0 END) as vendidos'),
                DB::raw('SUM(CASE WHEN d.cortesia = 0 THEN d.cdevve ELSE 0 END) as total'),
                DB::raw('SUM(CASE WHEN d.cortesia = 1 THEN d.cdecan ELSE 0 END) as cortesias'),
                DB::raw('SUM(CASE WHEN d.cortesia = 1 THEN d.cdecan * COALESCE(d.precio_lista, 0) ELSE 0 END) as valor_cortesias')])
            ->map(fn ($p) => (object) ['nombre' => $p->cdedes, 'vendidos' => (float) $p->vendidos, 'total' => round((float) $p->total, 2),
                'cortesias' => (float) $p->cortesias, 'valor_cortesias' => round((float) $p->valor_cortesias, 2)]);

        // Lo que salió del almacén por esas ventas (insumos de las recetas y productos): salidas menos lo devuelto al corregir ventas
        $insumos = DB::table('movimientos_productos as m')->join('productos as p', 'p.IdProducto', '=', 'm.IdProducto')
            ->whereIn('m.IdCpe_cabecera', $elegidos)
            ->where(fn ($q) => $q->where('m.mov_tip', 'E')->orWhere(fn ($i) => $i->where('m.mov_tip', 'I')->where('m.cod_tip_ope', '05')))
            ->groupBy('m.IdProducto', 'p.pronom', 'p.umecod', 'p.ume_equivalente', 'p.factor_equivalente', 'p.costo')->orderBy('p.pronom')
            ->get(['m.IdProducto', 'p.pronom', 'p.umecod', 'p.ume_equivalente', 'p.factor_equivalente', 'p.costo',
                DB::raw("SUM(CASE WHEN m.mov_tip = 'E' THEN m.cantidad ELSE -m.cantidad END) as cantidad"),
                DB::raw("SUM(CASE WHEN m.mov_tip = 'E' THEN m.cantidad ELSE -m.cantidad END * m.costo) as costo_total")])
            ->filter(fn ($i) => abs((float) $i->cantidad) > 0.00001)
            ->map(fn ($i) => (object) [
                'nombre' => $i->pronom, 'cantidad' => round((float) $i->cantidad, 4),
                'texto' => Unidades::texto((float) $i->cantidad, $i->umecod, $i->ume_equivalente, (float) $i->factor_equivalente),
                'costo' => round((float) $i->costo_total, 2),
            ])->values();

        return compact('cajas', 'barras', 'productos', 'insumos');
    }
}
