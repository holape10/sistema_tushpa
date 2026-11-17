<?php

namespace App\Support;

use App\Models\User;

/**
 * Permisos especiales por acción (además de las opciones del menú). El administrador siempre puede todo.
 * - Anular, editar ventas, notas de crédito y anular compras o movimientos de caja: SOLO el administrador, salvo que
 *   se le dé el permiso a un usuario (se guardan en acciones_permitidas).
 * - Precios y medio de pago: permitidos, salvo que el administrador los apague (se guardan en acciones_bloqueadas).
 */
class Acciones
{
    /** Clave => [nombre, explicación] (en este orden se muestran en la ficha del usuario) */
    public const LISTA = [
        'anular_ventas' => ['Anular ventas', 'Anular notas de venta (y boletas y facturas no enviadas, si la empresa lo activó), pedir a SUNAT la baja y descartar rechazados.'],
        'editar_ventas' => ['Editar ventas', 'Corregir productos, cliente o tipo de una venta que aún no se envió a SUNAT.'],
        'notas_credito' => ['Emitir notas de crédito y débito', 'Devoluciones y correcciones de boletas y facturas ya emitidas.'],
        'cambiar_precio' => ['Bajar el precio de lista', 'Cobrar más barato que el precio de lista al vender.'],
        'subir_precio' => ['Subir el precio de lista', 'Cobrar más caro que el precio de lista al vender (por ejemplo, un plato con algo extra).'],
        'medio_pago' => ['Cambiar el medio de pago', 'Corregir con qué pagó el cliente una venta del turno abierto.'],
        'compras_editar' => ['Editar y anular compras', 'Cambian el stock y el costo de los productos.'],
        'caja_anular' => ['Anular movimientos de caja, cobros y pagos', 'Ingresos y salidas de efectivo, cobros a clientes y pagos a proveedores.'],
    ];

    /** Solo el administrador, salvo que se le den a un usuario: anular y editar */
    public const SOLO_CON_PERMISO = ['anular_ventas', 'editar_ventas', 'notas_credito', 'compras_editar', 'caja_anular'];

    /** Si el usuario puede hacer esa acción */
    public static function puede(?User $user, string $accion): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->esAdmin()) {
            return true;
        }

        return in_array($accion, self::SOLO_CON_PERMISO, true)
            ? in_array($accion, self::lista($user->acciones_permitidas ?? null), true)
            : ! in_array($accion, self::bloqueadas($user), true);
    }

    /** Detiene la acción con un mensaje claro (RuntimeException: cada pantalla ya la muestra como sus otros avisos) */
    public static function exigir(?User $user, string $accion): void
    {
        if (! self::puede($user, $accion)) {
            throw new \RuntimeException(self::mensaje($accion));
        }
    }

    public static function mensaje(string $accion): string
    {
        return 'No tienes permiso para '.mb_strtolower(self::LISTA[$accion][0] ?? $accion).'. Pídeselo al administrador.';
    }

    /** @return array<int, string> las que se le apagaron (de las que están permitidas por defecto) */
    public static function bloqueadas(User $user): array
    {
        return array_values(array_diff(self::lista($user->acciones_bloqueadas ?? null), self::SOLO_CON_PERMISO));
    }

    /** @return array<int, string> */
    private static function lista(?string $json): array
    {
        $lista = json_decode((string) $json, true);

        return is_array($lista) ? array_values(array_intersect($lista, array_keys(self::LISTA))) : [];
    }

    /**
     * Lo que se ve marcado en la ficha del usuario (sin usuario: lo de un usuario nuevo).
     *
     * @return array<int, string>
     */
    public static function marcadas(?object $user): array
    {
        $normales = array_diff(array_keys(self::LISTA), self::SOLO_CON_PERMISO);
        if (! $user) {
            return array_values($normales);
        }

        return array_values(array_merge(array_diff($normales, self::lista($user->acciones_bloqueadas ?? null)),
            array_intersect(self::SOLO_CON_PERMISO, self::lista($user->acciones_permitidas ?? null))));
    }

    /**
     * Lo que se guarda a partir de lo marcado en el formulario (marcado = permitido). Al administrador no se le guarda nada.
     *
     * @param  array<int, string>  $marcadas
     * @return array{acciones_bloqueadas: ?string, acciones_permitidas: ?string}
     */
    public static function guardables(array $marcadas, int $rol): array
    {
        if ($rol === 2) {
            return ['acciones_bloqueadas' => null, 'acciones_permitidas' => null];
        }
        $bloqueadas = array_values(array_diff(array_keys(self::LISTA), self::SOLO_CON_PERMISO, $marcadas));
        $permitidas = array_values(array_intersect(self::SOLO_CON_PERMISO, $marcadas));

        return ['acciones_bloqueadas' => $bloqueadas ? json_encode($bloqueadas) : null, 'acciones_permitidas' => $permitidas ? json_encode($permitidas) : null];
    }
}
