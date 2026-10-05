<?php
namespace App\Support\Sunat;

use Illuminate\Support\Facades\DB;

/**
 * Compara la propuesta del SIRE (lo que SUNAT tiene registrado) con lo registrado en el sistema
 * para un periodo: coinciden, total distinto, solo en SUNAT y solo en el sistema.
 * Las columnas del .txt se ubican por su título (no por posición) para tolerar cambios de formato de SUNAT.
 */
class SireCuadre
{
    // Comprobantes que SUNAT conoce: los electrónicos. Notas de venta y tickets internos no van al SIRE.
    private const TIPOS_VENTAS = ['01', '03', '07', '08'];
    private const TIPOS_COMPRAS = ['01', '03', '07', '08'];

    public static function comparar(string $libro, string $periodo, array $propuesta, string $idEmpresa): array
    {
        $sunat = self::desdePropuesta($propuesta);
        $sistema = $libro === Sire::VENTAS
            ? self::ventasSistema($periodo, $idEmpresa)
            : self::comprasSistema($periodo, $idEmpresa);

        $coinciden = $diferencias = $soloSunat = $soloSistema = [];
        foreach ($sunat as $clave => $s) {
            $p = $sistema[$clave] ?? null;
            if (!$p) {
                $soloSunat[] = $s;
            } elseif (abs($s['total'] - $p['total']) > 0.01) {
                $diferencias[] = ['sunat' => $s, 'sistema' => $p, 'dif' => round($s['total'] - $p['total'], 2)];
            } else {
                $coinciden[] = $s;
            }
        }
        foreach ($sistema as $clave => $p) {
            if (!isset($sunat[$clave])) {
                $soloSistema[] = $p;
            }
        }

        return [
            'coinciden' => $coinciden, 'diferencias' => $diferencias, 'soloSunat' => $soloSunat, 'soloSistema' => $soloSistema,
            'totalSunat' => round(array_sum(array_column($sunat, 'total')), 2),
            'totalSistema' => round(array_sum(array_column($sistema, 'total')), 2),
            'cantSunat' => count($sunat), 'cantSistema' => count($sistema),
            'columnasOk' => self::columnas($propuesta['cabecera'])['numero'] !== null,
        ];
    }

    /** Filas de la propuesta indexadas por tipo|serie|número */
    public static function desdePropuesta(array $propuesta): array
    {
        $c = self::columnas($propuesta['cabecera']);
        if ($c['tipo'] === null || $c['serie'] === null || $c['numero'] === null) {
            return [];
        }

        $res = [];
        foreach ($propuesta['filas'] as $f) {
            $tipo = str_pad(trim($f[$c['tipo']] ?? ''), 2, '0', STR_PAD_LEFT);
            $serie = strtoupper(trim($f[$c['serie']] ?? ''));
            $numero = ltrim(trim($f[$c['numero']] ?? ''), '0') ?: '0';
            if ($serie === '') {
                continue;
            }
            $fila = [
                'tipo' => $tipo, 'serie' => $serie, 'numero' => $numero,
                'fecha' => $c['fecha'] !== null ? ($f[$c['fecha']] ?? '') : '',
                'doc' => $c['doc'] !== null ? ($f[$c['doc']] ?? '') : '',
                'nombre' => $c['nombre'] !== null ? ($f[$c['nombre']] ?? '') : '',
                'total' => $c['total'] !== null ? self::numero($f[$c['total']] ?? 0) : 0,
                'car' => $c['car'] !== null ? ($f[$c['car']] ?? '') : '',
            ];
            $res["{$tipo}|{$serie}|{$numero}"] = $fila;
        }
        return $res;
    }

    /** Índices de las columnas de importes (BI, IGV, totales, valores, tipo de cambio…) para exportarlas como número */
    public static function columnasMonto(array $cabecera): array
    {
        $montos = [];
        foreach ($cabecera as $i => $h) {
            $h = ' ' . self::normalizar($h) . ' ';
            $esMonto = preg_match('/ (bi|igv|dscto|mto|total|valor|isc|ivap|icbper|monto|imp) |otros trib|tipo (de )?cambio/', $h);
            $esCodigo = preg_match('/ (nro|serie|fecha|car|ruc|periodo|moneda|doc) |tipo cp/', $h);
            if ($esMonto && !$esCodigo) {
                $montos[] = $i;
            }
        }
        return $montos;
    }

    private static function normalizar(string $h): string
    {
        $h = mb_strtolower(trim(preg_replace('/^\x{FEFF}/u', '', $h)));
        $h = strtr($h, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]/', ' ', $h)));
    }

    /** Ubica cada columna por palabras de su título (RVIE y RCE usan títulos parecidos) */
    public static function columnas(array $cabecera): array
    {
        $norm = array_map(function ($h) {
            $h = mb_strtolower(trim($h));
            $h = strtr($h, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
            return preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]/', ' ', $h));
        }, $cabecera);

        $buscar = function (callable $cumple, int $ocurrencia = 0) use ($norm) {
            $vistos = 0;
            foreach ($norm as $i => $h) {
                if ($cumple($h)) {
                    if ($vistos === $ocurrencia) {
                        return $i;
                    }
                    $vistos++;
                }
            }
            return null;
        };
        $sinMod = fn(string $palabra) => fn($h) => str_contains($h, $palabra) && !str_contains($h, 'modificado');

        // El 1er "razón social" es el contribuyente; el 2do, el cliente (ventas) o proveedor (compras)
        $nombre = $buscar(fn($h) => str_contains($h, 'razon social'), 1) ?? $buscar(fn($h) => str_contains($h, 'razon social'));

        return [
            'tipo'   => $buscar($sinMod('tipo cp')),
            'serie'  => $buscar($sinMod('serie')),
            'numero' => $buscar(fn($h) => (str_contains($h, 'nro cp') || str_contains($h, 'nro inicial')) && !str_contains($h, 'modificado')),
            'fecha'  => $buscar(fn($h) => str_starts_with($h, 'fecha de emision') || str_starts_with($h, 'fecha emision')),
            'doc'    => $buscar(fn($h) => str_contains($h, 'nro doc identidad')),
            'nombre' => $nombre,
            'total'  => $buscar(fn($h) => str_contains($h, 'total cp')),
            'car'    => $buscar(fn($h) => str_contains($h, 'car sunat')),
        ];
    }

    private static function ventasSistema(string $periodo, string $idEmpresa): array
    {
        [$desde, $hasta] = self::rango($periodo);
        $res = [];
        DB::table('cpe_cabecera')->where('IdEmpresa', $idEmpresa)
            ->whereIn('tdocod', self::TIPOS_VENTAS)->whereBetween('ccafem', [$desde, $hasta])
            ->whereNull('ccabaj')
            ->orderBy('ccafem')
            ->get(['tdocod', 'serdoc', 'numdoc', 'ccafem', 'ccandi', 'ccanom', 'ccaitv', 'est_sunat'])
            ->each(function ($c) use (&$res) {
                $numero = ltrim((string) $c->numdoc, '0') ?: '0';
                $res["{$c->tdocod}|" . strtoupper($c->serdoc) . "|{$numero}"] = [
                    'tipo' => $c->tdocod, 'serie' => strtoupper($c->serdoc), 'numero' => $numero,
                    'fecha' => date('d/m/Y', strtotime($c->ccafem)), 'doc' => $c->ccandi, 'nombre' => $c->ccanom,
                    'total' => self::conSigno($c->tdocod, $c->ccaitv), 'est_sunat' => $c->est_sunat,
                ];
            });
        return $res;
    }

    private static function comprasSistema(string $periodo, string $idEmpresa): array
    {
        [$desde, $hasta] = self::rango($periodo);
        $res = [];
        DB::table('compras_cabecera as c')->leftJoin('proveedor as p', 'p.prov_id', '=', 'c.prov_id')
            ->where('c.IdEmpresa', $idEmpresa)->where('c.est_compra', 'Registrado')
            ->whereIn('c.tdocod', self::TIPOS_COMPRAS)->whereBetween('c.com_fec', [$desde, $hasta])
            ->orderBy('c.com_fec')
            ->get(['c.tdocod', 'c.com_doc_ser', 'c.com_doc_num', 'c.com_fec', 'c.total_com', 'c.mon_id', 'c.tip_cam', 'p.prov_ruc', 'p.prov_raz'])
            ->each(function ($c) use (&$res) {
                $numero = ltrim((string) $c->com_doc_num, '0') ?: '0';
                $res["{$c->tdocod}|" . strtoupper($c->com_doc_ser) . "|{$numero}"] = [
                    'tipo' => $c->tdocod, 'serie' => strtoupper($c->com_doc_ser), 'numero' => $numero,
                    'fecha' => date('d/m/Y', strtotime($c->com_fec)), 'doc' => $c->prov_ruc, 'nombre' => $c->prov_raz,
                    // La propuesta trae el total en la moneda del comprobante
                    'total' => self::conSigno($c->tdocod, $c->total_com),
                ];
            });
        // Gastos (servicios, alquiler, honorarios…) también son comprobantes de compra para SUNAT
        DB::table('gastos')->where('IdEmpresa', $idEmpresa)->where('estado', 'Registrado')
            ->whereIn('tdocod', self::TIPOS_COMPRAS)->whereBetween('fecha', [$desde, $hasta])->whereNotNull('serie')
            ->get(['tdocod', 'serie', 'numero', 'fecha', 'total', 'prov_doc', 'prov_nombre'])
            ->each(function ($g) use (&$res) {
                $numero = ltrim((string) $g->numero, '0') ?: '0';
                $res["{$g->tdocod}|" . strtoupper($g->serie) . "|{$numero}"] = [
                    'tipo' => $g->tdocod, 'serie' => strtoupper($g->serie), 'numero' => $numero,
                    'fecha' => date('d/m/Y', strtotime($g->fecha)), 'doc' => $g->prov_doc, 'nombre' => $g->prov_nombre,
                    'total' => self::conSigno($g->tdocod, $g->total),
                ];
            });
        return $res;
    }

    /** SUNAT muestra las notas de crédito en negativo; el sistema las guarda en positivo */
    private static function conSigno(string $tipo, $total): float
    {
        return $tipo === '07' ? -abs((float) $total) : (float) $total;
    }

    private static function rango(string $periodo): array
    {
        $desde = substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01';
        return [$desde, date('Y-m-t', strtotime($desde))];
    }

    private static function numero($v): float
    {
        return round((float) str_replace(',', '', (string) $v), 2);
    }
}
