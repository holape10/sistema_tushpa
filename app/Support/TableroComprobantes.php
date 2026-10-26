<?php

namespace App\Support;

use App\Models\Central\Cliente;
use App\Support\Tenancy\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tablero del panel: comprobantes emitidos por todas las empresas en un periodo (facturas, boletas, notas, guías y
 * notas de venta), con su estado en SUNAT. Se lee de la base de cada empresa y se guarda un rato para no cargar el panel.
 */
class TableroComprobantes
{
    /** Tipos que se muestran: nombre y color (clases de Tailwind y color para el gráfico) */
    public const TIPOS = [
        '01' => ['Facturas', 'bg-indigo-500', '#6366f1'],
        '03' => ['Boletas', 'bg-emerald-500', '#10b981'],
        '07' => ['Notas de crédito', 'bg-rose-500', '#f43f5e'],
        '08' => ['Notas de débito', 'bg-amber-500', '#f59e0b'],
        '09' => ['Guías de remisión', 'bg-sky-500', '#0ea5e9'],
        '13' => ['Notas de venta', 'bg-slate-400', '#94a3b8'],
    ];

    /** Estados de SUNAT agrupados en lo que le importa al dueño */
    public const GRUPOS_SUNAT = [
        'aceptados' => ['Aceptados', 'bg-emerald-500'],
        'proceso' => ['En proceso', 'bg-sky-500'],
        'pendientes' => ['Por enviar', 'bg-amber-500'],
        'rechazados' => ['Rechazados', 'bg-rose-500'],
    ];

    public const PERIODOS = ['hoy' => 'Hoy', 'mes' => 'Este mes', 'anterior' => 'Mes anterior', 'anio' => 'Este año'];

    /**
     * Fechas del periodo elegido (o del rango escrito).
     *
     * @return array{0: string, 1: string, 2: string} desde, hasta y nombre del periodo
     */
    public static function periodo(?string $periodo, ?string $desde, ?string $hasta): array
    {
        $valida = fn (?string $f) => $f && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && strtotime($f);
        if ($valida($desde) && $valida($hasta)) {
            [$desde, $hasta] = $desde <= $hasta ? [$desde, $hasta] : [$hasta, $desde];

            return [$desde, $hasta, Carbon::parse($desde)->format('d/m/Y').' al '.Carbon::parse($hasta)->format('d/m/Y')];
        }

        return match ($periodo) {
            'hoy' => [now()->toDateString(), now()->toDateString(), 'Hoy '.now()->format('d/m/Y')],
            'anterior' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
                ucfirst(now()->subMonthNoOverflow()->translatedFormat('F Y'))],
            'anio' => [now()->startOfYear()->toDateString(), now()->toDateString(), 'Año '.now()->year],
            default => [now()->startOfMonth()->toDateString(), now()->toDateString(), ucfirst(now()->translatedFormat('F Y'))],
        };
    }

    public static function grupoSunat(?string $estado): string
    {
        return match ($estado) {
            'ACEPTADO', 'OBSERVADO', 'ANULADO' => 'aceptados',
            'EN RESUMEN', 'GENERADO', 'ENVIADO', 'EN PROCESO' => 'proceso',
            'RECHAZADO' => 'rechazados',
            default => 'pendientes',
        };
    }

    /**
     * Comprobantes de una empresa en el periodo. Los anulados no se cuentan en los tipos (van aparte).
     *
     * @return array{ok: bool, tipos: array<string, array{n: int, monto: float}>, sunat: array<string, int>, anulados: int, dias: array<string, int>}
     */
    public static function deCliente(Cliente $cliente, string $desde, string $hasta): array
    {
        $vacio = ['ok' => false, 'tipos' => [], 'sunat' => [], 'anulados' => 0, 'dias' => []];
        // Un periodo ya cerrado casi no cambia: se guarda más tiempo
        $segundos = $hasta < now()->toDateString() ? 6 * 3600 : 600;

        return Cache::store('file')->remember("panel-tablero-{$cliente->id}-{$desde}-{$hasta}", $segundos, function () use ($cliente, $desde, $hasta, $vacio) {
            try {
                return Tenancy::en($cliente->base_datos, function () use ($desde, $hasta, $vacio) {
                    if (! Schema::hasTable('cpe_cabecera')) {
                        return $vacio;
                    }
                    $r = $vacio;
                    $r['ok'] = true;
                    $filas = DB::table('cpe_cabecera')->whereBetween('ccafem', [$desde, $hasta])
                        ->groupBy('tdocod', 'est_sunat', DB::raw('ccabaj IS NULL'))
                        ->selectRaw("tdocod, est_sunat, ccabaj IS NULL AS vigente, COUNT(*) AS n, SUM(CASE WHEN moncod = 'PEN' THEN ccaitv ELSE 0 END) AS monto")
                        ->get();
                    foreach ($filas as $f) {
                        if (! $f->vigente) {
                            $r['anulados'] += (int) $f->n;

                            continue;
                        }
                        $r['tipos'][$f->tdocod]['n'] = ($r['tipos'][$f->tdocod]['n'] ?? 0) + (int) $f->n;
                        $r['tipos'][$f->tdocod]['monto'] = ($r['tipos'][$f->tdocod]['monto'] ?? 0) + (float) $f->monto;
                        if ($f->tdocod !== '13') {
                            $g = self::grupoSunat($f->est_sunat);
                            $r['sunat'][$g] = ($r['sunat'][$g] ?? 0) + (int) $f->n;
                        }
                    }
                    $r['dias'] = DB::table('cpe_cabecera')->whereBetween('ccafem', [$desde, $hasta])->whereNull('ccabaj')
                        ->groupBy('ccafem')->selectRaw('ccafem AS d, COUNT(*) AS n')->pluck('n', 'd')->map(fn ($n) => (int) $n)->all();

                    if (Schema::hasTable('gre_cabecera')) {
                        $guias = DB::table('gre_cabecera')->whereBetween('fecha_emision', [$desde, $hasta])
                            ->groupBy('est_sunat')->selectRaw('est_sunat, COUNT(*) AS n')->pluck('n', 'est_sunat');
                        foreach ($guias as $estado => $n) {
                            $r['tipos']['09']['n'] = ($r['tipos']['09']['n'] ?? 0) + (int) $n;
                            $r['tipos']['09']['monto'] = 0;
                            $g = self::grupoSunat($estado);
                            $r['sunat'][$g] = ($r['sunat'][$g] ?? 0) + (int) $n;
                        }
                    }

                    return $r;
                });
            } catch (\Throwable $e) {
                report($e);

                return $vacio;
            }
        });
    }

    /**
     * Suma de todas las empresas: totales por tipo, estados SUNAT, gráfico por día (o por mes si el periodo es largo)
     * y la lista de empresas ordenada por cantidad de comprobantes.
     *
     * @param  iterable<int, Cliente>  $clientes
     * @return array<string, mixed>
     */
    public static function resumen(iterable $clientes, string $desde, string $hasta): array
    {
        $tipos = array_fill_keys(array_keys(self::TIPOS), ['n' => 0, 'monto' => 0.0]);
        $sunat = array_fill_keys(array_keys(self::GRUPOS_SUNAT), 0);
        $porMes = Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) > 62;
        $serie = [];
        $cursor = Carbon::parse($desde);
        while ($cursor->toDateString() <= $hasta) {
            $serie[$porMes ? $cursor->format('Y-m') : $cursor->toDateString()] = 0;
            $porMes ? $cursor->addMonthNoOverflow()->startOfMonth() : $cursor->addDay();
        }
        $empresas = [];
        $anulados = 0;
        $sinLeer = 0;

        foreach ($clientes as $cl) {
            $d = self::deCliente($cl, $desde, $hasta);
            if (! $d['ok']) {
                $sinLeer++;

                continue;
            }
            foreach ($d['tipos'] as $t => $v) {
                $tipos[$t] ??= ['n' => 0, 'monto' => 0.0];
                $tipos[$t]['n'] += $v['n'];
                $tipos[$t]['monto'] += $v['monto'];
            }
            foreach ($d['sunat'] as $g => $n) {
                $sunat[$g] += $n;
            }
            foreach ($d['dias'] as $dia => $n) {
                $clave = $porMes ? substr($dia, 0, 7) : $dia;
                if (isset($serie[$clave])) {
                    $serie[$clave] += $n;
                }
            }
            $anulados += $d['anulados'];
            $total = array_sum(array_column($d['tipos'], 'n'));
            if ($total || $d['anulados']) {
                $empresas[] = [
                    'cliente' => $cl, 'total' => $total, 'tipos' => array_map(fn ($v) => $v['n'], $d['tipos']),
                    'monto' => ($d['tipos']['01']['monto'] ?? 0) + ($d['tipos']['03']['monto'] ?? 0),
                    // Los que van a SUNAT (facturas, boletas, notas y guías vigentes): lo que cobraría un PSE
                    'validos' => array_sum($d['sunat']),
                    'pendientes' => ($d['sunat']['pendientes'] ?? 0) + ($d['sunat']['rechazados'] ?? 0),
                ];
            }
        }
        usort($empresas, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'tipos' => $tipos,
            'total' => array_sum(array_column($tipos, 'n')),
            'electronicos' => array_sum($sunat),
            // Ventas con comprobante SUNAT (facturas y boletas) y aparte las notas de venta
            'vendido' => $tipos['01']['monto'] + $tipos['03']['monto'],
            'sunat' => $sunat,
            'anulados' => $anulados,
            'serie' => $serie,
            'porMes' => $porMes,
            'empresas' => $empresas,
            'sinLeer' => $sinLeer,
        ];
    }
}
