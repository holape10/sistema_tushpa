<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reportes avícolas de un rango de fechas: ventas por día, por cliente y por producto (kilos, aves, jabas, soles y
 * precio promedio), ingresos de pollo (kilos y costo), margen por kilo, mermas por tipo y % de mortalidad, rendimiento
 * del beneficio, jabas por devolver y saldos de la cuenta corriente. Las ventas anuladas no cuentan.
 */
class AvicolaReportes
{
    /** @return array<string, mixed> */
    public static function generar(int $suc, Carbon $desde, Carbon $hasta): array
    {
        $rango = [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()];
        $ventas = fn () => DB::table('avi_ventas as v')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'v.IdCpe_cabecera')
            ->where('v.id_empresa_negocio', $suc)->whereNull('c.ccabaj')->whereBetween('v.fecha', $rango);
        $sumas = 'COUNT(*) as ventas, SUM(v.jabas) as jabas, SUM(v.aves) as aves, SUM(v.peso_neto) as kg, SUM(v.total) as total,
            SUM(v.aves_muertas) as muertas, SUM(CASE WHEN v.credito = 1 THEN v.total ELSE 0 END) as credito';

        $porDia = $ventas()->groupByRaw('DATE(v.fecha)')->orderByRaw('DATE(v.fecha)')->selectRaw('DATE(v.fecha) as dia, '.$sumas)->get();
        $porCliente = $ventas()->groupBy('v.clicod', 'v.clinom')->orderByDesc(DB::raw('SUM(v.total)'))->selectRaw('v.clicod, v.clinom, '.$sumas)->get();
        $porProducto = $ventas()->join('productos as p', 'p.IdProducto', '=', 'v.IdProducto')->groupBy('v.IdProducto', 'p.pronom')
            ->orderByDesc(DB::raw('SUM(v.total)'))->selectRaw('p.pronom, '.$sumas)->get();
        $totVentas = $ventas()->selectRaw($sumas)->first();

        $ingresos = DB::table('avi_ingresos')->where('id_empresa_negocio', $suc)->where('estado', 'ACTIVO')->whereBetween('fecha', $rango)
            ->selectRaw('COUNT(*) as ingresos, COALESCE(SUM(jabas),0) as jabas, COALESCE(SUM(aves),0) as aves, COALESCE(SUM(peso_neto),0) as kg,
                COALESCE(SUM(total),0) as total, COALESCE(SUM(aves_muertas),0) as muertas, COALESCE(SUM(peso_muertas),0) as kg_muertas')->first();
        $porProveedor = DB::table('avi_ingresos')->where('id_empresa_negocio', $suc)->where('estado', 'ACTIVO')->whereBetween('fecha', $rango)
            ->groupBy('proveedor')->orderByDesc(DB::raw('SUM(total)'))
            ->selectRaw('proveedor, COUNT(*) as ingresos, SUM(aves) as aves, SUM(peso_neto) as kg, SUM(total) as total, SUM(aves_muertas) as muertas')->get();

        $mermas = DB::table('avi_mermas')->where('id_empresa_negocio', $suc)->where('estado', 'ACTIVA')->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->groupBy('tipo')->selectRaw('tipo, COUNT(*) as veces, SUM(aves) as aves, SUM(kg) as kg, SUM(costo) as costo')->get()->keyBy('tipo');
        $beneficio = DB::table('avi_beneficios')->where('id_empresa_negocio', $suc)->where('estado', 'ACTIVO')->whereBetween('fecha', $rango)
            ->selectRaw('COUNT(*) as veces, COALESCE(SUM(aves),0) as aves, COALESCE(SUM(kg_vivo),0) as kg_vivo, COALESCE(SUM(kg_salida),0) as kg_salida')->first();

        $kgVendidos = (float) ($totVentas->kg ?? 0);
        $precioProm = $kgVendidos > 0 ? round((float) $totVentas->total / $kgVendidos, 2) : 0;
        $costoProm = (float) $ingresos->kg > 0 ? round((float) $ingresos->total / (float) $ingresos->kg, 2) : 0;
        $avesEntraron = (int) $ingresos->aves + (int) $ingresos->muertas;

        $saldos = Avicola::cuentaCorriente($suc);
        $jabas = collect(Avicola::saldoJabas($suc))->filter(fn ($s) => $s > 0);
        $nombres = DB::table('cliente')->whereIn('clicod', $saldos->keys()->merge($jabas->keys())->unique())->pluck('clinom', 'clicod');

        return [
            'porDia' => $porDia, 'porCliente' => $porCliente, 'porProducto' => $porProducto, 'totVentas' => $totVentas,
            'ingresos' => $ingresos, 'porProveedor' => $porProveedor, 'mermas' => $mermas, 'beneficio' => $beneficio,
            'precioProm' => $precioProm, 'costoProm' => $costoProm,
            // Margen por kilo: precio promedio de venta − costo promedio de lo que entró (referencial: no descuenta gastos)
            'margenKg' => $precioProm && $costoProm ? round($precioProm - $costoProm, 2) : null,
            'utilidad' => $precioProm && $costoProm ? round(($precioProm - $costoProm) * $kgVendidos, 2) : null,
            'mortalidadPct' => $avesEntraron > 0 ? round((int) $ingresos->muertas / $avesEntraron * 100, 2) : null,
            'rendimiento' => (float) $beneficio->kg_vivo > 0 ? round((float) $beneficio->kg_salida / (float) $beneficio->kg_vivo * 100, 2) : null,
            'saldos' => $saldos->sortByDesc('saldo')->map(fn ($s) => (object) ((array) $s + ['clinom' => $nombres[$s->clicod] ?? '—']))->values(),
            'jabas' => $jabas->sortDesc()->map(fn ($n, $c) => (object) ['clicod' => $c, 'clinom' => $nombres[$c] ?? '—', 'jabas' => $n])->values(),
        ];
    }
}
