<?php
namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Estimación de impuestos mensuales y anuales (Perú) con lo registrado en el sistema.
 * Es una guía para planificar; la declaración la hace el contribuyente o su contador (PDT 621 / PLAME / renta anual).
 *
 *  IGV        débito (ventas) − crédito fiscal (IGV de facturas de compras y gastos) − saldo a favor del mes anterior
 *  Renta      NRUS: cuota fija (S/ 20 o S/ 50) · RER: 1.5 % · RMT: 1 % hasta 300 UIT anuales, luego coeficiente o 1.5 %
 *             · Régimen general: coeficiente o 1.5 % — sobre ventas netas del mes
 *  Laborales  EsSalud 9 %, ONP 13 % y renta de 5ta retenidas en la planilla; renta de 4ta retenida en recibos por honorarios
 *  Anual      RMT: 10 % hasta 15 UIT de utilidad y 29.5 % el exceso · Régimen general: 29.5 %, menos los pagos a cuenta
 */
class Tributos
{
    public const REGIMENES = [
        'NRUS' => 'Nuevo RUS', 'RER' => 'Régimen Especial (RER)', 'RMT' => 'Régimen MYPE Tributario (RMT)', 'RG' => 'Régimen General',
    ];

    public static function config(string $ruc): object
    {
        DB::table('tributos_config')->insertOrIgnore(['IdEmpresa' => $ruc, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('tributos_config')->where('IdEmpresa', $ruc)->first();
    }

    public static function resumen(string $ruc, int $anio): array
    {
        $cfg = self::config($ruc);
        $uit = (float) Planilla::parametros($ruc, $anio)->uit;
        $desde = "$anio-01-01";
        $hasta = "$anio-12-31";
        $mes = fn(string $col) => DB::raw("DATE_FORMAT($col, '%m') as mes");

        // Ventas electrónicas (las notas de crédito restan)
        $ventas = DB::table('cpe_cabecera')->where('IdEmpresa', $ruc)->whereIn('tdocod', ['01', '03', '07', '08'])->whereNull('ccabaj')
            ->whereBetween('ccafem', [$desde, $hasta])->groupBy('mes')
            ->select($mes('ccafem'), DB::raw("SUM(CASE WHEN tdocod='07' THEN -1 ELSE 1 END * (ccatvg + ccatexo + ccatinaf)) as base"),
                DB::raw("SUM(CASE WHEN tdocod='07' THEN -1 ELSE 1 END * ccaigv) as igv"), DB::raw("SUM(CASE WHEN tdocod='07' THEN -1 ELSE 1 END * ccaitv) as total"))
            ->get()->keyBy('mes');
        $notasVenta = DB::table('cpe_cabecera')->where('IdEmpresa', $ruc)->where('tdocod', '13')->whereNull('ccabaj')
            ->whereBetween('ccafem', [$desde, $hasta])->groupBy('mes')->select($mes('ccafem'), DB::raw('SUM(ccaitv) as total'))->pluck('total', 'mes');
        $costo = DB::table('cpe_detalle as d')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
            ->where('c.IdEmpresa', $ruc)->whereIn('c.tdocod', ['01', '03', '07', '08'])->whereNull('c.ccabaj')->whereBetween('c.ccafem', [$desde, $hasta])
            ->groupBy('mes')->select($mes('c.ccafem'), DB::raw("SUM(CASE WHEN c.tdocod='07' THEN -1 ELSE 1 END * d.costo * d.cdecan) as costo"))->pluck('costo', 'mes');

        // Compras de mercadería (en soles); solo las facturas dan crédito fiscal
        $tc = "CASE WHEN mon_id='USD' THEN tip_cam ELSE 1 END";
        $compras = DB::table('compras_cabecera')->where('IdEmpresa', $ruc)->where('est_compra', 'Registrado')->whereBetween('com_fec', [$desde, $hasta])
            ->groupBy('mes')->select($mes('com_fec'), DB::raw("SUM((total_com - igv_com) * $tc) as base"),
                DB::raw("SUM(CASE WHEN tdocod='01' THEN igv_com * $tc ELSE 0 END) as credito"),
                DB::raw("SUM(CASE WHEN tdocod='01' THEN 0 ELSE igv_com * $tc END) as igv_sin_credito"), DB::raw("SUM(total_com * $tc) as total"))
            ->get()->keyBy('mes');

        // Gastos (las notas de crédito del proveedor restan)
        $s = "CASE WHEN tdocod='07' THEN -1 ELSE 1 END * CASE WHEN moneda='USD' THEN tipo_cambio ELSE 1 END";
        $gastos = DB::table('gastos')->where('IdEmpresa', $ruc)->where('estado', 'Registrado')->whereBetween('fecha', [$desde, $hasta])
            ->groupBy('mes')->select($mes('fecha'), DB::raw("SUM($s * (base + no_gravado)) as base"),
                DB::raw("SUM(CASE WHEN credito_fiscal = 1 THEN $s * igv ELSE 0 END) as credito"),
                DB::raw("SUM(CASE WHEN credito_fiscal = 1 THEN 0 ELSE $s * igv END) as igv_sin_credito"),
                DB::raw("SUM($s * total) as total"), DB::raw("SUM($s * retencion) as retencion"))
            ->get()->keyBy('mes');

        // Planilla
        $planillas = DB::table('planillas as p')->join('planilla_detalle as d', 'd.planilla_id', '=', 'p.id')
            ->where('p.IdEmpresa', $ruc)->where('p.periodo', 'like', "$anio%")->groupBy('p.periodo')
            ->select(DB::raw('RIGHT(p.periodo, 2) as mes'), DB::raw('SUM(d.total_ingresos - d.desc_faltas - d.desc_tardanza) as remun'),
                DB::raw('SUM(d.essalud) as essalud'), DB::raw('SUM(d.onp) as onp'), DB::raw('SUM(d.afp_aporte + d.afp_prima + d.afp_comision) as afp'),
                DB::raw('SUM(d.renta_quinta) as quinta'))->get()->keyBy('mes');

        $meses = [];
        $saldoFavor = (float) $cfg->saldo_favor_inicial;
        $acumIngresos = 0.0;
        $tot = [];
        $hoy = now();
        for ($m = 1; $m <= 12; $m++) {
            $k = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            if ($anio > $hoy->year || ($anio == $hoy->year && $m > $hoy->month)) {
                break;
            }
            $v = $ventas[$k] ?? null;
            $c = $compras[$k] ?? null;
            $g = $gastos[$k] ?? null;
            $pl = $planillas[$k] ?? null;
            $r = [
                'mes' => $m, 'nombre' => ucfirst(Carbon::create($anio, $m, 1)->locale('es')->isoFormat('MMMM')),
                'ventas' => round((float) ($v->base ?? 0), 2), 'igv_ventas' => round((float) ($v->igv ?? 0), 2), 'ventas_total' => round((float) ($v->total ?? 0), 2),
                'notas_venta' => round((float) ($notasVenta[$k] ?? 0), 2), 'costo_ventas' => round((float) ($costo[$k] ?? 0), 2),
                'compras' => round((float) ($c->base ?? 0), 2), 'gastos' => round((float) ($g->base ?? 0), 2),
                'credito' => round((float) ($c->credito ?? 0) + (float) ($g->credito ?? 0), 2),
                'igv_sin_credito' => round((float) ($c->igv_sin_credito ?? 0) + (float) ($g->igv_sin_credito ?? 0), 2),
                'remuneraciones' => round((float) ($pl->remun ?? 0), 2), 'essalud' => round((float) ($pl->essalud ?? 0), 2),
                'onp' => round((float) ($pl->onp ?? 0), 2), 'afp' => round((float) ($pl->afp ?? 0), 2), 'quinta' => round((float) ($pl->quinta ?? 0), 2),
                'cuarta' => round((float) ($g->retencion ?? 0), 2),
            ];

            // ---- IGV ----
            if ($cfg->exonerado_igv || $cfg->regimen === 'NRUS') {
                $r['igv_pagar'] = 0;
                $r['saldo_favor'] = 0;
                $r['igv_sin_credito'] = round($r['igv_sin_credito'] + $r['credito'], 2);   // se vuelve costo
                $r['credito_usado'] = 0;
            } else {
                $neto = round($r['igv_ventas'] - $r['credito'] - $saldoFavor, 2);
                $r['credito_usado'] = round($r['credito'] + $saldoFavor, 2);
                $r['igv_pagar'] = max(0, $neto);
                $saldoFavor = max(0, -$neto);
                $r['saldo_favor'] = $saldoFavor;
            }

            // ---- Renta: pago a cuenta / cuota ----
            $acumIngresos += $r['ventas'];
            [$r['renta'], $r['renta_tasa']] = match ($cfg->regimen) {
                'NRUS' => self::cuotaNrus(max($r['ventas_total'], $r['compras'] + $r['gastos'])),
                'RER' => [round(max(0, $r['ventas']) * 0.015, 0), '1.5 %'],
                'RMT' => $acumIngresos <= 300 * $uit
                    ? [round(max(0, $r['ventas']) * 0.01, 0), '1 %']
                    : [round(max(0, $r['ventas']) * ((float) $cfg->coeficiente ?: 0.015), 0), $cfg->coeficiente ? 'coef. ' . $cfg->coeficiente : '1.5 %'],
                default => [round(max(0, $r['ventas']) * ((float) $cfg->coeficiente ?: 0.015), 0), $cfg->coeficiente ? 'coef. ' . $cfg->coeficiente : '1.5 %'],
            };

            $r['laborales'] = round($r['essalud'] + $r['onp'] + $r['quinta'] + $r['cuarta'], 2);
            $r['total_pagar'] = round($r['igv_pagar'] + (float) $r['renta'] + $r['laborales'], 2);
            $r['utilidad'] = round($r['ventas'] - $r['costo_ventas'] - $r['gastos'] - $r['igv_sin_credito'] - $r['remuneraciones'] - $r['essalud'], 2);
            $meses[] = $r;
            foreach ($r as $kk => $vv) {
                if (is_numeric($vv) && $kk !== 'mes') {
                    $tot[$kk] = round(($tot[$kk] ?? 0) + $vv, 2);
                }
            }
        }

        return ['meses' => $meses, 'tot' => $tot, 'anual' => self::anual($cfg, $tot, $uit), 'cfg' => $cfg, 'uit' => $uit,
            'alertas' => self::alertas($cfg, $tot, $meses, $uit)];
    }

    /** NRUS: categoría 1 (hasta S/ 5,000 al mes) S/ 20; categoría 2 (hasta S/ 8,000) S/ 50 */
    private static function cuotaNrus(float $monto): array
    {
        return $monto <= 5000 ? [20, 'Cat. 1'] : ($monto <= 8000 ? [50, 'Cat. 2'] : [50, 'excede']);
    }

    /** Proyección del impuesto a la renta anual (solo RMT y régimen general) */
    private static function anual(object $cfg, array $tot, float $uit): ?array
    {
        if (!in_array($cfg->regimen, ['RMT', 'RG'], true) || empty($tot)) {
            return null;
        }
        $utilidad = (float) ($tot['utilidad'] ?? 0);
        $impuesto = 0;
        if ($utilidad > 0) {
            $impuesto = $cfg->regimen === 'RMT'
                ? min($utilidad, 15 * $uit) * 0.10 + max(0, $utilidad - 15 * $uit) * 0.295
                : $utilidad * 0.295;
        }
        $pagos = (float) ($tot['renta'] ?? 0);
        return ['utilidad' => round($utilidad, 2), 'impuesto' => round($impuesto, 0), 'pagos' => $pagos,
            'regularizar' => round($impuesto - $pagos, 0)];
    }

    private static function alertas(object $cfg, array $tot, array $meses, float $uit): array
    {
        $a = [];
        $ingresos = (float) ($tot['ventas_total'] ?? 0);
        if ($cfg->regimen === 'NRUS' && ($ingresos > 96000 || collect($meses)->contains(fn($m) => $m['ventas_total'] > 8000))) {
            $a[] = 'Tus ventas superan el límite del Nuevo RUS (S/ 8,000 al mes o S/ 96,000 al año). Consulta a tu contador por el cambio de régimen.';
        }
        if ($cfg->regimen === 'RER' && $ingresos > 525000) {
            $a[] = 'Tus ventas del año superan S/ 525,000, el límite del RER.';
        }
        if ($cfg->regimen === 'RMT' && $ingresos > 1700 * $uit) {
            $a[] = 'Tus ventas superan 1,700 UIT, el límite del Régimen MYPE Tributario.';
        }
        if (($tot['notas_venta'] ?? 0) > 0) {
            $a[] = 'Hay S/ ' . number_format($tot['notas_venta'], 2) . ' vendidos con NOTAS DE VENTA: no son comprobantes válidos para SUNAT y no están en este cálculo. Emite boleta o factura por esas ventas.';
        }
        if (!$cfg->exonerado_igv && collect($meses)->sum('igv_ventas') == 0 && collect($meses)->sum('ventas') > 0) {
            $a[] = 'Tus ventas no tienen IGV. Si estás en la Amazonía u otra zona exonerada, marca “Exonerado de IGV” en la configuración.';
        }
        return $a;
    }
}
