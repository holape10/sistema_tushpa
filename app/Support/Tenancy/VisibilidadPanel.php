<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;

/**
 * Qué empresas ve cada usuario del panel (una sola regla para la lista, el tablero, los tickets, el diagnóstico, los
 * comprobantes, las alertas, las contraseñas, el contrato y el parte de la mañana):
 * - el dueño del sistema: todas;
 * - un usuario de una marca: TODAS las empresas de su marca (todos los usuarios de la marca trabajan las mismas);
 * - un usuario de TUSHPA que no es el dueño: solo las que él creó (o le pasaron).
 */
class VisibilidadPanel
{
    public static function puedeVer(object $usuario, Cliente $cliente): bool
    {
        if ($usuario->es_dueno ?? false) {
            return true;
        }
        if ($usuario->marca_id ?? null) {
            return (int) $cliente->marca_id === (int) $usuario->marca_id;
        }

        return (int) $cliente->creado_por === (int) $usuario->id;
    }

    /**
     * Filtra una consulta de empresas (Eloquent o de la base central) a las que ve ese usuario.
     *
     * @template T of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     *
     * @param  T  $consulta
     * @param  string  $tabla  alias de la tabla clientes en la consulta ('' = sin alias)
     * @return T
     */
    public static function filtrar($consulta, object $usuario, string $tabla = '')
    {
        $col = fn (string $c) => $tabla !== '' ? $tabla.'.'.$c : $c;
        if ($usuario->es_dueno ?? false) {
            return $consulta;
        }
        if ($usuario->marca_id ?? null) {
            return $consulta->where($col('marca_id'), (int) $usuario->marca_id);
        }

        return $consulta->where($col('creado_por'), (int) $usuario->id);
    }
}
