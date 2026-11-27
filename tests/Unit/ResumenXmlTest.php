<?php

namespace Tests\Unit;

use App\Support\Sunat\ResumenXml;
use DateTime;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Summary\Summary;
use Greenter\Model\Summary\SummaryDetail;
use Greenter\Xml\Builder\SummaryBuilder;
use PHPUnit\Framework\TestCase;

/** SUNAT exige la tasa del IGV en cada línea del resumen diario (error 2992): cada boleta lleva la suya */
class ResumenXmlTest extends TestCase
{
    private function linea(string $numero, float $gravada, float $exonerada, float $igv): SummaryDetail
    {
        return (new SummaryDetail)->setTipoDoc('03')->setSerieNro($numero)->setEstado('1')->setClienteTipo('1')->setClienteNro('00000000')
            ->setTotal($gravada + $exonerada + $igv)->setMtoOperGravadas($gravada)->setMtoOperExoneradas($exonerada)->setMtoOperInafectas(0)->setMtoIGV($igv);
    }

    public function test_cada_linea_del_resumen_lleva_la_tasa_del_igv_de_su_boleta(): void
    {
        $resumen = (new Summary)->setFecGeneracion(new DateTime('2026-11-26'))->setFecResumen(new DateTime('2026-11-27'))->setCorrelativo('001')
            ->setCompany((new Company)->setRuc('20123456789')->setRazonSocial('EMPRESA')->setAddress(new Address))
            ->setDetails([$this->linea('B001-1', 100, 0, 18), $this->linea('BE01-2', 100, 0, 10.5), $this->linea('B001-3', 0, 50, 0)]);

        $xml = ResumenXml::conTasas((new SummaryBuilder)->build($resumen), [18.0, 10.5, 0.0]);

        $this->assertSame(['18.00', '10.50', '0.00'], $this->tasas($xml));
        $this->assertSame(['18.00', '10.50', '0.00'], $this->tasas(ResumenXml::conTasas($xml, [18.0, 10.5, 0.0])), 'No se repite si ya la tiene');
    }

    public function test_la_tasa_sale_de_la_boleta(): void
    {
        $this->assertSame(18.0, ResumenXml::tasa((object) ['ccatvg' => 100, 'por_igv' => 18]));
        $this->assertSame(10.5, ResumenXml::tasa((object) ['ccatvg' => 100, 'por_igv' => 10.5]));
        $this->assertSame(0.0, ResumenXml::tasa((object) ['ccatvg' => 0, 'por_igv' => 18]), 'Sin base gravada (exonerada o inafecta): 0');
    }

    /** @return list<string> */
    private function tasas(string $xml): array
    {
        $dom = new \DOMDocument;
        $dom->loadXML($xml);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xp->registerNamespace('sac', 'urn:sunat:names:specification:ubl:peru:schema:xsd:SunatAggregateComponents-1');
        $tasas = [];
        foreach ($xp->query('//sac:SummaryDocumentsLine') as $linea) {
            $nodos = $xp->query('.//cbc:Percent', $linea);
            $this->assertSame(1, $nodos->length);
            $tasas[] = $nodos->item(0)->textContent;
        }

        return $tasas;
    }
}
