<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Liquidación del motorizado: al terminar su turno rinde el efectivo que cobró en las entregas.
 *  - Lo que el cliente pagó antes (en caja o "Ya pagó") no lo tiene el motorizado.
 *  - Pedido ya cobrado en caja: el efectivo es lo cobrado en EFECTIVO en sus comprobantes; Yape/Plin/tarjeta se muestran aparte.
 *  - Pedido aún sin comprobante: se espera lo que dijo el cliente (efectivo = todo el total) y se avisa para emitirlo.
 */
class LiquidacionDelivery
{
    /**
     * Pedidos entregados por el motorizado que aún no se liquidan (últimos 7 días).
     *
     * @return array{pedidos: array<int, array<string, mixed>>, total: float, efectivo: float, otros: float, envios: float, sin_comprobante: int}
     */
    public static function pendientes(int $sucursal, int $motId): array
    {
        $pedidos = DB::table('pedidos')->where('id_empresa_negocio', $sucursal)->where('mot_id', $motId)->where('ped_tip', 'Delivery')
            ->where('entrega_estado', 'ENTREGADO')->whereNull('liq_id')->whereNotIn('ped_est', ['Anulado', 'Unido'])
            ->where('fecha_hora', '>=', now()->subDays(7)->startOfDay())->orderBy('entrega_fin')->get();
        $cpes = DB::table('cpe_cabecera')->whereIn('ped_id', $pedidos->pluck('ped_id'))->whereNull('ccabaj')
            ->get(['IdCpe_cabecera', 'ped_id', 'serdoc', 'numdoc'])->groupBy('ped_id');
        $pagos = DB::table('venta_medio_pago as v')->join('medios_pagos as m', 'm.id_med_pag', '=', 'v.id_med_pag')
            ->whereIn('v.IdCpe_cabecera', $cpes->flatten()->pluck('IdCpe_cabecera'))
            ->get(['v.IdCpe_cabecera', 'v.monto', 'm.nom_med_pag'])->groupBy('IdCpe_cabecera');

        $filas = $pedidos->map(function ($p) use ($cpes, $pagos) {
            $comprobantes = $cpes[$p->ped_id] ?? collect();
            $medios = $comprobantes->flatMap(fn ($c) => $pagos[$c->IdCpe_cabecera] ?? collect())
                ->groupBy(fn ($m) => mb_strtoupper($m->nom_med_pag))->map(fn ($g) => round((float) $g->sum('monto'), 2));
            $total = (float) $p->ped_tot;
            if ($p->entrega_pago === 'PAGADO') {
                $efectivo = 0.0;
                $otros = 0.0;
            } elseif ($comprobantes->isNotEmpty()) {
                $efectivo = (float) ($medios['EFECTIVO'] ?? 0);
                $otros = round(max(0, $medios->sum() - $efectivo), 2);
            } else {
                $efectivo = in_array($p->entrega_pago, [null, 'EFECTIVO'], true) ? $total : 0.0;
                $otros = round($total - $efectivo, 2);
            }

            return [
                'id' => (int) $p->ped_id, 'cliente' => $p->ped_cli_nom !== 'DELIVERY' ? $p->ped_cli_nom : '', 'direccion' => $p->ped_dir,
                'hora' => $p->entrega_fin ? substr((string) $p->entrega_fin, 11, 5) : '', 'fecha' => substr((string) $p->entrega_fin, 0, 10),
                'total' => $total, 'pago' => $p->entrega_pago, 'efectivo' => $efectivo, 'otros' => $otros, 'envio' => (float) $p->entrega_envio,
                'comprobantes' => $comprobantes->map(fn ($c) => $c->serdoc.'-'.$c->numdoc)->implode(', '),
                'medios' => $medios->map(fn ($monto, $medio) => $medio.' '.number_format($monto, 2))->values()->implode(' · '),
                'sin_comprobante' => $comprobantes->isEmpty(),
            ];
        })->values();

        return [
            'pedidos' => $filas->all(),
            'total' => round($filas->sum('total'), 2),
            'efectivo' => round($filas->sum('efectivo'), 2),
            'otros' => round($filas->sum('otros'), 2),
            'envios' => round($filas->sum('envio'), 2),
            'sin_comprobante' => $filas->where('sin_comprobante', true)->count(),
        ];
    }

    /**
     * Registra lo que rindió el motorizado y deja esos pedidos como liquidados.
     *
     * @param  array<int, int>  $pedIds  los pedidos que se liquidan (deben estar pendientes)
     *
     * @throws \RuntimeException
     */
    public static function liquidar(int $sucursal, int $motId, array $pedIds, float $recibido, ?string $observacion, ?int $usuario): int
    {
        return DB::transaction(function () use ($sucursal, $motId, $pedIds, $recibido, $observacion, $usuario) {
            DB::table('pedidos')->whereIn('ped_id', $pedIds)->lockForUpdate()->get(['ped_id']);
            $pendientes = collect(self::pendientes($sucursal, $motId)['pedidos'])->whereIn('id', array_map('intval', $pedIds));
            if ($pendientes->count() !== count(array_unique($pedIds)) || $pendientes->isEmpty()) {
                throw new \RuntimeException('Algunos pedidos ya se liquidaron o no son de este motorizado. Actualiza la página.');
            }
            $efectivo = round($pendientes->sum('efectivo'), 2);
            $id = DB::table('delivery_liquidaciones')->insertGetId([
                'id_empresa_negocio' => $sucursal, 'mot_id' => $motId, 'IdUsuario' => $usuario, 'pedidos' => $pendientes->count(),
                'total' => round($pendientes->sum('total'), 2), 'efectivo' => $efectivo, 'otros' => round($pendientes->sum('otros'), 2),
                'envios' => round($pendientes->sum('envio'), 2), 'recibido' => round($recibido, 2), 'diferencia' => round($recibido - $efectivo, 2),
                'observacion' => $observacion ? mb_substr($observacion, 0, 255) : null, 'created_at' => now(),
            ]);
            DB::table('pedidos')->whereIn('ped_id', $pendientes->pluck('id'))->update(['liq_id' => $id]);

            return $id;
        });
    }
}
