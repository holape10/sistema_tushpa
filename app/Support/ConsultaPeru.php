<?php
namespace App\Support;

use Illuminate\Support\Facades\{DB, Http, Schema};

/**
 * Consultas a fuentes públicas:
 *  - RUC: tu servicio consultas.holape.app (sin token).
 *  - DNI: consultas.holape.app y, si no lo encuentra, apiperu.dev (token APIPERU_TOKEN en .env).
 *  - Tipo de cambio SUNAT: apiperu.dev, guardado en la tabla tipo_cambio para no gastar consultas del plan.
 */
class ConsultaPeru
{
    private const HOLAPE = 'https://consultas.holape.app/api/v1';

    private static function apiperu(string $ruta, array $datos): ?array
    {
        $token = config('services.apiperu.token');
        if (!$token) {
            return null;
        }
        try {
            $r = Http::timeout(10)->withOptions(['verify' => false])->acceptJson()->withToken($token)
                ->post(rtrim(config('services.apiperu.url'), '/') . '/' . $ruta, $datos)->json();
            return !empty($r['success']) ? ($r['data'] ?? null) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function holape(string $ruta): ?array
    {
        try {
            $r = Http::timeout(8)->withOptions(['verify' => false])->get(self::HOLAPE . '/' . $ruta)->json();
            return !empty($r['success']) ? ($r['data'] ?? null) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array{nombre:string, direccion:?string, estado:?string, condicion:?string}|null */
    public static function ruc(string $ruc): ?array
    {
        if (!preg_match('/^\d{11}$/', $ruc) || !($d = self::holape("ruc/{$ruc}"))) {
            return null;
        }
        return ['nombre' => $d['razon_social'], 'direccion' => $d['direccion'] ?? null, 'estado' => $d['estado'] ?? null, 'condicion' => $d['condicion'] ?? null];
    }

    /** @return array{nombre:string, fuente:string}|null */
    public static function dni(string $dni): ?array
    {
        if (!preg_match('/^\d{8}$/', $dni) || $dni === '00000000') {
            return null;
        }
        if (($d = self::holape("dni/{$dni}")) && !empty($d['nombres'])) {
            return ['nombre' => trim($d['nombres']), 'fuente' => 'holape'];
        }
        if (($d = self::apiperu('dni', ['dni' => $dni])) && !empty($d['nombre_completo'])) {
            return ['nombre' => trim($d['nombre_completo']), 'fuente' => 'apiperu'];
        }
        return null;
    }

    /**
     * Tipo de cambio SUNAT (compra y venta) que rige en esa fecha. Se guarda: la segunda consulta del mismo día no gasta plan.
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
        if (!$d || !isset($d['compra'], $d['venta'])) {
            return null;
        }
        $fila = ['fecha' => $fecha, 'moneda' => $moneda, 'compra' => (float) $d['compra'], 'venta' => (float) $d['venta'], 'fecha_sunat' => $d['fecha_sunat'] ?? null];
        if (Schema::hasTable('tipo_cambio')) {
            DB::table('tipo_cambio')->insertOrIgnore($fila + ['creado' => now()]);
        }
        return $fila;
    }
}
