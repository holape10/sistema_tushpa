<?php

namespace App\Support;

use App\Models\EmpresaNegocio;
use App\Models\Turno;
use App\Models\User;
use App\Support\Sunat\EnvioAutomatico;
use Illuminate\Support\Facades\DB;

/**
 * Notas de crédito (07) y débito (08) electrónicas sobre una factura o boleta.
 *
 * Nota de crédito:
 *  - 01 Anulación de la operación, 02 Anulación por error en el RUC, 06 Devolución total:
 *    copia todo el comprobante, devuelve el stock al kardex, anula la cuenta por cobrar y marca el comprobante como anulado.
 *  - 07 Devolución por ítem: los productos y cantidades elegidos; devuelve ese stock.
 *  - 04 Descuento global, 05 Descuento por ítem, 09 Disminución en el valor: solo importes, no mueve stock.
 * Nota de débito (01 Intereses por mora, 02 Aumento en el valor, 03 Penalidades): líneas libres que suman al comprobante.
 *
 * En los reportes de ventas la nota de crédito RESTA y la de débito SUMA (ver Notas::SIGNO_SQL).
 */
class Notas
{
    public const MOTIVOS_TOTALES = ['01', '02', '06'];

    public const MOTIVOS_CON_STOCK = ['01', '02', '06', '07'];

    public const MOTIVOS_NC = ['01', '02', '04', '05', '06', '07', '09'];

    public const MOTIVOS_ND = ['01', '02', '03'];

    /** Serie y correlativo en empresa_negocios según nota y documento que modifica (F = factura, B = boleta) */
    public const SERIES = [
        '07F' => ['SerNCF', 'NumNCF'], '07B' => ['SerNCB', 'NumNCB'],
        '08F' => ['SerNDF', 'NumNDF'], '08B' => ['SerNDB', 'NumNDB'],
    ];

    /** Importe con signo para sumar ventas: las notas de crédito restan */
    public const SIGNO_SQL = "CASE WHEN c.tdocod = '07' THEN -1 ELSE 1 END";

    /** Comprobantes que admiten nota: facturas y boletas aceptadas por SUNAT y no anuladas */
    public static function validarReferencia(?object $ref): void
    {
        if (! $ref) {
            throw new \RuntimeException('El comprobante no existe.');
        }
        if (! in_array($ref->tdocod, ['01', '03'], true)) {
            throw new \RuntimeException('Solo se emiten notas sobre facturas y boletas electrónicas. Las notas de venta se anulan desde el Panel de ventas.');
        }
        if ($ref->ccabaj || $ref->anulado_nc) {
            throw new \RuntimeException('El comprobante ya está anulado'.($ref->anulado_nc ? " con la nota {$ref->anulado_nc}" : '').'.');
        }
        if (! in_array($ref->est_sunat, ['ACEPTADO', 'OBSERVADO'], true)) {
            throw new \RuntimeException("El comprobante {$ref->serdoc}-{$ref->numdoc} está ".($ref->est_sunat ?: 'PENDIENTE')
                .' en SUNAT. Primero envíalo y espera que sea ACEPTADO (las boletas van en el resumen diario).');
        }
        if (! empty($ref->res_id_baja)) {
            throw new \RuntimeException('El comprobante tiene una comunicación de baja en proceso. Consulta su ticket en Resumen diario.');
        }
    }

    /** Lo que ya se rebajó con notas de crédito anteriores: total y cantidad devuelta por línea del comprobante */
    public static function usado(int $refId): array
    {
        $notas = DB::table('cpe_cabecera')->where('IdCpe_cabecera_ref', $refId)->where('tdocod', '07')->whereNull('ccabaj')->pluck('IdCpe_cabecera');
        // Solo las devoluciones de productos cuentan como cantidad devuelta (las rebajas de precio no)
        $porLinea = DB::table('cpe_detalle as d')->join('cpe_cabecera as n', 'n.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
            ->whereIn('d.IdCpe_cabecera', $notas)->whereNotNull('d.IdCpe_detalle_ref')->whereIn('n.tipnot', self::MOTIVOS_CON_STOCK)
            ->groupBy('d.IdCpe_detalle_ref')->select('d.IdCpe_detalle_ref', DB::raw('SUM(d.cdecan) as devuelto'))
            ->pluck('devuelto', 'IdCpe_detalle_ref');

        return [
            'total' => (float) DB::table('cpe_cabecera')->whereIn('IdCpe_cabecera', $notas)->sum('ccaitv'),
            'cantidades' => $porLinea->map(fn ($v) => (float) $v)->all(),
            'notas' => $notas->count(),
        ];
    }

    /**
     * @param  array  $d  tdocod (07|08), tipnot, motivo (texto), items: [['IdCpe_detalle' => ?int, 'descripcion' => ?string, 'cantidad', 'precio']]
     * @return int IdCpe_cabecera de la nota
     */
    public static function emitir(User $user, int $refId, array $d): int
    {
        return DB::transaction(function () use ($user, $refId, $d) {
            $ref = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $refId)
                ->where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first();
            self::validarReferencia($ref);
            $factor = Comprobante::factorDe($ref);   // la nota lleva el mismo IGV que el comprobante

            $tdocod = $d['tdocod'] === '08' ? '08' : '07';
            $motivo = (string) $d['tipnot'];
            if (! in_array($motivo, $tdocod === '07' ? self::MOTIVOS_NC : self::MOTIVOS_ND, true)) {
                throw new \RuntimeException('Motivo de nota no válido.');
            }

            $detRef = DB::table('cpe_detalle')->where('IdCpe_cabecera', $refId)->orderBy('IdCpe_detalle')->get()->keyBy('IdCpe_detalle');
            $usado = self::usado($refId);
            $total = $tdocod === '07' && in_array($motivo, self::MOTIVOS_TOTALES, true);
            $tigPred = (float) $ref->ccatvg > 0 ? '10' : ((float) $ref->ccatinaf > 0 && (float) $ref->ccatexo == 0 ? '30' : '20');

            // ---- Líneas de la nota ----
            $lineas = [];
            if ($total) {
                if ($usado['notas']) {
                    throw new \RuntimeException('El comprobante ya tiene notas de crédito; ya no se puede anular por completo. Usa devolución por ítem o disminución en el valor.');
                }
                foreach ($detRef as $l) {
                    $lineas[] = self::linea($l->cdedes, (float) $l->cdecan, (float) $l->cdepuni, $l->tigcod ?: $tigPred, $l, true, $factor);
                }
            } else {
                foreach ($d['items'] ?? [] as $i) {
                    $cant = round((float) ($i['cantidad'] ?? 0), 2);
                    $precio = round((float) ($i['precio'] ?? 0), 2);
                    if ($cant <= 0 || $precio <= 0) {
                        continue;
                    }
                    $l = ! empty($i['IdCpe_detalle']) ? ($detRef[$i['IdCpe_detalle']] ?? null) : null;
                    if (! empty($i['IdCpe_detalle']) && ! $l) {
                        throw new \RuntimeException('Una línea no pertenece al comprobante.');
                    }

                    if ($tdocod === '07' && $motivo === '07') {
                        // Devolución por ítem: mismo precio, sin pasar lo vendido menos lo ya devuelto
                        if (! $l) {
                            throw new \RuntimeException('En la devolución por ítem elige productos del comprobante.');
                        }
                        $queda = round((float) $l->cdecan - ($usado['cantidades'][$l->IdCpe_detalle] ?? 0), 2);
                        if ($cant > $queda + 0.001) {
                            throw new \RuntimeException("De {$l->cdedes} solo quedan {$queda} por devolver.");
                        }
                        $lineas[] = self::linea($l->cdedes, $cant, (float) $l->cdepuni, $l->tigcod ?: $tigPred, $l, true, $factor);
                    } else {
                        $desc = mb_strtoupper(trim((string) ($i['descripcion'] ?? ''))) ?: ($l->cdedes ?? '');
                        if ($desc === '') {
                            throw new \RuntimeException('Escribe la descripción de cada línea.');
                        }
                        $lineas[] = self::linea($desc, $cant, $precio, $l->tigcod ?? $tigPred, $l, false, $factor);
                    }
                }
            }
            if (! $lineas) {
                throw new \RuntimeException('Agrega al menos una línea con cantidad e importe.');
            }

            $tot = [
                'grav' => round(array_sum(array_map(fn ($l) => $l['tigcod'] === '10' ? $l['cdepve'] : 0, $lineas)), 2),
                'exo' => round(array_sum(array_map(fn ($l) => $l['tigcod'] === '20' ? $l['cdepve'] : 0, $lineas)), 2),
                'inaf' => round(array_sum(array_map(fn ($l) => $l['tigcod'] === '30' ? $l['cdepve'] : 0, $lineas)), 2),
                'igv' => round(array_sum(array_column($lineas, 'cdeigv')), 2),
                'total' => round(array_sum(array_column($lineas, 'cdevve')), 2),
            ];
            if ($tdocod === '07' && $tot['total'] > round((float) $ref->ccaitv - $usado['total'], 2) + 0.01) {
                throw new \RuntimeException('La nota (S/ '.number_format($tot['total'], 2).') supera el saldo del comprobante (S/ '
                    .number_format((float) $ref->ccaitv - $usado['total'], 2).').');
            }

            // ---- Serie y correlativo propios (bloqueado para no repetir número) ----
            $letra = $ref->serdoc[0] === 'F' ? 'F' : 'B';
            [$colSerie, $colNum] = self::SERIES[$tdocod.$letra];
            $sucursal = EmpresaNegocio::where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first();
            $serie = strtoupper((string) $sucursal->$colSerie);
            if (! preg_match('/^'.$letra.'[A-Z0-9]{3}$/', $serie)) {
                throw new \RuntimeException("La serie {$serie} no es válida para notas de ".($letra === 'F' ? 'facturas' : 'boletas')
                    ." (debe empezar con {$letra}). Corrígela en Sucursales.");
            }
            $numero = (int) $sucursal->$colNum + 1;
            $sucursal->$colNum = $numero;
            $sucursal->save();

            $notaId = DB::table('cpe_cabecera')->insertGetId([
                'tdocod' => $tdocod, 'serdoc' => $serie, 'numdoc' => $numero, 'topcod' => '0101',
                'ccafem' => now()->toDateString(), 'ccafve' => now()->toDateString(), 'fecha_hora' => now(),
                'tdicod' => $ref->tdicod, 'ccandi' => $ref->ccandi, 'ccanom' => $ref->ccanom, 'direccion' => $ref->direccion,
                'clicod' => $ref->clicod, 'moncod' => $ref->moncod ?: 'PEN',
                'ccatvg' => $tot['grav'], 'ccaigv' => $tot['igv'], 'por_igv' => $tot['igv'] > 0 ? round(($factor - 1) * 100, 2) : null, 'ccatexo' => $tot['exo'], 'ccatinaf' => $tot['inaf'], 'ccaitv' => $tot['total'],
                'totalcontado' => 0, 'totalcredito' => 0, 'estadopago' => 'CONTADO',
                'ccaobs' => mb_substr(trim((string) ($d['motivo'] ?? '')), 0, 100) ?: null,
                'IdUsuario' => $user->IdUsuario, 'IdEmpresa' => $ref->IdEmpresa, 'id_empresa_negocio' => $ref->id_empresa_negocio,
                'id_almacen' => $ref->id_almacen,
                // Fuera del turno: la nota no altera el cuadre de caja (si se devuelve dinero, se registra como egreso de caja)
                'id_turno' => 0,
                'est_sunat' => 'PENDIENTE', 'enviado' => 0,
                'tipnot' => $motivo, 'tdocod_ref' => $ref->tdocod, 'serie_ref' => $ref->serdoc,
                'num_ref' => (string) $ref->numdoc, 'ccafem_ref' => $ref->ccafem, 'IdCpe_cabecera_ref' => $ref->IdCpe_cabecera,
            ]);

            foreach ($lineas as $l) {
                DB::table('cpe_detalle')->insert(collect($l)->except(['ref', 'stock'])->all() + ['IdCpe_cabecera' => $notaId]);
            }

            $numNota = $serie.'-'.str_pad((string) $numero, 8, '0', STR_PAD_LEFT);
            $glosa = 'NC '.$numNota.' de '.$ref->serdoc.'-'.$ref->numdoc;

            // ---- Stock: lo devuelto vuelve al almacén (al mismo lote del que salió) ----
            if ($tdocod === '07' && in_array($motivo, self::MOTIVOS_CON_STOCK, true)) {
                if ($total) {
                    Kardex::revertirVenta($ref->IdCpe_cabecera, ['cliente' => $ref->ccanom, 'descripcion' => $glosa,
                        'fecha_mov' => now()->toDateString(), 'tdocod' => '07', 'serie' => $serie, 'numero' => (string) $numero]);
                } else {
                    foreach ($lineas as $l) {
                        if ($l['stock'] && $l['ref']) {
                            self::devolverStock($ref, $l['ref'], (float) $l['cdecan'], $serie, $numero, $glosa);
                        }
                    }
                }
            }

            // ---- Anulación total: cuenta por cobrar y marca en el comprobante ----
            if ($total) {
                Cuentas::anularPorDocumento('cobrar', $ref->IdCpe_cabecera);
                DB::table('cpe_cabecera')->where('IdCpe_cabecera', $ref->IdCpe_cabecera)->update(['anulado_nc' => $numNota]);
                Socios::revertirComprobante((int) $ref->IdCpe_cabecera);   // cuotas de socio pagadas con él vuelven a deberse
            }

            EnvioAutomatico::programar($notaId);

            return $notaId;
        });
    }

    /** Línea de detalle con IGV según su afectación; $conStock = devolución de producto */
    private static function linea(string $desc, float $cant, float $precio, string $tig, ?object $ref, bool $conStock, float $factor): array
    {
        $totalLinea = round($cant * $precio, 2);
        $sub = $tig === '10' ? round($totalLinea / $factor, 2) : $totalLinea;

        return [
            'IdProducto' => $ref->IdProducto ?? null, 'IdProducto_rel' => $ref->IdProducto_rel ?? null,
            'procod' => $ref->procod ?? '', 'umecod' => $ref->umecod ?? 'NIU',
            'cdecan' => $cant, 'cdedes' => mb_substr($desc, 0, 150),
            'cdevun' => $tig === '10' ? round($precio / $factor, 2) : $precio, 'cdepuni' => $precio,
            'cdepve' => $sub, 'cdeigv' => round($totalLinea - $sub, 2), 'cdevve' => $totalLinea, 'tigcod' => $tig,
            // costo solo cuando vuelve el producto (para que la utilidad del reporte se corrija)
            'costo' => $conStock ? (float) ($ref->costo ?? 0) : 0,
            'cpe_det_factor' => 1, 'id_almacen_pro' => $ref->id_almacen_pro ?? null,
            'debe' => $ref->debe ?? null, 'haber' => $ref->haber ?? null,   // misma cuenta que la línea original (CONCAR la invierte)
            'IdCpe_detalle_ref' => $ref->IdCpe_detalle ?? null, 'lotes' => $conStock ? ($ref->lotes ?? null) : null,
            'ref' => $ref, 'stock' => $conStock,
        ];
    }

    /** Devolución parcial: reingresa la cantidad usando las salidas del kardex de esa venta (respeta combos y lotes) */
    private static function devolverStock(object $cab, object $det, float $cantidad, string $serie, int $numero, string $glosa): void
    {
        if (! $det->IdProducto) {
            return;
        }
        $proporcion = (float) $det->cdecan > 0 ? $cantidad / (float) $det->cdecan : 0;
        $salidas = DB::table('movimientos_productos')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->where('mov_tip', 'E')
            ->where(fn ($w) => $w->where('IdProducto', $det->IdProducto)->orWhere('IdProducto_rel', $det->IdProducto))
            ->orderBy('mov_pro_id')->get();

        // Un producto simple puede haber salido de varios lotes: se devuelve primero al último lote del que salió
        $simple = $salidas->where('IdProducto', $det->IdProducto)->whereNull('IdProducto_rel');
        $falta = $cantidad;
        foreach ($simple->reverse() as $s) {
            if ($falta <= 0) {
                break;
            }
            $toma = min($falta, (float) $s->cantidad);
            self::reingreso($s, $toma, $cab, $serie, $numero, $glosa);
            $falta = round($falta - $toma, 5);
        }
        // Componentes de un combo: en proporción a lo devuelto
        foreach ($salidas->where('IdProducto_rel', $det->IdProducto) as $s) {
            self::reingreso($s, round((float) $s->cantidad * $proporcion, 5), $cab, $serie, $numero, $glosa);
        }
    }

    private static function reingreso(object $s, float $cantidad, object $cab, string $serie, int $numero, string $glosa): void
    {
        Kardex::registrar((int) $s->IdProducto, (int) $s->id_almacen, $cantidad, 'I', [
            'cod_tip_ope' => '05', 'IdCpe_cabecera' => $cab->IdCpe_cabecera, 'IdProducto_rel' => $s->IdProducto_rel,
            'costo' => $s->costo, 'precio' => $s->precio, 'tdocod' => '07', 'serie' => $serie, 'numero' => (string) $numero,
            'cliente' => $cab->ccanom, 'descripcion' => $glosa, 'fecha_mov' => now()->toDateString(),
            'lote' => $s->mov_lote, 'vencimiento' => $s->mov_vencimiento, 'id_empresa_negocio' => $s->id_empresa_negocio,
        ]);
    }
}
