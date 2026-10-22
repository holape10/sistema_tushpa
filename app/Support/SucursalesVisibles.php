<?php

namespace App\Support;

use App\Models\EmpresaNegocio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Qué sucursales puede ver cada usuario en los reportes, el panel y la lista de ventas:
 * el administrador ve cualquiera (o todas juntas); los demás (caja, mozo, contador…) solo la suya,
 * aunque cambien el ?sucursal= de la dirección.
 */
class SucursalesVisibles
{
    /** Valor del filtro para "Todas las sucursales" (solo administrador) */
    public const TODAS = 0;

    /** @return Collection<int, EmpresaNegocio> */
    public static function de(User $user): Collection
    {
        return EmpresaNegocio::where('IdEmpresa', $user->IdEmpresa)
            ->when(! $user->esAdmin(), fn ($q) => $q->where('id_empresa_negocio', $user->id_empresa_negocio))
            ->orderBy('id_empresa_negocio')->get();
    }

    /** Sucursal pedida en el filtro: la suya por defecto; TODAS solo si es administrador, hay más de una y la pantalla lo permite */
    public static function elegida(Request $request, User $user, bool $permiteTodas = true): int
    {
        $propia = (int) $user->id_empresa_negocio;
        if (! $user->esAdmin()) {
            return $propia;
        }
        $pedida = (int) $request->get('sucursal', $propia);
        $visibles = self::de($user);
        if ($pedida === self::TODAS) {
            return $permiteTodas && $visibles->count() > 1 ? self::TODAS : $propia;
        }
        abort_unless($visibles->contains('id_empresa_negocio', $pedida), 403);

        return $pedida;
    }

    /** @return array<int, int> Ids para whereIn (todas las de la empresa o solo una) */
    public static function ids(User $user, int $sucursal): array
    {
        return $sucursal === self::TODAS ? self::de($user)->pluck('id_empresa_negocio')->map(fn ($id) => (int) $id)->all() : [$sucursal];
    }

    public static function nombre(User $user, int $sucursal): string
    {
        return $sucursal === self::TODAS ? 'TODAS LAS SUCURSALES' : (string) (self::de($user)->firstWhere('id_empresa_negocio', $sucursal)->nombre_comercial ?? '');
    }
}
