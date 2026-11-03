<?php

namespace Tests\Unit;

use App\Support\ComprasDetalle;
use App\Support\Sunat\CompraXml;
use PHPUnit\Framework\TestCase;

/** Lectura del XML de una factura recibida: lo que se compró, línea por línea */
class CompraXmlTest extends TestCase
{
    /** Una factura UBL 2.1 como las que emiten los proveedores (con sus prefijos cbc:/cac:) */
    public static function factura(string $comprador = '20614580063', string $id = 'F001-00134277', string $descripcion = 'INTERNET DEDICADO JULIO 2026'): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
  <cbc:ID>{$id}</cbc:ID>
  <cbc:IssueDate>2026-07-22</cbc:IssueDate>
  <cbc:InvoiceTypeCode listID="0101">01</cbc:InvoiceTypeCode>
  <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>
  <cac:AccountingSupplierParty><cac:Party>
    <cac:PartyIdentification><cbc:ID schemeID="6">20553510661</cbc:ID></cac:PartyIdentification>
    <cac:PartyLegalEntity><cbc:RegistrationName><![CDATA[LLAMA PE SA]]></cbc:RegistrationName></cac:PartyLegalEntity>
  </cac:Party></cac:AccountingSupplierParty>
  <cac:AccountingCustomerParty><cac:Party>
    <cac:PartyIdentification><cbc:ID schemeID="6">{$comprador}</cbc:ID></cac:PartyIdentification>
  </cac:Party></cac:AccountingCustomerParty>
  <cac:TaxTotal><cbc:TaxAmount currencyID="PEN">27.00</cbc:TaxAmount>
    <cac:TaxSubtotal><cbc:TaxAmount currencyID="PEN">27.00</cbc:TaxAmount></cac:TaxSubtotal></cac:TaxTotal>
  <cac:LegalMonetaryTotal><cbc:PayableAmount currencyID="PEN">177.00</cbc:PayableAmount></cac:LegalMonetaryTotal>
  <cac:InvoiceLine>
    <cbc:ID>1</cbc:ID>
    <cbc:InvoicedQuantity unitCode="ZZ">1</cbc:InvoicedQuantity>
    <cbc:LineExtensionAmount currencyID="PEN">100.00</cbc:LineExtensionAmount>
    <cac:PricingReference><cac:AlternativeConditionPrice><cbc:PriceAmount currencyID="PEN">118.00</cbc:PriceAmount></cac:AlternativeConditionPrice></cac:PricingReference>
    <cac:TaxTotal><cbc:TaxAmount currencyID="PEN">18.00</cbc:TaxAmount></cac:TaxTotal>
    <cac:Item><cbc:Description><![CDATA[{$descripcion}]]></cbc:Description><cac:SellersItemIdentification><cbc:ID>INT100</cbc:ID></cac:SellersItemIdentification></cac:Item>
    <cac:Price><cbc:PriceAmount currencyID="PEN">100.00</cbc:PriceAmount></cac:Price>
  </cac:InvoiceLine>
  <cac:InvoiceLine>
    <cbc:ID>2</cbc:ID>
    <cbc:InvoicedQuantity unitCode="NIU">2</cbc:InvoicedQuantity>
    <cbc:LineExtensionAmount currencyID="PEN">50.00</cbc:LineExtensionAmount>
    <cac:TaxTotal><cbc:TaxAmount currencyID="PEN">9.00</cbc:TaxAmount></cac:TaxTotal>
    <cac:Item><cbc:Description>ROUTER WIFI</cbc:Description></cac:Item>
    <cac:Price><cbc:PriceAmount currencyID="PEN">25.00</cbc:PriceAmount></cac:Price>
  </cac:InvoiceLine>
</Invoice>
XML;
    }

    public function test_lee_proveedor_totales_y_cada_linea_de_la_factura(): void
    {
        $c = CompraXml::leer(self::factura());

        $this->assertSame(['20553510661', 'LLAMA PE SA', '20614580063', '01', 'F001', '134277', '2026-07-22', 'PEN', 27.0, 177.0],
            [$c['ruc_emisor'], $c['razon_emisor'], $c['ruc_comprador'], $c['tipo'], $c['serie'], $c['numero'], $c['fecha'], $c['moneda'], $c['igv'], $c['total']]);
        $this->assertCount(2, $c['lineas']);
        $this->assertSame(['INTERNET DEDICADO JULIO 2026', 'INT100', 'ZZ', 1.0, 100.0, 100.0, 18.0],
            [$c['lineas'][0]['descripcion'], $c['lineas'][0]['codigo'], $c['lineas'][0]['unidad'], $c['lineas'][0]['cantidad'], $c['lineas'][0]['precio'], $c['lineas'][0]['base'], $c['lineas'][0]['igv']]);
        $this->assertSame([2.0, 25.0, 50.0], [$c['lineas'][1]['cantidad'], $c['lineas'][1]['precio'], $c['lineas'][1]['base']]);
    }

    public function test_una_nota_de_credito_y_lo_que_no_es_comprobante(): void
    {
        $nota = str_replace(['<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"', '</Invoice>', 'InvoiceLine', 'InvoicedQuantity', 'F001-00134277'],
            ['<CreditNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2"', '</CreditNote>', 'CreditNoteLine', 'CreditedQuantity', 'FC01-15'], self::factura());
        $c = CompraXml::leer($nota);
        $this->assertSame(['07', 'FC01', '15', 2.0], [$c['tipo'], $c['serie'], $c['numero'], $c['lineas'][1]['cantidad']]);

        // La constancia de SUNAT (CDR) y un XML roto no son comprobantes
        $this->assertNull(CompraXml::leer('<?xml version="1.0"?><ar:ApplicationResponse xmlns:ar="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2"/>'));
        $this->assertNull(CompraXml::leer('<Invoice><cbc:ID>'));
    }

    public function test_un_xml_no_puede_leer_archivos_del_servidor(): void
    {
        $secreto = tempnam(sys_get_temp_dir(), 'sec');
        file_put_contents($secreto, 'CLAVE-SECRETA');
        $uri = 'file:///'.ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $secreto), '/');
        $malo = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '<?xml version="1.0"?><!DOCTYPE Invoice [<!ENTITY x SYSTEM "'.$uri.'">]>', self::factura(descripcion: 'X'));
        $malo = str_replace('<![CDATA[X]]>', '&x;', $malo);
        $c = CompraXml::leer($malo);
        unlink($secreto);

        $this->assertStringNotContainsString('CLAVE-SECRETA', json_encode($c));
    }

    public function test_la_misma_compra_de_otro_mes_se_reconoce_igual(): void
    {
        $this->assertSame(ComprasDetalle::clave('Internet dedicado JULIO 2026 - 100MB'), ComprasDetalle::clave('INTERNET DEDICADO agosto 2026 - 100MB'));
        $this->assertNotSame(ComprasDetalle::clave('INTERNET DEDICADO'), ComprasDetalle::clave('ROUTER WIFI'));
    }
}
