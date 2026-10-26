<?php

namespace App\Support;

use App\Http\Controllers\TurnoController;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El negocio visto por el dueño: cómo va hoy y el mes (contra la semana y el mes pasado), proyección, meta y punto de
 * equilibrio; cuándo se vende (mapa de calor); cómo trabaja cada uno (ranking); qué es raro (alertas); y el resumen del
 * día para mandarle por WhatsApp.
 */
class PanelDueno
{
    private const SIGNO = Notas::SIGNO_SQL;

    public const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    /** Comprobantes vigentes de la sucursal (los anulados no cuentan) */
    private static function ventas(int $suc)
    {
        return DB::table('cpe_cabecera as c')->where('c.id_empresa_negocio', $suc)->whereNull('c.ccabaj');
    }

    /** Total, comprobantes y ticket promedio entre dos momentos */
    public static function totales(int $suc, string $desde, string $hasta): object
    {
        $r = self::ventas($suc)->whereBetween('c.fecha_hora', [$desde, $hasta])
            ->selectRaw('COALESCE(SUM('.self::SIGNO.' * c.ccaitv), 0) as total, SUM(c.tdocod NOT IN (\'07\',\'08\')) as tickets')->first();
        $tickets = (int) $r->tickets;

        return (object) ['total' => round((float) $r->total, 2), 'tickets' => $tickets, 'promedio' => $tickets ? round((float) $r->total / $tickets, 2) : 0.0];
    }

    /** Variación en % (null si antes no hubo nada) */
    public static function variacion(float $ahora, float $antes): ?float
    {
        return $antes > 0 ? round(($ahora - $antes) / $antes * 100, 1) : null;
    }

    /**
     * Cómo va el negocio: hoy contra el mismo día de la semana pasada (hasta la misma hora), el mes contra el mismo
     * tramo del mes pasado, la proyección de cierre, la meta y el punto de equilibrio.
     */
    public static function resumen(int $suc, ?Carbon $ahora = null): array
    {
        $ahora ??= now();
        $hoy = self::totales($suc, $ahora->copy()->startOfDay(), $ahora);
        $semanaPasada = self::totales($suc, $ahora->copy()->subWeek()->startOfDay(), $ahora->copy()->subWeek());
        $inicioMes = $ahora->copy()->startOfMonth();
        $mes = self::totales($suc, $inicioMes, $ahora);
        $mesPasadoTramo = self::totales($suc, $inicioMes->copy()->subMonthNoOverflow(), $ahora->copy()->subMonthNoOverflow());
        $mesPasado = self::totales($suc, $inicioMes->copy()->subMonthNoOverflow(), $inicioMes->copy()->subSecond());

        // Proyección: lo vendido por día transcurrido (el día de hoy cuenta según la hora) por los días del mes
        $diasMes = $ahora->daysInMonth;
        $transcurridos = max(0.5, ($ahora->day - 1) + min(1, $ahora->secondsSinceMidnight() / 86400 * 1.4));
        $proyeccion = round($mes->total / $transcurridos * $diasMes, 2);

        $config = DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->first();
        $meta = (float) ($config->meta_mensual ?? 0);
        // Margen bruto del mes (ventas menos costo de lo vendido) para saber cuánto deja cada sol vendido
        $fc = Rentabilidad::foodCost($suc, $inicioMes->toDateString(), $ahora->toDateString(), 1)['total'];
        // Sin costos registrados el margen saldría 100% y el punto de equilibrio sería falso: mejor no calcularlo
        $margen = $fc->ventas > 0 && $fc->costo > 0 ? max(0.01, $fc->ganancia / $fc->ventas) : null;
        $gastosFijos = (float) ($config->gastos_fijos_mes ?? 0);
        $gastosSugeridos = Schema::hasTable('gastos') ? round((float) DB::table('gastos')->where('id_empresa_negocio', $suc)->where('estado', '!=', 'ANULADO')
            ->whereBetween('fecha', [$inicioMes->copy()->subMonthNoOverflow()->toDateString(), $inicioMes->copy()->subDay()->toDateString()])->sum('total'), 2) : 0.0;
        $equilibrioMes = $gastosFijos > 0 && $margen ? round($gastosFijos / $margen, 2) : null;

        return [
            'hoy' => $hoy, 'semanaPasada' => $semanaPasada, 'varHoy' => self::variacion($hoy->total, $semanaPasada->total),
            'mes' => $mes, 'mesPasadoTramo' => $mesPasadoTramo, 'mesPasado' => $mesPasado, 'varMes' => self::variacion($mes->total, $mesPasadoTramo->total),
            'proyeccion' => $proyeccion, 'varProyeccion' => self::variacion($proyeccion, $mesPasado->total), 'diasMes' => $diasMes, 'diaActual' => $ahora->day,
            'meta' => $meta, 'avanceMeta' => $meta > 0 ? round($mes->total / $meta * 100, 1) : null,
            'faltaPorDia' => $meta > 0 ? round(max(0, $meta - $mes->total) / max(1, $diasMes - $ahora->day + 1), 2) : null,
            'margen' => $margen !== null ? round($margen * 100, 1) : null, 'gananciaBruta' => $fc->ganancia,
            'gastosFijos' => $gastosFijos, 'gastosSugeridos' => $gastosSugeridos,
            'equilibrioMes' => $equilibrioMes, 'equilibrioDia' => $equilibrioMes !== null ? round($equilibrioMes / $diasMes, 2) : null,
            // Lo que quedaría al cerrar el mes: la ganancia bruta proyectada menos los gastos fijos
            'gananciaNeta' => $margen !== null && $gastosFijos > 0 ? round($proyeccion * $margen - $gastosFijos, 2) : null,
            'porDia' => self::ventas($suc)->whereBetween('c.fecha_hora', [$inicioMes, $ahora])->groupBy('dia')
                ->selectRaw('DAY(c.fecha_hora) as dia, SUM('.self::SIGNO.' * c.ccaitv) as total')->pluck('total', 'dia')->map(fn ($v) => round((float) $v, 2)),
            'topProductos' => DB::table('cpe_detalle as d')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
                ->where('c.id_empresa_negocio', $suc)->whereNull('c.ccabaj')->whereNotIn('c.tdocod', ['07', '08'])->whereBetween('c.fecha_hora', [$inicioMes, $ahora])
                ->groupBy('d.cdedes')->selectRaw('d.cdedes as nombre, SUM(d.cdecan) as cantidad, SUM(d.cdevve) as total')
                ->orderByDesc('total')->limit(5)->get(),
            'whatsapp' => $config->whatsapp_dueno ?? null,
        ];
    }

    /**
     * Mapa de calor: ventas por día de la semana y hora (lunes = 1). También el momento más fuerte y el más flojo.
     *
     * @return array{celdas: array<int, array<int, array{total: float, tickets: int}>>, maximo: float, horas: array<int, int>, pico: ?array, flojo: ?array, porDia: array<int, float>}
     */
    public static function mapaCalor(int $suc, string $desde, string $hasta): array
    {
        $filas = self::ventas($suc)->whereBetween('c.fecha_hora', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->groupBy('dia', 'hora')
            ->selectRaw('WEEKDAY(c.fecha_hora) + 1 as dia, HOUR(c.fecha_hora) as hora, SUM('.self::SIGNO.' * c.ccaitv) as total, SUM(c.tdocod NOT IN (\'07\',\'08\')) as tickets')
            ->get();
        $celdas = [];
        foreach ($filas as $f) {
            $celdas[(int) $f->dia][(int) $f->hora] = ['total' => round((float) $f->total, 2), 'tickets' => (int) $f->tickets];
        }
        $horas = $filas->pluck('hora')->map(fn ($h) => (int) $h);
        $rango = $horas->isEmpty() ? [] : range($horas->min(), $horas->max());
        $todas = $filas->map(fn ($f) => ['dia' => (int) $f->dia, 'hora' => (int) $f->hora, 'total' => (float) $f->total]);
        // Las semanas del rango: el promedio por celda dice cuánto se vende "un viernes a las 9 pm"
        $semanas = max(1, (int) ceil((Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1) / 7));

        return ['celdas' => $celdas, 'maximo' => (float) $todas->max('total'), 'horas' => $rango, 'semanas' => $semanas,
            'pico' => $todas->sortByDesc('total')->first(), 'flojo' => $todas->where('total', '>', 0)->sortBy('total')->first(),
            'porDia' => $todas->groupBy('dia')->map(fn ($g) => round($g->sum('total'), 2))->all()];
    }

    /**
     * Ranking del equipo: lo que vendió cada mozo y cobró cada cajero, su ticket promedio, y lo que conviene vigilar
     * (descuentos, cortesías, platos quitados de la comanda, comprobantes anulados y faltantes de caja).
     */
    public static function equipo(int $suc, string $desde, string $hasta): Collection
    {
        [$d, $h] = [$desde.' 00:00:00', $hasta.' 23:59:59'];
        $base = fn () => self::ventas($suc)->whereBetween('c.fecha_hora', [$d, $h]);
        $comoMozo = $base()->whereNotNull('c.mozo')->groupBy('c.mozo')
            ->selectRaw('c.mozo as usuario, SUM('.self::SIGNO.' * c.ccaitv) as total, SUM(c.tdocod NOT IN (\'07\',\'08\')) as tickets')->get()->keyBy('usuario');
        $comoCaja = $base()->groupBy('c.IdUsuario')
            ->selectRaw('c.IdUsuario as usuario, SUM('.self::SIGNO.' * c.ccaitv) as total, SUM(c.tdocod NOT IN (\'07\',\'08\')) as tickets')->get()->keyBy('usuario');
        $descuentos = $base()->where('c.descuento', '>', 0)->groupBy(DB::raw('COALESCE(c.IdUsuario_descuento, c.IdUsuario)'))
            ->selectRaw('COALESCE(c.IdUsuario_descuento, c.IdUsuario) as usuario, SUM(c.descuento) as total, COUNT(*) as n')->get()->keyBy('usuario');
        $cortesias = DB::table('cpe_detalle as d')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
            ->where('c.id_empresa_negocio', $suc)->whereBetween('c.fecha_hora', [$d, $h])->where('d.cortesia', 1)
            ->groupBy('c.IdUsuario')->selectRaw('c.IdUsuario as usuario, COUNT(*) as n, SUM(d.cdecan * COALESCE(d.precio_lista, 0)) as valor')->get()->keyBy('usuario');
        $anulados = DB::table('cpe_cabecera')->where('id_empresa_negocio', $suc)->whereBetween('ccabaj', [$d, $h])
            ->groupBy('IdUsuario_baja')->selectRaw('IdUsuario_baja as usuario, COUNT(*) as n, SUM(ccaitv) as total')->get()->keyBy('usuario');
        $quitados = Schema::hasColumn('cocina_tickets', 'IdUsuario') ? DB::table('cocina_tickets')->where('id_empresa_negocio', $suc)
            ->where('tipo', 'ANULACION')->whereBetween('creado', [$d, $h])->groupBy(DB::raw('COALESCE(IdUsuario, mozo)'))
            ->selectRaw('COALESCE(IdUsuario, mozo) as usuario, COUNT(*) as n')->pluck('n', 'usuario') : collect();
        $caja = self::diferenciasCaja($suc, $desde, $hasta)->groupBy('usuario')->map(fn ($g) => round($g->sum('diferencia'), 2));

        $ids = collect([$comoMozo, $comoCaja, $descuentos, $cortesias, $anulados])->flatMap(fn ($c) => $c->keys())
            ->merge($quitados->keys())->merge($caja->keys())->filter()->unique();
        $usuarios = DB::table('users as u')->leftJoin('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')->leftJoin('roles as ro', 'ro.id', '=', 'r.role_id')
            ->whereIn('u.IdUsuario', $ids)->get(['u.IdUsuario', 'u.apeusu', 'u.name', 'ro.description as rol'])->keyBy('IdUsuario');

        return $ids->map(function ($id) use ($usuarios, $comoMozo, $comoCaja, $descuentos, $cortesias, $anulados, $quitados, $caja) {
            $u = $usuarios[$id] ?? null;
            $mozo = $comoMozo[$id] ?? null;
            $cobro = $comoCaja[$id] ?? null;
            $vendio = (float) ($mozo->total ?? 0);
            $tickets = (int) ($mozo->tickets ?? 0);

            return (object) ['id' => (int) $id, 'nombre' => trim((string) ($u->apeusu ?? '') ?: (string) ($u->name ?? 'Usuario '.$id)), 'rol' => $u->rol ?? '',
                'vendio' => round($vendio, 2), 'atenciones' => $tickets, 'promedio' => $tickets ? round($vendio / $tickets, 2) : null,
                'cobro' => round((float) ($cobro->total ?? 0), 2), 'cobros' => (int) ($cobro->tickets ?? 0),
                'descuentos' => round((float) ($descuentos[$id]->total ?? 0), 2), 'n_descuentos' => (int) ($descuentos[$id]->n ?? 0),
                'cortesias' => (int) ($cortesias[$id]->n ?? 0), 'valor_cortesias' => round((float) ($cortesias[$id]->valor ?? 0), 2),
                'anulados' => (int) ($anulados[$id]->n ?? 0), 'monto_anulado' => round((float) ($anulados[$id]->total ?? 0), 2),
                'quitados' => (int) ($quitados[$id] ?? 0), 'caja' => (float) ($caja[$id] ?? 0)];
        })->sortByDesc(fn ($u) => $u->vendio ?: $u->cobro / 1000)->values();
    }

    /**
     * La jornada en curso (discotecas y bares venden de noche hasta la madrugada): de mediodía a mediodía, como el reporte
     * de ventas por vendedor. $atras = 1 es la jornada anterior.
     *
     * @return array{0: Carbon, 1: Carbon} inicio y fin (el fin de la jornada en curso es ahora)
     */
    public static function jornada(int $atras = 0, ?Carbon $ahora = null): array
    {
        $ahora ??= now();
        $inicio = $ahora->copy()->setTime(Reportes::CORTE_JORNADA, 0);
        if ($ahora->lt($inicio)) {
            $inicio->subDay();
        }
        $inicio->subDays($atras);

        return [$inicio, $atras ? $inicio->copy()->addDay()->subSecond() : $ahora->copy()];
    }

    /**
     * Ventas de un turno o una jornada por persona: cuánto vendió cada mozo (sus mesas), cuánto cobró cada cajero y
     * cuánto trajo cada promotor. Con $turnos, solo las ventas cobradas en esos turnos de caja.
     *
     * @param  array<int, int>|null  $turnos
     * @return array{total: object, mozos: Collection, cajeros: Collection, promotores: Collection}
     */
    public static function ventasDelTurno(int $suc, ?Carbon $desde, ?Carbon $hasta, ?array $turnos = null): array
    {
        $base = fn () => self::ventas($suc)
            ->when($turnos !== null, fn ($q) => $q->whereIn('c.id_turno', $turnos ?: [0]))
            ->when($turnos === null, fn ($q) => $q->whereBetween('c.fecha_hora', [$desde, $hasta]));
        $total = $base()->selectRaw('COALESCE(SUM('.self::SIGNO.' * c.ccaitv), 0) as total, SUM(c.tdocod NOT IN (\'07\',\'08\')) as tickets')->first();
        $suma = round((float) $total->total, 2);
        $por = function (string $columna) use ($base, $suma) {
            $filas = $base()->whereNotNull($columna)->groupBy($columna)
                ->selectRaw($columna.' as usuario, SUM('.self::SIGNO.' * c.ccaitv) as total, SUM(c.tdocod NOT IN (\'07\',\'08\')) as tickets, MIN(c.fecha_hora) as primera, MAX(c.fecha_hora) as ultima')
                ->get();
            $nombres = DB::table('users')->whereIn('IdUsuario', $filas->pluck('usuario'))->get(['IdUsuario', 'apeusu', 'name'])->keyBy('IdUsuario');

            return $filas->map(fn ($f) => (object) [
                'nombre' => trim((string) ($nombres[$f->usuario]->apeusu ?? '')) ?: (string) ($nombres[$f->usuario]->name ?? 'Usuario '.$f->usuario),
                'total' => round((float) $f->total, 2), 'tickets' => (int) $f->tickets,
                'promedio' => $f->tickets ? round((float) $f->total / $f->tickets, 2) : 0.0,
                'participacion' => $suma > 0 ? round((float) $f->total / $suma * 100, 1) : 0.0,
                'primera' => $f->primera, 'ultima' => $f->ultima,
            ])->sortByDesc('total')->values();
        };

        return [
            'total' => (object) ['total' => $suma, 'tickets' => (int) $total->tickets, 'promedio' => $total->tickets ? round($suma / $total->tickets, 2) : 0.0],
            'mozos' => $por('c.mozo'), 'cajeros' => $por('c.IdUsuario'), 'promotores' => $por('c.promotor_id'),
            'sinMozo' => round((float) $base()->whereNull('c.mozo')->sum(DB::raw(self::SIGNO.' * c.ccaitv')), 2),
        ];
    }

    /**
     * Turnos de caja de la sucursal para elegir uno: los recientes (los abiertos primero), o los que se abrieron
     * alrededor de una fecha pasada (ese día y el siguiente), o uno en particular.
     */
    public static function turnosRecientes(int $suc, int $cuantos = 20, ?Carbon $fecha = null, ?int $id = null): Collection
    {
        return DB::table('turnos as t')->leftJoin('users as u', 'u.IdUsuario', '=', 't.IdUsuario')
            ->where('t.id_empresa_negocio', $suc)
            ->when($id, fn ($q) => $q->where('t.id_turno', $id))
            ->when($fecha && ! $id, fn ($q) => $q->whereBetween('t.apertura', [$fecha->copy()->startOfDay(), $fecha->copy()->addDay()->endOfDay()]))
            ->orderByRaw("t.estado = 'ABIERTO' DESC")->orderByDesc('t.id_turno')->limit($cuantos)
            ->get(['t.id_turno', 't.turno', 't.estado', 't.apertura', 't.cierre', DB::raw("COALESCE(NULLIF(u.apeusu, ''), u.name) as cajero")]);
    }

    /** Turnos cerrados con el efectivo contado: diferencia contra lo que debía haber (negativo = faltó) */
    public static function diferenciasCaja(int $suc, string $desde, string $hasta): Collection
    {
        return Turno::where('id_empresa_negocio', $suc)->where('estado', 'CERRADO')->whereNotNull('montocierre')
            ->whereBetween('cierre', [$desde.' 00:00:00', $hasta.' 23:59:59'])->get()
            ->map(function (Turno $t) {
                $esperado = TurnoController::resumen($t)['efectivoEsperado'];

                return (object) ['turno' => $t->id_turno, 'numero' => $t->turno ?? $t->id_turno, 'usuario' => (int) $t->IdUsuario, 'cierre' => (string) $t->cierre,
                    'esperado' => $esperado, 'contado' => (float) $t->montocierre, 'diferencia' => round((float) $t->montocierre - $esperado, 2)];
            });
    }

    /**
     * Alertas: lo que el dueño debería mirar. Cada una con nivel (alta, media, info), título, detalle y enlace.
     *
     * @return Collection<int, object{nivel: string, icono: string, titulo: string, detalle: string, url: ?string}>
     */
    public static function alertas(int $suc, string $desde, string $hasta): Collection
    {
        $alertas = collect();
        $agregar = fn (string $nivel, string $icono, string $titulo, string $detalle, ?string $url = null) => $alertas->push((object) compact('nivel', 'icono', 'titulo', 'detalle', 'url'));
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $ventas = self::totales($suc, $desde.' 00:00:00', $hasta.' 23:59:59')->total;

        foreach (self::diferenciasCaja($suc, $desde, $hasta)->where('diferencia', '<=', -5) as $t) {
            $nombre = DB::table('users')->where('IdUsuario', $t->usuario)->value('apeusu') ?: 'Usuario '.$t->usuario;
            $agregar(abs($t->diferencia) >= 50 ? 'alta' : 'media', '💸', "Faltó {$soles(abs($t->diferencia))} en caja (turno N° {$t->numero})",
                "{$nombre} cerró el ".Carbon::parse($t->cierre)->format('d/m H:i').": debía haber {$soles($t->esperado)} y contó {$soles($t->contado)}.",
                route('turnos.show', $t->turno));
        }
        foreach (self::equipo($suc, $desde, $hasta) as $u) {
            $base = max($u->cobro, $u->vendio, 1);
            if ($u->anulados >= 3 || ($u->anulados >= 2 && $u->monto_anulado / $base > 0.05)) {
                $agregar('alta', '🚫', "{$u->nombre} anuló {$u->anulados} comprobantes ({$soles($u->monto_anulado)})", 'Revisa si fueron errores de verdad: una venta cobrada y luego anulada puede ser dinero que no entró a caja.', route('actividad.index'));
            }
            if ($u->descuentos > 0 && ($u->n_descuentos >= 5 || $u->descuentos / $base > 0.08)) {
                $agregar('media', '🏷️', "{$u->nombre} hizo {$u->n_descuentos} descuentos por {$soles($u->descuentos)}", 'Es '.round($u->descuentos / $base * 100, 1).'% de lo que vendió o cobró.', route('descuentos.reporte'));
            }
            if ($u->cortesias >= 5) {
                $agregar('media', '🎁', "{$u->nombre} dio {$u->cortesias} cortesías", $u->valor_cortesias > 0 ? 'Valor aproximado regalado: '.$soles($u->valor_cortesias).'.' : 'Revisa sus motivos.', route('descuentos.reporte'));
            }
            if ($u->quitados >= 5) {
                $agregar('media', '❌', "{$u->nombre} quitó platos de la comanda {$u->quitados} veces", 'Quitar después de enviar a cocina puede ser un error… o un plato que se sirvió y no se cobró.', route('actividad.index'));
            }
        }
        $descuentosTotal = (float) self::ventas($suc)->whereBetween('c.fecha_hora', [$desde.' 00:00:00', $hasta.' 23:59:59'])->sum('c.descuento');
        if ($ventas > 0 && $descuentosTotal / $ventas > 0.05) {
            $agregar('media', '🏷️', 'Los descuentos son el '.round($descuentosTotal / $ventas * 100, 1).'% de las ventas', "Se descontó {$soles($descuentosTotal)} en el periodo.", route('descuentos.reporte'));
        }
        // Ahora mismo
        $mesas = DB::table('pedidos')->where('id_empresa_negocio', $suc)->where('ped_est', 'Aperturado')->whereNotNull('mes_id')
            ->where('fecha_hora', '<', now()->subHours(3))->get(['ped_id', 'ped_tot', 'fecha_hora']);
        if ($mesas->isNotEmpty()) {
            $agregar('media', '🍽️', $mesas->count().' mesa(s) abiertas hace más de 3 horas', 'Suman '.$soles($mesas->sum('ped_tot')).' sin cobrar. Puede ser una mesa que se fue sin pagar o que no se cerró.', url('comandas'));
        }
        $sinSunat = DB::table('cpe_cabecera')->where('id_empresa_negocio', $suc)->whereIn('tdocod', ['01', '03', '07', '08'])->whereNull('ccabaj')
            ->where(fn ($q) => $q->whereNull('est_sunat')->orWhereIn('est_sunat', ['PENDIENTE', 'ERROR', 'RECHAZADO']))
            ->where('ccafem', '<', now()->subDays(2)->toDateString())->count();
        if ($sinSunat) {
            $agregar('alta', '🏛️', "{$sinSunat} comprobante(s) sin aceptar por SUNAT hace más de 2 días", 'Envíalos pronto: después del plazo SUNAT puede multar.', route('ventas.index', ['sunat' => 'PENDIENTE']));
        }
        $negativos = DB::table('producto_stock as s')->join('productos as p', 'p.IdProducto', '=', 's.IdProducto')
            ->where('p.id_empresa_negocio', $suc)->where('p.proest', 'Activo')->where('s.stock', '<', 0)->count();
        if ($negativos) {
            $agregar('info', '📦', "{$negativos} producto(s) con stock negativo", 'Se vendió sin registrar la compra o el ingreso: el inventario y los costos no son exactos.', route('kardex.stock'));
        }

        return $alertas->sortBy(fn ($a) => ['alta' => 0, 'media' => 1, 'info' => 2][$a->nivel])->values();
    }

    /** El resumen del día en texto para WhatsApp */
    public static function mensajeDia(int $suc, Carbon $fecha): string
    {
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $negocio = (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->value('nombre_comercial');
        $dia = self::totales($suc, $fecha->copy()->startOfDay(), $fecha->copy()->endOfDay());
        $antes = self::totales($suc, $fecha->copy()->subWeek()->startOfDay(), $fecha->copy()->subWeek()->endOfDay());
        $var = self::variacion($dia->total, $antes->total);
        $desde = $fecha->toDateString();
        $lineas = ["📊 *{$negocio}* · resumen del ".$fecha->locale('es')->translatedFormat('l d/m'),
            '', "💰 Vendiste *{$soles($dia->total)}* en {$dia->tickets} ventas (ticket promedio {$soles($dia->promedio)})"];
        if ($var !== null) {
            $lineas[] = ($var >= 0 ? '📈 ' : '📉 ').($var >= 0 ? '+' : '').$var.'% vs. el '.$fecha->copy()->subWeek()->locale('es')->translatedFormat('l').' pasado ('.$soles($antes->total).')';
        }
        $medios = DB::table('venta_medio_pago as v')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'v.IdCpe_cabecera')
            ->leftJoin('medios_pagos as m', 'm.id_med_pag', '=', 'v.id_med_pag')->where('c.id_empresa_negocio', $suc)->whereNull('c.ccabaj')
            ->whereBetween('c.fecha_hora', [$fecha->copy()->startOfDay(), $fecha->copy()->endOfDay()])->groupBy('m.nom_med_pag')
            ->selectRaw('m.nom_med_pag as medio, SUM(v.monto) as total')->orderByDesc('total')->get();
        if ($medios->isNotEmpty()) {
            $lineas[] = '💳 '.$medios->map(fn ($m) => mb_convert_case((string) $m->medio, MB_CASE_TITLE).' '.$soles($m->total))->implode(' · ');
        }
        $top = DB::table('cpe_detalle as d')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
            ->where('c.id_empresa_negocio', $suc)->whereNull('c.ccabaj')->whereNotIn('c.tdocod', ['07', '08'])
            ->whereBetween('c.fecha_hora', [$fecha->copy()->startOfDay(), $fecha->copy()->endOfDay()])
            ->groupBy('d.cdedes')->selectRaw('d.cdedes as nombre, SUM(d.cdecan) as cantidad')->orderByDesc('cantidad')->limit(3)->get();
        if ($top->isNotEmpty()) {
            $lineas[] = '🏆 Lo más vendido: '.$top->map(fn ($t) => $t->nombre.' ('.rtrim(rtrim(number_format((float) $t->cantidad, 2), '0'), '.').')')->implode(', ');
        }
        $mes = self::resumen($suc, $fecha->isToday() ? now() : $fecha->copy()->endOfDay());
        $lineas[] = '📅 En el mes: '.$soles($mes['mes']->total).' · a este ritmo cierras en '.$soles($mes['proyeccion'])
            .($mes['avanceMeta'] !== null ? ' (meta al '.$mes['avanceMeta'].'%)' : '');
        $alertas = self::alertas($suc, $desde, $desde);
        if ($alertas->isNotEmpty()) {
            $lineas[] = '';
            $lineas[] = '⚠️ *Para revisar:*';
            foreach ($alertas->take(5) as $a) {
                $lineas[] = '• '.$a->titulo;
            }
        }
        $comprar = Rentabilidad::listaCompras($suc, 14, 2)->filter(fn ($p) => $p->dias !== null && $p->dias <= 2);
        if ($comprar->isNotEmpty()) {
            $lineas[] = '🛒 Se acaba pronto: '.$comprar->take(5)->pluck('nombre')->implode(', ');
        }
        $lineas[] = '';
        $lineas[] = '_Enviado desde tu sistema TUSHPA_';

        return implode("\n", $lineas);
    }
}
