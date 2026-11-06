<?php

namespace App\Support;

use App\Models\User;

/**
 * Permisos especiales por acción (además de las opciones del menú). El administrador siempre puede todo; a los demás
 * el administrador les apaga lo que no quiera que hagan. Lo que no se apagó está permitido, como siempre fue: así
 * ningún negocio deja de funcionar al actualizar el sistema.
 */
class Acciones
{
    /** Clave => [nombre, explicación] (en este orden se muestran en la ficha del usuario) */
    public const LISTA = [
        'anular_ventas' => ['Anular ventas', 'Anular notas de venta, pedir a SUNAT la baja de boletas y facturas y descartar rechazados.'],
        'notas_credito' => ['Emitir notas de crédito y débito', 'Devoluciones y correcciones de boletas y facturas ya emitidas.'],
        'cambiar_precio' => ['Cobrar a otro precio', 'Cambiar el precio de lista al vender en el punto de venta.'],
        'medio_pago' => ['Cambiar el medio de pago', 'Corregir con qué pagó el cliente una venta del turno abierto.'],
        'compras_editar' => ['Editar y anular compras', 'Cambian el stock y el costo de los productos.'],
        'caja_anular' => ['Anular movimientos de caja, cobros y pagos', 'Ingresos y salidas de efectivo, cobros a clientes y pagos a proveedores.'],
    ];

    /** Si el usuario puede hacer esa acción */
    public static function puede(?User $user, string $accion): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->esAdmin()) {
            return true;
        }

        return ! in_array($accion, self::bloqueadas($user), true);
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

    /** @return array<int, string> */
    public static function bloqueadas(User $user): array
    {
        $lista = json_decode((string) ($user->acciones_bloqueadas ?? ''), true);

        return is_array($lista) ? array_values(array_intersect($lista, array_keys(self::LISTA))) : [];
    }

    /**
     * Lo que se guarda a partir de lo marcado en el formulario (marcado = permitido). Al administrador no se le bloquea nada.
     *
     * @param  array<int, string>  $permitidas
     */
    public static function guardables(array $permitidas, int $rol): ?string
    {
        $bloqueadas = $rol === 2 ? [] : array_values(array_diff(array_keys(self::LISTA), $permitidas));

        return $bloqueadas ? json_encode($bloqueadas) : null;
    }
}
