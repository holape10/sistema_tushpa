<?php

namespace App\Support\Contabilidad;

/**
 * Auditoría del SIRE de un mes: sumas por tipo de documento (facturas, boletas, notas…) de lo que se lleva al .dbf, de lo
 * que dice SUNAT y de lo que dicen los XML, todo en soles; y, comprobante por comprobante, por qué no cuadra.
 * Recibe los comprobantes de SireDbf::delPeriodo.
 */
class SireAuditoria
{
    /** Notas de crédito: restan (SUNAT las da en negativo; el XML, en positivo) */
    public const NOTAS_CREDITO = ['07', '87', '97'];

    /** Diferencias menores a esto se toman como redondeo */
    private const TOLERANCIA = 0.01;

    /**
     * @return array{filas: array<string, array<string, mixed>>, todos: array<string, mixed>, motivos: list<array<string, mixed>>, libro: string}
     */
    public static function calcular(string $libro, array $comprobantes): array
    {
        $filas = [];
        $motivos = [];
        foreach ($comprobantes as $c) {
            $tipo = $c['tipo'];
            $filas[$tipo] ??= self::vacia($tipo);
            $f = &$filas[$tipo];
            $f['cantidad']++;
            $dbf = self::enSoles($c, $c['montos'], false);
            $sunat = $c['manual'] ? null : self::enSoles($c, $c['montos_sunat'] ?? $c['montos'], true);
            $xml = self::xmlEnSoles($c);

            if (! $c['excluido']) {
                $f['llevan']++;
                foreach (self::partes($libro, $dbf) as $k => $v) {
                    $f[$k] = round($f[$k] + $v, 2);
                }
            } else {
                $f['excluidos']++;
            }
            if ($sunat !== null) {
                $f['sunat'] = round($f['sunat'] + $sunat['total'], 2);
            } else {
                $f['manuales']++;
            }
            if ($xml !== null) {
                $f['con_xml']++;
                $f['xml'] = round($f['xml'] + $xml, 2);
                $f['xml_de_llevan'] = round($f['xml_de_llevan'] + ($c['excluido'] ? 0 : $xml), 2);
                $f['dbf_con_xml'] = round($f['dbf_con_xml'] + ($c['excluido'] ? 0 : $dbf['total']), 2);
            }
            unset($f);

            foreach (self::porQue($libro, $c, $dbf, $sunat, $xml) as [$grave, $texto, $efecto]) {
                $motivos[] = ['clave' => $c['clave'], 'tipo' => $tipo, 'comprobante' => $c['serie'].'-'.$c['numero'], 'numero' => $c['numero'],
                    'nombre' => $c['nombre'], 'grave' => $grave, 'texto' => $texto, 'efecto' => $efecto];
            }
        }
        ksort($filas);
        foreach ($filas as &$f) {
            $f['dif_sunat'] = round($f['total'] - $f['sunat'], 2);
            $f['dif_xml'] = round($f['dbf_con_xml'] - $f['xml_de_llevan'], 2);
        }
        unset($f);
        $todos = self::vacia('todos');
        foreach ($filas as $f) {
            foreach ($todos as $k => $v) {
                if (is_numeric($v)) {
                    $todos[$k] = round($v + $f[$k], 2);
                }
            }
        }
        // Lo grave primero y, dentro, por tipo y número
        usort($motivos, fn ($a, $b) => [$b['grave'], $a['tipo'], (int) $a['numero']] <=> [$a['grave'], $b['tipo'], (int) $b['numero']]);

        return ['filas' => $filas, 'todos' => $todos, 'motivos' => $motivos, 'libro' => $libro];
    }

    private static function vacia(string $tipo): array
    {
        return ['tipo' => $tipo, 'cantidad' => 0, 'llevan' => 0, 'excluidos' => 0, 'manuales' => 0, 'con_xml' => 0,
            'base' => 0.0, 'igv' => 0.0, 'no_gravado' => 0.0, 'otros' => 0.0, 'total' => 0.0, 'sunat' => 0.0, 'xml' => 0.0,
            'xml_de_llevan' => 0.0, 'dbf_con_xml' => 0.0, 'dif_sunat' => 0.0, 'dif_xml' => 0.0];
    }

    /** Base, IGV, no gravado, otros y total de un comprobante */
    private static function partes(string $libro, array $m): array
    {
        $s = fn (string ...$k) => round(array_sum(array_map(fn ($x) => (float) ($m[$x] ?? 0), $k)), 2);

        return $libro === 'compras'
            ? ['base' => $s('base_dg', 'base_dgng', 'base_dng'), 'igv' => $s('igv_dg', 'igv_dgng', 'igv_dng'), 'no_gravado' => $s('no_gravado'),
                'otros' => $s('isc', 'otros'), 'total' => $s('total')]
            : ['base' => $s('gravada'), 'igv' => $s('igv'), 'no_gravado' => $s('exonerada', 'inafecta', 'exporta'),
                'otros' => $s('isc', 'icbper', 'otros'), 'total' => $s('total')];
    }

    /**
     * Montos en soles: los de dólares por su tipo de cambio. Los de SUNAT pueden venir ya en soles aunque el comprobante
     * sea en dólares (se sabe con su XML): esos no se multiplican.
     *
     * @return array<string, float>
     */
    private static function enSoles(array $c, array $montos, bool $deSunat): array
    {
        $tc = ($c['moneda'] ?? 'PEN') === 'USD' && (float) ($c['tc'] ?? 0) > 0 ? (float) $c['tc'] : 1.0;
        if ($tc !== 1.0 && $deSunat && SireDbf::montosEnSoles(['montos' => $montos, 'editado' => false] + $c)) {
            $tc = 1.0;
        }

        return array_map(fn ($v) => round((float) $v * $tc, 2), $montos);
    }

    /** Total del XML en soles y con el signo del libro (las notas de crédito restan). null si no hay XML. */
    private static function xmlEnSoles(array $c): ?float
    {
        if (($c['xml_total'] ?? null) === null) {
            return null;
        }
        $tc = ($c['xml_moneda'] ?? 'PEN') === 'USD' && (float) ($c['tc'] ?? 0) > 0 ? (float) $c['tc'] : 1.0;
        $signo = in_array($c['tipo'], self::NOTAS_CREDITO, true) ? -1 : 1;

        return round($signo * abs((float) $c['xml_total']) * $tc, 2);
    }

    /**
     * Por qué este comprobante hace que algo no cuadre (o merece revisarse).
     *
     * @return list<array{0: bool, 1: string, 2: float}> [es grave, explicación, cuánto mueve en soles]
     */
    private static function porQue(string $libro, array $c, array $dbf, ?array $sunat, ?float $xml): array
    {
        $soles = fn (float $n) => 'S/ '.number_format($n, 2);
        $res = [];
        $esNota = in_array($c['tipo'], self::NOTAS_CREDITO, true);
        if ($c['excluido']) {
            $res[] = [false, ! empty($c['yaManual']) ? 'No se lleva: ya se agregó a mano en otro mes.' : 'No se lleva al .dbf (desmarcado «Llevar»).', -($sunat['total'] ?? $dbf['total'])];

            return $res;
        }
        if ($c['manual']) {
            $res[] = [false, 'Agregado a mano: SUNAT no lo tiene en la propuesta de este mes.', $dbf['total']];
        }
        if ($sunat !== null && abs($dbf['total'] - $sunat['total']) >= self::TOLERANCIA) {
            $res[] = [true, 'El total que se lleva ('.$soles($dbf['total']).') no es el de SUNAT ('.$soles($sunat['total']).'): se cambiaron los montos a mano.', round($dbf['total'] - $sunat['total'], 2)];
        }
        if (abs((float) $c['diferencia']) >= self::TOLERANCIA) {
            $res[] = [true, 'Sus partes (base + IGV + no gravado + otros) no suman su total: falta o sobra '.number_format((float) $c['diferencia'], 2).'.', 0.0];
        }
        if (! empty($c['alerta'])) {
            $res[] = [true, $c['alerta'], 0.0];
        }
        if ($esNota && (float) $c['montos']['total'] > 0) {
            $res[] = [true, 'Nota de crédito en positivo: debería restar (en negativo, como la da SUNAT).', round(-2 * $dbf['total'], 2)];
        }
        if (! $esNota && $c['tipo'] !== '08' && (float) $c['montos']['total'] < 0) {
            $res[] = [true, 'Comprobante en negativo sin ser nota de crédito.', 0.0];
        }
        if (in_array($c['tipo'], ['07', '08', '87', '88'], true) && empty($c['ref'])) {
            $res[] = [true, 'Nota sin el comprobante que modifica (serie y número de referencia).', 0.0];
        }
        if ($libro === 'compras') {
            if ($xml === null) {
                $res[] = [false, 'Falta su XML: no se puede comparar con lo que dice el proveedor.', 0.0];
            } elseif (abs($dbf['total'] - $xml) >= self::TOLERANCIA) {
                $res[] = [true, 'Distinto a su XML: el XML dice '.$soles($xml).' y se lleva '.$soles($dbf['total']).'.', round($dbf['total'] - $xml, 2)];
            }
        }
        if (($c['de_soles'] ?? null) !== null) {
            $res[] = [false, 'En dólares: SUNAT lo trajo en soles (S/ '.number_format((float) $c['de_soles'], 2).') y se pasó a dólares como su XML.', 0.0];
        } elseif (($c['moneda'] ?? 'PEN') === 'USD') {
            $res[] = [false, 'En dólares (TC '.$c['tc'].'): en soles son '.$soles($dbf['total']).'.', 0.0];
        }

        return $res;
    }
}
