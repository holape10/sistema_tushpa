<?php

namespace App\Support\Contabilidad;

use App\Support\Concar;
use App\Support\ConsultaPeru;
use Illuminate\Support\Facades\DB;

/**
 * Otros › Contabilidad DBF: el tienfact.dbf de ventas (la misma estructura que sale del SIRE) armado con los comprobantes
 * del propio sistema (cpe_cabecera), sin esperar la propuesta de SUNAT. Lleva facturas, boletas y sus notas de todas las
 * sucursales del mes; las rechazadas por SUNAT no van y las anuladas van marcadas como anuladas y en cero (como en el
 * SIRE). Las notas de crédito van en negativo, igual que en la propuesta del SIRE. Las cuentas son las de la configuración
 * contable de la empresa (Concar).
 */
class VentasDbf
{
    /** Comprobantes que van al registro de ventas (la nota de venta no es un comprobante de SUNAT) */
    public const TIPOS = ['01', '03', '07', '08'];

    /**
     * Los comprobantes del periodo (AAAAMM) con la forma que usa SireDbf::tienfact.
     *
     * @return list<array<string, mixed>>
     */
    public static function comprobantes(string $ruc, string $periodo): array
    {
        $desde = substr($periodo, 0, 4).'-'.substr($periodo, 4, 2).'-01';
        $hasta = date('Y-m-t', strtotime($desde));
        $cfg = Concar::config($ruc);
        $cambios = [];

        return DB::table('cpe_cabecera')->where('IdEmpresa', $ruc)->whereIn('tdocod', self::TIPOS)->whereBetween('ccafem', [$desde, $hasta])
            ->where(fn ($q) => $q->whereNull('est_sunat')->orWhereRaw("UPPER(TRIM(est_sunat)) NOT REGEXP '^RECHAZAD'"))
            ->orderBy('ccafem')->orderBy('tdocod')->orderBy('serdoc')->orderBy('numdoc')->get()
            ->map(function ($c) use ($cfg, &$cambios) {
                $anulado = $c->ccabaj !== null && $c->ccabaj !== '';
                // La nota de crédito resta: en negativo, como la trae el SIRE
                $signo = $c->tdocod === '07' ? -1 : 1;
                $m = ['gravada' => (float) $c->ccatvg, 'igv' => (float) $c->ccaigv, 'exonerada' => (float) $c->ccatexo, 'inafecta' => (float) $c->ccatinaf,
                    'exporta' => 0.0, 'isc' => 0.0, 'total' => (float) $c->ccaitv];
                // Lo que el total tiene de más sobre sus partes es el impuesto a las bolsas (ICBPER)
                $m['icbper'] = max(0.0, round($m['total'] - $m['gravada'] - $m['igv'] - $m['exonerada'] - $m['inafecta'], 2));
                $m['otros'] = 0.0;
                $m = array_map(fn ($v) => $anulado ? 0.0 : round($v * $signo, 2), $m);
                $moneda = strtoupper((string) ($c->moncod ?: 'PEN')) === 'USD' ? 'USD' : 'PEN';
                $tc = 1.0;
                if ($moneda === 'USD') {
                    $tc = $cambios[$c->ccafem] ??= (float) (ConsultaPeru::tipoCambio((string) $c->ccafem)['venta'] ?? 1) ?: 1.0;
                }

                return [
                    'tipo' => $c->tdocod, 'serie' => strtoupper((string) $c->serdoc), 'numero' => (string) (int) $c->numdoc,
                    'fecha' => (string) $c->ccafem, 'vence' => $c->ccafve ? (string) $c->ccafve : null,
                    'ruc' => trim((string) $c->ccandi), 'doc_tipo' => (string) $c->tdicod, 'nombre' => mb_substr(trim((string) $c->ccanom), 0, 120),
                    'moneda' => $moneda, 'tc' => $tc, 'anulado' => $anulado, 'excluido' => false, 'montos' => $m,
                    'ref' => in_array($c->tdocod, ['07', '08'], true) && $c->serie_ref
                        ? ['tipo' => (string) ($c->tdocod_ref ?: '01'), 'serie' => strtoupper((string) $c->serie_ref), 'numero' => (string) (int) $c->num_ref,
                            'fecha' => $c->ccafem_ref ? (string) $c->ccafem_ref : null]
                        : null,
                    'ctacargo' => $cfg['cta_por_cobrar'], 'ctaabono' => (float) $c->ccatvg != 0 ? $cfg['cta_ventas'] : $cfg['cta_ventas_exo'], 'ctacance' => null,
                    'pendiente' => ! $anulado && ! preg_match('/^(ACEPTAD|OBSERVAD)/i', trim((string) $c->est_sunat)),
                ];
            })->all();
    }

    /**
     * Totales para la pantalla: cuántos van, cuántos anulados, cuántos sin respuesta de SUNAT y los montos (en soles).
     *
     * @param  list<array<string, mixed>>  $comprobantes
     * @return array<string, int|float>
     */
    public static function totales(array $comprobantes): array
    {
        $soles = fn (array $c, string $k) => round($c['montos'][$k] * ($c['moneda'] === 'USD' ? $c['tc'] : 1), 2);
        $suma = fn (string $k) => round(array_sum(array_map(fn ($c) => $soles($c, $k), $comprobantes)), 2);

        return ['registros' => count($comprobantes), 'anulados' => count(array_filter($comprobantes, fn ($c) => $c['anulado'])),
            'pendientes' => count(array_filter($comprobantes, fn ($c) => $c['pendiente'])),
            'gravada' => $suma('gravada'), 'igv' => $suma('igv'), 'exonerada' => round($suma('exonerada') + $suma('inafecta'), 2),
            'icbper' => $suma('icbper'), 'total' => $suma('total')];
    }

    /** El tienfact.dbf del periodo (la misma estructura que el del SIRE) */
    public static function tienfact(string $ruc, string $periodo): string
    {
        return SireDbf::tienfact($ruc, $periodo, self::comprobantes($ruc, $periodo));
    }
}
