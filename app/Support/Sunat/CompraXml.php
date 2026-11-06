<?php

namespace App\Support\Sunat;

/**
 * Lee el XML (UBL de SUNAT) de un comprobante recibido: quién lo emitió, a quién, totales y cada línea (lo que se
 * compró). Acepta facturas, boletas, notas de crédito y de débito, sueltos o dentro de un .zip.
 */
class CompraXml
{
    /** Raíz del XML → tipo de comprobante (la factura y la boleta dicen su tipo en InvoiceTypeCode) */
    private const RAICES = ['Invoice' => null, 'CreditNote' => '07', 'DebitNote' => '08'];

    private const LINEAS = ['Invoice' => 'InvoiceLine', 'CreditNote' => 'CreditNoteLine', 'DebitNote' => 'DebitNoteLine'];

    private const CANTIDADES = ['Invoice' => 'InvoicedQuantity', 'CreditNote' => 'CreditedQuantity', 'DebitNote' => 'DebitedQuantity'];

    /** Tope por archivo (un XML de factura pesa pocos KB) y de archivos por subida */
    private const MAX_BYTES = 5_000_000;

    public const MAX_ARCHIVOS = 3000;

    /**
     * Comprobante leído de un XML, o null si no es un comprobante (por ejemplo la constancia CDR que viene en el .zip).
     *
     * @return array{ruc_emisor: string, razon_emisor: ?string, ruc_comprador: ?string, tipo: string, serie: string, numero: string,
     *     fecha: ?string, moneda: string, igv: float, total: float, lineas: array<int, array{orden: int, codigo: ?string,
     *     descripcion: string, unidad: ?string, cantidad: float, precio: float, base: float, igv: float}>}|null
     */
    public static function leer(string $xml): ?array
    {
        if (strlen($xml) > self::MAX_BYTES) {
            return null;
        }
        $dom = new \DOMDocument;
        // Sin LIBXML_NOENT ni red: un XML no puede leer archivos del servidor ni llamar a otras direcciones
        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR) || ! $dom->documentElement) {
            return null;
        }
        $raiz = $dom->documentElement->localName;
        if (! array_key_exists($raiz, self::RAICES)) {
            return null;
        }
        $x = new \DOMXPath($dom);
        $texto = fn (string $ruta, ?\DOMNode $nodo = null) => ($n = $x->query($ruta, $nodo)->item(0)) ? trim($n->textContent) : null;
        $num = fn (?string $v) => round((float) str_replace(',', '', (string) $v), 4);
        // Los nombres de las etiquetas sin su prefijo (cbc:, cac:): así sirve para UBL 2.0 y 2.1
        $e = fn (string ...$partes) => implode('/', array_map(fn ($p) => "*[local-name()='{$p}']", $partes));

        $id = (string) $texto('/*/'.$e('ID'));
        if (! preg_match('/^([A-Z0-9]{1,4})-0*(\d{1,8})$/i', $id, $m)) {
            return null;
        }
        $tipo = self::RAICES[$raiz] ?? str_pad((string) $texto('/*/'.$e('InvoiceTypeCode')), 2, '0', STR_PAD_LEFT);
        $emisor = '/*/'.$e('AccountingSupplierParty');
        $comprador = '/*/'.$e('AccountingCustomerParty');
        $total = $texto('/*/'.$e('LegalMonetaryTotal', 'PayableAmount')) ?? $texto('/*/'.$e('RequestedMonetaryTotal', 'PayableAmount'));

        $lineas = [];
        foreach ($x->query('/*/'.$e(self::LINEAS[$raiz])) as $i => $l) {
            $descripcion = collect($x->query('./'.$e('Item', 'Description'), $l))->map(fn ($n) => trim($n->textContent))->filter()->implode(' ');
            $cantidad = $x->query('./'.$e(self::CANTIDADES[$raiz]), $l)->item(0);
            $lineas[] = [
                'orden' => $i + 1,
                'codigo' => $texto('./'.$e('Item', 'SellersItemIdentification', 'ID'), $l) ?: null,
                'descripcion' => mb_substr(preg_replace('/\s+/', ' ', $descripcion) ?: 'SIN DESCRIPCIÓN', 0, 500),
                'unidad' => $cantidad instanceof \DOMElement ? (mb_substr($cantidad->getAttribute('unitCode'), 0, 10) ?: null) : null,
                'cantidad' => $num($cantidad?->textContent),
                'precio' => $num($texto('./'.$e('Price', 'PriceAmount'), $l)),
                'base' => round($num($texto('./'.$e('LineExtensionAmount'), $l)), 2),
                'igv' => round($num($texto('./'.$e('TaxTotal', 'TaxAmount'), $l)), 2),
            ];
        }

        return [
            'ruc_emisor' => (string) ($texto($emisor.'/'.$e('Party', 'PartyIdentification', 'ID')) ?? $texto($emisor.'/'.$e('CustomerAssignedAccountID'))),
            'razon_emisor' => $texto($emisor.'/'.$e('Party', 'PartyLegalEntity', 'RegistrationName')),
            'ruc_comprador' => $texto($comprador.'/'.$e('Party', 'PartyIdentification', 'ID')) ?? $texto($comprador.'/'.$e('CustomerAssignedAccountID')),
            'razon_comprador' => $texto($comprador.'/'.$e('Party', 'PartyLegalEntity', 'RegistrationName')),
            'tipo' => $tipo,
            'serie' => strtoupper($m[1]),
            'numero' => ltrim($m[2], '0') ?: '0',
            'fecha' => $texto('/*/'.$e('IssueDate')),
            'moneda' => strtoupper((string) ($texto('/*/'.$e('DocumentCurrencyCode')) ?: 'PEN')),
            'igv' => round($num($texto('/*/'.$e('TaxTotal', 'TaxAmount'))), 2),
            'total' => round($num($total), 2),
            'lineas' => $lineas,
        ];
    }

    /**
     * Los XML de una subida: archivos .xml sueltos o .zip (también un .zip dentro de otro, como los que baja SUNAT).
     *
     * @param  array<int, array{nombre: string, ruta: string}>  $archivos
     * @return array{comprobantes: array<int, array>, ignorados: int}
     */
    public static function desdeArchivos(array $archivos): array
    {
        $comprobantes = [];
        $ignorados = 0;
        $agregar = function (string $nombre, string $contenido, int $nivel) use (&$agregar, &$comprobantes, &$ignorados) {
            if (count($comprobantes) >= self::MAX_ARCHIVOS) {
                return;
            }
            if (str_ends_with(strtolower($nombre), '.zip') && $nivel < 2) {
                $tmp = tempnam(sys_get_temp_dir(), 'cxml');
                file_put_contents($tmp, $contenido);
                $zip = new \ZipArchive;
                if ($zip->open($tmp) === true) {
                    for ($i = 0; $i < $zip->numFiles && $i < self::MAX_ARCHIVOS; $i++) {
                        $stat = $zip->statIndex($i);
                        if (! $stat || $stat['size'] > self::MAX_BYTES || str_ends_with($stat['name'], '/')) {
                            continue;
                        }
                        $agregar($stat['name'], (string) $zip->getFromIndex($i), $nivel + 1);
                    }
                    $zip->close();
                }
                @unlink($tmp);

                return;
            }
            if (! str_ends_with(strtolower($nombre), '.xml')) {
                return;
            }
            $c = self::leer($contenido);
            $c ? $comprobantes[] = $c : $ignorados++;
        };
        foreach ($archivos as $a) {
            if (filesize($a['ruta']) <= 50_000_000) {
                $agregar($a['nombre'], (string) file_get_contents($a['ruta']), 0);
            }
        }

        return ['comprobantes' => $comprobantes, 'ignorados' => $ignorados];
    }
}
