<?php

namespace App\Support\Impresion;

/**
 * Código de barras Code 128 (el que leen todos los lectores, con letras y números) sin librerías: las barras para dibujarlo
 * en una imagen (etiquetas en PDF) y los datos para que la ticketera lo imprima con su propio comando.
 * Números de largo par van en Code 128-C (más compacto); lo demás en Code 128-B.
 */
class CodigoBarras
{
    /** Anchos barra/espacio de cada símbolo 0..106 (106 = parada) */
    private const PATRONES = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const INICIO_B = 104;

    private const INICIO_C = 105;

    private const PARADA = 106;

    /** Lo que se puede codificar (Code 128-B: letras, números y signos comunes) */
    public static function limpiar(string $datos): string
    {
        return mb_substr(preg_replace('/[^\x20-\x7E]/', '', trim($datos)), 0, 40);
    }

    public static function esNumericoPar(string $datos): bool
    {
        return preg_match('/^\d+$/', $datos) === 1 && strlen($datos) % 2 === 0 && strlen($datos) >= 4;
    }

    /**
     * Los anchos (en módulos) de barras y espacios, alternados empezando por una barra.
     *
     * @return list<int>
     */
    public static function anchos(string $datos): array
    {
        $datos = self::limpiar($datos);
        if ($datos === '') {
            return [];
        }
        if (self::esNumericoPar($datos)) {
            $valores = array_map('intval', str_split($datos, 2));
            $inicio = self::INICIO_C;
        } else {
            $valores = array_map(fn ($c) => ord($c) - 32, str_split($datos));
            $inicio = self::INICIO_B;
        }
        $suma = $inicio;
        foreach ($valores as $i => $v) {
            $suma += $v * ($i + 1);
        }
        $simbolos = [$inicio, ...$valores, $suma % 103, self::PARADA];

        return array_map('intval', str_split(implode('', array_map(fn ($s) => self::PATRONES[$s], $simbolos))));
    }

    /**
     * Imagen PNG del código (para las etiquetas en PDF), con su margen blanco a los lados.
     *
     * @param  int  $modulo  ancho de la barra más delgada, en píxeles
     */
    public static function png(string $datos, int $alto = 60, int $modulo = 2): ?string
    {
        $anchos = self::anchos($datos);
        if (! $anchos || ! function_exists('imagecreatetruecolor')) {
            return null;
        }
        $margen = 10 * $modulo;
        $img = imagecreatetruecolor(array_sum($anchos) * $modulo + 2 * $margen, $alto);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        $negro = imagecolorallocate($img, 0, 0, 0);
        $x = $margen;
        foreach ($anchos as $i => $w) {
            if ($i % 2 === 0) {
                imagefilledrectangle($img, $x, 0, $x + $w * $modulo - 1, $alto - 1, $negro);
            }
            $x += $w * $modulo;
        }
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    /** Los datos para el comando de código de barras de la ticketera (GS k, Code 128): juego B o C y el contenido */
    public static function datosEscpos(string $datos): string
    {
        $datos = self::limpiar($datos);
        if (self::esNumericoPar($datos)) {
            return '{C'.implode('', array_map(fn ($par) => chr((int) $par), str_split($datos, 2)));
        }

        return '{B'.str_replace('{', '{{', $datos);
    }
}
