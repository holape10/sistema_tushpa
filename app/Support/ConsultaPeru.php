<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Consultas a fuentes públicas:
 *  - RUC: el padrón de SUNAT cargado en el sistema (PadronRuc) y, de respaldo, consultas.holape.app.
 *  - DNI: el padrón (RUC 10), consultas.holape.app y, si no lo encuentra, apiperu.dev (token APIPERU_TOKEN en .env).
 *  - Tipo de cambio SUNAT: apiperu.dev, guardado en la tabla tipo_cambio para no gastar consultas del plan.
 */
class ConsultaPeru
{
    private const HOLAPE = 'https://consultas.holape.app/api/v1';

    private static function apiperu(string $ruta, array $datos): ?array
    {
        $token = config('services.apiperu.token');
        if (! $token) {
            return null;
        }
        try {
            $r = Http::timeout(10)->withOptions(['verify' => false])->acceptJson()->withToken($token)
                ->post(rtrim(config('services.apiperu.url'), '/').'/'.$ruta, $datos)->json();

            return ! empty($r['success']) ? ($r['data'] ?? null) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Estado de un comprobante en SUNAT (apiperu.dev /cpe). Gasta 1 consulta del plan.
     *
     * @return array{estado: string, codigo: string, empresa_estado: ?string, empresa_condicion: ?string}|null null = sin token o sin respuesta
     */
    public static function estadoCpe(string $ruc, string $tipo, string $serie, string $numero, string $fecha, float $total): ?array
    {
        $d = self::apiperu('cpe', ['ruc_emisor' => $ruc, 'codigo_tipo_documento' => $tipo, 'serie_documento' => $serie,
            'numero_documento' => $numero, 'fecha_de_emision' => $fecha, 'total' => round($total, 2)]);

        return $d && isset($d['comprobante_estado_descripcion']) ? [
            'estado' => mb_strtoupper((string) $d['comprobante_estado_descripcion']), 'codigo' => (string) ($d['comprobante_estado_codigo'] ?? ''),
            'empresa_estado' => $d['empresa_estado_descripcion'] ?? null, 'empresa_condicion' => $d['empresa_condicion_descripcion'] ?? null,
        ] : null;
    }

    /**
     * Valida un comprobante en SUNAT (apiperu.dev /cpe) y explica el resultado, también cuando no se pudo consultar.
     * Se guarda 10 minutos para no gastar consultas del plan si se pulsa otra vez.
     *
     * @return array{ok: bool, estado: ?string, color: string, mensaje: string, empresa_estado: ?string, empresa_condicion: ?string, observaciones: array<int, string>}
     */
    public static function validarCpe(string $ruc, string $tipo, string $serie, string $numero, string $fecha, float $total): array
    {
        $falla = fn (string $mensaje) => ['ok' => false, 'estado' => null, 'color' => 'slate', 'mensaje' => $mensaje,
            'empresa_estado' => null, 'empresa_condicion' => null, 'observaciones' => []];
        $token = config('services.apiperu.token');
        if (! $token) {
            return $falla('Falta el token de apiperu.dev (APIPERU_TOKEN en el archivo .env).');
        }
        $datos = ['ruc_emisor' => $ruc, 'codigo_tipo_documento' => $tipo, 'serie_documento' => mb_strtoupper(trim($serie)),
            'numero_documento' => (string) (int) $numero, 'fecha_de_emision' => $fecha, 'total' => round($total, 2)];

        $llave = 'cpe-validar:'.md5(json_encode($datos));
        if ($guardado = Cache::get($llave)) {
            return $guardado;
        }
        $resultado = (function () use ($datos, $token, $falla) {
            try {
                $r = Http::timeout(15)->withOptions(['verify' => false])->acceptJson()->withToken($token)
                    ->post(rtrim(config('services.apiperu.url'), '/').'/cpe', $datos);
            } catch (\Throwable $e) {
                return $falla('No hubo conexión con el servicio de consulta. Intenta en unos segundos.');
            }
            $j = $r->json() ?? [];
            if (empty($j['success']) || ! isset($j['data']['comprobante_estado_descripcion'])) {
                return $falla(match (true) {
                    $r->status() === 401 || $r->status() === 403 => 'El token de apiperu.dev no es válido o se acabaron las consultas del plan.',
                    ! empty($j['retryable']) || $r->status() >= 500 => 'SUNAT no responde en este momento. Intenta en unos segundos.',
                    ($j['code'] ?? '') === 'invalid_input' || $r->status() === 400 => 'SUNAT no acepta esos datos: revisa el RUC, la serie, el número, la fecha y el total.',
                    default => $j['message'] ?? 'No se pudo consultar.',
                });
            }
            $d = $j['data'];
            $estado = mb_strtoupper(trim((string) $d['comprobante_estado_descripcion']));
            [$color, $mensaje] = match (true) {
                str_contains($estado, 'ACEPTADO') => ['emerald', 'El comprobante es válido: está informado y aceptado en SUNAT.'],
                str_contains($estado, 'ANULADO') || str_contains($estado, 'BAJA') => ['rose', 'El comprobante fue anulado: ya no tiene validez.'],
                str_contains($estado, 'NO EXISTE') => ['amber', 'SUNAT no lo tiene registrado: aún no fue enviado o los datos (fecha, total) no coinciden.'],
                str_contains($estado, 'AUTORIZADO') => ['amber', 'Comprobante físico: SUNAT informa su autorización de impresión.'],
                default => ['slate', 'Estado informado por SUNAT.'],
            };

            return ['ok' => true, 'estado' => $estado, 'color' => $color, 'mensaje' => $mensaje,
                'empresa_estado' => $d['empresa_estado_descripcion'] ?? null, 'empresa_condicion' => $d['empresa_condicion_descripcion'] ?? null,
                'observaciones' => array_values(array_filter((array) ($d['observaciones'] ?? []), 'is_string'))];
        })();
        // Solo se guarda una respuesta de SUNAT (un error de conexión se vuelve a intentar)
        if ($resultado['ok']) {
            Cache::put($llave, $resultado, 600);
        }

        return $resultado;
    }

    private static function holape(string $ruta): ?array
    {
        try {
            $r = Http::timeout(8)->withOptions(['verify' => false])->get(self::HOLAPE.'/'.$ruta)->json();

            return ! empty($r['success']) ? ($r['data'] ?? null) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array{nombre:string, direccion:?string, estado:?string, condicion:?string, ubigeo:?string}|null */
    public static function ruc(string $ruc): ?array
    {
        if (! preg_match('/^\d{11}$/', $ruc)) {
            return null;
        }
        // Primero el padrón de SUNAT cargado en el propio sistema; si no está, consultas.holape.app
        if ($propio = PadronRuc::ruc($ruc)) {
            return $propio;
        }
        if (! ($d = self::holape("ruc/{$ruc}"))) {
            return null;
        }

        return ['nombre' => $d['razon_social'], 'direccion' => $d['direccion'] ?? null, 'estado' => $d['estado'] ?? null, 'condicion' => $d['condicion'] ?? null,
            'ubigeo' => $d['ubigeo'] ?? null];
    }

    /** @return array{nombre:string, fuente:string}|null */
    public static function dni(string $dni): ?array
    {
        if (! preg_match('/^\d{8}$/', $dni) || $dni === '00000000') {
            return null;
        }
        // Personas con RUC 10 en el padrón propio; si no, consultas.holape.app y apiperu.dev
        if ($propio = PadronRuc::dni($dni)) {
            return ['nombre' => $propio['nombre'], 'fuente' => 'padron'];
        }
        if (($d = self::holape("dni/{$dni}")) && ! empty($d['nombres'])) {
            return ['nombre' => trim($d['nombres']), 'fuente' => 'holape'];
        }
        if (($d = self::apiperu('dni', ['dni' => $dni])) && ! empty($d['nombre_completo'])) {
            return ['nombre' => trim($d['nombre_completo']), 'fuente' => 'apiperu'];
        }

        return null;
    }

    /**
     * Tipo de cambio SUNAT (compra y venta) que rige en esa fecha. Se guarda: la segunda consulta del mismo día no gasta plan.
     *
     * @return array{fecha:string, moneda:string, compra:float, venta:float, fecha_sunat:?string}|null
     */
    public static function tipoCambio(string $fecha, string $moneda = 'USD'): ?array
    {
        $moneda = strtoupper($moneda) === 'EUR' ? 'EUR' : 'USD';
        $guardado = Schema::hasTable('tipo_cambio')
            ? DB::table('tipo_cambio')->where('fecha', $fecha)->where('moneda', $moneda)->first() : null;
        if ($guardado) {
            return ['fecha' => $fecha, 'moneda' => $moneda, 'compra' => (float) $guardado->compra, 'venta' => (float) $guardado->venta, 'fecha_sunat' => $guardado->fecha_sunat];
        }

        $d = self::apiperu('tipo-de-cambio', ['fecha' => $fecha, 'moneda' => $moneda]);
        if (! $d || ! isset($d['compra'], $d['venta'])) {
            return null;
        }
        $fila = ['fecha' => $fecha, 'moneda' => $moneda, 'compra' => (float) $d['compra'], 'venta' => (float) $d['venta'], 'fecha_sunat' => $d['fecha_sunat'] ?? null];
        if (Schema::hasTable('tipo_cambio')) {
            DB::table('tipo_cambio')->insertOrIgnore($fila + ['creado' => now()]);
        }

        return $fila;
    }
}
