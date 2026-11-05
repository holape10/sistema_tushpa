<?php

namespace App\Support\Sunat;

use App\Models\Empresa;
use App\Support\Estudio;
use App\Support\Tenancy\ConsumoXml;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

/**
 * Trae de SUNAT el XML de una factura que le emitieron al cliente, por json.pe con su Clave SOL principal (la que se
 * guarda en el panel de TUSHPA, Clientes → Editar). Cada consulta usa créditos de la cuenta de json.pe de TUSHPA.
 */
class XmlJsonPe
{
    /** Tipos que json.pe puede traer: factura, boleta, nota de crédito y de débito */
    public const TIPOS = ['01', '03', '07', '08'];

    /** ¿Qué falta para poder traer XML? null = todo listo */
    public static function falta(): ?string
    {
        if (! config('services.jsonpe.token')) {
            return 'Falta el token de json.pe en el servidor (JSONPE_TOKEN en el .env).';
        }
        if (! self::credenciales()) {
            return self::enEmpresaDelEstudio()
                ? 'Falta la Clave SOL principal de esta empresa (Empresas del estudio → Configurar).'
                : 'Falta la Clave SOL principal de esta empresa en el panel de '.config('marca.nombre').' (Clientes → Editar).';
        }

        return null;
    }

    /** ¿Se trabaja en la empresa de un cliente del estudio contable? (cada una tiene su propia Clave SOL) */
    private static function enEmpresaDelEstudio(?string $ruc = null): bool
    {
        $ruc ??= (string) Auth::user()?->IdEmpresa;

        return Estudio::activo() && $ruc !== '' && $ruc !== Estudio::ruc();
    }

    /** @return array{usuario: string, clave: string}|null */
    private static function credenciales(?string $ruc = null): ?array
    {
        $ruc ??= (string) Auth::user()?->IdEmpresa;
        if (self::enEmpresaDelEstudio($ruc)) {
            $empresa = Empresa::find($ruc);

            return $empresa?->sol_usuario && $empresa->sol_clave ? ['usuario' => $empresa->sol_usuario, 'clave' => $empresa->sol_clave] : null;
        }
        $cliente = Tenancy::cliente();
        if (! $cliente || ! $cliente->sol_usuario || ! $cliente->sol_clave) {
            return null;
        }

        return ['usuario' => $cliente->sol_usuario, 'clave' => $cliente->sol_clave];
    }

    /**
     * El XML de un comprobante recibido.
     *
     * @return array{xml: ?string, error: ?string, detener: bool} detener = no sigas pidiendo (clave o token mal, sin créditos)
     */
    public static function traer(string $ruc, string $rucEmisor, string $tipo, string $serie, string $numero): array
    {
        $sol = self::credenciales($ruc);
        if (! $sol || ! config('services.jsonpe.token')) {
            return ['xml' => null, 'error' => self::falta(), 'detener' => true];
        }
        try {
            $r = Http::timeout(45)->acceptJson()->withToken(config('services.jsonpe.token'))
                ->post(rtrim(config('services.jsonpe.url'), '/').'/sunat/xml', [
                    'ruc' => $ruc, 'usuario' => $sol['usuario'], 'password' => $sol['clave'],
                    'proveedor' => $rucEmisor, 'tipo_doc' => $tipo, 'serie' => $serie, 'correlativo' => $numero,
                ]);
        } catch (\Throwable) {
            return ['xml' => null, 'error' => 'json.pe no respondió. Intenta en unos minutos.', 'detener' => true];
        }
        // Cada consulta que llegó a json.pe usa créditos: se cuenta para el panel de TUSHPA
        $resultado = self::respuesta($r);
        ConsumoXml::registrar($resultado['xml'] !== null);

        return $resultado;
    }

    /** @return array{xml: ?string, error: ?string, detener: bool} */
    private static function respuesta(Response $r): array
    {
        $mensaje = trim((string) ($r->json('message') ?? ''));
        // Token, créditos o la Clave SOL: si fallan, fallarán todas las demás
        if (in_array($r->status(), [401, 402, 403, 429], true) || preg_match('/token|cr[eé]dito|saldo|clave|contrase|usuario|credencial|autentic/i', $mensaje) && ! $r->json('success')) {
            return ['xml' => null, 'error' => 'json.pe: '.($mensaje ?: 'HTTP '.$r->status()), 'detener' => true];
        }
        if (! $r->successful() || ! $r->json('success')) {
            return ['xml' => null, 'error' => $mensaje ?: 'SUNAT no tiene ese XML.', 'detener' => false];
        }
        $soloCdr = false;
        $xml = self::extraer($r->json('data'), $soloCdr);
        if ($xml) {
            return ['xml' => $xml, 'error' => null, 'detener' => false];
        }

        // Si solo devuelve la constancia de SUNAT, lo mismo pasará con todas: no se gastan más créditos
        return $soloCdr
            ? ['xml' => null, 'error' => 'json.pe devolvió solo la constancia de SUNAT (CDR), que no trae el detalle de la factura. Consulta con json.pe.', 'detener' => true]
            : ['xml' => null, 'error' => 'La respuesta de json.pe no trae el XML.', 'detener' => false];
    }

    /** El XML dentro de la respuesta: un .zip o el XML en Base64 (directo o dentro de algún campo) */
    private static function extraer(mixed $data, bool &$soloCdr = false): ?string
    {
        if (is_array($data)) {
            foreach ($data as $valor) {
                if ($xml = self::extraer($valor, $soloCdr)) {
                    return $xml;
                }
            }

            return null;
        }
        if (! is_string($data) || strlen($data) < 20) {
            return null;
        }
        $bytes = base64_decode(preg_replace('/^data:[^,]+,/', '', $data), true);
        if ($bytes === false) {
            return null;
        }
        if (str_starts_with($bytes, 'PK')) {
            $tmp = tempnam(sys_get_temp_dir(), 'jpe');
            file_put_contents($tmp, $bytes);
            $zip = new \ZipArchive;
            $xml = null;
            if ($zip->open($tmp) === true) {
                for ($i = 0; $i < $zip->numFiles && ! $xml; $i++) {
                    $contenido = (string) $zip->getFromIndex($i);
                    if (str_ends_with(strtolower($zip->getNameIndex($i)), '.xml') && CompraXml::leer($contenido)) {
                        $xml = $contenido;
                    } elseif (str_contains($contenido, 'ApplicationResponse')) {
                        $soloCdr = true;
                    }
                }
                $zip->close();
            }
            @unlink($tmp);

            return $xml;
        }

        return CompraXml::leer($bytes) ? $bytes : null;
    }
}
