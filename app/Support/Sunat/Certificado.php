<?php
namespace App\Support\Sunat;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Convierte el certificado digital (.pfx / .p12) a .pem para firmar los XML.
 *
 * OpenSSL 3 (el de PHP 8.3) no lee los .pfx antiguos cifrados con RC2, que es como vienen muchos certificados
 * en Perú. Si la lectura normal falla por eso, se repite en un proceso PHP aparte con el proveedor "legacy"
 * de OpenSSL activado (PHP trae legacy.dll en extras/ssl).
 */
class Certificado
{
    /** @return array{pem: string, desde: ?string, hasta: ?string, titular: ?string} */
    public static function aPem(string $pfx, string $password): array
    {
        $certs = [];
        if (!@openssl_pkcs12_read($pfx, $certs, $password)) {
            $error = self::erroresOpenssl();

            if (str_contains($error, 'unsupported')) {
                $certs = self::leerConProveedorLegacy($pfx, $password);
            } elseif (str_contains($error, 'mac verify failure') || str_contains($error, 'invalid password')) {
                throw new RuntimeException('La contraseña del certificado es incorrecta.');
            } else {
                throw new RuntimeException('No se pudo leer el certificado: ' . ($error ?: 'formato no válido.'));
            }
        }

        if (empty($certs['cert']) || empty($certs['pkey'])) {
            throw new RuntimeException('El certificado no contiene la clave privada.');
        }

        $info = openssl_x509_parse($certs['cert']) ?: [];

        return [
            'pem'     => $certs['cert'] . $certs['pkey'],
            'desde'   => isset($info['validFrom_time_t']) ? date('Y-m-d', $info['validFrom_time_t']) : null,
            'hasta'   => isset($info['validTo_time_t']) ? date('Y-m-d', $info['validTo_time_t']) : null,
            'titular' => $info['subject']['CN'] ?? null,
        ];
    }

    private static function erroresOpenssl(): string
    {
        $errores = [];
        while ($e = openssl_error_string()) {
            $errores[] = $e;
        }
        return implode(' | ', $errores);
    }

    private static function leerConProveedorLegacy(string $pfx, string $password): array
    {
        $phpDir = dirname((string) php_ini_loaded_file());
        $phpExe = PHP_OS_FAMILY === 'Windows' ? $phpDir . DIRECTORY_SEPARATOR . 'php.exe' : (PHP_BINARY ?: 'php');
        $modulos = $phpDir . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl';

        if (!is_file($phpExe)) {
            throw new RuntimeException('El certificado usa un formato antiguo y no se encontró php.exe para convertirlo.');
        }

        $tmp = storage_path('app/tmp_cert_' . bin2hex(random_bytes(6)));
        $cnf = $tmp . '.cnf';
        $pfxPath = $tmp . '.pfx';
        file_put_contents($cnf, "openssl_conf = openssl_init\n[openssl_init]\nproviders = provider_sect\n"
            . "[provider_sect]\ndefault = default_sect\nlegacy = legacy_sect\n"
            . "[default_sect]\nactivate = 1\n[legacy_sect]\nactivate = 1\n");
        file_put_contents($pfxPath, $pfx);

        try {
            // La contraseña viaja por variable de entorno, no por la línea de comandos
            $script = '$c = []; if (!openssl_pkcs12_read(file_get_contents(getenv("TUSHPA_PFX")), $c, getenv("TUSHPA_PASS"))) {'
                . ' fwrite(STDERR, (string) openssl_error_string()); exit(1); }'
                . ' echo json_encode(["cert" => $c["cert"] ?? "", "pkey" => $c["pkey"] ?? ""]);';

            $proceso = new Process([$phpExe, '-n', '-d', 'extension_dir=' . $phpDir . DIRECTORY_SEPARATOR . 'ext',
                '-d', 'extension=openssl', '-r', $script], null, [
                'OPENSSL_CONF' => $cnf, 'OPENSSL_MODULES' => $modulos,
                'TUSHPA_PFX' => $pfxPath, 'TUSHPA_PASS' => $password,
            ]);
            $proceso->setTimeout(30)->run();

            if (!$proceso->isSuccessful()) {
                $err = $proceso->getErrorOutput();
                if (str_contains($err, 'mac verify failure') || str_contains($err, 'invalid password')) {
                    throw new RuntimeException('La contraseña del certificado es incorrecta.');
                }
                throw new RuntimeException('No se pudo convertir el certificado antiguo: ' . trim($err ?: $proceso->getOutput()));
            }

            return json_decode($proceso->getOutput(), true) ?: [];
        } finally {
            @unlink($cnf);
            @unlink($pfxPath);
        }
    }
}
