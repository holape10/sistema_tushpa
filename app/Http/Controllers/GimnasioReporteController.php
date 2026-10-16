<?php

namespace App\Http\Controllers;

use App\Support\Gimnasio;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Reporte del gimnasio: ventas de planes (nuevos vs renovaciones, por plan, medio de pago y cajero), asistencia (por día,
 * hora y día de la semana, clientes más constantes), clientes por vencer y vencidos sin renovar (para recuperarlos),
 * congelamientos, ingresos denegados, cumpleaños y clientes por entrenador.
 */
class GimnasioReporteController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        abort_unless($user->esAdmin() || $user->tieneModulo('/gimnasio/reporte'), 403, 'No tienes acceso al reporte del gimnasio.');
        $suc = (int) $user->id_empresa_negocio;
        $desde = Carbon::parse($request->get('desde', now()->startOfMonth()->toDateString()))->startOfDay();
        $hasta = Carbon::parse($request->get('hasta', now()->toDateString()))->endOfDay();
        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
        }
        $rango = [$desde, $hasta];

        return view('empresas.gimnasio.reporte', [
            'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(),
            'negocio' => (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->value('nombre_comercial'),
        ] + $this->ventas($suc, $rango) + $this->asistencia($suc, $rango, $desde, $hasta) + $this->clientes($suc, $rango));
    }

    /** Ventas de planes vigentes (las anuladas no cuentan) */
    private function ventas(int $suc, array $rango): array
    {
        $ventas = DB::table('gym_membresias as m')->leftJoin('users as u', 'u.IdUsuario', '=', 'm.IdUsuario')
            ->where('m.id_empresa_negocio', $suc)->where('m.estado', 'ACTIVA')->whereBetween('m.creado', $rango)
            ->get(['m.mem_id', 'm.soc_id', 'm.plan', 'm.dias', 'm.precio', 'm.IdCpe_cabecera', 'u.apeusu as cajero']);

        // Primera membresía (de varios días) de cada cliente: si es la que se vendió, es cliente nuevo; si no, renovación
        $planes = $ventas->where('dias', '>', 1);
        $primeras = $planes->isEmpty() ? collect() : DB::table('gym_membresias')->where('id_empresa_negocio', $suc)->where('estado', 'ACTIVA')
            ->where('dias', '>', 1)->whereIn('soc_id', $planes->pluck('soc_id')->unique())->groupBy('soc_id')
            ->select('soc_id', DB::raw('MIN(mem_id) as primera'))->pluck('primera', 'soc_id');
        $nuevas = $planes->filter(fn ($m) => (int) ($primeras[$m->soc_id] ?? 0) === (int) $m->mem_id);

        $medios = $ventas->pluck('IdCpe_cabecera')->filter()->isEmpty() ? collect() : DB::table('venta_medio_pago as v')
            ->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'v.IdCpe_cabecera')->leftJoin('medios_pagos as p', 'p.id_med_pag', '=', 'v.id_med_pag')
            ->whereIn('v.IdCpe_cabecera', $ventas->pluck('IdCpe_cabecera')->filter()->unique())->whereNull('c.ccabaj')
            ->groupBy('p.nom_med_pag')->select('p.nom_med_pag as nombre', DB::raw('SUM(v.monto) as monto'), DB::raw('COUNT(*) as veces'))
            ->orderByDesc('monto')->get();

        $agrupar = fn (Collection $c, string $campo) => $c->groupBy($campo)->map(fn ($g, $k) => (object) ['nombre' => $k ?: '—',
            'cantidad' => $g->count(), 'monto' => round((float) $g->sum('precio'), 2)])->sortByDesc('monto')->values();

        return [
            'totalVentas' => round((float) $ventas->sum('precio'), 2), 'cantidadVentas' => $ventas->count(),
            'nuevas' => $nuevas->count(), 'montoNuevas' => round((float) $nuevas->sum('precio'), 2),
            'renovaciones' => $planes->count() - $nuevas->count(), 'montoRenovaciones' => round((float) $planes->sum('precio') - (float) $nuevas->sum('precio'), 2),
            'rutinas' => $ventas->where('dias', '<=', 1)->count(), 'montoRutinas' => round((float) $ventas->where('dias', '<=', 1)->sum('precio'), 2),
            'porPlan' => $agrupar($ventas, 'plan'), 'porCajero' => $agrupar($ventas, 'cajero'), 'porMedio' => $medios,
        ];
    }

    /** Ingresos permitidos: por día, por hora (horas pico), por día de la semana y los clientes más constantes */
    private function asistencia(int $suc, array $rango, Carbon $desde, Carbon $hasta): array
    {
        $base = fn () => DB::table('gym_asistencias')->where('id_empresa_negocio', $suc)->whereBetween('fecha_hora', $rango);

        $porDia = $base()->where('resultado', 'PERMITIDO')->groupBy(DB::raw('DATE(fecha_hora)'))
            ->select(DB::raw('DATE(fecha_hora) as dia'), DB::raw('COUNT(DISTINCT soc_id) as personas'))->pluck('personas', 'dia');
        // Todos los días del periodo (también los que no vino nadie), hasta 62 días
        $dias = collect();
        for ($d = $desde->copy(); $d->lte($hasta) && $dias->count() < 62; $d->addDay()) {
            $dias->push((object) ['dia' => $d->toDateString(), 'personas' => (int) ($porDia[$d->toDateString()] ?? 0)]);
        }
        $porHora = $base()->where('resultado', 'PERMITIDO')->groupBy(DB::raw('HOUR(fecha_hora)'))
            ->select(DB::raw('HOUR(fecha_hora) as hora'), DB::raw('COUNT(*) as ingresos'))->orderBy('hora')->pluck('ingresos', 'hora');
        $semana = $base()->where('resultado', 'PERMITIDO')->groupBy(DB::raw('DAYOFWEEK(fecha_hora)'))
            ->select(DB::raw('DAYOFWEEK(fecha_hora) as dia'), DB::raw('COUNT(*) as ingresos'))->pluck('ingresos', 'dia');
        $nombresDia = [2 => 'Lunes', 3 => 'Martes', 4 => 'Miércoles', 5 => 'Jueves', 6 => 'Viernes', 7 => 'Sábado', 1 => 'Domingo'];

        $top = DB::table('gym_asistencias as a')->join('socios as s', 's.soc_id', '=', 'a.soc_id')->join('cliente as c', 'c.clicod', '=', 's.clicod')
            ->where('a.id_empresa_negocio', $suc)->whereBetween('a.fecha_hora', $rango)->where('a.resultado', 'PERMITIDO')
            ->groupBy('a.soc_id', 'c.clinom', 's.codigo')->select('c.clinom as nombre', 's.codigo', DB::raw('COUNT(DISTINCT DATE(a.fecha_hora)) as dias'))
            ->orderByDesc('dias')->limit(10)->get();

        $total = (int) $porDia->sum();
        $diasConIngresos = max(1, $porDia->count());

        return [
            'asistencias' => $total, 'personasUnicas' => (int) $base()->where('resultado', 'PERMITIDO')->distinct()->count('soc_id'),
            'promedioDiario' => round($total / $diasConIngresos, 1),
            'denegados' => (int) $base()->where('resultado', 'DENEGADO')->count(),
            'motivosDenegados' => $base()->where('resultado', 'DENEGADO')->groupBy(DB::raw('SUBSTRING_INDEX(motivo, ".", 1)'))
                ->select(DB::raw('SUBSTRING_INDEX(motivo, ".", 1) as motivo'), DB::raw('COUNT(*) as veces'))->orderByDesc('veces')->limit(5)->get(),
            'porDia' => $dias, 'porHora' => collect(range(5, 23))->map(fn ($h) => (object) ['hora' => $h, 'ingresos' => (int) ($porHora[$h] ?? 0)]),
            'porSemana' => collect($nombresDia)->map(fn ($n, $k) => (object) ['dia' => $n, 'ingresos' => (int) ($semana[$k] ?? 0)])->values(),
            'top' => $top,
        ];
    }

    /** Situación de hoy, clientes para recuperar, congelamientos, cumpleaños y clientes por entrenador */
    private function clientes(int $suc, array $rango): array
    {
        $mems = Gimnasio::membresiasVigentes($suc);
        $cong = Gimnasio::congelamientosVigentes($mems->flatten()->pluck('mem_id')->all());
        $hoy = now()->toDateString();
        $socios = DB::table('socios as s')->join('cliente as c', 'c.clicod', '=', 's.clicod')->leftJoin('users as u', 'u.IdUsuario', '=', 's.entrenador_id')
            ->where('s.id_empresa_negocio', $suc)->where('s.estado', 'ACTIVO')
            ->get(['s.soc_id', 's.codigo', 's.fecha_nac', 's.entrenador_id', 'u.apeusu as entrenador', 'c.clinom', 'c.telefono'])
            ->map(function ($s) use ($mems, $cong) {
                $sit = Gimnasio::situacion($mems->get($s->soc_id, collect()), $cong);
                $s->estado = $sit['estado'];
                $s->vence = $sit['vence'];
                $s->dias = $sit['dias'];
                $s->plan = $sit['membresia']->plan ?? null;

                return $s;
            });

        $congelamientos = DB::table('gym_congelamientos as g')->join('gym_membresias as m', 'm.mem_id', '=', 'g.mem_id')
            ->where('m.id_empresa_negocio', $suc)->where('g.estado', 'ACTIVO')->whereBetween('g.creado', $rango)->get(['g.dias', 'g.motivo', 'g.origen']);

        return [
            'situacion' => $socios->countBy('estado'),
            'porVencer' => $socios->filter(fn ($s) => $s->estado === 'POR_VENCER' && $s->dias <= 7)->sortBy('vence')->values(),
            'sinRenovar' => $socios->filter(fn ($s) => $s->estado === 'VENCIDO' && $s->vence && $s->vence >= now()->subDays(30)->toDateString())->sortByDesc('vence')->values(),
            'nuevosClientes' => (int) DB::table('socios')->where('id_empresa_negocio', $suc)->whereBetween('creado', $rango)->count(),
            'congelamientos' => $congelamientos->count(), 'diasCongelados' => (int) $congelamientos->sum('dias'),
            'congeladosPorMotivo' => $congelamientos->countBy('motivo')->sortDesc(),
            'cumpleanos' => $socios->filter(fn ($s) => $s->fecha_nac && Carbon::parse($s->fecha_nac)->month === now()->month)
                ->sortBy(fn ($s) => Carbon::parse($s->fecha_nac)->day)->values(),
            'porEntrenador' => $socios->filter(fn ($s) => $s->entrenador_id)->groupBy('entrenador')->map(fn ($g, $n) => (object) ['nombre' => $n,
                'clientes' => $g->count(), 'vigentes' => $g->whereIn('estado', ['VIGENTE', 'POR_VENCER'])->count()])->sortByDesc('clientes')->values(),
            'hoy' => $hoy,
        ];
    }
}
