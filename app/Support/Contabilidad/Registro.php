<?php

namespace App\Support\Contabilidad;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Registro contable de ventas y compras (solo montos, como el CONCAR). Cada comprobante genera:
 *  - Venta:  12 por cobrar (D) → 40 IGV (H) + 70 ventas (H)                    subdiario 05
 *  - Compra: 60/63/65… (D) + 40 IGV (D) → 42 por pagar (H)                      subdiario 11
 *            y su destino: 20 (D) → 61 (H) si es compra de mercadería; 94 (D) → 79 (H) si es gasto
 *  - Contado: el cobro (10 → 12) o el pago (42 → 10)                            subdiario 01
 * Nota de crédito: al revés. En dólares todo se registra en soles con el tipo de cambio.
 * El asiento propuesto se puede cambiar antes de guardar (debe y haber a mano), siempre que cuadre.
 */
class Registro
{
    public const ORIGEN = ['VENTA' => 'REG. VENTAS', 'COMPRA' => 'REG. COMPRAS'];

    public const ORIGEN_PAGO = ['VENTA' => 'REG. COBROS', 'COMPRA' => 'REG. PAGOS'];

    public const DOCUMENTOS = [
        'VENTA' => ['01' => 'Factura', '03' => 'Boleta', '07' => 'Nota de crédito', '08' => 'Nota de débito', '12' => 'Ticket', '00' => 'Otros'],
        'COMPRA' => ['01' => 'Factura', '03' => 'Boleta', '02' => 'Recibo por honorarios', '07' => 'Nota de crédito', '08' => 'Nota de débito',
            '12' => 'Ticket', '14' => 'Recibo de servicios públicos', '50' => 'DUA (importación)', '00' => 'Otros'],
    ];

    /** Primeros dígitos de las cuentas que se ofrecen para el monto: ingresos (70, 75) o compras y gastos (60 al 68) */
    public const PREFIJOS = ['VENTA' => ['70', '73', '75', '77'], 'COMPRA' => ['60', '62', '63', '64', '65', '66', '67', '68', '33', '34']];

    /** Total del comprobante: base + IGV + ISC + ICBPER + exonerado + otros */
    public static function total(array $d): float
    {
        return round(array_sum(array_map(fn ($c) => (float) ($d[$c] ?? 0), ['base', 'igv', 'isc', 'icbper', 'no_gravado', 'otros'])), 2);
    }

    /**
     * Montos en soles (con el tipo de cambio). El monto principal (venta o costo) se saca por diferencia para que el
     * asiento cuadre al céntimo aunque haya redondeos.
     */
    public static function enSoles(array $d): array
    {
        $tc = ($d['moneda'] ?? 'PEN') === 'USD' ? max(0.001, (float) ($d['tipo_cambio'] ?? 1)) : 1.0;
        $s = fn (string $c) => round((float) ($d[$c] ?? 0) * $tc, 2);
        $total = round(self::total($d) * $tc, 2);

        return ['tc' => $tc, 'total' => $total, 'igv' => $s('igv'), 'isc' => $s('isc'), 'icbper' => $s('icbper'), 'no_gravado' => $s('no_gravado'),
            'retencion' => $s('retencion'), 'neto' => round($total - $s('retencion'), 2)];
    }

    /**
     * Asientos que corresponden al comprobante.
     *  Venta:  12 (D) total → IGV, ISC, ICBPER (H) + ventas gravadas y exoneradas (H)
     *  Compra: gasto o compra (D) con todo lo que no es crédito fiscal (ISC, ICBPER, exonerado, otros) + IGV compras (D)
     *          → 42 por pagar (H) y la retención de 4ta (H) en los recibos por honorarios; luego su destino
     *
     * @return array{provision: array<int, array<string, mixed>>, pago: ?array<int, array<string, mixed>>}
     */
    public static function propuesta(object $cfg, array $d, ?Collection $imputables = null): array
    {
        $s = self::enSoles($d);
        $nota = ($d['tdocod'] ?? '') === '07';
        $venta = $d['tipo'] === 'VENTA';
        $doc = trim(($d['serie'] ?? '') !== '' ? $d['serie'].'-'.$d['numero'] : (string) $d['numero']);
        $anexo = ['anexo_doc' => $d['doc_num'] ?? null, 'anexo_nombre' => $d['nombre'] ?? null, 'documento' => $doc];
        // Debe/haber según el lado; la nota de crédito va al revés
        $linea = fn (?string $cuenta, float $monto, bool $alDebe, string $glosa) => $monto == 0 || ! $cuenta ? null : [
            'cuenta' => $cuenta, 'debe' => ($alDebe xor $nota) ? $monto : 0, 'haber' => ($alDebe xor $nota) ? 0 : $monto, 'glosa' => $glosa,
        ] + $anexo;

        if ($venta) {
            // Lo exonerado va a su propia cuenta si se usa la cuenta de ventas por defecto
            $exoAparte = $s['no_gravado'] > 0 && $d['cuenta'] === $cfg->cta_ventas && $cfg->cta_ventas_exo !== $cfg->cta_ventas;
            $principal = round($s['total'] - $s['igv'] - $s['isc'] - $s['icbper'] - ($exoAparte ? $s['no_gravado'] : 0), 2);
            $provision = [
                $linea($cfg->cta_por_cobrar, $s['total'], true, 'POR COBRAR'),
                $linea($cfg->cta_igv, $s['igv'], false, 'IGV'),
                $linea($cfg->cta_isc ?? null, $s['isc'], false, 'ISC'),
                $linea($cfg->cta_icbper ?? null, $s['icbper'], false, 'ICBPER'),
                $linea($d['cuenta'], $principal, false, 'VENTA'),
                $exoAparte ? $linea($cfg->cta_ventas_exo, $s['no_gravado'], false, 'VENTA EXONERADA') : null,
            ];
        } else {
            $costo = round($s['total'] - $s['igv'], 2);
            $provision = [
                $linea($d['cuenta'], $costo, true, 'COMPRA / GASTO'),
                $linea($cfg->cta_igv_compras ?? $cfg->cta_igv, $s['igv'], true, 'IGV CRÉDITO FISCAL'),
                $linea($cfg->cta_por_pagar, $s['neto'], false, 'POR PAGAR'),
                $linea($cfg->cta_renta4_pagar, $s['retencion'], false, 'RETENCIÓN 4TA CATEGORÍA'),
            ];
            // Destino: mercaderías al almacén (20 → 61); gastos a su función (94 → 79)
            $existe = fn (string $c) => $imputables === null || isset($imputables[$c]);
            if (str_starts_with($d['cuenta'], '60') && $cfg->asiento_destino && $existe($cfg->cta_mercaderias) && $existe($cfg->cta_variacion)) {
                $provision[] = $linea($cfg->cta_mercaderias, $costo, true, 'DESTINO');
                $provision[] = $linea($cfg->cta_variacion, $costo, false, 'DESTINO');
            } elseif (preg_match('/^6[2-8]/', $d['cuenta']) && $existe('94') && $existe('79')) {
                $provision[] = $linea('94', $costo, true, 'DESTINO GASTOS ADMINISTRATIVOS');
                $provision[] = $linea('79', $costo, false, 'DESTINO');
            }
        }

        $pago = null;
        if (($d['condicion'] ?? 'CREDITO') === 'CONTADO' && ! empty($d['cuenta_pago'])) {
            $pago = $venta
                ? [$linea($d['cuenta_pago'], $s['neto'], true, 'COBRO'), $linea($cfg->cta_por_cobrar, $s['neto'], false, 'COBRO')]
                : [$linea($cfg->cta_por_pagar, $s['neto'], true, 'PAGO'), $linea($d['cuenta_pago'], $s['neto'], false, 'PAGO')];
            $pago = array_values(array_filter($pago));
        }

        return ['provision' => array_values(array_filter($provision)), 'pago' => $pago];
    }

    /**
     * Guarda (o reemplaza) el comprobante y sus asientos. $lineas: el asiento cambiado a mano (null = el propuesto).
     */
    public static function guardar(string $ruc, array $d, ?array $lineas = null, ?int $id = null): int
    {
        return DB::transaction(function () use ($ruc, $d, $lineas, $id) {
            $cfg = Contabilidad::config($ruc);
            $imputables = Contabilidad::imputables($ruc);
            $periodo = Carbon::parse($d['fecha'])->format('Ym');
            Contabilidad::validarPeriodo($ruc, $periodo);

            $repetido = DB::table('conta_registros')->where('IdEmpresa', $ruc)->where('tipo', $d['tipo'])->where('tdocod', $d['tdocod'])
                ->where('serie', $d['serie'] ?? null)->where('numero', $d['numero'])->where('doc_num', $d['doc_num'] ?? null)
                ->when($id, fn ($q) => $q->where('id', '!=', $id))->first();
            if ($repetido) {
                throw new RuntimeException('Ese comprobante ya está registrado en '.Contabilidad::nombrePeriodo($repetido->periodo).'.');
            }

            $fila = array_intersect_key($d, array_flip(['tipo', 'fecha', 'fecha_vence', 'tdocod', 'serie', 'numero', 'doc_tipo', 'doc_num', 'nombre',
                'moneda', 'tipo_cambio', 'base', 'igv', 'isc', 'icbper', 'no_gravado', 'otros', 'retencion', 'total', 'cuenta', 'condicion', 'cuenta_pago', 'glosa']))
                + ['IdEmpresa' => $ruc, 'periodo' => $periodo, 'asiento_editado' => $lineas !== null, 'IdUsuario' => Auth::id(), 'updated_at' => now()];
            if ($id) {
                $anterior = DB::table('conta_registros')->where('id', $id)->where('IdEmpresa', $ruc)->lockForUpdate()->first();
                if (! $anterior) {
                    throw new RuntimeException('El comprobante ya no existe.');
                }
                Contabilidad::validarPeriodo($ruc, $anterior->periodo);
                self::borrarAsientos($ruc, $id);
                DB::table('conta_registros')->where('id', $id)->update($fila);
            } else {
                $id = DB::table('conta_registros')->insertGetId($fila + ['created_at' => now()]);
            }

            $propuesta = self::propuesta($cfg, $d, $imputables);
            $doc = trim(($d['serie'] ?? '') !== '' ? $d['serie'].'-'.$d['numero'] : (string) $d['numero']);
            $tipoDoc = self::DOCUMENTOS[$d['tipo']][$d['tdocod']] ?? 'Doc.';
            $glosa = ($d['glosa'] ?? null) ?: ($d['tipo'] === 'VENTA' ? 'VENTA ' : 'COMPRA ')."{$tipoDoc} {$doc} - {$d['nombre']}";
            $cab = ['fecha' => $d['fecha'], 'tdocod' => $d['tdocod'], 'documento' => mb_substr($doc, 0, 30), 'ref_tabla' => 'conta_registros', 'ref_id' => $id];

            Contabilidad::crearAsiento($ruc, $cab + ['glosa' => $glosa, 'subdiario' => $d['tipo'] === 'VENTA' ? '05' : '11', 'origen' => self::ORIGEN[$d['tipo']]],
                $lineas ?? $propuesta['provision'], $imputables);
            if ($propuesta['pago']) {
                Contabilidad::crearAsiento($ruc, $cab + ['glosa' => ($d['tipo'] === 'VENTA' ? 'COBRO ' : 'PAGO ').$doc.' - '.$d['nombre'],
                    'subdiario' => '01', 'origen' => self::ORIGEN_PAGO[$d['tipo']]], $propuesta['pago'], $imputables);
            }

            return $id;
        });
    }

    public static function eliminar(string $ruc, int $id): void
    {
        DB::transaction(function () use ($ruc, $id) {
            $r = DB::table('conta_registros')->where('id', $id)->where('IdEmpresa', $ruc)->lockForUpdate()->first();
            if (! $r) {
                throw new RuntimeException('El comprobante ya no existe.');
            }
            Contabilidad::validarPeriodo($ruc, $r->periodo);
            self::borrarAsientos($ruc, $id);
            DB::table('conta_registros')->where('id', $id)->delete();
        });
    }

    private static function borrarAsientos(string $ruc, int $id): void
    {
        $ids = DB::table('conta_asientos')->where('IdEmpresa', $ruc)->where('ref_tabla', 'conta_registros')->where('ref_id', $id)->pluck('id');
        DB::table('conta_asiento_detalle')->whereIn('asiento_id', $ids)->delete();
        DB::table('conta_asientos')->whereIn('id', $ids)->delete();
    }
}
