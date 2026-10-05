<?php
namespace App\Support\Sunat;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Código QR de la representación impresa (formato SUNAT):
 * RUC | TIPO | SERIE | NÚMERO | IGV | TOTAL | FECHA | TIPO DOC. CLIENTE | NÚMERO DOC. CLIENTE | HASH
 * El hash (valor resumen) existe recién cuando el comprobante se firmó y envió; antes va vacío.
 */
class CodigoQr
{
    public const TIPOS = ['01', '03', '07', '08'];

    public static function aplica(object $cab): bool
    {
        return in_array($cab->tdocod, self::TIPOS, true);
    }

    public static function texto(object $cab): string
    {
        return implode('|', [
            $cab->IdEmpresa, $cab->tdocod, $cab->serdoc, $cab->numdoc,
            number_format((float) $cab->ccaigv, 2, '.', ''), number_format((float) $cab->ccaitv, 2, '.', ''),
            $cab->ccafem, $cab->tdicod, $cab->ccandi, $cab->ccaqr ?? '',
        ]) . '|';
    }

    /** SVG listo para incrustar en el HTML (sin depender de internet ni de JavaScript) */
    public static function svg(object $cab, int $tamano = 140): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($tamano, 1), new SvgImageBackEnd()));
        return preg_replace('/^<\?xml[^>]*>\s*/', '', $writer->writeString(self::texto($cab)));
    }
}
