<?php

namespace App\Http\Controllers;

use App\Support\Cie10;
use App\Support\Clinica;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Reporte de la clínica / consultorio odontológico: atenciones, pacientes nuevos, citas (asistencia), ingresos,
 * por doctor y especialidad, diagnósticos CIE-10 más frecuentes, hallazgos del odontograma e índice CPO-D,
 * tratamientos con saldo y cuotas vencidas, de dónde llegan los pacientes y a quién volver a llamar.
 */
class ClinicaReporteController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        abort_unless($user->esAdmin() || $user->tieneModulo('/clinica/reportes'), 403, 'No tienes acceso a los reportes de la clínica.');
        $suc = (int) $user->id_empresa_negocio;
        $desde = Carbon::parse($request->get('desde', now()->startOfMonth()->toDateString()))->startOfDay();
        $hasta = Carbon::parse($request->get('hasta', now()->toDateString()))->endOfDay();
        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
        }
        $rango = [$desde->toDateString(), $hasta->toDateString()];

        $atenciones = DB::table('atencion_clinica as a')->leftJoin('especialidad as e', 'e.esp_id', '=', 'a.esp_id')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'a.doctor')
            ->where('a.id_empresa_negocio', $suc)->where('a.ate_cli_est', 'ATENDIDA')->whereBetween('a.ate_cli_fec', $rango)
            ->get(['a.ate_cli_id', 'a.his_cli_id', 'a.cie10', 'a.odontograma', 'a.doctor', 'e.esp_des', DB::raw("COALESCE(NULLIF(TRIM(u.apeusu), ''), u.name) as doctor_nom")]);

        return view('empresas.clinica.reporte', [
            'desde' => $rango[0], 'hasta' => $rango[1],
            'negocio' => (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->value('nombre_comercial'),
        ] + $this->resumen($suc, $rango, $desde, $hasta, $atenciones) + $this->diagnosticos($atenciones) + $this->odontologia($atenciones)
            + $this->tratamientos($suc, $rango) + $this->pacientes($suc, $rango));
    }

    /** Atenciones, pacientes, citas e ingresos; por doctor y por especialidad */
    private function resumen(int $suc, array $rango, Carbon $desde, Carbon $hasta, $atenciones): array
    {
        $citas = DB::table('citas')->where('id_empresa_negocio', $suc)->whereBetween('fecha', $rango)->get(['estado']);
        $ventas = DB::table('cpe_cabecera as c')->leftJoin('users as u', 'u.IdUsuario', '=', 'c.mozo')
            ->where('c.id_empresa_negocio', $suc)->where('c.ped_tip', 'Clinica')->whereNull('c.ccabaj')->whereIn('c.tdocod', ['01', '03', '13'])
            ->whereBetween('c.ccafem', $rango)->get(['c.ccaitv', 'c.mozo', DB::raw("COALESCE(NULLIF(TRIM(u.apeusu), ''), u.name) as doctor_nom")]);
        $atendidas = $citas->where('estado', 'ATENDIDA')->count();
        $noVino = $citas->where('estado', 'NO_ASISTIO')->count();

        $porDoctor = $atenciones->groupBy(fn ($a) => $a->doctor_nom ?: 'Sin doctor')->map(fn ($g, $n) => (object) ['nombre' => $n, 'atenciones' => $g->count(),
            'pacientes' => $g->pluck('his_cli_id')->unique()->count(),
            'ingresos' => round((float) $ventas->filter(fn ($v) => ($v->doctor_nom ?: 'Sin doctor') === $n)->sum('ccaitv'), 2)])
            ->sortByDesc('atenciones')->values();

        return [
            'totalAtenciones' => $atenciones->count(),
            'pacientesAtendidos' => $atenciones->pluck('his_cli_id')->unique()->count(),
            'pacientesNuevos' => DB::table('historia_clinica')->where('id_empresa_negocio', $suc)->whereBetween('his_cli_fec', [$desde, $hasta])->count(),
            'ingresos' => round((float) $ventas->sum('ccaitv'), 2),
            'ticketPromedio' => $ventas->count() ? round((float) $ventas->sum('ccaitv') / $ventas->count(), 2) : 0,
            'citas' => $citas->count(), 'citasAtendidas' => $atendidas, 'citasNoVino' => $noVino,
            'citasCanceladas' => $citas->where('estado', 'CANCELADA')->count(),
            'asistencia' => $atendidas + $noVino ? round($atendidas / ($atendidas + $noVino) * 100) : null,
            'porDoctor' => $porDoctor,
            'porEspecialidad' => $atenciones->groupBy(fn ($a) => $a->esp_des ?: 'Sin especialidad')->map(fn ($g, $n) => (object) ['nombre' => $n, 'atenciones' => $g->count()])
                ->sortByDesc('atenciones')->values(),
        ];
    }

    /** Diagnósticos CIE-10 más frecuentes del periodo */
    private function diagnosticos($atenciones): array
    {
        $conteo = [];
        foreach ($atenciones as $a) {
            foreach (Cie10::codigos($a->cie10) as $c) {
                $conteo[$c] = ($conteo[$c] ?? 0) + 1;
            }
        }
        arsort($conteo);

        return [
            'topCie10' => collect($conteo)->take(12)->map(fn ($n, $c) => (object) ['codigo' => $c, 'descripcion' => Cie10::descripcion($c) ?? '—', 'veces' => $n])->values(),
            'conCie10' => $atenciones->filter(fn ($a) => Cie10::codigos($a->cie10))->count(),
        ];
    }

    /**
     * Hallazgos del odontograma de las atenciones y el índice CPO-D (piezas Cariadas, Perdidas y Obturadas por paciente,
     * solo dientes permanentes): el indicador de salud bucal que usa el MINSA.
     */
    private function odontologia($atenciones): array
    {
        $hallazgos = [];
        $porPaciente = [];
        foreach ($atenciones as $a) {
            $piezas = json_decode((string) $a->odontograma, true) ?: [];
            if (! $piezas) {
                continue;
            }
            $cpo = ['c' => 0, 'p' => 0, 'o' => 0];
            foreach ($piezas as $n => $p) {
                $p = is_array($p) ? $p : [];
                $e = (array) ($p['e'] ?? []);
                $caras = array_values((array) ($p['c'] ?? []));
                foreach (array_merge($e, array_unique($caras)) as $h) {
                    $hallazgos[$h] = ($hallazgos[$h] ?? 0) + 1;
                }
                if ((int) $n >= 50) {
                    continue;   // CPO-D: solo dientes permanentes
                }
                if (in_array('CARIES', $caras, true) || in_array('RESTAURACION DEFECTUOSA', $caras, true)) {
                    $cpo['c']++;
                } elseif (array_intersect(['AUSENTE', 'POR EXTRAER'], $e)) {
                    $cpo['p']++;
                } elseif (in_array('RESTAURACION', $caras, true) || array_intersect(['CORONA', 'ENDODONCIA'], $e)) {
                    $cpo['o']++;
                }
            }
            $porPaciente[$a->his_cli_id] = $cpo;   // la última atención del paciente en el periodo
        }
        arsort($hallazgos);
        $n = count($porPaciente);
        $suma = fn (string $k) => array_sum(array_column($porPaciente, $k));

        return [
            'hallazgos' => collect($hallazgos)->take(10),
            'cpod' => $n ? (object) ['pacientes' => $n, 'c' => round($suma('c') / $n, 1), 'p' => round($suma('p') / $n, 1), 'o' => round($suma('o') / $n, 1),
                'total' => round(($suma('c') + $suma('p') + $suma('o')) / $n, 1)] : null,
        ];
    }

    /** Tratamientos: lo contratado, lo cobrado en el periodo, el saldo por cobrar y las cuotas vencidas */
    private function tratamientos(int $suc, array $rango): array
    {
        $activos = DB::table('clinica_tratamientos as t')->join('historia_clinica as h', 'h.his_cli_id', '=', 't.his_cli_id')
            ->join('cliente as c', 'c.clicod', '=', 'h.clicod')
            ->where('t.id_empresa_negocio', $suc)->where('t.estado', '!=', 'CANCELADO')
            ->get(['t.*', 'c.clinom', 'c.telefono', 'h.his_cli_cod']);
        $pagos = DB::table('clinica_tratamiento_registros')->whereIn('tra_id', $activos->pluck('tra_id'))
            ->groupBy('tra_id')->select('tra_id', DB::raw('SUM(adelanto) as pagado'))->pluck('pagado', 'tra_id');
        $lista = $activos->map(function ($t) use ($pagos) {
            $t->pagado = round((float) ($pagos[$t->tra_id] ?? 0), 2);
            $t->saldo = round((float) $t->costo - $t->pagado, 2);

            return Clinica::conCuotas($t);
        });

        return [
            'tratActivos' => $lista->where('saldo', '>', 0.009)->count(),
            'tratNuevos' => DB::table('clinica_tratamientos')->where('id_empresa_negocio', $suc)->whereBetween('creado', [$rango[0].' 00:00:00', $rango[1].' 23:59:59'])->count(),
            'cobradoTratamientos' => round((float) DB::table('clinica_tratamiento_registros as r')->join('clinica_tratamientos as t', 't.tra_id', '=', 'r.tra_id')
                ->where('t.id_empresa_negocio', $suc)->whereBetween('r.fecha_hora', [$rango[0].' 00:00:00', $rango[1].' 23:59:59'])->sum('r.adelanto'), 2),
            'saldoPorCobrar' => round((float) $lista->where('saldo', '>', 0)->sum('saldo'), 2),
            'conVencido' => $lista->where('vencido', '>', 0.009)->sortByDesc('vencido')->values(),
        ];
    }

    /** De dónde llegan, sexo y edad de los pacientes nuevos; a quién llamar para su control */
    private function pacientes(int $suc, array $rango): array
    {
        $nuevos = DB::table('historia_clinica')->where('id_empresa_negocio', $suc)->whereBetween('his_cli_fec', [$rango[0].' 00:00:00', $rango[1].' 23:59:59'])
            ->get(['origen', 'sexo', 'fecha_nac', 'tipo']);
        $edad = fn ($f) => $f ? Carbon::parse($f)->age : null;
        $grupo = fn ($e) => $e === null ? 'Sin dato' : ($e < 12 ? 'Niños (0-11)' : ($e < 18 ? 'Adolescentes (12-17)' : ($e < 30 ? 'Jóvenes (18-29)' : ($e < 60 ? 'Adultos (30-59)' : 'Adultos mayores (60+)'))));

        // Última atención hace 6 a 18 meses: toca su control (profilaxis, revisión)
        $ultimas = DB::table('atencion_clinica')->where('id_empresa_negocio', $suc)->where('ate_cli_est', 'ATENDIDA')
            ->groupBy('his_cli_id')->select('his_cli_id', DB::raw('MAX(ate_cli_fec) as ultima'))
            ->havingBetween('ultima', [now()->subMonths(18)->toDateString(), now()->subMonths(6)->toDateString()]);
        $control = DB::table('historia_clinica as h')->joinSub($ultimas, 'x', 'x.his_cli_id', '=', 'h.his_cli_id')->join('cliente as c', 'c.clicod', '=', 'h.clicod')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('citas as ci')->whereColumn('ci.his_cli_id', 'h.his_cli_id')->where('ci.fecha', '>=', now()->toDateString())
                ->whereIn('ci.estado', ['PROGRAMADA', 'CONFIRMADA']))
            ->orderBy('x.ultima')->limit(40)->get(['h.his_cli_id', 'h.his_cli_cod', 'c.clinom', 'c.telefono', 'x.ultima']);

        return [
            'porOrigen' => $nuevos->countBy(fn ($h) => $h->origen ?: 'Sin dato')->sortDesc(),
            'porSexo' => $nuevos->countBy(fn ($h) => ['M' => 'Masculino', 'F' => 'Femenino'][$h->sexo] ?? 'Sin dato'),
            'porEdad' => $nuevos->where('tipo', '!=', 'MASCOTA')->countBy(fn ($h) => $grupo($edad($h->fecha_nac))),
            'paraControl' => $control,
            'proximasCitas' => DB::table('citas')->where('id_empresa_negocio', $suc)->whereBetween('fecha', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->whereIn('estado', ['PROGRAMADA', 'CONFIRMADA'])->orderBy('fecha')->orderBy('hora')->limit(30)->get(['fecha', 'hora', 'paciente', 'telefono', 'motivo', 'estado']),
        ];
    }
}
