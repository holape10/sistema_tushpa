<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reporte del hotel: ventas (habitación, horas extra, consumos), ocupación por día y por habitación,
 * horas de más concurridas, servicios preferidos, reservas y la bitácora de lo delicado (quién y por qué).
 * Las ventas se cuentan por la fecha de ingreso de cada estadía; las estadías anuladas no suman.
 */
class HotelReporte
{
    public const EVENTOS = [
        'SALIDA_SIN_EXCESO' => ['Salida sin cobrar tiempo de más', '#dc2626'],
        'CONSUMO_QUITADO' => ['Consumo quitado', '#d97706'],
        'ANULACION' => ['Ingreso anulado', '#7c3aed'],
        'CAMBIO_HABITACION' => ['Cambio de habitación', '#0891b2'],
        'EXCESO_COBRADO' => ['Tiempo de más cobrado', '#059669'],
    ];

    public static function generar(int $suc, Carbon $desde, Carbon $hasta): array
    {
        $habitaciones = DB::table('habitaciones')->where('id_empresa_negocio', $suc)->orderBy('hab_piso')->orderBy('hab_nom')->get(['hab_id', 'hab_nom', 'hab_tip', 'hab_piso']);
        $nHab = max(1, $habitaciones->count());
        $ventas = self::ventas($suc, $desde, $hasta);
        $estadias = $ventas['estadias'];

        // Periodo anterior del mismo largo, para comparar
        $dias = (int) $desde->diffInDays($hasta) + 1;
        $antes = self::ventas($suc, $desde->copy()->subDays($dias), $desde->copy()->subSecond());

        $ocupacion = self::ocupacion($suc, $habitaciones, $desde, $hasta);
        $ocupacionAntes = self::ocupacion($suc, $habitaciones, $desde->copy()->subDays($dias), $desde->copy()->subSecond());

        // Por habitación
        $porHabitacion = $habitaciones->map(function ($h) use ($estadias, $ocupacion) {
            $suyas = $estadias->where('hab_id', $h->hab_id);
            $min = $ocupacion['por_habitacion'][$h->hab_id] ?? 0;

            return (object) ['nombre' => $h->hab_nom, 'tipo' => $h->hab_tip, 'piso' => $h->hab_piso, 'estadias' => $suyas->count(),
                'ingresos' => round($suyas->sum('total'), 2), 'horas' => round($min / 60, 1),
                'ocupacion' => $ocupacion['disponible'] > 0 ? round($min / ($ocupacion['disponible'] / max(1, $ocupacion['n_hab'])) * 100, 1) : 0];
        })->sortByDesc('ingresos')->values();

        // Servicios de tiempo preferidos (la habitación en sí)
        $servicios = $ventas['lineas']->where('clase', 'hospedaje')->groupBy('descripcion')
            ->map(fn ($g, $n) => (object) ['nombre' => $n, 'veces' => $g->count(), 'monto' => round($g->sum('importe'), 2)])->sortByDesc('veces')->values();

        // Hora del día en que más llegan
        $porHora = array_fill(0, 24, 0);
        foreach ($estadias as $e) {
            $porHora[(int) Carbon::parse($e->inicio)->format('G')]++;
        }

        // Por día: ventas por tipo y ocupación
        $porDia = collect(CarbonPeriod::create($desde->copy()->startOfDay(), $hasta->copy()->startOfDay()))->map(function ($d) use ($ventas, $ocupacion) {
            $k = $d->toDateString();
            $l = $ventas['lineas']->where('dia', $k);

            return (object) ['dia' => $k, 'hospedaje' => round($l->where('clase', 'hospedaje')->sum('importe'), 2),
                'extra' => round($l->where('clase', 'extra')->sum('importe'), 2), 'consumo' => round($l->where('clase', 'consumo')->sum('importe'), 2),
                'estadias' => $ventas['estadias']->where('dia', $k)->count(), 'ocupacion' => $ocupacion['por_dia'][$k] ?? null];
        });

        $finalizadas = $estadias->whereNotNull('salida');
        $eventos = DB::table('hotel_eventos as ev')->leftJoin('habitaciones as h', 'h.hab_id', '=', 'ev.hab_id')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'ev.IdUsuario')->leftJoin('users as a', 'a.IdUsuario', '=', 'ev.autorizado_por')
            ->leftJoin('hospedajes as ho', 'ho.hos_id', '=', 'ev.hos_id')
            ->where('ev.id_empresa_negocio', $suc)->whereBetween('ev.created_at', [$desde, $hasta])->where('ev.tipo', '!=', 'EXCESO_COBRADO')
            ->orderByDesc('ev.id')->limit(300)
            ->get(['ev.*', 'h.hab_nom', 'ho.cliente', DB::raw('COALESCE(u.apeusu, u.name) as usuario'), DB::raw('COALESCE(a.apeusu, a.name) as autorizo')]);

        $reservas = DB::table('hotel_reservas')->where('id_empresa_negocio', $suc)->whereBetween('llegada', [$desde, $hasta])
            ->select('estado', DB::raw('COUNT(*) as n'))->groupBy('estado')->pluck('n', 'estado');
        $noLlegaron = DB::table('hotel_reservas')->where('id_empresa_negocio', $suc)->where('estado', 'PENDIENTE')
            ->whereBetween('llegada', [$desde, min($hasta, now()->subHours(3))])->count();

        $total = $ventas['total'];

        return [
            'kpi' => [
                'total' => $total, 'total_antes' => $antes['total'],
                'hospedaje' => $ventas['hospedaje'], 'extra' => $ventas['extra'], 'consumo' => $ventas['consumo'],
                'estadias' => $estadias->count(), 'estadias_antes' => $antes['estadias']->count(),
                'ticket' => $estadias->count() ? round($total / $estadias->count(), 2) : 0,
                'ocupacion' => $ocupacion['porcentaje'], 'ocupacion_antes' => $ocupacionAntes['porcentaje'],
                'duracion' => $finalizadas->count() ? round($finalizadas->avg(fn ($e) => Carbon::parse($e->inicio)->diffInMinutes(Carbon::parse($e->salida))) / 60, 1) : 0,
                'cobrado' => $ventas['cobrado'], 'pendiente' => round($total - $ventas['cobrado'], 2),
                'horas_extra' => round($ventas['lineas']->where('clase', 'extra')->sum('horas'), 1),
                'anuladas' => $ventas['anuladas'], 'habitaciones' => $habitaciones->count(),
                'sin_exceso' => $eventos->where('tipo', 'SALIDA_SIN_EXCESO')->count(),
                'sin_exceso_monto' => round((float) $eventos->where('tipo', 'SALIDA_SIN_EXCESO')->sum('monto'), 2),
                'quitados_monto' => round((float) $eventos->where('tipo', 'CONSUMO_QUITADO')->sum('monto'), 2),
            ],
            'porDia' => $porDia, 'porHabitacion' => $porHabitacion, 'servicios' => $servicios, 'porHora' => $porHora,
            'eventos' => $eventos, 'reservas' => $reservas, 'noLlegaron' => $noLlegaron,
            'consumosTop' => $ventas['lineas']->where('clase', 'consumo')->groupBy('descripcion')
                ->map(fn ($g, $n) => (object) ['nombre' => $n, 'cantidad' => $g->sum('cantidad'), 'monto' => round($g->sum('importe'), 2)])
                ->sortByDesc('monto')->take(8)->values(),
        ];
    }

    /**
     * Estadías que ingresaron en el periodo y sus líneas clasificadas: la habitación, las horas extra y los consumos.
     *
     * @return array{estadias: Collection, lineas: Collection, total: float, hospedaje: float, extra: float, consumo: float, cobrado: float, anuladas: int}
     */
    private static function ventas(int $suc, Carbon $desde, Carbon $hasta): array
    {
        $todas = DB::table('hospedajes')->where('id_empresa_negocio', $suc)->whereBetween('inicio', [$desde, $hasta])->get();
        $estadias = $todas->where('hos_est', '!=', 'ANULADO')->values();
        $lineas = DB::table('pedidos_detalle as d')->leftJoin('productos as p', 'p.IdProducto', '=', 'd.IdProducto')
            ->whereIn('d.ped_id', $estadias->pluck('ped_id'))->where('d.estadoitem', '!=', 'Eliminado')->orderBy('d.ped_det_id')
            ->get(['d.ped_id', 'd.ped_det_id', 'd.descripcion', 'd.ped_det_can', 'd.ped_det_pre', 'd.item_facturado', 'p.minutos']);

        $porPedido = $estadias->keyBy('ped_id');
        $vistos = [];
        $lineas = $lineas->map(function ($l) use (&$vistos, $porPedido) {
            $esTiempo = (int) $l->minutos > 0;
            $clase = ! $esTiempo ? 'consumo' : (isset($vistos[$l->ped_id]) ? 'extra' : 'hospedaje');
            if ($esTiempo) {
                $vistos[$l->ped_id] = true;
            }

            return (object) ['ped_id' => $l->ped_id, 'descripcion' => $l->descripcion, 'clase' => $clase, 'cantidad' => (float) $l->ped_det_can,
                'importe' => (float) $l->ped_det_can * (float) $l->ped_det_pre, 'cobrado' => (float) $l->item_facturado * (float) $l->ped_det_pre,
                'horas' => $esTiempo ? (float) $l->ped_det_can * (int) $l->minutos / 60 : 0,
                'dia' => Carbon::parse($porPedido[$l->ped_id]->inicio)->toDateString()];
        });
        $totales = $lineas->groupBy('ped_id')->map(fn ($g) => $g->sum('importe'));
        $estadias = $estadias->map(function ($e) use ($totales) {
            $e->total = round((float) ($totales[$e->ped_id] ?? 0), 2);
            $e->dia = Carbon::parse($e->inicio)->toDateString();

            return $e;
        });

        return ['estadias' => $estadias, 'lineas' => $lineas, 'total' => round($lineas->sum('importe'), 2),
            'hospedaje' => round($lineas->where('clase', 'hospedaje')->sum('importe'), 2), 'extra' => round($lineas->where('clase', 'extra')->sum('importe'), 2),
            'consumo' => round($lineas->where('clase', 'consumo')->sum('importe'), 2), 'cobrado' => round($lineas->sum('cobrado'), 2),
            'anuladas' => $todas->where('hos_est', 'ANULADO')->count()];
    }

    /**
     * Ocupación: minutos ocupados ÷ minutos disponibles (habitaciones × tiempo del periodo, hasta ahora).
     *
     * @return array{porcentaje: float, por_dia: array<string, float>, por_habitacion: array<int, float>, disponible: float, n_hab: int}
     */
    private static function ocupacion(int $suc, Collection $habitaciones, Carbon $desde, Carbon $hasta): array
    {
        $nHab = max(1, $habitaciones->count());
        $limite = $hasta->copy()->min(now());
        if ($limite->lte($desde)) {
            return ['porcentaje' => 0.0, 'por_dia' => [], 'por_habitacion' => [], 'disponible' => 0, 'n_hab' => $nHab];
        }
        $estadias = DB::table('hospedajes')->where('id_empresa_negocio', $suc)->where('hos_est', '!=', 'ANULADO')
            ->where('inicio', '<', $limite)->where(fn ($q) => $q->whereNull('salida')->orWhere('salida', '>', $desde))
            ->get(['hab_id', 'inicio', 'salida', 'hos_est']);

        $porDia = [];
        $porHab = [];
        $ocupado = 0;
        foreach ($estadias as $e) {
            $ini = Carbon::parse($e->inicio)->max($desde);
            $fin = ($e->salida ? Carbon::parse($e->salida) : now())->min($limite);
            if ($fin->lte($ini)) {
                continue;
            }
            $ocupado += $ini->diffInMinutes($fin);
            $porHab[$e->hab_id] = ($porHab[$e->hab_id] ?? 0) + $ini->diffInMinutes($fin);
            // Repartir por día
            $cursor = $ini->copy();
            while ($cursor->lt($fin)) {
                $finDia = $cursor->copy()->endOfDay()->min($fin);
                $k = $cursor->toDateString();
                $porDia[$k] = ($porDia[$k] ?? 0) + $cursor->diffInMinutes($finDia);
                $cursor = $cursor->copy()->addDay()->startOfDay();
            }
        }
        $disponible = $nHab * $desde->diffInMinutes($limite);
        $pctDia = [];
        foreach (CarbonPeriod::create($desde->copy()->startOfDay(), $limite->copy()->startOfDay()) as $d) {
            $iniDia = $d->copy()->max($desde);
            $finDia = $d->copy()->endOfDay()->min($limite);
            $minutos = max(1, $iniDia->diffInMinutes($finDia));
            $pctDia[$d->toDateString()] = round(min(100, ($porDia[$d->toDateString()] ?? 0) / ($nHab * $minutos) * 100), 1);
        }

        return ['porcentaje' => $disponible > 0 ? round(min(100, $ocupado / $disponible * 100), 1) : 0.0, 'por_dia' => $pctDia,
            'por_habitacion' => $porHab, 'disponible' => $disponible, 'n_hab' => $nHab];
    }
}
