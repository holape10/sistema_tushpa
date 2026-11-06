<?php

namespace App\Support\Contabilidad;

use Carbon\Carbon;

/**
 * Pasa la propuesta del SIRE (ya revisada en "Contabilidad (DBF)": cuentas, montos y los que no se llevan) al Registro
 * de Ventas o de Compras de Contabilidad, con su asiento, para no digitar todo de nuevo. Lo que ya estaba registrado
 * no se repite; los anulados no se llevan.
 */
class SireARegistro
{
    /**
     * @param  array<int, array>  $comprobantes  de SireDbf::delPeriodo
     * @return array{nuevos: int, repetidos: int, cuentaDefecto: int, errores: array<int, string>}
     */
    public static function importar(string $ruc, string $libro, string $periodo, array $comprobantes): array
    {
        Contabilidad::asegurar($ruc);
        $cfg = Contabilidad::config($ruc);
        $imputables = Contabilidad::imputables($ruc);
        $venta = $libro === 'ventas';
        $inicio = Carbon::createFromFormat('!Ym', $periodo)->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();
        $r = ['nuevos' => 0, 'repetidos' => 0, 'cuentaDefecto' => 0, 'errores' => []];

        foreach ($comprobantes as $c) {
            if ($c['excluido'] || $c['anulado']) {
                continue;
            }
            $m = array_map(fn ($v) => abs((float) $v), $c['montos']);
            $montos = $venta
                ? ['base' => $m['gravada'], 'igv' => $m['igv'], 'isc' => $m['isc'], 'icbper' => $m['icbper'],
                    'no_gravado' => round($m['exonerada'] + $m['inafecta'] + $m['exporta'], 2), 'otros' => $m['otros']]
                : ['base' => round($m['base_dg'] + $m['base_dgng'] + $m['base_dng'], 2), 'igv' => round($m['igv_dg'] + $m['igv_dgng'] + $m['igv_dng'], 2),
                    'isc' => $m['isc'], 'icbper' => 0, 'no_gravado' => $m['no_gravado'], 'otros' => $m['otros']];

            // La cuenta revisada (abono en ventas, cargo en compras); si no está en el plan contable, la de la configuración
            $cuenta = (string) ($venta ? $c['ctaabono'] : $c['ctacargo']);
            if (! isset($imputables[$cuenta])) {
                $cuenta = $venta ? ($montos['base'] > 0 ? $cfg->cta_ventas : $cfg->cta_ventas_exo) : $cfg->cta_compras;
                $r['cuentaDefecto']++;
            }
            $fecha = Carbon::parse($c['fecha'] ?? $inicio);
            $d = $montos + [
                'tipo' => $venta ? 'VENTA' : 'COMPRA', 'fecha' => $fecha->toDateString(), 'fecha_vence' => $c['vence'] ?: null,
                // Se anota en el mes del SIRE aunque el comprobante sea de antes (o de después, si SUNAT lo trajo así)
                'fecha_registro' => $fecha->between($inicio, $fin) ? $fecha->toDateString() : ($fecha->lt($inicio) ? $inicio : $fin)->toDateString(),
                'tdocod' => $c['tipo'], 'serie' => $c['serie'], 'numero' => $c['numero'],
                'doc_tipo' => match (strlen((string) $c['ruc'])) {
                    11 => '6', 8 => '1', default => '0'
                }, 'doc_num' => $c['ruc'] ?: null,
                'nombre' => mb_substr($c['nombre'] ?: 'SIN NOMBRE', 0, 200),
                'moneda' => $c['moneda'] === 'USD' ? 'USD' : 'PEN', 'tipo_cambio' => $c['moneda'] === 'USD' ? (float) $c['tc'] : 1,
                'retencion' => 0, 'cuenta' => $cuenta, 'condicion' => 'CREDITO', 'cuenta_pago' => null, 'glosa' => null,
            ];
            $d['total'] = Registro::total($d);
            $documento = "{$c['serie']}-{$c['numero']}";
            if ($d['total'] <= 0) {
                $r['errores'][] = "{$documento}: el total es cero.";

                continue;
            }
            try {
                Registro::guardar($ruc, $d);
                $r['nuevos']++;
            } catch (\RuntimeException $e) {
                if (str_contains($e->getMessage(), 'ya está registrado')) {
                    $r['repetidos']++;

                    continue;
                }
                $r['errores'][] = "{$documento}: ".$e->getMessage();
                // Un periodo cerrado o una cuenta del sistema que falta fallarán igual en todos: se detiene
                if (str_contains($e->getMessage(), 'CERRADO') || str_contains($e->getMessage(), 'plan contable')) {
                    break;
                }
            }
        }

        return $r;
    }
}
