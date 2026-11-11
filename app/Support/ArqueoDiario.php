<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Arqueo diario de caja (formato para imprimir, como el del sistema antiguo): todo lo que pasó por la caja de una
 * sucursal en un día: ventas con su medio de pago, ventas al crédito, cobranzas, pagos a proveedores, gastos y otros
 * ingresos, con el resumen del efectivo (ingresos − egresos) y las ventas por medio de pago.
 */
class ArqueoDiario
{
    /** Comprobantes que son venta (las notas de crédito/débito no) */
    public const TIPOS_VENTA = ['01', '03', '13'];

    /** Movimientos de caja que no son gasto ni otro ingreso: fondo, venta, cobranzas y pagos ya salen en su sección */
    private const MOVIMIENTOS_PROPIOS = ['001', '002', '007', '009'];

    /**
     * @return array{fecha: string, medios: Collection<int, object>, ventas: Collection<int, object>, credito: Collection<int, object>,
     *     cobranzas: Collection<int, object>, pagos: Collection<int, object>, gastos: Collection<int, object>, otros: Collection<int, object>,
     *     totales: array<string, mixed>, porMedio: Collection<int, object>}
     */
    public static function datos(int $sucursal, string $fecha, ?int $usuario = null): array
    {
        $medios = DB::table('medios_pagos')->where('id_empresa_negocio', $sucursal)->pluck('nom_med_pag', 'id_med_pag')
            ->map(fn ($n) => mb_strtoupper(trim((string) $n)));

        // 01. Ventas del día con lo cobrado por cada medio
        $ventas = DB::table('cpe_cabecera as c')
            ->where('c.id_empresa_negocio', $sucursal)->whereDate('c.ccafem', $fecha)->whereIn('c.tdocod', self::TIPOS_VENTA)->whereNull('c.ccabaj')
            ->when($usuario, fn ($q) => $q->where('c.IdUsuario', $usuario))
            ->orderBy('c.fecha_hora')->orderBy('c.IdCpe_cabecera')
            ->get(['c.IdCpe_cabecera', 'c.tdocod', 'c.serdoc', 'c.numdoc', 'c.ccanom', 'c.ccafem', 'c.ccaitv', 'c.totalcredito']);
        $cobros = DB::table('venta_medio_pago')->whereIn('IdCpe_cabecera', $ventas->pluck('IdCpe_cabecera')->all() ?: [0])
            ->get(['IdCpe_cabecera', 'id_med_pag', 'monto', 'moneda', 'monto_usd'])->groupBy('IdCpe_cabecera');
        foreach ($ventas as $v) {
            $v->montos = self::porMedio($cobros->get($v->IdCpe_cabecera, collect()), 'id_med_pag');
            $v->credito = round((float) $v->totalcredito, 2);
        }

        // 03. Cobranzas de ventas al crédito (pagos registrados ese día)
        $cobranzas = DB::table('cuentas_cobrar_detalle as d')
            ->join('cuentas_cobrar as cc', 'cc.cue_cob_id', '=', 'd.cue_cob_id')
            ->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'cc.IdCpe_cabecera')
            ->where('cc.id_empresa_negocio', $sucursal)->where('d.est_cue_cob_det', 'REGISTRADO')->whereDate('d.fec_dep', $fecha)
            ->when($usuario, fn ($q) => $q->where('d.IdUsuario', $usuario))
            ->orderBy('d.cue_cob_det_id')
            ->get(['d.cue_cob_det_id', 'd.numero_recibo', 'd.fec_dep', 'd.abono', 'c.tdocod', 'c.serdoc', 'c.numdoc', 'c.ccanom']);
        $cobrosCuentas = DB::table('cuentas_cobrar_medios')->whereIn('cue_cob_det_id', $cobranzas->pluck('cue_cob_det_id')->all() ?: [0])
            ->get(['cue_cob_det_id', 'med_pag_id', 'monto'])->groupBy('cue_cob_det_id');
        foreach ($cobranzas as $c) {
            $c->montos = self::porMedio($cobrosCuentas->get($c->cue_cob_det_id, collect()), 'med_pag_id');
        }

        // 04. Pagos a proveedores registrados ese día
        $pagos = DB::table('cuentas_pagar_detalle as d')
            ->join('cuentas_pagar as cp', 'cp.cue_pag_id', '=', 'd.cue_pag_id')
            ->join('compras_cabecera as c', 'c.com_cab_id', '=', 'cp.com_cab_id')
            ->leftJoin('proveedor as p', 'p.prov_id', '=', 'c.prov_id')
            ->leftJoin('movimientoscaja as mc', 'mc.mov_caj_id', '=', 'd.mov_caj_id')
            ->where('cp.id_empresa_negocio', $sucursal)->where('d.est_cue_pag_det', 'REGISTRADO')->whereDate('d.fec_dep', $fecha)
            ->when($usuario, fn ($q) => $q->where('d.IdUsuario', $usuario))
            ->orderBy('d.cue_pag_det_id')
            ->get(['d.cue_pag_det_id', 'd.fec_dep', 'd.abono', 'c.tdocod', 'c.com_doc_ser', 'c.com_doc_num', 'p.prov_raz',
                DB::raw("CASE WHEN mc.estado = 'ACTIVO' THEN mc.importe ELSE 0 END AS de_caja")]);

        // 10. Gastos y demás salidas de caja · 15. Otros ingresos
        $movimientos = DB::table('movimientoscaja as mc')
            ->join('tiposcaja as tc', 'tc.tip_caj_id', '=', 'mc.tip_caj_id')
            ->where('mc.id_empresa_negocio', $sucursal)->where('mc.estado', 'ACTIVO')->whereDate('mc.registro', $fecha)
            ->whereNotIn('mc.tip_caj_id', self::MOVIMIENTOS_PROPIOS)
            ->when($usuario, fn ($q) => $q->where('mc.IdUsuario', $usuario))
            ->orderBy('mc.mov_caj_id')
            ->get(['mc.mov_caj_id', 'mc.registro', 'mc.mov_num_doc', 'mc.mov_com', 'mc.importe', 'tc.tip_caj_nom', 'tc.tipo']);
        $gastos = $movimientos->where('tipo', 'SALIDA')->values();
        $otros = $movimientos->where('tipo', 'ENTRADA')->values();

        $fondo = round((float) DB::table('turnos')->where('id_empresa_negocio', $sucursal)->whereDate('apertura', $fecha)
            ->when($usuario, fn ($q) => $q->where('IdUsuario', $usuario))->sum('monto'), 2);

        // Columnas: solo los medios que se usaron, el efectivo primero
        $usados = $ventas->pluck('montos')->merge($cobranzas->pluck('montos'))->flatMap(fn ($m) => array_keys($m))->unique();
        $columnas = $usados->map(fn ($id) => (object) ['id' => (int) $id, 'nombre' => $medios[$id] ?? 'OTRO'])
            ->sortBy(fn ($m) => ($m->nombre === 'EFECTIVO' ? '0' : '1').$m->nombre)->values();
        $efectivo = $columnas->firstWhere('nombre', 'EFECTIVO')?->id;
        $suma = fn (Collection $filas, ?int $medio) => $medio === null ? 0.0 : round($filas->sum(fn ($f) => $f->montos[$medio] ?? 0), 2);

        // Efectivo cobrado en dólares: esos billetes van aparte de los soles
        $dolares = DB::table('venta_medio_pago')->whereIn('IdCpe_cabecera', $ventas->pluck('IdCpe_cabecera')->all() ?: [0])
            ->where('moneda', 'USD')->where('id_med_pag', $efectivo ?? 0)->selectRaw('COALESCE(SUM(monto), 0) AS soles, COALESCE(SUM(monto_usd), 0) AS usd')->first();

        $porTipo = fn (string $tdocod) => $suma($ventas->where('tdocod', $tdocod), $efectivo);
        $ingresos = [
            'fondo' => $fondo,
            'cobranzas' => $suma($cobranzas, $efectivo),
            'facturas' => $porTipo('01'),
            'boletas' => $porTipo('03'),
            'notas' => $porTipo('13'),
            'otros' => round((float) $otros->sum('importe'), 2),
        ];
        $egresos = [
            'gastos' => round((float) $gastos->sum('importe'), 2),
            'pagos' => round((float) $pagos->sum('de_caja'), 2),
            'dolares' => round((float) $dolares->soles, 2),
        ];
        $totalIngresos = round(array_sum($ingresos), 2);
        $totalEgresos = round(array_sum($egresos), 2);

        return [
            'fecha' => $fecha, 'medios' => $columnas, 'ventas' => $ventas, 'credito' => $ventas->where('credito', '>', 0)->values(),
            'cobranzas' => $cobranzas, 'pagos' => $pagos, 'gastos' => $gastos, 'otros' => $otros,
            'porMedio' => $columnas->map(fn ($m) => (object) ['nombre' => $m->nombre, 'ventas' => $suma($ventas, $m->id),
                'cobranzas' => $suma($cobranzas, $m->id)])->values(),
            'totales' => [
                'ventas' => round((float) $ventas->sum('ccaitv'), 2), 'credito' => round((float) $ventas->sum('credito'), 2),
                'cobranzas' => round((float) $cobranzas->sum('abono'), 2), 'pagos' => round((float) $pagos->sum('abono'), 2),
                'gastos' => $egresos['gastos'], 'otros' => $ingresos['otros'], 'dolares_usd' => round((float) $dolares->usd, 2),
                'ingresos' => $ingresos, 'egresos' => $egresos, 'total_ingresos' => $totalIngresos, 'total_egresos' => $totalEgresos,
                'saldo' => round($totalIngresos - $totalEgresos, 2),
            ],
        ];
    }

    /**
     * @param  Collection<int, object>  $filas
     * @return array<int, float> id del medio => monto
     */
    private static function porMedio(Collection $filas, string $campo): array
    {
        $montos = [];
        foreach ($filas as $f) {
            $montos[(int) $f->{$campo}] = round(($montos[(int) $f->{$campo}] ?? 0) + (float) $f->monto, 2);
        }

        return $montos;
    }
}
