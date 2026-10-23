<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unidades de medida más usadas en el Perú (códigos del catálogo 03 de SUNAT) y cómo se llamaban en sistemas antiguos.
 * Así ningún producto importado se queda como "Unidad" por tener un código que la tabla no conocía.
 */
class Unidades
{
    /** @var array<string, array{0: string, 1: string}> código SUNAT => [nombre, abreviatura en el ticket] */
    public const CATALOGO = [
        // Cantidad
        'NIU' => ['Unidad', 'UNI'], 'ZZ' => ['Servicio', 'SER'], 'DZN' => ['Docena', 'DOC'], 'CEN' => ['Ciento', 'CIE'], 'MIL' => ['Millar', 'MIL'],
        'PR' => ['Par', 'PAR'], 'SET' => ['Juego', 'JGO'], 'KT' => ['Kit', 'KIT'],
        // Peso
        'KGM' => ['Kilogramo', 'KG'], 'GRM' => ['Gramo', 'GR'], 'TNE' => ['Tonelada', 'TN'], 'LBR' => ['Libra', 'LB'], 'ONZ' => ['Onza', 'OZ'],
        // Volumen
        'LTR' => ['Litro', 'LT'], 'MLT' => ['Mililitro', 'ML'], 'GLL' => ['Galón', 'GL'], 'MTQ' => ['Metro cúbico', 'M3'],
        // Longitud y área
        'MTR' => ['Metro', 'M'], 'CMT' => ['Centímetro', 'CM'], 'MMT' => ['Milímetro', 'MM'], 'INH' => ['Pulgada', 'PLG'], 'FOT' => ['Pie', 'PIE'],
        'MTK' => ['Metro cuadrado', 'M2'], 'FTK' => ['Pie cuadrado', 'P2'],
        // Envases
        'BG' => ['Bolsa', 'BOL'], 'BX' => ['Caja', 'CJA'], 'SA' => ['Saco', 'SAC'], 'BO' => ['Botella', 'BOT'], 'CA' => ['Lata', 'LAT'],
        'FR' => ['Frasco', 'FCO'], 'BJ' => ['Balde', 'BAL'], 'PK' => ['Pack', 'PAK'], 'NMP' => ['Paquete', 'PQT'], 'RO' => ['Rollo', 'ROL'],
        'TU' => ['Tubo', 'TUB'], 'BE' => ['Fardo', 'FAR'], 'CT' => ['Cartón', 'CAR'], 'ST' => ['Pliego', 'PLI'], 'SR' => ['Tira', 'TRA'],
        'DS' => ['Display', 'DSP'], 'BLL' => ['Barril', 'BRL'], 'CY' => ['Cilindro', 'CIL'], 'AM' => ['Ampolla', 'AMP'],
        // Tiempo (hotel, estacionamiento, gimnasio)
        'HUR' => ['Hora', 'HR'], 'DAY' => ['Día', 'DIA'], 'MON' => ['Mes', 'MES'],
    ];

    /** Cómo escribían la unidad los sistemas antiguos o los Excel => código SUNAT */
    public const ALIAS = [
        'UND' => 'NIU', 'UNI' => 'NIU', 'UN' => 'NIU', 'U' => 'NIU', 'UNIDAD' => 'NIU', 'PZA' => 'NIU', 'PZ' => 'NIU',
        'KG' => 'KGM', 'KGS' => 'KGM', 'KILO' => 'KGM', 'GR' => 'GRM', 'G' => 'GRM', 'TN' => 'TNE', 'LB' => 'LBR', 'OZ' => 'ONZ',
        'LT' => 'LTR', 'L' => 'LTR', 'LTS' => 'LTR', 'ML' => 'MLT', 'GL' => 'GLL', 'GAL' => 'GLL', 'M3' => 'MTQ',
        'M' => 'MTR', 'MT' => 'MTR', 'MTS' => 'MTR', 'CM' => 'CMT', 'MM' => 'MMT', 'PLG' => 'INH', 'PUL' => 'INH', 'PIE' => 'FOT', 'M2' => 'MTK', 'P2' => 'FTK',
        'BOL' => 'BG', 'CJA' => 'BX', 'CJ' => 'BX', 'CAJ' => 'BX', 'SAC' => 'SA', 'BOT' => 'BO', 'LAT' => 'CA', 'FCO' => 'FR', 'BAL' => 'BJ',
        'PAQ' => 'NMP', 'PQT' => 'NMP', 'PAK' => 'PK', 'ROL' => 'RO', 'DOC' => 'DZN', 'PAR' => 'PR', 'NPR' => 'PR', 'JGO' => 'SET', 'HR' => 'HUR', 'DIA' => 'DAY',
    ];

    /**
     * Códigos vigentes del catálogo 03 (UN/ECE Recomendación 20): los únicos que se mandan a SUNAT tal cual.
     * Los de envases (BX caja, BG bolsa, PK pack, SA saco…) figuran como eliminados en la recomendación y los inventados
     * (TAP, POR…) no existen: en el sistema y en el ticket siguen saliendo con su nombre, pero en el XML van como NIU.
     */
    public const VIGENTES_SUNAT = [
        'NIU', 'ZZ', 'C62', 'DZN', 'DZP', 'CEN', 'MIL', 'GRO', 'PR', 'SET', 'KT', 'NMP', 'RM', 'LEF', 'SR',
        'KGM', 'GRM', 'MGM', 'TNE', 'STN', 'LTN', 'LBR', 'ONZ',
        'LTR', 'MLT', 'HLT', 'GLL', 'GLI', 'MTQ', 'CMQ', 'MMQ', 'FTQ', 'BLL',
        'MTR', 'KMT', 'CMT', 'MMT', 'INH', 'FOT', 'YRD', 'MTK', 'CMK', 'MMK', 'FTK',
        'SEC', 'MIN', 'HUR', 'DAY', 'WEE', 'MON', 'ANN', 'KWH', 'MWH',
    ];

    /** Códigos antiguos que tienen un reemplazo vigente */
    private const REEMPLAZO_SUNAT = ['NPR' => 'PR', 'KTM' => 'KMT', 'UND' => 'NIU', 'UNI' => 'NIU'];

    /** El código que va en el XML para SUNAT: el vigente equivalente o NIU (unidad) si no tiene uno */
    public static function sunat(?string $umecod): string
    {
        $cod = strtoupper(trim((string) $umecod));
        $cod = self::REEMPLAZO_SUNAT[$cod] ?? $cod;

        return in_array($cod, self::VIGENTES_SUNAT, true) ? $cod : 'NIU';
    }

    /** @var array<string, string>|null nombre de cada unidad (Botella, Litro…), leído una vez por petición */
    private static ?array $nombres = null;

    /** Nombre de la unidad para leerlo fácil: "Botella", y en plural si la cantidad no es 1 ("Botellas", "Galones") */
    public static function nombre(string $umecod, ?float $cantidad = null): string
    {
        self::$nombres ??= Schema::hasTable('unidad_medida')
            ? DB::table('unidad_medida')->pluck('umenom', 'umecod')->map(fn ($n) => trim((string) $n))->all() : [];
        $nombre = (self::$nombres[$umecod] ?? '') ?: (self::CATALOGO[$umecod][0] ?? $umecod);

        return $cantidad !== null && abs($cantidad - 1) < 1e-9 ? $nombre : self::plural($nombre);
    }

    /** Botella → Botellas, Galón → Galones, Unidad → Unidades, Metro cúbico → Metros cúbicos (los conectores no cambian) */
    public static function plural(string $nombre): string
    {
        return implode(' ', array_map(function (string $p) {
            $minus = mb_strtolower($p);
            $mayus = $p === mb_strtoupper($p);
            if ($minus === 'mes') {
                return $p.($mayus ? 'ES' : 'es');
            }
            if ($p === '' || in_array($minus, ['de', 'o', 'y', 'por', 'en', 'x'], true) || str_ends_with($minus, 's') || ! preg_match('/\pL$/u', $p) || mb_strlen($p) <= 2) {
                return $p;
            }
            $fin = mb_substr($minus, -1);
            $nuevo = match (true) {
                in_array($fin, ['a', 'e', 'i', 'o', 'u', 'á', 'é', 'í', 'ó', 'ú'], true) => $p.'s',
                str_ends_with($minus, 'ón') => mb_substr($p, 0, -2).($mayus ? 'ONES' : 'ones'),
                $fin === 'z' => mb_substr($p, 0, -1).($mayus ? 'CES' : 'ces'),
                // Españolas terminadas en l, r, n, d, j: Barril → Barriles, Unidad → Unidades (táper es préstamo: Tapers)
                in_array($fin, ['l', 'r', 'n', 'd', 'j'], true) && $minus !== 'taper' => $p.($mayus ? 'ES' : 'es'),
                // Préstamos del inglés: Kit → Kits, Pack → Packs, Display → Displays
                default => $p.($mayus ? 'S' : 's'),
            };

            return $mayus ? mb_strtoupper($nuevo) : $nuevo;
        }, explode(' ', $nombre)));
    }

    /**
     * Cantidad en la unidad principal y su equivalente: 4.08 botellas (1 botella = 25 onzas) → "4 Botellas + 2 Onzas".
     * Null si el producto no tiene unidad equivalente (se muestra el número de siempre).
     */
    public static function mixto(float $cantidad, string $ume, ?string $umeEquivalente, float $factor): ?string
    {
        if (! $umeEquivalente || $umeEquivalente === $ume || $factor <= 1) {
            return null;
        }
        $signo = $cantidad < 0 ? '-' : '';
        $total = abs($cantidad);
        $enteras = (int) floor($total + 1e-9);
        $resto = round(($total - $enteras) * $factor, 2);
        if ($resto >= $factor - 0.005) {
            $enteras++;
            $resto = 0.0;
        }
        $partes = array_filter([$enteras ? $enteras.' '.self::nombre($ume, $enteras) : null, $resto > 0 ? self::numero($resto).' '.self::nombre($umeEquivalente, $resto) : null]);

        return $partes ? $signo.implode(' + ', $partes) : '0 '.self::nombre($ume, 0);
    }

    /** La cantidad lista para leer: "538 Botellas", "1 Caja" o, con unidad equivalente, "3 Litros + 2 Onzas" */
    public static function texto(float $cantidad, string $ume, ?string $umeEquivalente = null, float $factor = 0): string
    {
        return self::mixto($cantidad, $ume, $umeEquivalente, $factor) ?? self::numero($cantidad).' '.self::nombre($ume, $cantidad);
    }

    private static function numero(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ','), '0'), '.');
    }

    /** Agrega a la tabla las unidades del catálogo que falten (no cambia las que ya existen) */
    public static function completar(): int
    {
        if (! Schema::hasTable('unidad_medida')) {
            return 0;
        }
        $existentes = DB::table('unidad_medida')->pluck('umecod')->map(fn ($u) => strtoupper($u))->flip();
        $conEstado = Schema::hasColumn('unidad_medida', 'umeest');
        $nuevas = 0;
        foreach (self::CATALOGO as $cod => [$nombre, $abrev]) {
            if (isset($existentes[$cod])) {
                continue;
            }
            DB::table('unidad_medida')->insert(['umecod' => $cod, 'umenom' => $nombre, 'umecin' => $abrev] + ($conEstado ? ['umeest' => 'Activo'] : []));
            $nuevas++;
        }

        return $nuevas;
    }

    /** El código SUNAT para una unidad escrita en un sistema antiguo (KG, UND, MTS…); null si no se reconoce */
    public static function codigo(?string $escrita): ?string
    {
        $cod = strtoupper(trim((string) $escrita));
        if ($cod === '') {
            return null;
        }
        $cod = self::ALIAS[$cod] ?? $cod;
        if (DB::table('unidad_medida')->where('umecod', $cod)->exists()) {
            return $cod;
        }
        if (isset(self::CATALOGO[$cod])) {
            self::completar();

            return $cod;
        }

        return null;
    }
}
