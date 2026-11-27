<?php

namespace App\Support\Sunat;

use App\Support\Comprobante;
use DOMDocument;
use DOMXPath;
use Greenter\Model\Response\BaseResult;
use Greenter\Model\Summary\Summary;
use Greenter\See;
use Greenter\Xml\Builder\SummaryBuilder;
use RuntimeException;

/**
 * XML del resumen diario con la tasa del IGV en cada línea. SUNAT exige ahora la tasa (cbc:Percent) del tributo 1000
 * en cada línea del resumen (error 2992 "Tasa del tributo faltante para código 1000") y Greenter no la pone: se arma
 * el XML con Greenter, se le agrega la tasa de cada boleta, se firma y se envía igual que antes.
 */
class ResumenXml
{
    private const UBL_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const UBL_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const SUNAT = 'urn:sunat:names:specification:ubl:peru:schema:xsd:SunatAggregateComponents-1';

    /** Tasa del IGV de una boleta (18, 10.5…): la con la que se emitió si tiene base gravada; 0 si no tiene */
    public static function tasa(object $cabecera): float
    {
        return (float) ($cabecera->ccatvg ?? 0) > 0 ? round((Comprobante::factorDe($cabecera) - 1) * 100, 2) : 0.0;
    }

    /**
     * Agrega la tasa del IGV (cbc:Percent) a cada línea del resumen, en el orden de sus líneas.
     *
     * @param  list<float>  $tasas
     */
    public static function conTasas(string $xml, array $tasas): string
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        if (! $dom->loadXML($xml)) {
            throw new RuntimeException('No se pudo armar el XML del resumen.');
        }
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('cbc', self::UBL_CBC);
        $xp->registerNamespace('cac', self::UBL_CAC);
        $xp->registerNamespace('sac', self::SUNAT);

        foreach ($xp->query('//sac:SummaryDocumentsLine') as $i => $linea) {
            $tasa = number_format((float) ($tasas[$i] ?? 0), 2, '.', '');
            foreach ($xp->query('cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory[cac:TaxScheme/cbc:ID="1000"]', $linea) as $categoria) {
                if ($xp->query('cbc:Percent', $categoria)->length) {
                    continue;
                }
                $categoria->insertBefore($dom->createElementNS(self::UBL_CBC, 'cbc:Percent', $tasa), $xp->query('cac:TaxScheme', $categoria)->item(0));
            }
        }

        return $dom->saveXML();
    }

    /**
     * Arma, firma y envía el resumen con las tasas.
     *
     * @param  list<float>  $tasas
     * @return array{0: BaseResult|null, 1: string} respuesta de SUNAT y el XML firmado (para guardarlo)
     */
    public static function enviar(See $see, Summary $resumen, array $tasas): array
    {
        $firmado = $see->getFactory()->getSigner()->signXml(self::conTasas((new SummaryBuilder)->build($resumen), $tasas));

        return [$see->sendXml(Summary::class, $resumen->getName(), $firmado), $firmado];
    }
}
