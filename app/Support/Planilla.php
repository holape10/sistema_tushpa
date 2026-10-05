<?php
namespace App\Support;

use App\Support\Contabilidad\Contabilidad;
use Carbon\Carbon;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Planilla mensual de remuneraciones (Perú).
 *
 *  Ingresos       sueldo + asignación familiar (10 % RMV) + horas extra (25 % / 35 %) + bonos
 *  Descuentos     faltas (sueldo/30 por día) y tardanzas (por minuto) — tomadas de Asistencia —,
 *                 ONP 13 % o AFP (aporte 10 % + prima de seguro + comisión sobre flujo), renta de 5ta categoría,
 *                 adelantos y otros
 *  Aporte empleador  EsSalud 9 % (base mínima: la RMV)
 *
 * La renta de 5ta es una estimación: proyecta la remuneración del año (con gratificaciones según el régimen),
 * resta 7 UIT, aplica la escala (8 / 14 / 17 / 20 / 30 %) y la reparte en 12 meses.
 * Los parámetros (RMV, UIT, tasas de AFP) se editan cada año en Parámetros de planilla.
 */
class Planilla
{
    public const AFPS = ['HABITAT' => 1.47, 'INTEGRA' => 1.55, 'PRIMA' => 1.60, 'PROFUTURO' => 1.69];
    public const REGIMENES = ['GENERAL' => 'Régimen general', 'PEQUENA' => 'Pequeña empresa (REMYPE)', 'MICRO' => 'Microempresa (REMYPE)'];
    private const ESCALA_QUINTA = [[5, 8], [20, 14], [35, 17], [45, 20], [PHP_INT_MAX, 30]];

    public static function parametros(string $ruc, int $anio): object
    {
        $p = DB::table('planilla_parametros')->where('IdEmpresa', $ruc)->where('anio', $anio)->first();
        if (!$p) {
            // Copia el año anterior (o los valores por defecto) para no empezar de cero
            $prev = DB::table('planilla_parametros')->where('IdEmpresa', $ruc)->where('anio', '<', $anio)->orderByDesc('anio')->first();
            $datos = $prev ? collect((array) $prev)->except(['anio', 'created_at', 'updated_at'])->all()
                : ['IdEmpresa' => $ruc, 'afp_comisiones' => json_encode(self::AFPS)];
            DB::table('planilla_parametros')->insert($datos + ['anio' => $anio, 'created_at' => now(), 'updated_at' => now()]);
            $p = DB::table('planilla_parametros')->where('IdEmpresa', $ruc)->where('anio', $anio)->first();
        }
        $p->comisiones = json_decode($p->afp_comisiones ?: '{}', true) ?: self::AFPS;
        return $p;
    }

    /** Faltas y minutos de tardanza del mes según el módulo de Asistencia */
    public static function asistencia(int $empId, string $periodo): array
    {
        $ini = Carbon::createFromFormat('Ym', $periodo)->startOfMonth();
        $fin = $ini->copy()->endOfMonth();
        $hasta = $fin->lt(today()) ? $fin : today()->subDay();
        if ($hasta->lt($ini)) {
            return ['faltas' => 0, 'tardanza' => 0];
        }
        $marcados = DB::table('asistencias')->where('emp_id', $empId)->whereBetween('fecha', [$ini->toDateString(), $hasta->toDateString()])
            ->whereNotNull('check_in_1')->pluck('tardanza_minutos', 'fecha');
        // Falta = día con turno de trabajo asignado, sin marcación y que no es feriado
        $feriados = Asistencia::feriados($ini->toDateString(), $hasta->toDateString(), 0);
        $faltas = DB::table('asistencia_horarios as h')->join('asistencia_turnos as t', 't.id', '=', 'h.turno_id')
            ->where('h.emp_id', $empId)->where('t.tipo', 'TRABAJO')->whereBetween('h.fecha', [$ini->toDateString(), $hasta->toDateString()])
            ->pluck('h.fecha')->filter(fn($f) => !isset($marcados[$f]) && !isset($feriados[$f]))->count();
        return ['faltas' => $faltas, 'tardanza' => (int) $marcados->sum()];
    }

    /** Calcula una fila de la planilla a partir de los datos editables (días, faltas, horas extra, bonos, adelantos…) */
    public static function calcular(array $f, object $t, object $p): array
    {
        $r2 = fn($n) => round((float) $n, 2);
        $sueldo = (float) $t->sueldo;
        $asig = $t->asignacion_familiar ? $r2($p->rmv * 0.10) : 0;
        $dias = max(0, min(30, (int) ($f['dias'] ?? 30)));
        // Ingreso o cese en el mes: proporcional a los días
        $sueldoMes = $r2($sueldo * $dias / 30);
        $asigMes = $r2($asig * $dias / 30);
        $valorHora = ($sueldo + $asig) / 30 / 8;
        $he = $r2((float) ($f['he25'] ?? 0) * $valorHora * 1.25 + (float) ($f['he35'] ?? 0) * $valorHora * 1.35);
        $bonos = $r2($f['bonos'] ?? 0);
        $ingresos = $r2($sueldoMes + $asigMes + $he + $bonos);

        $descFaltas = $r2((float) ($f['faltas'] ?? 0) * $sueldo / 30);
        $descTard = $r2((int) ($f['tardanza_min'] ?? 0) * $sueldo / 30 / 8 / 60);
        $computable = max(0, $r2($ingresos - $descFaltas - $descTard));

        $onp = $afpAporte = $afpPrima = $afpComision = 0;
        if ($t->sistema_pension === 'ONP') {
            $onp = $r2($computable * $p->onp / 100);
        } elseif ($t->sistema_pension === 'AFP') {
            $afpAporte = $r2($computable * $p->afp_aporte / 100);
            $afpPrima = $r2(min($computable, (float) $p->afp_tope_prima) * $p->afp_prima / 100);
            $afpComision = $t->afp_comision === 'MIXTA' ? 0 : $r2($computable * (float) ($p->comisiones[$t->afp] ?? 0) / 100);
        }

        $quinta = 0;
        if ($p->calcular_quinta) {
            // Gratificaciones: 2 sueldos (general), 1 (pequeña empresa: medio sueldo en julio y diciembre), 0 (micro); con bonificación de 9 %
            $grati = ['GENERAL' => 2, 'PEQUENA' => 1, 'MICRO' => 0][$p->regimen_laboral] ?? 2;
            $anual = ($sueldo + $asig) * 12 + ($sueldo + $asig) * $grati * 1.09 - 7 * (float) $p->uit;
            $impuesto = 0;
            $piso = 0;
            foreach (self::ESCALA_QUINTA as [$tope, $tasa]) {
                if ($anual <= $piso) {
                    break;
                }
                $techo = $tope * (float) $p->uit;
                $impuesto += (min($anual, $techo) - $piso) * $tasa / 100;
                $piso = $techo;
            }
            $quinta = $r2(max(0, $impuesto) / 12);
        }

        $adelantos = $r2($f['adelantos'] ?? 0);
        $otros = $r2($f['otros_descuentos'] ?? 0);
        $descuentos = $r2($descFaltas + $descTard + $onp + $afpAporte + $afpPrima + $afpComision + $quinta + $adelantos + $otros);
        $essalud = $r2(max($computable, $dias > 0 ? (float) $p->rmv * $dias / 30 : 0) * $p->essalud / 100);

        return [
            'dias' => $dias, 'faltas' => (float) ($f['faltas'] ?? 0), 'tardanza_min' => (int) ($f['tardanza_min'] ?? 0),
            'he25' => (float) ($f['he25'] ?? 0), 'he35' => (float) ($f['he35'] ?? 0),
            'sueldo' => $sueldoMes, 'asig_familiar' => $asigMes, 'horas_extra' => $he, 'bonos' => $bonos, 'total_ingresos' => $ingresos,
            'desc_faltas' => $descFaltas, 'desc_tardanza' => $descTard, 'onp' => $onp, 'afp_aporte' => $afpAporte, 'afp_prima' => $afpPrima,
            'afp_comision' => $afpComision, 'renta_quinta' => $quinta, 'adelantos' => $adelantos, 'otros_descuentos' => $otros,
            'total_descuentos' => $descuentos, 'neto' => $r2($ingresos - $descuentos), 'essalud' => $essalud,
        ];
    }

    private static function trabajador(int $empId): ?object
    {
        return DB::table('planilla_trabajadores as t')->join('empleado as e', 'e.emp_id', '=', 't.emp_id')
            ->where('t.emp_id', $empId)->select('t.*', 'e.emp_nom', 'e.emp_ape_pat', 'e.emp_ape_mat', 'e.emp_num_doc')->first();
    }

    /** Crea la planilla del mes (o agrega a los trabajadores nuevos) con lo que se sabe de asistencia */
    public static function generar(string $ruc, string $periodo): int
    {
        return DB::transaction(function () use ($ruc, $periodo) {
            $pl = DB::table('planillas')->where('IdEmpresa', $ruc)->where('periodo', $periodo)->lockForUpdate()->first();
            if ($pl && $pl->estado !== 'BORRADOR') {
                throw new \RuntimeException('La planilla de ' . Contabilidad::nombrePeriodo($periodo) . ' ya está cerrada.');
            }
            $id = $pl->id ?? DB::table('planillas')->insertGetId(['IdEmpresa' => $ruc, 'periodo' => $periodo, 'estado' => 'BORRADOR',
                'IdUsuario' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
            $p = self::parametros($ruc, (int) substr($periodo, 0, 4));
            $ini = Carbon::createFromFormat('Ym', $periodo)->startOfMonth();
            $fin = $ini->copy()->endOfMonth();

            $trabajadores = DB::table('planilla_trabajadores as t')->join('empleado as e', 'e.emp_id', '=', 't.emp_id')
                ->where('t.IdEmpresa', $ruc)->where('t.activo', 1)
                ->where(fn($w) => $w->whereNull('t.fecha_ingreso')->orWhere('t.fecha_ingreso', '<=', $fin->toDateString()))
                ->where(fn($w) => $w->whereNull('t.fecha_cese')->orWhere('t.fecha_cese', '>=', $ini->toDateString()))
                ->select('t.*', 'e.emp_nom', 'e.emp_ape_pat', 'e.emp_ape_mat', 'e.emp_num_doc')->get();
            $existentes = DB::table('planilla_detalle')->where('planilla_id', $id)->pluck('emp_id')->flip();

            foreach ($trabajadores as $t) {
                if (isset($existentes[$t->emp_id])) {
                    continue;
                }
                // Días del mes según ingreso o cese
                $desde = $t->fecha_ingreso && $t->fecha_ingreso > $ini->toDateString() ? Carbon::parse($t->fecha_ingreso) : $ini;
                $hasta = $t->fecha_cese && $t->fecha_cese < $fin->toDateString() ? Carbon::parse($t->fecha_cese) : $fin;
                $dias = ($desde->eq($ini) && $hasta->eq($fin)) ? 30 : min(30, $desde->diffInDays($hasta) + 1);
                $a = self::asistencia($t->emp_id, $periodo);
                $calc = self::calcular(['dias' => $dias, 'faltas' => $a['faltas'], 'tardanza_min' => $a['tardanza']], $t, $p);
                DB::table('planilla_detalle')->insert($calc + [
                    'planilla_id' => $id, 'emp_id' => $t->emp_id, 'dni' => $t->emp_num_doc, 'cargo' => $t->cargo,
                    'nombre' => trim(implode(' ', array_filter([$t->emp_ape_pat, $t->emp_ape_mat])) . ', ' . $t->emp_nom, ' ,'),
                    'sistema_pension' => $t->sistema_pension === 'AFP' ? 'AFP ' . $t->afp : $t->sistema_pension,
                ]);
            }
            self::totales($id);
            return $id;
        });
    }

    /** Recalcula una fila con los valores editados en pantalla */
    public static function actualizarFila(int $detalleId, array $f): void
    {
        $d = DB::table('planilla_detalle')->where('id', $detalleId)->first();
        $pl = DB::table('planillas')->where('id', $d->planilla_id)->first();
        if ($pl->estado !== 'BORRADOR') {
            throw new \RuntimeException('La planilla ya está cerrada.');
        }
        $t = self::trabajador($d->emp_id);
        $p = self::parametros($pl->IdEmpresa, (int) substr($pl->periodo, 0, 4));
        DB::table('planilla_detalle')->where('id', $detalleId)->update(self::calcular($f, $t, $p));
        self::totales($pl->id);
    }

    public static function totales(int $id): void
    {
        $t = DB::table('planilla_detalle')->where('planilla_id', $id)
            ->selectRaw('SUM(total_ingresos) i, SUM(total_descuentos) d, SUM(neto) n, SUM(essalud) e')->first();
        DB::table('planillas')->where('id', $id)->update(['total_ingresos' => (float) $t->i, 'total_descuentos' => (float) $t->d,
            'total_neto' => (float) $t->n, 'total_aportes' => (float) $t->e, 'updated_at' => now()]);
    }

    /**
     * Cierra la planilla y registra su asiento (si la contabilidad está en uso):
     *   62 Remuneraciones + 627 EsSalud (D) → 4111 neto, 4031 EsSalud, 4032 ONP, 407 AFP, 40173 5ta, 1411 adelantos (H)
     */
    public static function cerrar(string $ruc, int $id, string $fechaPago): void
    {
        DB::transaction(function () use ($ruc, $id, $fechaPago) {
            $pl = DB::table('planillas')->where('id', $id)->where('IdEmpresa', $ruc)->lockForUpdate()->first();
            if (!$pl || $pl->estado !== 'BORRADOR') {
                throw new \RuntimeException('La planilla no existe o ya está cerrada.');
            }
            $s = DB::table('planilla_detalle')->where('planilla_id', $id)->selectRaw('SUM(total_ingresos - desc_faltas - desc_tardanza) remun,
                SUM(essalud) essalud, SUM(onp) onp, SUM(afp_aporte + afp_prima + afp_comision) afp, SUM(renta_quinta) quinta,
                SUM(adelantos) adelantos, SUM(otros_descuentos) otros, SUM(neto) neto, COUNT(*) n')->first();
            if (!$s->n) {
                throw new \RuntimeException('La planilla no tiene trabajadores.');
            }

            $asiento = null;
            if (DB::table('conta_plan')->where('IdEmpresa', $ruc)->exists()) {
                $c = Contabilidad::config($ruc);
                $fin = Carbon::createFromFormat('Ym', $pl->periodo)->endOfMonth()->toDateString();
                $asiento = Contabilidad::crearAsiento($ruc, ['fecha' => $fin, 'subdiario' => '35', 'origen' => 'PLANILLA',
                    'glosa' => 'PLANILLA DE REMUNERACIONES ' . mb_strtoupper(Contabilidad::nombrePeriodo($pl->periodo)), 'ref_tabla' => 'planillas', 'ref_id' => $id], [
                    ['cuenta' => $c->cta_sueldos, 'debe' => (float) $s->remun],
                    ['cuenta' => $c->cta_essalud_gasto, 'debe' => (float) $s->essalud],
                    ['cuenta' => $c->cta_essalud_pagar, 'haber' => (float) $s->essalud],
                    ['cuenta' => $c->cta_onp_pagar, 'haber' => (float) $s->onp],
                    ['cuenta' => $c->cta_afp_pagar, 'haber' => (float) $s->afp],
                    ['cuenta' => $c->cta_renta5_pagar, 'haber' => (float) $s->quinta],
                    ['cuenta' => $c->cta_adelantos, 'haber' => (float) $s->adelantos + (float) $s->otros],
                    ['cuenta' => $c->cta_remun_pagar, 'haber' => (float) $s->neto],
                ]);
            }
            DB::table('planillas')->where('id', $id)->update(['estado' => 'CERRADA', 'fecha_pago' => $fechaPago, 'asiento_id' => $asiento, 'updated_at' => now()]);
        });
    }

    public static function reabrir(string $ruc, int $id): void
    {
        DB::transaction(function () use ($ruc, $id) {
            $pl = DB::table('planillas')->where('id', $id)->where('IdEmpresa', $ruc)->lockForUpdate()->first();
            if (!$pl || $pl->estado === 'BORRADOR') {
                return;
            }
            if ($pl->asiento_id) {
                $a = DB::table('conta_asientos')->where('id', $pl->asiento_id)->first();
                if ($a) {
                    Contabilidad::validarPeriodo($ruc, $a->periodo);
                    DB::table('conta_asientos')->where('id', $a->id)->delete();
                }
            }
            DB::table('planillas')->where('id', $id)->update(['estado' => 'BORRADOR', 'asiento_id' => null, 'updated_at' => now()]);
        });
    }
}
