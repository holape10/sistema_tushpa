<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use App\Support\Lotes;
use App\Support\Notas;
use App\Support\SucursalesVisibles;
use App\Support\Sunat\SunatService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $usuario = Auth::user();
        // El administrador elige la sucursal (o todas juntas); los demás solo ven la suya
        $sucursales = SucursalesVisibles::de($usuario);
        $sucursal = SucursalesVisibles::elegida($request, $usuario);
        $ids = SucursalesVisibles::ids($usuario, $sucursal);

        $desde = Carbon::parse($request->get('desde', now()->startOfMonth()->toDateString()))->startOfDay();
        $hasta = Carbon::parse($request->get('hasta', now()->toDateString()))->startOfDay();
        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        // Periodo anterior de la misma duración, para comparar
        $dias = $desde->diffInDays($hasta) + 1;
        $antDesde = $desde->copy()->subDays($dias);
        $antHasta = $desde->copy()->subDay();

        // Ventas vigentes (sin anuladas) de la sucursal en un rango
        $ventas = fn (Carbon $d, Carbon $h) => DB::table('cpe_cabecera as c')
            ->whereIn('c.id_empresa_negocio', $ids)
            ->whereNull('c.ccabaj')
            ->whereBetween('c.ccafem', [$d->toDateString(), $h->toDateString()]);

        // ---- Tarjetas ----
        $porTipo = $ventas($desde, $hasta)->groupBy('c.tdocod')
            ->select('c.tdocod', DB::raw('SUM('.Notas::SIGNO_SQL.' * c.ccaitv) as total'), DB::raw('COUNT(*) as n'))->get()->keyBy('tdocod');
        $total = (float) $porTipo->sum('total');
        $cantidad = (int) $porTipo->except(['07', '08'])->sum('n');   // las notas no son ventas nuevas
        $totalAnterior = (float) $ventas($antDesde, $antHasta)->sum(DB::raw(Notas::SIGNO_SQL.' * c.ccaitv'));

        // La nota de crédito que devuelve productos también devuelve su costo
        $costo = (float) DB::table('cpe_detalle as d')
            ->joinSub($ventas($desde, $hasta)->select('c.IdCpe_cabecera', 'c.tdocod'), 'v', 'v.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
            ->sum(DB::raw("CASE WHEN v.tdocod = '07' THEN -1 ELSE 1 END * d.costo * d.cdecan"));

        $kpi = [
            'notas' => (float) ($porTipo['13']->total ?? 0),
            'facturas' => (float) ($porTipo['01']->total ?? 0),
            'boletas' => (float) ($porTipo['03']->total ?? 0),
            'total' => $total,
            'cantidad' => $cantidad,
            'ticket' => $cantidad ? round($total / $cantidad, 2) : 0,
            'utilidad' => round($total - $costo, 2),
            'margen' => $total > 0 ? round(($total - $costo) / $total * 100, 1) : 0,
            'variacion' => $totalAnterior > 0 ? round(($total - $totalAnterior) / $totalAnterior * 100, 1) : null,
            'anterior' => $totalAnterior,
            'sinCosto' => $costo <= 0 && $total > 0,
        ];

        // ---- Ventas por día (periodo actual y anterior alineados por posición) ----
        $serie = function (Carbon $d, Carbon $h) use ($ventas) {
            $datos = $ventas($d, $h)->groupBy('c.ccafem')
                ->select('c.ccafem as dia', DB::raw('SUM('.Notas::SIGNO_SQL.' * c.ccaitv) as total'))->pluck('total', 'dia');
            $res = [];
            for ($f = $d->copy(); $f->lte($h); $f->addDay()) {
                $res[] = round((float) ($datos[$f->toDateString()] ?? 0), 2);
            }

            return $res;
        };
        $etiquetasDias = [];
        for ($f = $desde->copy(); $f->lte($hasta); $f->addDay()) {
            $etiquetasDias[] = $f->locale('es')->isoFormat('ddd DD/MM');
        }
        $graficoDias = ['labels' => $etiquetasDias, 'actual' => $serie($desde, $hasta), 'anterior' => $serie($antDesde, $antHasta)];

        // ---- Ventas por hora ----
        $porHora = $ventas($desde, $hasta)->groupBy(DB::raw('HOUR(c.fecha_hora)'))
            ->select(DB::raw('HOUR(c.fecha_hora) as hora'), DB::raw('SUM('.Notas::SIGNO_SQL.' * c.ccaitv) as total'))->pluck('total', 'hora');
        $graficoHoras = ['labels' => [], 'datos' => []];
        for ($h = 0; $h < 24; $h++) {
            $graficoHoras['labels'][] = str_pad($h, 2, '0', STR_PAD_LEFT).'h';
            $graficoHoras['datos'][] = round((float) ($porHora[$h] ?? 0), 2);
        }

        // ---- Medios de pago (contado) + crédito ----
        $medios = DB::table('venta_medio_pago as v')
            ->joinSub($ventas($desde, $hasta)->select('c.IdCpe_cabecera'), 'x', 'x.IdCpe_cabecera', '=', 'v.IdCpe_cabecera')
            ->leftJoin('medios_pagos as m', 'm.id_med_pag', '=', 'v.id_med_pag')
            ->groupBy('m.nom_med_pag')->select('m.nom_med_pag as nombre', DB::raw('SUM(v.monto) as total'))
            ->orderByDesc('total')->get();
        $credito = (float) $ventas($desde, $hasta)->sum('c.totalcredito');
        if ($credito > 0) {
            $medios->push((object) ['nombre' => 'CRÉDITO', 'total' => $credito]);
        }

        // ---- Tipo de pedido ----
        $porPedido = $ventas($desde, $hasta)
            ->groupBy(DB::raw("COALESCE(NULLIF(c.ped_tip, ''), 'Directa')"))
            ->select(DB::raw("COALESCE(NULLIF(c.ped_tip, ''), 'Directa') as tipo"), DB::raw('SUM('.Notas::SIGNO_SQL.' * c.ccaitv) as total'), DB::raw('COUNT(*) as n'))
            ->orderByDesc('total')->get();

        // ---- Rankings ----
        $topProductos = DB::table('cpe_detalle as d')
            ->joinSub($ventas($desde, $hasta)->select('c.IdCpe_cabecera', 'c.tdocod', 'c.tipnot'), 'v', 'v.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
            ->groupBy('d.IdProducto', 'd.cdedes', 'd.procod')
            // Notas de crédito restan (las cantidades solo cuando devuelven producto); notas de débito no son productos vendidos
            ->select('d.procod', 'd.cdedes',
                DB::raw("SUM(CASE WHEN v.tdocod = '07' AND v.tipnot IN ('01','02','06','07') THEN -d.cdecan WHEN v.tdocod IN ('07','08') THEN 0 ELSE d.cdecan END) as cantidad"),
                DB::raw("SUM(CASE WHEN v.tdocod = '07' THEN -1 ELSE 1 END * d.cdevve) as total"),
                DB::raw("SUM(CASE WHEN v.tdocod = '07' THEN -1 ELSE 1 END * (d.cdevve - d.costo * d.cdecan)) as utilidad"))
            ->orderByDesc('total')->limit(10)->get();

        $topClientes = $ventas($desde, $hasta)->where('c.ccandi', '!=', '00000000')
            ->groupBy('c.ccandi', 'c.ccanom')
            ->select('c.ccandi', 'c.ccanom', DB::raw("SUM(c.tdocod NOT IN ('07','08')) as compras"), DB::raw('SUM('.Notas::SIGNO_SQL.' * c.ccaitv) as total'))
            ->orderByDesc('total')->limit(10)->get();
        $ventasVarios = (float) $ventas($desde, $hasta)->where('c.ccandi', '00000000')->sum(DB::raw(Notas::SIGNO_SQL.' * c.ccaitv'));

        $topMozos = $ventas($desde, $hasta)->whereNotNull('c.mozo')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'c.mozo')
            ->groupBy('c.mozo', 'u.apeusu')
            ->select('u.apeusu', DB::raw("SUM(c.tdocod NOT IN ('07','08')) as atenciones"), DB::raw('SUM('.Notas::SIGNO_SQL.' * c.ccaitv) as total'))
            ->orderByDesc('total')->limit(8)->get();

        // ---- Inventario: productos con stock en o bajo el mínimo (o negativo) ----
        // El almacén principal de cada sucursal (con "todas", cada producto con el de su sucursal)
        $almacenes = collect($ids)->map(fn ($id) => DB::table('almacenes')->where('id_empresa_negocio', $id)->orderByDesc('predeterminado')->value('id_almacen'))->filter()->values()->all();
        $porAgotarse = DB::table('productos as p')
            ->leftJoin('producto_stock as s', fn ($j) => $j->on('s.IdProducto', '=', 'p.IdProducto')->whereIn('s.id_almacen', $almacenes ?: [0]))
            ->whereIn('p.id_empresa_negocio', $ids)->where('p.proest', 'Activo')->whereIn('p.promocion', [0, 4])
            ->whereRaw('COALESCE(s.stock, 0) <= GREATEST(p.stock_min, 0)')
            ->orderByRaw('COALESCE(s.stock, 0)')
            ->limit(15)->get(['p.IdProducto', 'p.pronom', 'p.stock_min', DB::raw('COALESCE(s.stock, 0) as stock')]);

        // ---- Farmacia: lotes vencidos y pronto a vencer (todos los almacenes de la sucursal) ----
        $diasAlerta = Lotes::diasAlerta($sucursal ?: (int) $usuario->id_empresa_negocio);
        $vencimientos = LoteController::resumen($ids, $diasAlerta);
        $porVencer = DB::table('producto_lote as l')
            ->join('productos as p', 'p.IdProducto', '=', 'l.IdProducto')
            ->join('almacenes as a', 'a.id_almacen', '=', 'l.id_almacen')
            ->whereIn('a.id_empresa_negocio', $ids)->where('l.stock', '>', 0)
            ->where('l.vencimiento', '<=', now()->addDays($diasAlerta)->toDateString())
            ->orderBy('l.vencimiento')->limit(12)
            ->get(['l.IdProducto', 'l.lote', 'l.vencimiento', 'l.stock', 'p.pronom', 'p.umecod', 'a.descripcion as almacen']);

        // ---- Ahora mismo ----
        $ahora = [
            'turno' => Turno::with('usuario')->whereIn('id_empresa_negocio', $ids)->where('estado', 'ABIERTO')->get(),
            'mesas' => DB::table('pedidos')->whereIn('id_empresa_negocio', $ids)->where('ped_est', 'Aperturado')->whereNotNull('mes_id')->count(),
            'totalMesas' => DB::table('mesas')->whereIn('id_empresa_negocio', $ids)->count(),
            'llevar' => DB::table('pedidos')->whereIn('id_empresa_negocio', $ids)->where('ped_est', 'Aperturado')->whereNull('mes_id')->count(),
            'porCobrar' => (float) DB::table('pedidos')->whereIn('id_empresa_negocio', $ids)->where('ped_est', 'Aperturado')->sum('ped_tot'),
            'sunat' => DB::table('cpe_cabecera')->whereIn('id_empresa_negocio', $ids)
                ->whereIn('tdocod', SunatService::TIPOS_ELECTRONICOS)->whereIn('est_sunat', SunatService::ESTADOS_REENVIABLES)
                ->whereNull('ccabaj')->count(),
            'hoy' => (float) DB::table('cpe_cabecera')->whereIn('id_empresa_negocio', $ids)->whereNull('ccabaj')
                ->where('ccafem', now()->toDateString())->sum(DB::raw("CASE WHEN tdocod = '07' THEN -ccaitv ELSE ccaitv END")),
        ];

        return view('dashboard', [
            'usuario' => $usuario, 'sucursales' => $sucursales, 'sucursal' => $sucursal,
            'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(),
            'antDesde' => $antDesde, 'antHasta' => $antHasta,
            'kpi' => $kpi, 'graficoDias' => $graficoDias, 'graficoHoras' => $graficoHoras,
            'medios' => $medios, 'porPedido' => $porPedido,
            'topProductos' => $topProductos, 'topClientes' => $topClientes, 'ventasVarios' => $ventasVarios, 'topMozos' => $topMozos,
            'porAgotarse' => $porAgotarse, 'ahora' => $ahora,
            'vencimientos' => $vencimientos, 'porVencer' => $porVencer, 'diasAlerta' => $diasAlerta,
        ]);
    }
}
