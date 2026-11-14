<?php

namespace App\Support\Contabilidad;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comprobantes que el contador agrega a mano al libro de un mes: el proveedor mandó tarde su factura (o el cliente no
 * figura) y SUNAT aún no lo tiene en la propuesta. Van junto a los de la propuesta en todo (cuentas, .dbf, Excel,
 * reporte, registro y CONCAR) y se pueden borrar.
 *
 * Para que nada se duplique: un comprobante se agrega una sola vez; si ya está en la propuesta del mes no se agrega; y
 * si después SUNAT lo trae en la propuesta de otro mes, en ese mes sale como «no se lleva» (ya se registró aquí).
 */
class SireManuales
{
    public const TIPOS = [
        'compras' => ['01' => 'Factura', '02' => 'Recibo por honorarios', '03' => 'Boleta', '07' => 'Nota de crédito', '08' => 'Nota de débito', '12' => 'Ticket', '14' => 'Recibo de servicio público'],
        'ventas' => ['01' => 'Factura', '03' => 'Boleta', '07' => 'Nota de crédito', '08' => 'Nota de débito'],
    ];

    /** Los montos de cada libro, en el mismo orden y con las mismas claves que la propuesta (el total al final) */
    public const MONTOS = [
        'compras' => ['base_dg' => 'B.I. gravadas', 'igv_dg' => 'IGV 1', 'base_dgng' => 'B.I. grav. y no grav.', 'igv_dgng' => 'IGV 2',
            'base_dng' => 'B.I. no gravadas', 'igv_dng' => 'IGV 3', 'no_gravado' => 'No gravado', 'isc' => 'ISC', 'otros' => 'Otros tributos'],
        'ventas' => ['exporta' => 'Exportación', 'gravada' => 'Gravada', 'igv' => 'IGV', 'exonerada' => 'Exonerada', 'inafecta' => 'Inafecta',
            'isc' => 'ISC', 'icbper' => 'ICBPER', 'otros' => 'Otros tributos'],
    ];

    private static function hay(): bool
    {
        return Schema::hasTable('sire_manuales');
    }

    public static function clave(string $ruc, string $tipo, string $serie, string $numero): string
    {
        return "{$ruc}|{$tipo}|{$serie}|{$numero}";
    }

    /** Último día del periodo (AAAAMM) */
    private static function finDe(string $periodo): string
    {
        return date('Y-m-t', strtotime(substr($periodo, 0, 4).'-'.substr($periodo, 4, 2).'-01'));
    }

    /**
     * Agrega un comprobante al libro del periodo. $d: tipo, serie, numero, fecha, vence, ruc, nombre, moneda, tc,
     * montos (sin el total: se suma solo), ref_tipo, ref_serie, ref_numero, ref_fecha.
     *
     * @throws \RuntimeException con el motivo, para mostrarlo tal cual
     */
    public static function guardar(string $idEmpresa, string $libro, string $periodo, array $d, ?int $idUsuario = null): int
    {
        if (! isset(self::TIPOS[$libro]) || ! preg_match('/^\d{6}$/', $periodo)) {
            throw new \RuntimeException('Libro o periodo no válido.');
        }
        $propuesta = SireDbf::propuesta($idEmpresa, $libro, $periodo);
        if ($propuesta === null) {
            throw new \RuntimeException('Primero trae la propuesta de SUNAT de este mes: el comprobante se agrega junto a ella.');
        }
        $tipo = (string) ($d['tipo'] ?? '');
        if (! isset(self::TIPOS[$libro][$tipo])) {
            throw new \RuntimeException('Elige el tipo de comprobante.');
        }
        $serie = mb_strtoupper(trim((string) ($d['serie'] ?? '')));
        $numero = ltrim(trim((string) ($d['numero'] ?? '')), '0');
        $ruc = trim((string) ($d['ruc'] ?? ''));
        $nombre = mb_strtoupper(trim((string) ($d['nombre'] ?? '')));
        if (! preg_match('/^[A-Z0-9]{1,20}$/', $serie)) {
            throw new \RuntimeException('La serie solo lleva letras y números (ej. F001, E001).');
        }
        if (! preg_match('/^\d{1,20}$/', $numero)) {
            throw new \RuntimeException('El número del comprobante solo lleva dígitos.');
        }
        if ($libro === 'compras' ? ! preg_match('/^(\d{8}|\d{11})$/', $ruc) : ! preg_match('/^[0-9A-Z]{1,15}$/', $ruc)) {
            throw new \RuntimeException($libro === 'compras' ? 'El RUC del proveedor tiene 11 dígitos (o el DNI 8).' : 'Escribe el documento del cliente (RUC, DNI o 0 si es varios).');
        }
        if ($nombre === '' || mb_strlen($nombre) > 200) {
            throw new \RuntimeException('Escribe el nombre o razón social.');
        }
        $fecha = (string) ($d['fecha'] ?? '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || ! strtotime($fecha)) {
            throw new \RuntimeException('Escribe la fecha de emisión.');
        }
        if ($fecha > self::finDe($periodo)) {
            throw new \RuntimeException('La fecha de emisión es posterior a este mes: agrégalo en el mes que corresponde.');
        }
        $vence = ! empty($d['vence']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d['vence']) ? (string) $d['vence'] : null;
        $moneda = ($d['moneda'] ?? 'PEN') === 'USD' ? 'USD' : 'PEN';
        $tc = round((float) ($d['tc'] ?? 0), 3);
        if ($moneda === 'USD' && ! ($tc > 0)) {
            throw new \RuntimeException('Escribe el tipo de cambio del comprobante en dólares.');
        }

        // Montos: positivos al escribirlos; la nota de crédito resta (en negativo, como la da SUNAT)
        $signo = $tipo === '07' ? -1 : 1;
        $montos = [];
        foreach (array_keys(self::MONTOS[$libro]) as $k) {
            $v = round((float) str_replace(',', '', (string) ($d['montos'][$k] ?? 0)), 2);
            if ($v < 0) {
                throw new \RuntimeException('Escribe los montos en positivo'.($tipo === '07' ? ': la nota de crédito se resta sola.' : '.'));
            }
            $montos[$k] = round($v * $signo, 2);
        }
        $montos['total'] = round(array_sum($montos), 2);
        if ($montos['total'] == 0) {
            throw new \RuntimeException('Escribe los montos del comprobante.');
        }

        $clave = self::clave($ruc, $tipo, $serie, $numero);
        if (isset(SireDbf::leer($libro, $propuesta)[$clave])) {
            throw new \RuntimeException("El {$serie}-{$numero} ya está en la propuesta de SUNAT de este mes: no hace falta agregarlo.");
        }
        $otro = DB::table('sire_manuales')->where('IdEmpresa', $idEmpresa)->where('libro', $libro)->where('clave', $clave)->first();
        if ($otro) {
            throw new \RuntimeException("El {$serie}-{$numero} ya se agregó en ".substr($otro->periodo, 4, 2).'/'.substr($otro->periodo, 0, 4).': no se repite.');
        }

        $ref = null;
        if (in_array($tipo, ['07', '08'], true) && ! empty($d['ref_serie']) && ! empty($d['ref_numero'])) {
            $ref = ['tipo' => (string) ($d['ref_tipo'] ?? '01') ?: '01', 'serie' => mb_strtoupper(trim((string) $d['ref_serie'])),
                'numero' => ltrim(trim((string) $d['ref_numero']), '0') ?: '0',
                'fecha' => ! empty($d['ref_fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d['ref_fecha']) ? (string) $d['ref_fecha'] : null];
        }

        return DB::table('sire_manuales')->insertGetId([
            'IdEmpresa' => $idEmpresa, 'libro' => $libro, 'periodo' => $periodo, 'clave' => $clave, 'tipo' => $tipo, 'serie' => $serie,
            'numero' => $numero, 'fecha' => $fecha, 'vence' => $vence, 'ruc' => $ruc, 'nombre' => mb_substr($nombre, 0, 200),
            'moneda' => $moneda, 'tc' => $moneda === 'USD' ? $tc : null, 'montos' => json_encode($montos), 'ref' => $ref ? json_encode($ref) : null,
            'IdUsuario' => $idUsuario, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Lo borra con lo que se le revisó (cuentas, montos); los demás comprobantes no se tocan */
    public static function eliminar(string $idEmpresa, int $id): bool
    {
        $m = self::hay() ? DB::table('sire_manuales')->where('id', $id)->where('IdEmpresa', $idEmpresa)->first() : null;
        if (! $m) {
            return false;
        }
        DB::table('sire_ajustes')->where(['IdEmpresa' => $idEmpresa, 'libro' => $m->libro, 'periodo' => $m->periodo, 'clave' => $m->clave])->delete();
        DB::table('sire_manuales')->where('id', $id)->delete();

        return true;
    }

    /**
     * Los agregados del periodo con la forma de SireDbf::leer (más 'manual' = su id), por clave.
     *
     * @return array<string, array>
     */
    public static function delPeriodo(string $idEmpresa, string $libro, string $periodo): array
    {
        if (! self::hay()) {
            return [];
        }
        $res = [];
        foreach (DB::table('sire_manuales')->where('IdEmpresa', $idEmpresa)->where('libro', $libro)->where('periodo', $periodo)->orderBy('id')->get() as $m) {
            $montos = array_map('floatval', json_decode((string) $m->montos, true) ?: []) + array_fill_keys(array_keys(self::MONTOS[$libro]), 0.0) + ['total' => 0.0];
            $ref = json_decode((string) $m->ref, true) ?: null;
            $res[$m->clave] = [
                'clave' => $m->clave, 'tipo' => $m->tipo, 'serie' => $m->serie, 'numero' => $m->numero, 'fecha' => $m->fecha, 'vence' => $m->vence,
                'ruc' => $m->ruc, 'nombre' => $m->nombre, 'moneda' => $m->moneda, 'tc' => (float) ($m->tc ?: 1) ?: 1.0,
                'ref' => $ref, 'anulado' => false, 'montos' => $montos, 'manual' => (int) $m->id,
            ];
        }

        return $res;
    }

    /**
     * Los agregados del periodo con la forma de SireCuadre::comprasPropuesta (para el CONCAR con detalle y los XML).
     *
     * @return array<string, array>
     */
    public static function comprasDelPeriodo(string $idEmpresa, string $periodo): array
    {
        $igvs = ['igv_dg', 'igv_dgng', 'igv_dng'];

        return array_map(fn ($c) => [
            'ruc' => $c['ruc'], 'nombre' => $c['nombre'], 'tipo' => $c['tipo'], 'serie' => $c['serie'], 'numero' => $c['numero'],
            'fecha' => $c['fecha'], 'vence' => $c['vence'],
            'igv' => abs(round(array_sum(array_intersect_key($c['montos'], array_flip($igvs))), 2)), 'total' => abs((float) $c['montos']['total']),
            'moneda' => $c['moneda'], 'tc' => $c['moneda'] === 'USD' ? $c['tc'] : null,
            'ref' => $c['ref'] ? [$c['ref']['tipo'], $c['ref']['serie'].'-'.str_pad($c['ref']['numero'], 8, '0', STR_PAD_LEFT), $c['ref']['fecha']] : null,
            'manual' => $c['manual'],
        ], self::delPeriodo($idEmpresa, 'compras', $periodo));
    }

    /**
     * De estas claves, las que se agregaron a mano en OTRO mes: clave => periodo (AAAAMM).
     *
     * @param  array<int, string>  $claves
     * @return array<string, string>
     */
    public static function enOtroPeriodo(string $idEmpresa, string $libro, string $periodo, array $claves): array
    {
        if (! self::hay() || ! $claves) {
            return [];
        }

        return DB::table('sire_manuales')->where('IdEmpresa', $idEmpresa)->where('libro', $libro)->where('periodo', '!=', $periodo)
            ->whereIn('clave', $claves)->pluck('periodo', 'clave')->all();
    }

    /**
     * Lo que dice un XML de compra, listo para el formulario: con la base gravada y su IGV, lo no gravado, y lo demás
     * (ISC, ICBPER, recargos) en otros tributos, de modo que sume el total del XML.
     *
     * @param  array<int, object>  $lineas
     */
    public static function desdeXml(object $xml, array $lineas): array
    {
        $igv = abs(round((float) $xml->igv, 2));
        $total = abs(round((float) $xml->total, 2));
        $gravada = round(array_sum(array_map(fn ($l) => (float) $l->igv != 0 ? abs((float) $l->base) : 0, $lineas)), 2);
        $noGravado = round(array_sum(array_map(fn ($l) => (float) $l->igv == 0 ? abs((float) $l->base) : 0, $lineas)), 2);
        if (! $lineas) {
            [$gravada, $noGravado] = $igv > 0 ? [round($total - $igv, 2), 0.0] : [0.0, $total];
        }
        $resto = round($total - $gravada - $igv - $noGravado, 2);
        // Descuentos globales: se restan de la base (o de lo no gravado); recargos e impuestos extra van a otros
        if ($resto < 0) {
            $gravada > 0 ? $gravada = round($gravada + $resto, 2) : $noGravado = round($noGravado + $resto, 2);
            $resto = 0.0;
        }

        return [
            'tipo' => $xml->tipo, 'serie' => $xml->serie, 'numero' => $xml->numero, 'fecha' => $xml->fecha, 'ruc' => $xml->ruc_emisor,
            'nombre' => (string) $xml->razon_emisor, 'moneda' => $xml->moneda === 'USD' ? 'USD' : 'PEN',
            'montos' => ['base_dg' => max(0, $gravada), 'igv_dg' => $igv, 'no_gravado' => max(0, $noGravado), 'otros' => $resto],
        ];
    }
}
