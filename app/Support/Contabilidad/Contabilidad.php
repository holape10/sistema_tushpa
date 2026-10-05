<?php
namespace App\Support\Contabilidad;

use Carbon\Carbon;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Motor contable por empresa (RUC).
 *
 * Centralización de VENTAS (subdiario 05) y su cobranza (subdiario 01):
 *   Factura / ND        12 Cuentas por cobrar (D)  →  40 IGV (H) + 70 Ventas (H)
 *   Boletas             un asiento por día (resumen B001-1 al B001-n), mismas cuentas
 *   Nota de crédito     al revés: 70 (D) + 40 (D) → 12 (H)
 *   Costo de ventas     un asiento por día: 69 (D) → 20 (H) con el costo de lo vendido (menos lo devuelto)
 *   Cobranza contado    un asiento por día: caja / banco de cada medio de pago (D) → 12 (H)
 *   Cobranza crédito    un asiento por día con los cobros de cuentas por cobrar
 *
 * Centralización de COMPRAS (subdiario 11) y su pago (subdiario 01):
 *   Provisión           60 Compras (D) + 40 IGV (D) → 42 Cuentas por pagar (H)    (en soles si la compra es en dólares)
 *   Destino             un asiento por día de compra: 20 Mercaderías (D) → 61 Variación de inventarios (H)
 *   Pago contado        un asiento por día: 42 (D) → caja (H)
 *   Pago crédito        un asiento por día con los pagos de cuentas por pagar
 *
 * Las notas de venta (documento interno) no se contabilizan.
 */
class Contabilidad
{
    public const SUBDIARIOS = ['00' => 'APERTURA', '01' => 'CAJA Y BANCOS', '05' => 'VENTAS', '11' => 'COMPRAS', '35' => 'DIARIO'];
    public const ORIGENES = ['VENTAS', 'COBRANZAS', 'COMPRAS', 'PAGOS', 'PLANILLA', 'MANUAL', 'APERTURA'];

    public static function asegurar(string $ruc): void
    {
        if (!DB::table('conta_plan')->where('IdEmpresa', $ruc)->exists()) {
            DB::table('conta_plan')->insert(Pcge::filas($ruc));
        }
        if (!DB::table('conta_config')->where('IdEmpresa', $ruc)->exists()) {
            DB::table('conta_config')->insert(['IdEmpresa' => $ruc, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public static function config(string $ruc): object
    {
        self::asegurar($ruc);
        return DB::table('conta_config')->where('IdEmpresa', $ruc)->first();
    }

    public static function cerrado(string $ruc, string $periodo): bool
    {
        return DB::table('conta_periodos')->where('IdEmpresa', $ruc)->where('periodo', $periodo)->where('estado', 'CERRADO')->exists();
    }

    public static function validarPeriodo(string $ruc, string $periodo): void
    {
        if (self::cerrado($ruc, $periodo)) {
            throw new \RuntimeException('El periodo ' . self::nombrePeriodo($periodo) . ' está CERRADO. Ábrelo en el Libro Diario para modificarlo.');
        }
    }

    public static function nombrePeriodo(string $periodo): string
    {
        return ucfirst(Carbon::createFromFormat('Ym', $periodo)->startOfMonth()->locale('es')->isoFormat('MMMM YYYY'));
    }

    /** Cuentas imputables del plan (para validar y para los buscadores) */
    public static function imputables(string $ruc)
    {
        return DB::table('conta_plan')->where('IdEmpresa', $ruc)->where('imputable', 1)->where('estado', 'Activo')->pluck('descripcion', 'cuenta');
    }

    /**
     * Registra un asiento. $lineas: [['cuenta','debe','haber','glosa','anexo_doc','anexo_nombre','documento'], ...]
     * Debe = Haber (al céntimo) y todas las cuentas deben ser imputables.
     */
    public static function crearAsiento(string $ruc, array $cab, array $lineas, ?\Illuminate\Support\Collection $imputables = null): int
    {
        $imputables ??= self::imputables($ruc);
        $lineas = array_values(array_filter($lineas, fn($l) => round((float) ($l['debe'] ?? 0), 2) != 0 || round((float) ($l['haber'] ?? 0), 2) != 0));
        if (count($lineas) < 2) {
            throw new \RuntimeException('El asiento "' . $cab['glosa'] . '" necesita al menos dos líneas.');
        }
        $debe = round(array_sum(array_map(fn($l) => round((float) ($l['debe'] ?? 0), 2), $lineas)), 2);
        $haber = round(array_sum(array_map(fn($l) => round((float) ($l['haber'] ?? 0), 2), $lineas)), 2);
        if (abs($debe - $haber) > 0.001) {
            throw new \RuntimeException("El asiento \"{$cab['glosa']}\" no cuadra: Debe " . number_format($debe, 2) . ' ≠ Haber ' . number_format($haber, 2) . '.');
        }
        foreach ($lineas as $l) {
            if (!isset($imputables[$l['cuenta']])) {
                throw new \RuntimeException("La cuenta {$l['cuenta']} no existe en el plan contable o no es imputable (asiento \"{$cab['glosa']}\"). Revisa el Plan Contable / Configuración de cuentas.");
            }
        }

        $periodo = Carbon::parse($cab['fecha'])->format('Ym');
        self::validarPeriodo($ruc, $periodo);
        $sub = $cab['subdiario'];
        // Al editar un asiento manual se conserva su número si sigue en el mismo periodo y subdiario
        $numero = !empty($cab['numero']) && ($cab['periodo_anterior'] ?? null) === $periodo . $sub
            ? (int) $cab['numero']
            : (int) DB::table('conta_asientos')->where('IdEmpresa', $ruc)->where('periodo', $periodo)->where('subdiario', $sub)
                ->lockForUpdate()->max('numero') + 1;

        $id = DB::table('conta_asientos')->insertGetId([
            'IdEmpresa' => $ruc, 'periodo' => $periodo, 'subdiario' => $sub, 'numero' => $numero,
            'fecha' => $cab['fecha'], 'glosa' => mb_substr(mb_strtoupper($cab['glosa']), 0, 200), 'origen' => $cab['origen'],
            'tdocod' => $cab['tdocod'] ?? null, 'documento' => $cab['documento'] ?? null,
            'ref_tabla' => $cab['ref_tabla'] ?? null, 'ref_id' => $cab['ref_id'] ?? null, 'total' => $debe,
            'IdUsuario' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($lineas as $l) {
            DB::table('conta_asiento_detalle')->insert([
                'asiento_id' => $id, 'cuenta' => $l['cuenta'],
                'debe' => round((float) ($l['debe'] ?? 0), 2), 'haber' => round((float) ($l['haber'] ?? 0), 2),
                'glosa' => isset($l['glosa']) ? mb_substr($l['glosa'], 0, 150) : null,
                'anexo_doc' => $l['anexo_doc'] ?? null, 'anexo_nombre' => isset($l['anexo_nombre']) ? mb_substr($l['anexo_nombre'], 0, 150) : null,
                'documento' => $l['documento'] ?? null,
            ]);
        }
        return $id;
    }

    /** Cuenta contable de un medio de pago: la configurada en el medio, o caja (efectivo) / banco (otros) */
    private static function cuentaMedio(object $cfg, ?string $nombre, ?string $cuenta): string
    {
        if ($cuenta) {
            return $cuenta;
        }
        return str_contains(mb_strtoupper((string) $nombre), 'EFECTIVO') || !$nombre ? $cfg->cta_caja : $cfg->cta_banco;
    }

    private static function rango(string $periodo): array
    {
        $ini = Carbon::createFromFormat('Ym', $periodo)->startOfMonth();
        return [$ini->toDateString(), $ini->copy()->endOfMonth()->toDateString()];
    }

    // ================================================================== VENTAS

    /** @return array resumen: asientos y totales generados */
    public static function centralizarVentas(string $ruc, string $periodo): array
    {
        return DB::transaction(function () use ($ruc, $periodo) {
            self::validarPeriodo($ruc, $periodo);
            $cfg = self::config($ruc);
            $imp = self::imputables($ruc);
            [$desde, $hasta] = self::rango($periodo);
            DB::table('conta_asientos')->where('IdEmpresa', $ruc)->where('periodo', $periodo)->whereIn('origen', ['VENTAS', 'COBRANZAS'])->delete();

            $docs = DB::table('cpe_cabecera')->where('IdEmpresa', $ruc)->whereIn('tdocod', ['01', '03', '07', '08'])
                ->whereBetween('ccafem', [$desde, $hasta])->whereNull('ccabaj')
                ->orderBy('ccafem')->orderBy('tdocod')->orderBy('serdoc')->orderBy('numdoc')->get();
            $n = ['documentos' => $docs->count(), 'asientos' => 0, 'ventas' => 0.0, 'igv' => 0.0, 'costo' => 0.0, 'cobranzas' => 0.0];

            $lineasVenta = function (object $c, bool $nota, string $anexoDoc, string $anexoNom, string $docTxt) use ($cfg) {
                $grav = (float) $c->ccatvg;
                $exo = round((float) $c->ccatexo + (float) $c->ccatinaf, 2);
                $igv = (float) $c->ccaigv;
                $total = (float) $c->ccaitv;
                // Diferencias de redondeo (o tributos no separados) van a la cuenta de ventas
                $ajuste = round($total - $grav - $exo - $igv, 2);
                if ($grav > 0 || $exo <= 0) {
                    $grav = round($grav + $ajuste, 2);
                } else {
                    $exo = round($exo + $ajuste, 2);
                }
                [$d, $h] = $nota ? ['haber', 'debe'] : ['debe', 'haber'];
                return [
                    ['cuenta' => $cfg->cta_por_cobrar, $d => $total, 'anexo_doc' => $anexoDoc, 'anexo_nombre' => $anexoNom, 'documento' => $docTxt],
                    ['cuenta' => $cfg->cta_igv, $h => $igv, 'documento' => $docTxt],
                    ['cuenta' => $cfg->cta_ventas, $h => $grav, 'documento' => $docTxt],
                    ['cuenta' => $cfg->cta_ventas_exo, $h => $exo, 'documento' => $docTxt],
                ];
            };
            $sumarLineas = function (array $acum, array $nuevas) {
                foreach ($nuevas as $l) {
                    $k = $l['cuenta'] . '|' . (isset($l['debe']) ? 'D' : 'H');
                    $acum[$k] ??= ['cuenta' => $l['cuenta'], 'debe' => 0, 'haber' => 0, 'documento' => $l['documento'] ?? null,
                        'anexo_doc' => $l['anexo_doc'] ?? null, 'anexo_nombre' => $l['anexo_nombre'] ?? null];
                    $acum[$k]['debe'] = round($acum[$k]['debe'] + (float) ($l['debe'] ?? 0), 2);
                    $acum[$k]['haber'] = round($acum[$k]['haber'] + (float) ($l['haber'] ?? 0), 2);
                }
                return $acum;
            };

            // ---- Provisión de ventas ----
            foreach ($docs->groupBy('ccafem') as $fecha => $delDia) {
                // Facturas, notas (de facturas y de boletas): un asiento por documento
                foreach ($delDia->filter(fn($c) => $c->tdocod !== '03') as $c) {
                    $docTxt = $c->serdoc . '-' . $c->numdoc;
                    $nota = $c->tdocod === '07';
                    $tipo = ['01' => 'FACTURA', '07' => 'NOTA DE CRÉDITO', '08' => 'NOTA DE DÉBITO'][$c->tdocod];
                    self::crearAsiento($ruc, ['fecha' => $fecha, 'subdiario' => '05', 'origen' => 'VENTAS', 'tdocod' => $c->tdocod, 'documento' => $docTxt,
                        'glosa' => "{$tipo} {$docTxt} " . $c->ccanom . ($c->serie_ref ? " (REF. {$c->serie_ref}-{$c->num_ref})" : ''),
                        'ref_tabla' => 'cpe_cabecera', 'ref_id' => $c->IdCpe_cabecera],
                        $lineasVenta($c, $nota, (string) $c->ccandi, (string) $c->ccanom, $docTxt), $imp);
                    $n['asientos']++;
                }
                // Boletas: un asiento por día y serie con el rango de números
                foreach ($delDia->where('tdocod', '03')->groupBy('serdoc') as $serie => $boletas) {
                    $acum = [];
                    foreach ($boletas as $c) {
                        $acum = $sumarLineas($acum, $lineasVenta($c, false, '00000000', 'CLIENTES VARIOS', ''));
                    }
                    $rangoDoc = "{$serie}-" . $boletas->min('numdoc') . ' AL ' . $boletas->max('numdoc');
                    foreach ($acum as &$l) {
                        $l['documento'] = $rangoDoc;
                    }
                    unset($l);
                    self::crearAsiento($ruc, ['fecha' => $fecha, 'subdiario' => '05', 'origen' => 'VENTAS', 'tdocod' => '03', 'documento' => $rangoDoc,
                        'glosa' => 'VENTAS CON BOLETA ' . $rangoDoc . ' (' . $boletas->count() . ' DOC.)'], array_values($acum), $imp);
                    $n['asientos']++;
                }

                foreach ($delDia as $c) {
                    $signo = $c->tdocod === '07' ? -1 : 1;
                    $n['ventas'] += $signo * round((float) $c->ccatvg + (float) $c->ccatexo + (float) $c->ccatinaf, 2);
                    $n['igv'] += $signo * (float) $c->ccaigv;
                }

                // ---- Costo de ventas del día ----
                if ($cfg->asiento_costo) {
                    $costo = (float) DB::table('cpe_detalle as d')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
                        ->whereIn('c.IdCpe_cabecera', $delDia->pluck('IdCpe_cabecera'))
                        ->sum(DB::raw("CASE WHEN c.tdocod = '07' THEN -1 ELSE 1 END * d.costo * d.cdecan"));
                    $costo = round($costo, 2);
                    if ($costo != 0) {
                        [$d, $h] = $costo > 0 ? ['debe', 'haber'] : ['haber', 'debe'];
                        self::crearAsiento($ruc, ['fecha' => $fecha, 'subdiario' => '05', 'origen' => 'VENTAS',
                            'glosa' => 'COSTO DE VENTAS DEL ' . Carbon::parse($fecha)->format('d/m/Y')], [
                            ['cuenta' => $cfg->cta_costo_ventas, $d => abs($costo)], ['cuenta' => $cfg->cta_mercaderias, $h => abs($costo)],
                        ], $imp);
                        $n['asientos']++;
                        $n['costo'] += $costo;
                    }
                }
            }

            // ---- Cobranza de ventas al contado (por día y medio de pago) ----
            $contado = DB::table('venta_medio_pago as v')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'v.IdCpe_cabecera')
                ->leftJoin('medios_pagos as m', 'm.id_med_pag', '=', 'v.id_med_pag')
                ->where('c.IdEmpresa', $ruc)->whereIn('c.tdocod', ['01', '03', '08'])->whereBetween('c.ccafem', [$desde, $hasta])->whereNull('c.ccabaj')
                ->groupBy('c.ccafem', 'm.nom_med_pag', 'm.cuenta_contable')
                ->select('c.ccafem as fecha', 'm.nom_med_pag', 'm.cuenta_contable', DB::raw('SUM(v.monto) as monto'))->get();
            foreach ($contado->groupBy('fecha') as $fecha => $medios) {
                $lineas = $medios->map(fn($m) => ['cuenta' => self::cuentaMedio($cfg, $m->nom_med_pag, $m->cuenta_contable), 'debe' => (float) $m->monto,
                    'glosa' => 'COBRO ' . ($m->nom_med_pag ?? 'CONTADO')])->all();
                $total = round($medios->sum('monto'), 2);
                $lineas[] = ['cuenta' => $cfg->cta_por_cobrar, 'haber' => $total, 'anexo_doc' => '00000000', 'anexo_nombre' => 'CLIENTES VARIOS'];
                self::crearAsiento($ruc, ['fecha' => $fecha, 'subdiario' => '01', 'origen' => 'COBRANZAS',
                    'glosa' => 'COBRANZA DE VENTAS AL CONTADO DEL ' . Carbon::parse($fecha)->format('d/m/Y')], self::agrupar($lineas), $imp);
                $n['asientos']++;
                $n['cobranzas'] += $total;
            }

            // ---- Cobranza de ventas al crédito (cuentas por cobrar) ----
            $cobros = DB::table('cuentas_cobrar_medios as cm')
                ->join('cuentas_cobrar_detalle as d', 'd.cue_cob_det_id', '=', 'cm.cue_cob_det_id')
                ->join('cuentas_cobrar as cc', 'cc.cue_cob_id', '=', 'd.cue_cob_id')
                ->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'cc.IdCpe_cabecera')
                ->leftJoin('medios_pagos as m', 'm.id_med_pag', '=', 'cm.med_pag_id')
                ->where('cc.IdEmpresa', $ruc)->where('d.est_cue_cob_det', 'REGISTRADO')->whereBetween('d.fec_dep', [$desde, $hasta])
                ->whereIn('c.tdocod', ['01', '03', '08'])
                ->select('d.fec_dep as fecha', 'm.nom_med_pag', 'm.cuenta_contable', 'cm.monto', 'c.serdoc', 'c.numdoc', 'c.ccandi', 'c.ccanom')
                ->orderBy('d.fec_dep')->get();
            foreach ($cobros->groupBy('fecha') as $fecha => $grupo) {
                $lineas = [];
                foreach ($grupo as $g) {
                    $doc = $g->serdoc . '-' . $g->numdoc;
                    $lineas[] = ['cuenta' => self::cuentaMedio($cfg, $g->nom_med_pag, $g->cuenta_contable), 'debe' => (float) $g->monto, 'glosa' => 'COBRO ' . ($g->nom_med_pag ?? '')];
                    $lineas[] = ['cuenta' => $cfg->cta_por_cobrar, 'haber' => (float) $g->monto, 'anexo_doc' => $g->ccandi, 'anexo_nombre' => $g->ccanom, 'documento' => $doc];
                }
                $total = round($grupo->sum('monto'), 2);
                self::crearAsiento($ruc, ['fecha' => $fecha, 'subdiario' => '01', 'origen' => 'COBRANZAS',
                    'glosa' => 'COBRANZA DE CUENTAS POR COBRAR DEL ' . Carbon::parse($fecha)->format('d/m/Y')], self::agrupar($lineas, true), $imp);
                $n['asientos']++;
                $n['cobranzas'] += $total;
            }

            self::renumerar($ruc, $periodo);
            return $n;
        });
    }

    // ================================================================== COMPRAS

    public static function centralizarCompras(string $ruc, string $periodo): array
    {
        return DB::transaction(function () use ($ruc, $periodo) {
            self::validarPeriodo($ruc, $periodo);
            $cfg = self::config($ruc);
            $imp = self::imputables($ruc);
            [$desde, $hasta] = self::rango($periodo);
            DB::table('conta_asientos')->where('IdEmpresa', $ruc)->where('periodo', $periodo)->whereIn('origen', ['COMPRAS', 'PAGOS'])->delete();

            $compras = DB::table('compras_cabecera as c')->leftJoin('proveedor as p', 'p.prov_id', '=', 'c.prov_id')
                ->where('c.IdEmpresa', $ruc)->where('c.est_compra', 'Registrado')->whereBetween('c.com_fec', [$desde, $hasta])
                ->orderBy('c.com_fec')->orderBy('c.com_cab_id')->select('c.*', 'p.prov_ruc', 'p.prov_raz')->get();
            $n = ['documentos' => $compras->count(), 'asientos' => 0, 'compras' => 0.0, 'igv' => 0.0, 'pagos' => 0.0];
            $nombres = ['01' => 'FACTURA', '03' => 'BOLETA', '12' => 'TICKET', '13' => 'NOTA DE VENTA', '00' => 'DOCUMENTO'];

            foreach ($compras as $c) {
                $tc = $c->mon_id === 'USD' ? (float) $c->tip_cam : 1.0;
                $total = round((float) $c->total_com * $tc, 2);
                $igv = round((float) $c->igv_com * $tc, 2);
                $neto = round($total - $igv, 2);
                $docTxt = $c->com_doc_ser . '-' . $c->com_doc_num;
                $anexo = ['anexo_doc' => $c->prov_ruc, 'anexo_nombre' => $c->prov_raz, 'documento' => $docTxt];
                self::crearAsiento($ruc, ['fecha' => $c->com_fec, 'subdiario' => '11', 'origen' => 'COMPRAS', 'tdocod' => $c->tdocod, 'documento' => $docTxt,
                    'glosa' => ($nombres[$c->tdocod] ?? 'COMPRA') . " {$docTxt} {$c->prov_raz}" . ($c->mon_id === 'USD' ? " (US$ {$c->total_com} TC {$c->tip_cam})" : ''),
                    'ref_tabla' => 'compras_cabecera', 'ref_id' => $c->com_cab_id], [
                    ['cuenta' => $cfg->cta_compras, 'debe' => $neto] + $anexo,
                    ['cuenta' => $cfg->cta_igv, 'debe' => $igv] + $anexo,
                    ['cuenta' => $cfg->cta_por_pagar, 'haber' => $total] + $anexo,
                ], $imp);
                $n['asientos']++;
                $n['compras'] += $neto;
                $n['igv'] += $igv;
            }

            // ---- Destino: lo comprado entra al almacén el mismo día (así los reportes a cualquier fecha cuadran) ----
            if ($cfg->asiento_destino) {
                foreach ($compras->groupBy('com_fec') as $fecha => $grupo) {
                    // Mismo cálculo que la provisión (total e IGV convertidos por separado) para que 20/61 igualen a la 60
                    $monto = round($grupo->sum(function ($c) {
                        $tc = $c->mon_id === 'USD' ? (float) $c->tip_cam : 1.0;
                        return round((float) $c->total_com * $tc, 2) - round((float) $c->igv_com * $tc, 2);
                    }), 2);
                    if ($monto > 0) {
                        self::crearAsiento($ruc, ['fecha' => $fecha, 'subdiario' => '11', 'origen' => 'COMPRAS',
                            'glosa' => 'DESTINO DE COMPRAS DEL ' . Carbon::parse($fecha)->format('d/m/Y')], [
                            ['cuenta' => $cfg->cta_mercaderias, 'debe' => $monto], ['cuenta' => $cfg->cta_variacion, 'haber' => $monto],
                        ], $imp);
                        $n['asientos']++;
                    }
                }
            }

            // ---- Pago de compras al contado (por día) ----
            foreach ($compras->filter(fn($c) => (float) $c->tot_con > 0)->groupBy('com_fec') as $fecha => $grupo) {
                $lineas = [];
                foreach ($grupo as $c) {
                    $monto = round((float) $c->tot_con * ($c->mon_id === 'USD' ? (float) $c->tip_cam : 1), 2);
                    $lineas[] = ['cuenta' => $cfg->cta_por_pagar, 'debe' => $monto, 'anexo_doc' => $c->prov_ruc, 'anexo_nombre' => $c->prov_raz,
                        'documento' => $c->com_doc_ser . '-' . $c->com_doc_num];
                    $lineas[] = ['cuenta' => $cfg->cta_caja, 'haber' => $monto, 'glosa' => 'PAGO AL CONTADO'];
                    $n['pagos'] += $monto;
                }
                self::crearAsiento($ruc, ['fecha' => $fecha, 'subdiario' => '01', 'origen' => 'PAGOS',
                    'glosa' => 'PAGO DE COMPRAS AL CONTADO DEL ' . Carbon::parse($fecha)->format('d/m/Y')], self::agrupar($lineas, true), $imp);
                $n['asientos']++;
            }

            // ---- Pagos de compras al crédito (cuentas por pagar) ----
            $pagos = DB::table('cuentas_pagar_medios as pm')
                ->join('cuentas_pagar_detalle as d', 'd.cue_pag_det_id', '=', 'pm.cue_pag_det_id')
                ->join('cuentas_pagar as cp', 'cp.cue_pag_id', '=', 'd.cue_pag_id')
                ->join('compras_cabecera as c', 'c.com_cab_id', '=', 'cp.com_cab_id')
                ->leftJoin('proveedor as p', 'p.prov_id', '=', 'c.prov_id')
                ->leftJoin('medios_pagos as m', 'm.id_med_pag', '=', 'pm.med_pag_id')
                ->where('cp.IdEmpresa', $ruc)->where('d.est_cue_pag_det', 'REGISTRADO')->whereBetween('d.fec_dep', [$desde, $hasta])
                ->select('d.fec_dep as fecha', 'm.nom_med_pag', 'm.cuenta_contable', 'pm.monto', 'c.com_doc_ser', 'c.com_doc_num', 'p.prov_ruc', 'p.prov_raz')
                ->orderBy('d.fec_dep')->get();
            foreach ($pagos->groupBy('fecha') as $fecha => $grupo) {
                $lineas = [];
                foreach ($grupo as $g) {
                    $lineas[] = ['cuenta' => $cfg->cta_por_pagar, 'debe' => (float) $g->monto, 'anexo_doc' => $g->prov_ruc, 'anexo_nombre' => $g->prov_raz,
                        'documento' => $g->com_doc_ser . '-' . $g->com_doc_num];
                    $lineas[] = ['cuenta' => self::cuentaMedio($cfg, $g->nom_med_pag, $g->cuenta_contable), 'haber' => (float) $g->monto, 'glosa' => 'PAGO ' . ($g->nom_med_pag ?? '')];
                }
                self::crearAsiento($ruc, ['fecha' => $fecha, 'subdiario' => '01', 'origen' => 'PAGOS',
                    'glosa' => 'PAGO A PROVEEDORES DEL ' . Carbon::parse($fecha)->format('d/m/Y')], self::agrupar($lineas, true), $imp);
                $n['asientos']++;
                $n['pagos'] += round($grupo->sum('monto'), 2);
            }

            // ---- Gastos: provisión por documento (cuenta de su categoría) y pago al contado por día ----
            $gastos = DB::table('gastos as g')->leftJoin('gasto_categorias as c', 'c.id', '=', 'g.categoria_id')->leftJoin('medios_pagos as m', 'm.id_med_pag', '=', 'g.id_med_pag')
                ->where('g.IdEmpresa', $ruc)->where('g.estado', 'Registrado')->whereBetween('g.fecha', [$desde, $hasta])
                ->orderBy('g.fecha')->orderBy('g.id')->select('g.*', 'c.nombre as categoria', 'c.cuenta_contable', 'm.nom_med_pag', 'm.cuenta_contable as cuenta_medio')->get();
            $n['gastos'] = 0.0;
            foreach ($gastos as $g) {
                $tc = $g->moneda === 'USD' ? (float) $g->tipo_cambio : 1.0;
                $total = round((float) $g->total * $tc, 2);
                $igv = round((float) $g->igv * $tc, 2);
                $ret = round((float) $g->retencion * $tc, 2);
                $credito = $g->credito_fiscal && $igv > 0;
                $gasto = round($total - ($credito ? $igv : 0), 2);
                $cuenta = $g->cuenta_contable && isset($imp[$g->cuenta_contable]) ? $g->cuenta_contable : '659';
                $doc = trim(($g->serie ? $g->serie . '-' : '') . ($g->numero ?? ''), '-') ?: null;
                $anexo = ['anexo_doc' => $g->prov_doc, 'anexo_nombre' => $g->prov_nombre, 'documento' => $doc];
                [$d, $h] = $g->tdocod === '07' ? ['haber', 'debe'] : ['debe', 'haber'];
                self::crearAsiento($ruc, ['fecha' => $g->fecha, 'subdiario' => '11', 'origen' => 'COMPRAS', 'tdocod' => $g->tdocod, 'documento' => $doc,
                    'glosa' => 'GASTO ' . ($g->categoria ?? '') . ' ' . ($doc ?? '') . ' ' . ($g->prov_nombre ?? $g->descripcion ?? ''),
                    'ref_tabla' => 'gastos', 'ref_id' => $g->id], [
                    ['cuenta' => $cuenta, $d => $gasto, 'glosa' => $g->descripcion] + $anexo,
                    ['cuenta' => $cfg->cta_igv, $d => $credito ? $igv : 0] + $anexo,
                    ['cuenta' => $cfg->cta_renta4_pagar ?? '40172', $h => $ret, 'glosa' => 'RETENCIÓN 4TA CATEGORÍA'] + $anexo,
                    ['cuenta' => $cfg->cta_por_pagar, $h => round($total - $ret, 2)] + $anexo,
                ], $imp);
                $n['asientos']++;
                $n['gastos'] += $g->tdocod === '07' ? -$gasto : $gasto;
            }
            foreach ($gastos->where('forma_pago', 'CONTADO')->where('tdocod', '!=', '07')->groupBy('fecha') as $fecha => $grupo) {
                $lineas = [];
                foreach ($grupo as $g) {
                    $monto = round(((float) $g->total - (float) $g->retencion) * ($g->moneda === 'USD' ? (float) $g->tipo_cambio : 1), 2);
                    $lineas[] = ['cuenta' => $cfg->cta_por_pagar, 'debe' => $monto, 'anexo_doc' => $g->prov_doc, 'anexo_nombre' => $g->prov_nombre,
                        'documento' => trim(($g->serie ? $g->serie . '-' : '') . ($g->numero ?? ''), '-') ?: null];
                    $lineas[] = ['cuenta' => self::cuentaMedio($cfg, $g->nom_med_pag, $g->cuenta_medio), 'haber' => $monto, 'glosa' => 'PAGO DE GASTO'];
                    $n['pagos'] += $monto;
                }
                self::crearAsiento($ruc, ['fecha' => $fecha, 'subdiario' => '01', 'origen' => 'PAGOS',
                    'glosa' => 'PAGO DE GASTOS AL CONTADO DEL ' . Carbon::parse($fecha)->format('d/m/Y')], self::agrupar($lineas, true), $imp);
                $n['asientos']++;
            }

            self::renumerar($ruc, $periodo);
            return $n;
        });
    }

    /** Correlativo de cada subdiario en orden cronológico (fecha y luego el orden en que se registró) */
    public static function renumerar(string $ruc, string $periodo): void
    {
        $asientos = DB::table('conta_asientos')->where('IdEmpresa', $ruc)->where('periodo', $periodo)
            ->orderBy('subdiario')->orderBy('fecha')->orderBy('id')->get(['id', 'subdiario']);
        // Primero se mueven fuera del rango para no chocar con el índice único
        DB::table('conta_asientos')->where('IdEmpresa', $ruc)->where('periodo', $periodo)->update(['numero' => DB::raw('numero + 1000000')]);
        foreach ($asientos->groupBy('subdiario') as $grupo) {
            foreach ($grupo->values() as $i => $a) {
                DB::table('conta_asientos')->where('id', $a->id)->update(['numero' => $i + 1]);
            }
        }
    }

    /** Une líneas repetidas de la misma cuenta y lado. Con $conAnexo se mantiene una línea por documento (para el mayor de clientes/proveedores) */
    private static function agrupar(array $lineas, bool $conAnexo = false): array
    {
        $res = [];
        foreach ($lineas as $l) {
            $lado = isset($l['debe']) ? 'D' : 'H';
            $k = $l['cuenta'] . '|' . $lado . ($conAnexo ? '|' . ($l['documento'] ?? '') . '|' . ($l['anexo_doc'] ?? '') : '');
            if (!isset($res[$k])) {
                $res[$k] = $l + ['debe' => 0, 'haber' => 0];
                $res[$k]['debe'] = 0;
                $res[$k]['haber'] = 0;
            }
            $res[$k]['debe'] = round($res[$k]['debe'] + (float) ($l['debe'] ?? 0), 2);
            $res[$k]['haber'] = round($res[$k]['haber'] + (float) ($l['haber'] ?? 0), 2);
        }
        return array_values($res);
    }

    // ================================================================== SALDOS (mayor, balance, estados)

    /** Sumas debe/haber por cuenta imputable en un rango (o acumulado hasta una fecha si $desde es null) */
    public static function sumas(string $ruc, ?string $desde, string $hasta)
    {
        return DB::table('conta_asiento_detalle as d')->join('conta_asientos as a', 'a.id', '=', 'd.asiento_id')
            ->where('a.IdEmpresa', $ruc)->where('a.fecha', '<=', $hasta)->when($desde, fn($w) => $w->where('a.fecha', '>=', $desde))
            ->groupBy('d.cuenta')->select('d.cuenta', DB::raw('SUM(d.debe) as debe'), DB::raw('SUM(d.haber) as haber'))
            ->orderBy('d.cuenta')->get()->keyBy('cuenta');
    }

    /** Saldo neto (haber − debe) de las cuentas que empiezan con alguno de los prefijos */
    public static function neto($sumas, array $prefijos, array $excluir = []): float
    {
        $t = 0.0;
        foreach ($sumas as $cuenta => $s) {
            $cuenta = (string) $cuenta;
            foreach ($excluir as $x) {
                if (str_starts_with($cuenta, $x)) {
                    continue 2;
                }
            }
            foreach ($prefijos as $p) {
                if (str_starts_with($cuenta, (string) $p)) {
                    $t += (float) $s->haber - (float) $s->debe;
                    break;
                }
            }
        }
        return round($t, 2);
    }
}
