<?php

namespace App\Http\Controllers;

use App\Support\PanelDueno;
use App\Support\Reportes;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Panel del dueño (administrador): cómo va el negocio, horas pico, ranking del equipo, alertas y el resumen
 * del día para mandar por WhatsApp. Los negocios nocturnos (discotecas, bares) lo ven por noches.
 */
class PanelDuenoController extends Controller
{
    public const VISTAS = ['panel' => ['Cómo va mi negocio', '📊'], 'turno' => ['Ventas del turno', '🕒'], 'horas' => ['Horas pico', '🔥'], 'equipo' => ['Mi equipo', '👥'], 'alertas' => ['Alertas', '🚨']];

    /** Qué se mira en "Ventas del turno" */
    public const PERIODOS = ['jornada' => 'Esta jornada', 'anterior' => 'Jornada anterior', 'hoy' => 'Hoy (00:00 a 23:59)', 'abiertos' => 'Turnos de caja abiertos'];

    /** Varias noches o días juntos: para ver la producción de cada uno y pagarle */
    public const RANGOS = ['semana' => 'Esta semana', 'semana_pasada' => 'Semana pasada', 'mes' => 'Este mes', 'mes_pasado' => 'Mes pasado'];

    /** Lo más largo que se puede ver de una vez en "Ventas del turno" */
    private const MAX_DIAS_RANGO = 92;

    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador ve el panel del dueño.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    /** @return array{0: string, 1: string} desde y hasta (por defecto, de las últimas 4 semanas para el mapa de calor y del mes para lo demás) */
    private function rango(Request $request, string $ver): array
    {
        $defecto = $ver === 'horas' ? now()->subDays(27)->toDateString() : now()->startOfMonth()->toDateString();
        try {
            $desde = Carbon::parse((string) $request->get('desde', $defecto))->toDateString();
            $hasta = Carbon::parse((string) $request->get('hasta', now()->toDateString()))->toDateString();
        } catch (\Throwable) {
            [$desde, $hasta] = [$defecto, now()->toDateString()];
        }

        return $hasta < $desde ? [$hasta, $desde] : [$desde, $hasta];
    }

    public function index(Request $request): View
    {
        $suc = $this->sucursal();
        $ver = array_key_exists($request->get('ver'), self::VISTAS) ? $request->get('ver') : 'panel';
        [$desde, $hasta] = $this->rango($request, $ver);
        $alertas = PanelDueno::alertas($suc, $desde, $hasta);

        return view('empresas.dueno.index', [
            'ver' => $ver, 'desde' => $desde, 'hasta' => $hasta, 'alertas' => $alertas,
            'resumen' => $ver === 'panel' ? PanelDueno::resumen($suc) : null,
            'mapa' => $ver === 'horas' ? PanelDueno::mapaCalor($suc, $desde, $hasta) : null,
            'equipo' => $ver === 'equipo' ? PanelDueno::equipo($suc, $desde, $hasta) : null,
            'turno' => $ver === 'turno' ? $this->ventasDelTurno($request, $suc) : null,
            'config' => DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->first(['meta_mensual', 'gastos_fijos_mes', 'whatsapp_dueno', 'atiende_de_noche']),
            // Vende mucho de madrugada y aún cuenta por días: se le sugiere contar por noches
            'sugerirNoche' => $ver === 'panel' && ! PanelDueno::corte($suc) && $this->vendeDeMadrugada($suc),
        ]);
    }

    /**
     * Ventas por mozo, cajero y promotor de la jornada (mediodía a mediodía: lo de madrugada cuenta en la noche que se
     * atendió), de hoy, de los turnos de caja abiertos o de un turno elegido.
     */
    private function ventasDelTurno(Request $request, int $suc): array
    {
        if (array_key_exists($request->get('periodo'), self::RANGOS) || $request->get('periodo') === 'rango') {
            return $this->ventasDelRango($request, $suc);
        }
        // Una noche pasada: la jornada de esa fecha (desde ese día a mediodía hasta el día siguiente a mediodía)
        try {
            $fecha = $request->filled('fecha') ? Carbon::parse((string) $request->get('fecha'))->startOfDay() : null;
        } catch (\Throwable) {
            $fecha = null;
        }
        if ($fecha && $fecha->isFuture()) {
            $fecha = null;
        }
        $turnos = PanelDueno::turnosRecientes($suc, 20, $fecha);
        $elegido = $request->integer('turno') ? ($turnos->firstWhere('id_turno', $request->integer('turno'))
            ?? PanelDueno::turnosRecientes($suc, 1, null, $request->integer('turno'))->first()) : null;
        $periodo = $elegido ? 'turno' : ($fecha ? 'fecha' : (array_key_exists($request->get('periodo'), self::PERIODOS) ? $request->get('periodo') : 'jornada'));
        [$desde, $hasta, $ids, $texto] = match ($periodo) {
            'fecha' => (function () use ($fecha) {
                $d = $fecha->copy()->setTime(Reportes::CORTE_JORNADA, 0);
                if ($d->isFuture()) {
                    // Hoy antes del mediodía: la noche de hoy aún no empieza, se ve la que está en curso
                    [$d] = PanelDueno::jornada();
                }
                $h = min($d->copy()->addDay()->subSecond(), now());

                return [$d, $h, null, 'Noche del '.$d->locale('es')->translatedFormat('l d/m/Y').': desde el '.$d->format('d/m H:i').' hasta el '.$h->format('d/m H:i')];
            })(),
            'turno' => [null, null, [(int) $elegido->id_turno], 'Turno N° '.$elegido->turno.' de '.$elegido->cajero.' · desde el '.Carbon::parse($elegido->apertura)->format('d/m H:i')
                .($elegido->cierre ? ' hasta el '.Carbon::parse($elegido->cierre)->format('d/m H:i') : ' (abierto)')],
            'abiertos' => [null, null, $turnos->where('estado', 'ABIERTO')->pluck('id_turno')->map(fn ($id) => (int) $id)->all(),
                $turnos->where('estado', 'ABIERTO')->map(fn ($t) => 'N° '.$t->turno.' '.$t->cajero)->implode(' · ') ?: 'No hay turnos abiertos'],
            'hoy' => [now()->startOfDay(), now(), null, 'Hoy '.now()->format('d/m').' desde las 00:00'],
            default => (function () use ($periodo) {
                [$d, $h] = PanelDueno::jornada($periodo === 'anterior' ? 1 : 0);

                return [$d, $h, null, 'Noche del '.$d->locale('es')->translatedFormat('l d/m').': desde el '.$d->format('d/m H:i').' hasta '.($periodo === 'anterior' ? 'el '.$h->format('d/m H:i') : 'ahora')];
            })(),
        };

        return PanelDueno::ventasDelTurno($suc, $desde, $hasta, $ids) + ['periodo' => $periodo, 'texto' => $texto, 'turnos' => $turnos,
            'elegido' => $elegido?->id_turno, 'fecha' => $fecha?->toDateString(), 'rango' => null];
    }

    /**
     * Semana, mes o fechas elegidas: lo de cada mozo, cajero y promotor en esas noches (o días) y en cada una, para
     * pagarles según su producción. Se pueden dejar fuera turnos de caja (los que no se quieren sumar).
     */
    private function ventasDelRango(Request $request, int $suc): array
    {
        $periodo = (string) $request->get('periodo');
        $corte = PanelDueno::corte($suc);
        // La noche (o el día) de hoy: de madrugada todavía es la noche de ayer
        $hoy = $corte ? PanelDueno::jornada()[0]->copy()->startOfDay() : now()->startOfDay();
        [$desde, $hasta] = match ($periodo) {
            'semana' => [$hoy->copy()->startOfWeek(), $hoy->copy()],
            'semana_pasada' => [$hoy->copy()->startOfWeek()->subWeek(), $hoy->copy()->startOfWeek()->subDay()],
            'mes' => [$hoy->copy()->startOfMonth(), $hoy->copy()],
            'mes_pasado' => [$hoy->copy()->subMonthNoOverflow()->startOfMonth(), $hoy->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            default => (function () use ($request, $hoy) {
                try {
                    $d = Carbon::parse((string) $request->get('desde'))->startOfDay();
                    $h = Carbon::parse((string) $request->get('hasta', $hoy->toDateString()))->startOfDay();
                } catch (\Throwable) {
                    return [$hoy->copy()->startOfWeek(), $hoy->copy()];
                }

                return $h->lt($d) ? [$h, $d] : [$d, $h];
            })(),
        };
        $hasta = $hasta->min($hoy);
        $desde = $desde->min($hasta);
        if ($desde->diffInDays($hasta) >= self::MAX_DIAS_RANGO) {
            $desde = $hasta->copy()->subDays(self::MAX_DIAS_RANGO - 1);
        }
        [$inicio, $fin] = array_map(fn ($m) => Carbon::parse($m), PanelDueno::limites($suc, $desde->toDateString(), $hasta->toDateString()));
        $fin = $fin->min(now());

        // Turnos de caja de esas fechas: todos suman, salvo los que el dueño desmarca
        $turnos = PanelDueno::turnosEntre($suc, $inicio, $fin);
        $marcados = array_map('intval', (array) $request->input('turnos', []));
        $soloAlgunos = $request->boolean('elegir') && count(array_intersect($turnos->pluck('id_turno')->map(fn ($id) => (int) $id)->all(), $marcados)) < $turnos->count();
        $ids = $soloAlgunos ? array_values(array_intersect($turnos->pluck('id_turno')->map(fn ($id) => (int) $id)->all(), $marcados)) : null;

        $unidad = $corte ? 'noche' : 'día';
        $cuantos = (int) $desde->diffInDays($hasta) + 1;
        $texto = 'Del '.$desde->locale('es')->translatedFormat('D d/m').' al '.$hasta->locale('es')->translatedFormat('D d/m/Y')
            .' · '.$cuantos.' '.($cuantos === 1 ? $unidad : ($corte ? 'noches' : 'días'))
            .($soloAlgunos ? ' · solo '.count($ids).' de '.$turnos->count().' turnos de caja' : '');

        return PanelDueno::ventasDelTurno($suc, $inicio, $fin, $ids, $corte) + ['periodo' => $periodo === 'rango' || ! array_key_exists($periodo, self::RANGOS) ? 'rango' : $periodo,
            'texto' => $texto, 'turnos' => $turnos, 'elegido' => null, 'fecha' => null,
            'rango' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'unidad' => $unidad,
                'marcados' => $ids ?? $turnos->pluck('id_turno')->map(fn ($id) => (int) $id)->all()]];
    }

    /** Más de la cuarta parte de lo vendido en las últimas 4 semanas fue entre las 12 am y las 6 am */
    private function vendeDeMadrugada(int $suc): bool
    {
        $r = DB::table('cpe_cabecera')->where('id_empresa_negocio', $suc)->whereNull('ccabaj')->where('fecha_hora', '>=', now()->subDays(28))
            ->selectRaw('SUM(ccaitv) as total, SUM(CASE WHEN HOUR(fecha_hora) < 6 THEN ccaitv ELSE 0 END) as madrugada')->first();

        return (float) $r->total > 0 && (float) $r->madrugada / (float) $r->total > 0.25;
    }

    /** El resumen del día listo para WhatsApp (texto y enlace al número del dueño) */
    public function resumenDia(Request $request): JsonResponse
    {
        $suc = $this->sucursal();
        try {
            $fecha = Carbon::parse((string) $request->get('fecha', now()->toDateString()));
        } catch (\Throwable) {
            $fecha = now();
        }
        $texto = PanelDueno::mensajeDia($suc, $fecha->isToday() ? now() : $fecha);
        // Un enlace por socio (o uno sin número para elegir el contacto en WhatsApp)
        $numeros = PanelDueno::whatsapps((string) DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->value('whatsapp_dueno')) ?: [''];
        $enlaces = array_map(fn ($n) => ['numero' => $n, 'url' => 'https://wa.me/'.($n !== '' && strlen($n) === 9 ? '51'.$n : $n).'?text='.rawurlencode($texto)], $numeros);

        return response()->json(['texto' => $texto, 'whatsapp' => $enlaces[0]['url'], 'enlaces' => $enlaces]);
    }

    /** Meta del mes, gastos fijos (punto de equilibrio), WhatsApp del dueño o de los socios y si atiende de noche */
    public function configuracion(Request $request): JsonResponse
    {
        $suc = $this->sucursal();
        $d = $request->validate([
            'meta_mensual' => 'nullable|numeric|min:0|max:99999999', 'gastos_fijos_mes' => 'nullable|numeric|min:0|max:99999999',
            // Varios socios: hasta 5 celulares separados por coma
            'whatsapp_dueno' => ['nullable', 'string', 'max:120', function ($campo, $valor, $falla) {
                $partes = array_filter(array_map('trim', preg_split('/[,;\/]+/', (string) $valor)));
                if (count($partes) > 5) {
                    $falla('Pon hasta 5 celulares.');
                }
                foreach ($partes as $n) {
                    if (! preg_match('/^\+?[\d\s]{9,15}$/', $n)) {
                        $falla("\"{$n}\" no es un celular: escribe 9 dígitos (o con el código de país), separados por coma.");
                    }
                }
            }],
            'atiende_de_noche' => 'nullable|boolean',
        ], [], ['meta_mensual' => 'meta', 'gastos_fijos_mes' => 'gastos fijos']);
        DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->update([
            'meta_mensual' => $d['meta_mensual'] ?? null, 'gastos_fijos_mes' => $d['gastos_fijos_mes'] ?? null,
            'whatsapp_dueno' => implode(',', PanelDueno::whatsapps($d['whatsapp_dueno'] ?? '')) ?: null,
            'atiende_de_noche' => (bool) ($d['atiende_de_noche'] ?? false),
        ]);

        return response()->json(['ok' => true, 'mensaje' => 'Guardado.']);
    }
}
