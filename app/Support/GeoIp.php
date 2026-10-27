<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Región del Perú desde donde entra una visita, por su IP (DB-IP Lite: gratis, licencia CC BY 4.0, se cita en el pie
 * de la web). Solo se guardan los rangos del Perú. Es aproximado: muchas conexiones de celular salen por Lima.
 */
class GeoIp
{
    private const URL = 'https://download.db-ip.com/free/dbip-city-lite-%s.csv.gz';

    /** Región ("Piura", "Loreto"…) o null si no se sabe (IP local, de otro país o sin datos cargados) */
    public static function region(?string $ip): ?string
    {
        $binario = $ip ? @inet_pton($ip) : false;
        if ($binario === false || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        return Cache::remember('geoip:'.$ip, 86400, function () use ($binario) {
            try {
                $fila = DB::connection('central')->table('web_geoip')->where('v6', strlen($binario) === 16)
                    ->where('ip_desde', '<=', $binario)->orderByDesc('ip_desde')->first(['ip_hasta', 'region']);
            } catch (\Throwable $e) {
                return null;
            }

            return $fila && strcmp($binario, $fila->ip_hasta) <= 0 ? $fila->region : null;
        });
    }

    /**
     * Descarga la base del mes (o la del mes anterior si aún no sale) y carga solo los rangos del Perú.
     *
     * @return array{filas: int, archivo: string}
     */
    public static function actualizar(): array
    {
        $gz = storage_path('app/geoip.csv.gz');
        $archivo = null;
        foreach ([now(), now()->subMonthNoOverflow()] as $mes) {
            $url = sprintf(self::URL, $mes->format('Y-m'));
            $curl = curl_init($url);
            $salida = fopen($gz, 'wb');
            curl_setopt_array($curl, [CURLOPT_FILE => $salida, CURLOPT_FOLLOWLOCATION => true, CURLOPT_FAILONERROR => true,
                CURLOPT_CONNECTTIMEOUT => 30, CURLOPT_TIMEOUT => 1200]);
            $ok = curl_exec($curl);
            curl_close($curl);
            fclose($salida);
            if ($ok && filesize($gz) > 1024 * 1024) {
                $archivo = basename($url);
                break;
            }
        }
        if (! $archivo) {
            @unlink($gz);
            throw new \RuntimeException('No se pudo descargar la base de DB-IP.');
        }

        $db = DB::connection('central');
        $db->statement('DROP TABLE IF EXISTS web_geoip_nuevo');
        $db->statement('CREATE TABLE web_geoip_nuevo LIKE web_geoip');
        $f = gzopen($gz, 'rb');
        $lote = [];
        $filas = 0;
        // ip_desde, ip_hasta, continente, país, región, ciudad, latitud, longitud
        while (($linea = gzgets($f)) !== false) {
            if (! str_contains($linea, ',PE,')) {
                continue;
            }
            $c = str_getcsv(trim($linea));
            if (($c[3] ?? '') !== 'PE' || ($desde = @inet_pton($c[0])) === false || ($hasta = @inet_pton($c[1])) === false) {
                continue;
            }
            $lote[] = ['v6' => strlen($desde) === 16, 'ip_desde' => $desde, 'ip_hasta' => $hasta,
                'region' => mb_substr($c[4] ?? '', 0, 80) ?: null, 'ciudad' => mb_substr($c[5] ?? '', 0, 80) ?: null];
            if (count($lote) === 1000) {
                $db->table('web_geoip_nuevo')->insert($lote);
                $filas += 1000;
                $lote = [];
            }
        }
        if ($lote) {
            $db->table('web_geoip_nuevo')->insert($lote);
            $filas += count($lote);
        }
        gzclose($f);
        @unlink($gz);
        if ($filas < 1000) {
            $db->statement('DROP TABLE web_geoip_nuevo');
            throw new \RuntimeException("La base de DB-IP solo trajo {$filas} rangos del Perú: se mantiene la anterior.");
        }
        $db->statement('RENAME TABLE web_geoip TO web_geoip_viejo, web_geoip_nuevo TO web_geoip');
        $db->statement('DROP TABLE web_geoip_viejo');

        return ['filas' => $filas, 'archivo' => $archivo];
    }
}
