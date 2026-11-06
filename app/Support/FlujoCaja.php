<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flujo de caja proyectado por semanas (lunes a domingo), de la sucursal:
 *  - Entra: lo que falta cobrar de las ventas al crédito (por su vencimiento) y las cuotas de los préstamos.
 *  - Sale: lo que falta pagar a los proveedores (por su vencimiento).
 *  - Estimado (opcional y marcado como tal): el promedio semanal de las ventas al contado, las compras al contado y los
 *    gastos de las últimas semanas, para que el dueño vea si le alcanza el día a día.
 * Lo que ya venció y no se cobró/pagó va aparte: no se sabe cuándo se va a mover.
 * Todo en soles: lo que está en dólares se convierte con su tipo de cambio (o el último registrado).
 */
class FlujoCaja
{
    public const MAX_SEMANAS = 26;

    /** Semanas que se miran hacia atrás para los estimados */
    private const SEMANAS_PROMEDIO = 4;

    /**
     * @return array{semanas: array<int, array<string, mixed>>, atrasado: array{cobrar: float, pagar: float, detalle: array<int, array<string, mixed>>},
     *               saldo_inicial: ?array{saldo: float, fecha: string}, tipo_cambio: ?float, negativa: ?int}
     */
    public static function proyeccion(int $sucursal, int $semanas = 8, bool $estimados = true, ?Carbon $hoy = null): array
    {
        $hoy = CarbonImmutable::parse(($hoy ?? now())->toDateString());
        $semanas = max(1, min(self::MAX_SEMANAS, $semanas));
        $inicio = $hoy->startOfWeek(Carbon::MONDAY);
        $fin = $inicio->addWeeks($semanas)->subDay();
        $tc = self::tipoCambio();

        $documentos = self::porCobrar($sucursal, $tc)->concat(self::cuotasPrestamos($sucursal, $hoy))->concat(self::porPagar($sucursal, $tc));
        $atrasados = $documentos->filter(fn ($d) => $d['fecha'] < $hoy->toDateString());
        $futuros = $documentos->filter(fn ($d) => $d['fecha'] >= $hoy->toDateString() && $d['fecha'] <= $fin->toDateString());
        $promedios = $estimados ? self::promedios($sucursal, $hoy) : [];

        $guardado = DB::table('flujo_caja_saldos')->where('id_empresa_negocio', $sucursal)->first();
        $saldo = (float) ($guardado->saldo ?? 0);
        $lista = [];
        $negativa = null;
        for ($i = 0; $i < $semanas; $i++) {
            $desde = $inicio->addWeeks($i);
            $hasta = $desde->addDays(6);
            $de = $futuros->filter(fn ($d) => $d['fecha'] >= $desde->toDateString() && $d['fecha'] <= $hasta->toDateString())->sortBy('fecha')->values();
            // La primera semana ya va empezada: el estimado es solo por los días que faltan
            $dias = $i === 0 ? $hoy->diffInDays($hasta) + 1 : 7;
            $estimado = fn (string $clave) => round(($promedios[$clave] ?? 0) * $dias / 7, 2);

            $entradas = ['Por cobrar' => round($de->where('tipo', 'cobrar')->sum('monto'), 2), 'Cuotas de préstamos' => round($de->where('tipo', 'prestamo')->sum('monto'), 2)];
            $salidas = ['Por pagar a proveedores' => round($de->where('tipo', 'pagar')->sum('monto'), 2)];
            if ($estimados) {
                $entradas['Ventas al contado (estimado)'] = $estimado('ventas');
                $salidas['Compras al contado (estimado)'] = $estimado('compras');
                $salidas['Gastos (estimado)'] = $estimado('gastos');
            }
            $entra = round(array_sum($entradas), 2);
            $sale = round(array_sum($salidas), 2);
            $saldo = round($saldo + $entra - $sale, 2);
            if ($guardado && $saldo < 0 && $negativa === null) {
                $negativa = $i;
            }
            $lista[] = ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'entradas' => $entradas, 'salidas' => $salidas,
                'entra' => $entra, 'sale' => $sale, 'neto' => round($entra - $sale, 2), 'saldo' => $saldo, 'detalle' => $de->all()];
        }

        return [
            'semanas' => $lista,
            'atrasado' => ['cobrar' => round($atrasados->whereIn('tipo', ['cobrar', 'prestamo'])->sum('monto'), 2),
                'pagar' => round($atrasados->where('tipo', 'pagar')->sum('monto'), 2), 'detalle' => $atrasados->sortBy('fecha')->values()->all()],
            'saldo_inicial' => $guardado ? ['saldo' => (float) $guardado->saldo, 'fecha' => (string) $guardado->fecha] : null,
            'tipo_cambio' => $tc,
            'negativa' => $negativa,
            'promedios' => $promedios,
        ];
    }

    /** Guarda con cuánto dinero cuenta hoy la sucursal (caja + bancos) */
    public static function guardarSaldo(int $sucursal, float $saldo, ?int $usuario): void
    {
        DB::table('flujo_caja_saldos')->updateOrInsert(['id_empresa_negocio' => $sucursal],
            ['saldo' => round($saldo, 2), 'fecha' => now()->toDateString(), 'IdUsuario' => $usuario, 'updated_at' => now(), 'created_at' => now()]);
    }

    /** Lo que falta cobrar de las ventas al crédito (sin vencimiento = hoy) */
    private static function porCobrar(int $sucursal, ?float $tc): Collection
    {
        return DB::table('cuentas_cobrar as cc')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'cc.IdCpe_cabecera')
            ->leftJoin('cliente as cl', 'cl.clicod', '=', 'cc.clicod')
            ->where('cc.id_empresa_negocio', $sucursal)->where('cc.saldo', '>', 0)->whereNotIn('cc.estado_cob', ['ANULADO', 'PAGADO'])->whereNull('c.ccabaj')
            ->get(['cc.saldo', 'cc.fec_ven', 'c.serdoc', 'c.numdoc', 'c.moncod', 'c.ccafem', 'cl.clinom', 'c.ccanom'])
            ->map(fn ($r) => [
                'tipo' => 'cobrar', 'fecha' => (string) ($r->fec_ven ?: now()->toDateString()), 'nombre' => (string) ($r->clinom ?: $r->ccanom),
                'documento' => $r->serdoc.'-'.$r->numdoc, 'monto' => self::enSoles((float) $r->saldo, $r->moncod === 'USD', $tc),
                'dolares' => $r->moncod === 'USD' ? (float) $r->saldo : null,
            ]);
    }

    /** Lo que falta pagar a los proveedores (en dólares, con el tipo de cambio de la compra) */
    private static function porPagar(int $sucursal, ?float $tc): Collection
    {
        return DB::table('cuentas_pagar as cp')->join('compras_cabecera as c', 'c.com_cab_id', '=', 'cp.com_cab_id')
            ->leftJoin('proveedor as p', 'p.prov_id', '=', 'cp.clicod')
            ->where('cp.id_empresa_negocio', $sucursal)->where('cp.saldo', '>', 0)->whereNotIn('cp.estado_cob', ['ANULADO', 'PAGADO'])
            ->where('c.est_compra', 'Registrado')
            ->get(['cp.saldo', 'cp.fec_ven', 'c.com_doc_ser', 'c.com_doc_num', 'c.mon_id', 'c.tip_cam', 'c.com_fec', 'p.prov_raz'])
            ->map(fn ($r) => [
                'tipo' => 'pagar', 'fecha' => (string) ($r->fec_ven ?: $r->com_fec ?: now()->toDateString()), 'nombre' => (string) $r->prov_raz,
                'documento' => $r->com_doc_ser.'-'.$r->com_doc_num,
                'monto' => self::enSoles((float) $r->saldo, $r->mon_id === 'USD', (float) $r->tip_cam > 0 ? (float) $r->tip_cam : $tc),
                'dolares' => $r->mon_id === 'USD' ? (float) $r->saldo : null,
            ]);
    }

    /** Cuotas que faltan cobrar de los préstamos vigentes (solo si la empresa usa préstamos) */
    private static function cuotasPrestamos(int $sucursal, CarbonImmutable $hoy): Collection
    {
        if (! Schema::hasTable('prestamos') || ! Schema::hasTable('prestamo_cuotas')) {
            return collect();
        }
        $prestamos = DB::table('prestamos as p')->leftJoin('prestamo_clientes as pc', 'pc.pcl_id', '=', 'p.pcl_id')
            ->leftJoin('cliente as cl', 'cl.clicod', '=', 'pc.clicod')
            ->where('p.id_empresa_negocio', $sucursal)->whereNotIn('p.estado', ['PAGADO', 'ANULADO'])->get(['p.*', 'cl.clinom as cliente']);
        if ($prestamos->isEmpty()) {
            return collect();
        }
        $cuotas = DB::table('prestamo_cuotas')->whereIn('pre_id', $prestamos->pluck('pre_id'))->orderBy('numero')->get()->groupBy('pre_id');
        $salida = collect();
        foreach ($prestamos as $p) {
            foreach (Prestamos::situacion($p, $cuotas[$p->pre_id] ?? collect(), $hoy->toDateString())['cuotas'] as $c) {
                $falta = round($c['monto'] - $c['pagado'], 2);
                if ($falta > 0.009) {
                    $salida->push(['tipo' => 'prestamo', 'fecha' => (string) $c['fecha'], 'nombre' => (string) ($p->cliente ?? ''),
                        'documento' => 'Préstamo '.$p->numero.' · cuota '.$c['numero'], 'monto' => $falta, 'dolares' => null]);
                }
            }
        }

        return $salida;
    }

    /**
     * Promedio semanal de las últimas semanas: ventas al contado (sin anuladas), compras al contado y gastos.
     *
     * @return array{ventas: float, compras: float, gastos: float}
     */
    private static function promedios(int $sucursal, CarbonImmutable $hoy): array
    {
        $desde = $hoy->subWeeks(self::SEMANAS_PROMEDIO)->toDateString();
        $hasta = $hoy->subDay()->toDateString();
        $contado = DB::table('credito_dias')->where('id_empresa_negocio', $sucursal)->where('cre_dia_tip', 'CONTADO')->pluck('cre_dia_id');
        $ventas = (float) DB::table('cpe_cabecera')->where('id_empresa_negocio', $sucursal)->whereBetween('ccafem', [$desde, $hasta])
            ->whereNull('ccabaj')->whereIn('tdocod', ['01', '03', '13'])->where('estadopago', 'CONTADO')->sum('ccaitv');
        $compras = (float) DB::table('compras_cabecera')->where('id_empresa_negocio', $sucursal)->whereBetween('com_fec', [$desde, $hasta])
            ->where('est_compra', 'Registrado')->whereIn('cre_dia_id', $contado)
            ->sum(DB::raw("CASE WHEN mon_id = 'USD' THEN total_com * tip_cam ELSE total_com END"));
        $gastos = Schema::hasTable('gastos') ? (float) DB::table('gastos')->where('id_empresa_negocio', $sucursal)->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', 'Registrado')->sum(DB::raw("CASE WHEN moneda = 'USD' THEN total * tipo_cambio ELSE total END")) : 0.0;

        return ['ventas' => round($ventas / self::SEMANAS_PROMEDIO, 2), 'compras' => round($compras / self::SEMANAS_PROMEDIO, 2),
            'gastos' => round($gastos / self::SEMANAS_PROMEDIO, 2)];
    }

    private static function tipoCambio(): ?float
    {
        $tc = Schema::hasTable('tipo_cambio') ? DB::table('tipo_cambio')->where('moneda', 'USD')->orderByDesc('fecha')->value('venta') : null;

        return $tc ? (float) $tc : null;
    }

    private static function enSoles(float $monto, bool $dolares, ?float $tc): float
    {
        return round($dolares && $tc ? $monto * $tc : $monto, 2);
    }
}
