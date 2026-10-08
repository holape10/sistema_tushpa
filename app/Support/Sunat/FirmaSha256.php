<?php

namespace App\Support\Sunat;

use Greenter\XMLSecLibs\Sunat\SignedXml;
use Greenter\XMLSecLibs\XMLSecurityDSig;
use Greenter\XMLSecLibs\XMLSecurityKey;

/**
 * Firma XML con RSA-SHA256 (SUNAT la acepta). Greenter firma por defecto con SHA-1, que OpenSSL 3 ya no permite
 * en servidores como AlmaLinux/RHEL 10 ("invalid digest"), sin forma de reactivarlo desde la política del sistema.
 */
class FirmaSha256 extends SignedXml
{
    protected $keyAlgorithm = XMLSecurityKey::RSA_SHA256;

    protected $digestAlgorithm = XMLSecurityDSig::SHA256;
}
