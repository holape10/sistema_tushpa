<?php
namespace App\Support\Sunat;

/** Leyenda 1000 de SUNAT: "SON CIENTO VEINTE CON 50/100 SOLES" */
class NumeroLetras
{
    private const UNIDADES = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ',
        'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE',
        'VEINTIUNO', 'VEINTIDOS', 'VEINTITRES', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISEIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
    private const DECENAS = ['', '', '', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    private const CENTENAS = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS',
        'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    public static function convertir(float $monto, string $moneda = 'SOLES'): string
    {
        $monto = round($monto, 2);
        $entero = (int) floor($monto);
        $centimos = (int) round(($monto - $entero) * 100);

        $letras = $entero === 0 ? 'CERO' : self::numero($entero);

        return 'SON ' . trim($letras) . ' CON ' . str_pad((string) $centimos, 2, '0', STR_PAD_LEFT) . '/100 ' . $moneda;
    }

    private static function numero(int $n): string
    {
        if ($n >= 1000000) {
            $millones = intdiv($n, 1000000);
            $resto = $n % 1000000;
            $txt = $millones === 1 ? 'UN MILLON' : self::numero($millones) . ' MILLONES';
            return $txt . ($resto ? ' ' . self::numero($resto) : '');
        }
        if ($n >= 1000) {
            $miles = intdiv($n, 1000);
            $resto = $n % 1000;
            $txt = $miles === 1 ? 'MIL' : self::apocope(self::numero($miles)) . ' MIL';
            return $txt . ($resto ? ' ' . self::numero($resto) : '');
        }
        if ($n >= 100) {
            if ($n === 100) {
                return 'CIEN';
            }
            $resto = $n % 100;
            return self::CENTENAS[intdiv($n, 100)] . ($resto ? ' ' . self::numero($resto) : '');
        }
        if ($n < 30) {
            return self::UNIDADES[$n];
        }
        $u = $n % 10;
        return self::DECENAS[intdiv($n, 10)] . ($u ? ' Y ' . self::UNIDADES[$u] : '');
    }

    // "VEINTIUNO MIL" -> "VEINTIUN MIL", "TREINTA Y UNO MIL" -> "TREINTA Y UN MIL"
    private static function apocope(string $txt): string
    {
        return preg_replace(['/VEINTIUNO$/', '/UNO$/'], ['VEINTIUN', 'UN'], $txt);
    }
}
