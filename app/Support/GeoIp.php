<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Desde dónde entra una visita, por su IP (DB-IP Lite: gratis, licencia CC BY 4.0, se cita en el pie de la web):
 * el país de todo el mundo y, en el Perú, también la región y la ciudad. Es aproximado: muchas conexiones de celular
 * salen por Lima.
 */
class GeoIp
{
    private const URL = 'https://download.db-ip.com/free/dbip-city-lite-%s.csv.gz';

    /** Región del Perú ("Piura", "Loreto"…) o null si no se sabe (IP local, de otro país o sin datos cargados) */
    public static function region(?string $ip): ?string
    {
        $u = self::ubicacion($ip);

        return $u && $u['pais'] === 'PE' ? $u['region'] : null;
    }

    /**
     * País (código ISO de 2 letras) y, en el Perú, región y ciudad; null si no se sabe.
     *
     * @return array{pais: string, region: ?string, ciudad: ?string}|null
     */
    public static function ubicacion(?string $ip): ?array
    {
        $binario = $ip ? @inet_pton($ip) : false;
        if ($binario === false || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        return Cache::remember('geoip2:'.$ip, 86400, function () use ($binario) {
            try {
                $fila = DB::connection('central')->table('web_geoip')->where('v6', strlen($binario) === 16)
                    ->where('ip_desde', '<=', $binario)->orderByDesc('ip_desde')->first(['ip_hasta', 'pais', 'region', 'ciudad']);
            } catch (\Throwable $e) {
                return null;
            }

            return $fila && strcmp($binario, $fila->ip_hasta) <= 0
                ? ['pais' => (string) $fila->pais, 'region' => $fila->region, 'ciudad' => $fila->ciudad] : null;
        });
    }

    /** Nombre del país en español (los más comunes); si no está, el código */
    public static function nombrePais(?string $codigo): string
    {
        return self::PAISES[$codigo] ?? ($codigo ?: 'Desconocido');
    }

    /** Bandera del país con emojis (🇵🇪) a partir del código */
    public static function bandera(?string $codigo): string
    {
        return $codigo && preg_match('/^[A-Z]{2}$/', $codigo)
            ? mb_chr(0x1F1E6 + ord($codigo[0]) - 65).mb_chr(0x1F1E6 + ord($codigo[1]) - 65) : '🌐';
    }

    private const PAISES = [
        'PE' => 'Perú', 'CO' => 'Colombia', 'EC' => 'Ecuador', 'BO' => 'Bolivia', 'CL' => 'Chile', 'AR' => 'Argentina', 'BR' => 'Brasil',
        'VE' => 'Venezuela', 'MX' => 'México', 'US' => 'Estados Unidos', 'ES' => 'España', 'CA' => 'Canadá', 'PY' => 'Paraguay',
        'UY' => 'Uruguay', 'PA' => 'Panamá', 'CR' => 'Costa Rica', 'GT' => 'Guatemala', 'DO' => 'República Dominicana', 'CU' => 'Cuba',
        'HN' => 'Honduras', 'SV' => 'El Salvador', 'NI' => 'Nicaragua', 'PR' => 'Puerto Rico', 'IT' => 'Italia', 'FR' => 'Francia',
        'DE' => 'Alemania', 'GB' => 'Reino Unido', 'NL' => 'Países Bajos', 'PT' => 'Portugal', 'CN' => 'China', 'JP' => 'Japón',
        'IN' => 'India', 'RU' => 'Rusia', 'SG' => 'Singapur', 'IE' => 'Irlanda', 'SE' => 'Suecia', 'CH' => 'Suiza', 'AU' => 'Australia',
    ];

    /**
     * Descarga la base del mes (o la del mes anterior si aún no sale): del Perú carga cada rango con su región y ciudad;
     * del resto del mundo, solo el país (los rangos seguidos del mismo país se juntan en uno).
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
        $otro = null;   // rango de otro país que se va juntando
        $guardar = function (array $fila) use ($db, &$lote) {
            $lote[] = $fila;
            if (count($lote) === 1000) {
                $db->table('web_geoip_nuevo')->insert($lote);
                $lote = [];
            }
        };
        // ip_desde, ip_hasta, continente, país, región, ciudad, latitud, longitud
        while (($linea = gzgets($f)) !== false) {
            $partes = explode(',', $linea, 5);
            $pais = $partes[3] ?? '';
            if (! preg_match('/^[A-Z]{2}$/', $pais) || ($desde = @inet_pton($partes[0])) === false || ($hasta = @inet_pton($partes[1])) === false) {
                continue;
            }
            $v6 = strlen($desde) === 16;
            if ($pais !== 'PE') {
                if ($otro && $otro['pais'] === $pais && $otro['v6'] === $v6) {
                    $otro['ip_hasta'] = $hasta;
                } else {
                    if ($otro) {
                        $guardar($otro);
                    }
                    $otro = ['v6' => $v6, 'ip_desde' => $desde, 'ip_hasta' => $hasta, 'pais' => $pais, 'region' => null, 'ciudad' => null];
                }

                continue;
            }
            if ($otro) {
                $guardar($otro);
                $otro = null;
            }
            $c = str_getcsv(trim($linea));
            $guardar(['v6' => $v6, 'ip_desde' => $desde, 'ip_hasta' => $hasta, 'pais' => 'PE',
                'region' => mb_substr($c[4] ?? '', 0, 80) ?: null, 'ciudad' => mb_substr($c[5] ?? '', 0, 80) ?: null]);
            $filas++;
        }
        if ($otro) {
            $guardar($otro);
        }
        if ($lote) {
            $db->table('web_geoip_nuevo')->insert($lote);
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
