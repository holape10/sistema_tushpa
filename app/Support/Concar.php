<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Exporta ventas y compras como asientos para la plantilla "Importar asientos desde Excel" de CONCAR (SQL/CB):
 * columna A "Campo" + 40 campos, 3 filas de títulos (campo, restricciones, tamaño) y los datos desde la fila 4.
 * Un asiento por comprobante; cuentas, subdiarios y tipo de conversión salen de concar_config (por empresa).
 */
class Concar
{
    // [campo, restricciones, tamaño/formato] en el orden de la plantilla
    public const CAMPOS = [
        ['Sub Diario', 'Ver T.G. 02', '4 Caracteres'],
        ['Número de Comprobante', 'Los dos primeros dígitos son el mes y los otros 4 siguientes un correlativo', '6 Caracteres'],
        ['Fecha de Comprobante', 'Mes del Comprobante debe coincidir con el mes del Número de Comprobante', 'dd/mm/aaaa'],
        ['Código de Moneda', 'Ver T.G. 03', '2 Caracteres'],
        ['Glosa Principal', '', '40 Caracteres'],
        ['Tipo de Cambio', "Llenar solo si Tipo de Conversión es 'C'. Debe estar entre >=0 y <=9999.999999", 'Numérico 11, 6'],
        ['Tipo de Conversión', "Solo: 'C'= Especial, 'M'=Compra, 'V'=Venta , 'F' De acuerdo a fecha", '1 Caracter'],
        ['Flag de Conversión de Moneda', "Solo: 'S' = Si se convierte, 'N'= No se convierte", '1 Caracter'],
        ['Fecha Tipo de Cambio', "Si Tipo de Conversión 'F'", 'dd/mm/aaaa'],
        ['Cuenta Contable', 'Debe existir en el Plan de Cuentas', '12 Caracteres'],
        ['Código de Anexo', 'Si Cuenta Contable tiene seleccionado Tipo de Anexo, debe existir en la tabla de Anexos', '18 Caracteres'],
        ['Código de Centro de Costo', 'Si Cuenta Contable tiene habilitado C. Costo, Ver T.G. 05', '6 Caracteres'],
        ['Debe / Haber', "D ó H", '1 Caracter'],
        ['Importe Original', 'Importe positivo siempre', 'Numérico 14,2'],
        ['Importe en Dólares', "Importe de Conversión a Dólares. Si Flag de Conversión de Moneda es 'N' debe llenarse", 'Numérico 14,2'],
        ['Importe en Soles', "Importe de Conversión a Soles. Si Flag de Conversión de Moneda es 'N' debe llenarse", 'Numérico 14,2'],
        ['Tipo de Documento', 'Si Cuenta Contable tiene habilitado el Documento Referencia Ver T.G. 06', '2 Caracteres'],
        ['Número de Documento', 'Si Cuenta Contable tiene habilitado el Documento Referencia Incluye Serie y Número', '20 Caracteres'],
        ['Fecha de Documento', 'Si Cuenta Contable tiene habilitado el Documento Referencia', 'dd/mm/aaaa'],
        ['Fecha de Vencimiento', 'Si Cuenta Contable tiene habilitada la Fecha de Vencimiento', 'dd/mm/aaaa'],
        ['Código de Area', 'Si Cuenta Contable tiene habilitada el Area. Ver T.G. 26', '3 Caracteres'],
        ['Glosa Detalle', '', '30 Caracteres'],
        ['Código de Anexo Auxiliar', 'Si Cuenta Contable tiene seleccionado Tipo de Anexo Referencia', '18 Caracteres'],
        ['Medio de Pago', "Si Cuenta Contable tiene habilitado Tipo Medio Pago. Ver T.G. 'S1'", '8 Caracteres'],
        ['Tipo de Documento de Referencia', "Si Tipo de Documento es 'NA' ó 'ND' Ver T.G. 06", '2 Caracteres'],
        ['Número de Documento Referencia', "Si Tipo de Documento es 'NC', 'NA' ó 'ND', incluye Serie y Número", '20 Caracteres'],
        ['Fecha Documento Referencia', "Si Tipo de Documento es 'NC', 'NA' ó 'ND'", 'dd/mm/aaaa'],
        ['Nro Máq. Registradora Tipo Doc. Ref.', "Si Tipo de Documento es 'NC', 'NA' ó 'ND' y Tipo Doc. Ref. es Ticket", '20 Caracteres'],
        ['Base Imponible Documento Referencia', "Si Tipo de Documento es 'NA' ó 'ND'", 'Numérico 14,2'],
        ['IGV Documento Provisión', "Si Tipo de Documento es 'NA' ó 'ND'", 'Numérico 14,2'],
        ['Tipo Referencia en estado MQ', "MQ", '10 Caracteres'],
        ['Número Serie Caja Registradora', 'Si Tipo de Documento es Ticket', '20 Caracteres'],
        ['Fecha de Operación', '', 'dd/mm/aaaa'],
        ['Tipo de Tasa', 'Si la Cuenta Contable tiene habilitada la detracción/percepción', '5 Caracteres'],
        ['Tasa Detracción/Percepción', '', 'Numérico 4,2'],
        ['Importe Base Detracción/Percepción Dólares', '', 'Numérico 14,2'],
        ['Importe Base Detracción/Percepción Soles', '', 'Numérico 14,2'],
        ['Tipo Cambio para F', "Especificar solo si Tipo de Conversión es 'F'. Se permite 'M' Compra y 'V' Venta", '1 Caracter'],
        ['Importe de IGV sin derecho crédito fiscal', '', 'Numérico 14,2'],
        ['Tasa IGV', '', 'Numérico 14,2'],
    ];

    // Tabla general 06 de CONCAR: tipo de documento
    public const TIPOS_DOC = ['01' => 'FT', '03' => 'BV', '07' => 'NA', '08' => 'ND', '12' => 'TK'];

    public const DEFECTO = [
        'subdiario_ventas' => '05', 'subdiario_compras' => '11',
        'cta_por_cobrar' => '121201', 'cta_igv' => '401111', 'cta_ventas' => '701111', 'cta_ventas_exo' => '701111',
        'cta_compras' => '601111', 'cta_por_pagar' => '421201', 'anexo_varios' => '00000000', 'tipo_conversion' => 'V',
    ];

    public static function config(string $ruc): array
    {
        $c = DB::table('concar_config')->where('IdEmpresa', $ruc)->first();
        return array_merge(self::DEFECTO, $c ? array_intersect_key((array) $c, self::DEFECTO) : []);
    }

    /** Títulos de la plantilla: 3 filas con la columna A "Campo" / "Restricciones" / "Tamaño/Formato" */
    public static function titulos(): array
    {
        return [
            array_merge(['Campo'], array_column(self::CAMPOS, 0)),
            array_merge(['Restricciones'], array_column(self::CAMPOS, 1)),
            array_merge(['Tamaño/Formato'], array_column(self::CAMPOS, 2)),
        ];
    }

    /** @return array{filas: array, asientos: int, total: float, omitidos: array} */
    public static function ventas(string $ruc, string $periodo, int $desde = 1): array
    {
        $cfg = self::config($ruc);
        [$ini, $fin] = self::rango($periodo);
        $docs = DB::table('cpe_cabecera')->where('IdEmpresa', $ruc)->whereBetween('ccafem', [$ini, $fin])
            ->orderBy('ccafem')->orderBy('tdocod')->orderBy('serdoc')->orderBy('numdoc')->get();

        $filas = [];
        $omitidos = ['Anulados' => 0, 'Notas de venta (no van a contabilidad)' => 0];
        $n = $desde;
        $total = 0;
        foreach ($docs as $d) {
            if (!isset(self::TIPOS_DOC[$d->tdocod])) {
                $omitidos['Notas de venta (no van a contabilidad)']++;
                continue;
            }
            if ($d->ccabaj) {
                $omitidos['Anulados']++;
                continue;
            }

            $esNota = $d->tdocod === '07';                 // nota de crédito: el asiento va al revés
            $numero = $d->serdoc . '-' . str_pad($d->numdoc, 8, '0', STR_PAD_LEFT);
            $anexo = in_array(trim((string) $d->ccandi), ['', '0', '00000000'], true) ? $cfg['anexo_varios'] : trim($d->ccandi);
            $cab = self::cabecera($cfg, $cfg['subdiario_ventas'], $periodo, $n++, $d->ccafem, $d->moncod ?: 'PEN', null,
                ($esNota ? 'N.CRED. ' : 'VENTA ') . $numero . ' ' . $d->ccanom);
            $doc = [
                'tipo' => self::TIPOS_DOC[$d->tdocod], 'numero' => $numero, 'fecha' => $d->ccafem, 'vence' => $d->ccafve ?: $d->ccafem,
                'ref' => $d->tdocod_ref ? [self::TIPOS_DOC[$d->tdocod_ref] ?? $d->tdocod_ref, $d->serie_ref . '-' . str_pad((string) $d->num_ref, 8, '0', STR_PAD_LEFT), $d->ccafem_ref] : null,
            ];

            $igv = round((float) $d->ccaigv, 2);
            $grav = round((float) $d->ccatvg, 2);
            $exo = round((float) $d->ccatexo + (float) $d->ccatinaf, 2);
            $tot = round((float) $d->ccaitv, 2);
            // Lo que no cuadre por redondeo (o ICBPER) se carga a la cuenta de ventas
            $resto = round($tot - $igv - $grav - $exo, 2);
            if ($exo > 0 && $grav <= 0) $exo = round($exo + $resto, 2); else $grav = round($grav + $resto, 2);

            $dh = fn(string $normal) => $esNota ? ($normal === 'D' ? 'H' : 'D') : $normal;
            $filas[] = self::linea($cab, $d->cuenta12 ?: $cfg['cta_por_cobrar'], $anexo, $dh('D'), $tot, $doc, 'POR COBRAR');
            if ($igv > 0) $filas[] = self::linea($cab, $cfg['cta_igv'], $anexo, $dh('H'), $igv, $doc, 'IGV');
            if ($grav > 0) $filas[] = self::linea($cab, $cfg['cta_ventas'], $anexo, $dh('H'), $grav, $doc, 'VENTA GRAVADA');
            if ($exo > 0) $filas[] = self::linea($cab, $cfg['cta_ventas_exo'], $anexo, $dh('H'), $exo, $doc, 'VENTA EXONERADA');
            $total += $esNota ? -$tot : $tot;
        }

        return ['filas' => $filas, 'asientos' => $n - $desde, 'total' => round($total, 2), 'omitidos' => array_filter($omitidos)];
    }

    /** @return array{filas: array, asientos: int, total: float, omitidos: array} */
    public static function compras(string $ruc, string $periodo, int $desde = 1): array
    {
        $cfg = self::config($ruc);
        [$ini, $fin] = self::rango($periodo);
        $docs = DB::table('compras_cabecera as c')->leftJoin('proveedor as p', 'p.prov_id', '=', 'c.prov_id')
            ->where('c.IdEmpresa', $ruc)->where('c.est_compra', 'Registrado')->whereBetween('c.com_fec', [$ini, $fin])
            ->orderBy('c.com_fec')->orderBy('c.com_cab_id')
            ->get(['c.*', 'p.prov_ruc', 'p.prov_raz']);

        $filas = [];
        $omitidos = ['Notas de venta / otros sin tipo CONCAR' => 0];
        $n = $desde;
        $total = 0;
        foreach ($docs as $d) {
            if (!isset(self::TIPOS_DOC[$d->tdocod])) {
                $omitidos['Notas de venta / otros sin tipo CONCAR']++;
                continue;
            }
            $esNota = $d->tdocod === '07';
            $numero = $d->com_doc_ser . '-' . str_pad((string) $d->com_doc_num, 8, '0', STR_PAD_LEFT);
            $usd = $d->mon_id === 'USD' && (float) $d->tip_cam > 0;
            $cab = self::cabecera($cfg, $cfg['subdiario_compras'], $periodo, $n++, $d->com_fec, $usd ? 'USD' : 'PEN', $usd ? (float) $d->tip_cam : null,
                ($esNota ? 'N.CRED. ' : 'COMPRA ') . $numero . ' ' . $d->prov_raz);
            $doc = ['tipo' => self::TIPOS_DOC[$d->tdocod], 'numero' => $numero, 'fecha' => $d->com_fec, 'vence' => $d->com_fec_ven ?: $d->com_fec, 'ref' => null];

            $igv = round((float) $d->igv_com, 2);
            $tot = round((float) $d->total_com, 2);
            $base = round($tot - $igv, 2);

            $dh = fn(string $normal) => $esNota ? ($normal === 'D' ? 'H' : 'D') : $normal;
            if ($base > 0) $filas[] = self::linea($cab, $cfg['cta_compras'], $d->prov_ruc, $dh('D'), $base, $doc, 'COMPRA');
            if ($igv > 0) $filas[] = self::linea($cab, $cfg['cta_igv'], $d->prov_ruc, $dh('D'), $igv, $doc, 'IGV');
            $filas[] = self::linea($cab, $cfg['cta_por_pagar'], $d->prov_ruc, $dh('H'), $tot, $doc, 'POR PAGAR');
            $total += ($esNota ? -$tot : $tot) * ($usd ? (float) $d->tip_cam : 1);
        }

        return ['filas' => $filas, 'asientos' => $n - $desde, 'total' => round($total, 2), 'omitidos' => array_filter($omitidos)];
    }

    /** Datos comunes a todas las líneas de un asiento */
    private static function cabecera(array $cfg, string $subdiario, string $periodo, int $correlativo, string $fecha, string $moneda, ?float $tc, string $glosa): array
    {
        $usd = $moneda === 'USD';
        // Dólares con tipo de cambio conocido: conversión especial 'C' con ese tipo de cambio
        $conversion = $usd && $tc ? 'C' : $cfg['tipo_conversion'];
        return [
            'subdiario' => $subdiario,
            'numero' => substr($periodo, 4, 2) . str_pad((string) $correlativo, 4, '0', STR_PAD_LEFT),
            'fecha' => self::fecha($fecha),
            'moneda' => $usd ? 'US' : 'MN',
            'glosa' => mb_substr(mb_strtoupper($glosa), 0, 40),
            'tc' => $conversion === 'C' ? ($tc ?: 1) : null,
            'conversion' => $conversion,
            'fecha_tc' => $conversion === 'F' ? self::fecha($fecha) : null,
            'tc_f' => $conversion === 'F' ? 'V' : null,
        ];
    }

    /** Una fila de la plantilla: columna A vacía + los 40 campos */
    private static function linea(array $cab, string $cuenta, ?string $anexo, string $dh, float $importe, array $doc, string $detalle): array
    {
        $f = array_fill(0, 41, null);
        $f[1] = $cab['subdiario'];
        $f[2] = $cab['numero'];
        $f[3] = $cab['fecha'];
        $f[4] = $cab['moneda'];
        $f[5] = $cab['glosa'];
        $f[6] = $cab['tc'];
        $f[7] = $cab['conversion'];
        $f[8] = 'S';
        $f[9] = $cab['fecha_tc'];
        $f[10] = $cuenta;
        $f[11] = $anexo ? mb_substr($anexo, 0, 18) : null;
        $f[13] = $dh;
        $f[14] = round($importe, 2);
        $f[17] = $doc['tipo'];
        $f[18] = $doc['numero'];
        $f[19] = self::fecha($doc['fecha']);
        $f[20] = self::fecha($doc['vence']);
        $f[22] = mb_substr($detalle, 0, 30);
        if ($doc['ref']) {
            [$f[25], $f[26], $fechaRef] = $doc['ref'];
            $f[27] = $fechaRef ? self::fecha($fechaRef) : null;
        }
        $f[38] = $cab['tc_f'];
        return $f;
    }

    private static function fecha(?string $fecha): ?string
    {
        return $fecha ? date('d/m/Y', strtotime($fecha)) : null;
    }

    private static function rango(string $periodo): array
    {
        $ini = substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01';
        return [$ini, date('Y-m-t', strtotime($ini))];
    }
}
