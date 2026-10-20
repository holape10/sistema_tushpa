<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Nombre corto de quien atendió (primer nombre y primer apellido) para tickets, comandas y la pantalla de cocina.
 * Se activa por sucursal (empresa_negocios.nombre_corto); si no, se usa el nombre completo.
 *
 *   ÁLVARO JUNIOR IZQUIERDO TUESTA   → ÁLVARO IZQUIERDO
 *   LEONARDO DEL CASTILLO PEÑA       → LEONARDO DEL CASTILLO
 *   TANGOA PACAYA, JIM BORIS         → JIM TANGOA
 *   LESTER LAVI VASQUEZ              → LESTER LAVI
 */
class NombreCorto
{
    /** Palabras que van pegadas al apellido (DEL CASTILLO, LA CRUZ, DE LOS RÍOS) */
    private const PARTICULAS = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'SAN', 'Y'];

    /** @var array<int, bool> */
    private static array $activo = [];

    public static function activo(int $sucursal): bool
    {
        return self::$activo[$sucursal] ??= (bool) DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('nombre_corto');
    }

    /** El nombre como debe salir en esa sucursal */
    public static function para(int $sucursal, ?string $nombre): ?string
    {
        return $nombre !== null && self::activo($sucursal) ? self::de($nombre) : $nombre;
    }

    public static function de(string $nombre): string
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
        // Razón social o nombre con números (el usuario administrador suele ser la empresa): tal cual
        if ($nombre === '' || preg_match('/[0-9.]/', $nombre)) {
            return $nombre;
        }

        // "APELLIDOS, NOMBRES"
        if (str_contains($nombre, ',')) {
            [$apellidos, $nombres] = array_map('trim', explode(',', $nombre, 2));

            return trim(strtok($nombres, ' ').' '.self::primerApellido(explode(' ', $apellidos)));
        }

        $palabras = explode(' ', $nombre);
        $n = count($palabras);
        if ($n <= 2) {
            return $nombre;
        }
        if ($n === 3) {
            return $palabras[0].' '.$palabras[1];
        }

        // Nombres y luego dos apellidos: el apellido paterno es el penúltimo (con su "DEL", "LA"… si lo tiene)
        $paterno = $n - 2;
        while ($paterno > 1 && in_array($palabras[$paterno - 1], self::PARTICULAS, true)) {
            $paterno--;
        }

        return $palabras[0].' '.implode(' ', array_slice($palabras, $paterno, $n - 1 - $paterno));
    }

    /** @param  array<int, string>  $apellidos */
    private static function primerApellido(array $apellidos): string
    {
        $primero = [];
        foreach ($apellidos as $palabra) {
            $primero[] = $palabra;
            if (! in_array($palabra, self::PARTICULAS, true)) {
                break;
            }
        }

        return implode(' ', $primero);
    }
}
