<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Búsqueda de productos "como piensa el usuario": cada palabra puede estar en cualquier parte del nombre,
 * sin importar mayúsculas ni tildes (la base compara "cafe" = "CAFÉ").
 * Ej.: "cafe leche" encuentra "CAFÉ CON LECHE"; "saltado lomo" encuentra "LOMO SALTADO".
 * Los códigos (interno, de barras) también se encuentran escritos completos.
 */
class Buscar
{
    /**
     * @param  array<int, string>  $columnas  donde se busca cada palabra (el nombre y, si se quiere, la categoría o descripción)
     * @param  array<int, string>  $codigos  columnas que se comparan con el texto completo (código interno, de barras)
     */
    public static function palabras(Builder|\Illuminate\Database\Eloquent\Builder $query, ?string $texto, array $columnas, array $codigos = []): void
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return;
        }
        $palabras = array_slice(array_filter(preg_split('/\s+/u', $texto)), 0, 6);
        $escapar = fn (string $p) => '%'.addcslashes($p, '%_\\').'%';

        $query->where(function ($w) use ($palabras, $columnas, $codigos, $texto, $escapar) {
            $w->where(function ($todas) use ($palabras, $columnas, $escapar) {
                foreach ($palabras as $p) {
                    $todas->where(function ($una) use ($p, $columnas, $escapar) {
                        foreach ($columnas as $c) {
                            $una->orWhere($c, 'like', $escapar($p));
                        }
                    });
                }
            });
            foreach ($codigos as $c) {
                $w->orWhere($c, $texto);
            }
        });
    }
}
