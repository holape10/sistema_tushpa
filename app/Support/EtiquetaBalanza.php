<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Etiquetas de la balanza etiquetadora (supermercados): la fruta, la carne o el queso se pesan en una balanza que imprime
 * una etiqueta con un código de barras EAN-13 que empieza con 2:
 *
 *     2 X  P P P P P  V V V V V  C
 *     └┬┘  └───┬───┘  └───┬───┘  └ dígito verificador
 *   prefijo   PLU     peso en gramos (modo PESO) o precio en céntimos (modo PRECIO)
 *
 * En la caja se escanea como cualquier producto: el PLU es el código del producto en el sistema y el peso (o el precio)
 * pasa a la línea. Se activa por sucursal (Sucursales › Etiquetas de balanza); apagado no cambia nada. Un código de
 * barras que ya es de un producto se vende como siempre: la etiqueta solo se lee si ese código no es de ningún producto.
 */
class EtiquetaBalanza
{
    public const MODOS = ['PESO' => 'Peso (gramos)', 'PRECIO' => 'Precio (céntimos)'];

    /** @return array{prefijo: string, modo: string}|null la configuración de la sucursal (null = no lee etiquetas) */
    public static function configuracion(int $sucursal): ?array
    {
        $s = DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->first();
        if (! $s || empty($s->balanza_etiqueta)) {
            return null;
        }

        return ['prefijo' => preg_replace('/\D/', '', (string) ($s->balanza_prefijo ?? '2')) ?: '2', 'modo' => ($s->balanza_modo ?? 'PESO') === 'PRECIO' ? 'PRECIO' : 'PESO'];
    }

    /**
     * Lee el código si es una etiqueta de balanza válida (EAN-13, empieza con el prefijo, dígito verificador correcto).
     *
     * @param  array{prefijo: string, modo: string}  $cfg
     * @return array{plu: string, cantidad: ?float, importe: ?float}|null
     */
    public static function leer(string $codigo, array $cfg): ?array
    {
        if (! preg_match('/^2\d{12}$/', $codigo) || ! str_starts_with($codigo, $cfg['prefijo']) || ! self::verificadorValido($codigo)) {
            return null;
        }
        $plu = substr($codigo, 2, 5);
        $valor = (int) substr($codigo, 7, 5);
        if ($valor <= 0) {
            return null;
        }

        return $cfg['modo'] === 'PRECIO'
            ? ['plu' => $plu, 'cantidad' => null, 'importe' => round($valor / 100, 2)]
            : ['plu' => $plu, 'cantidad' => round($valor / 1000, 3), 'importe' => null];
    }

    /** Dígito verificador EAN-13 */
    public static function verificadorValido(string $codigo): bool
    {
        $suma = 0;
        for ($i = 0; $i < 12; $i++) {
            $suma += (int) $codigo[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $suma % 10) % 10 === (int) $codigo[12];
    }

    /** Los códigos con que se busca el producto del PLU: tal cual ("00123") y sin ceros a la izquierda ("123") */
    public static function codigosDelPlu(string $plu): array
    {
        return array_values(array_unique([$plu, ltrim($plu, '0') ?: '0']));
    }
}
