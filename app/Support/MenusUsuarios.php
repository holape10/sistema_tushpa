<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * El menú que tiene la empresa es el de su administrador principal (el primero, creado con la empresa, al que el panel
 * le eligió las opciones). Nadie más de la empresa puede tener opciones fuera de ese menú.
 */
class MenusUsuarios
{
    /** Administrador principal: el primer usuario con rol de administrador */
    public static function administradorPrincipal(): ?int
    {
        $id = DB::table('role_user')->where('role_id', 2)->min('user_IdUsuario');

        return $id ? (int) $id : null;
    }

    /** @return array<int, int> mod_id del menú de la empresa (vacío = no se sabe: no se recorta nada) */
    public static function deLaEmpresa(): array
    {
        $principal = self::administradorPrincipal();

        return $principal ? DB::table('modulos_usuario')->where('user_IdUsuario', $principal)->pluck('mod_id')->map(fn ($m) => (int) $m)->unique()->values()->all() : [];
    }

    /**
     * Quita a cada usuario las opciones que no están en el menú de la empresa (lo demás que tenga se respeta).
     * Con $aplicar = false solo cuenta lo que se quitaría.
     *
     * @return array<int, array{usuario: string, quitar: int}> usuarios con opciones de más
     */
    public static function recortar(bool $aplicar): array
    {
        $empresa = self::deLaEmpresa();
        $principal = self::administradorPrincipal();
        if (! $empresa || ! $principal) {
            return [];
        }
        $sobran = DB::table('modulos_usuario as mu')->join('users as u', 'u.IdUsuario', '=', 'mu.user_IdUsuario')
            ->where('mu.user_IdUsuario', '!=', $principal)->whereNotIn('mu.mod_id', $empresa)
            ->groupBy('mu.user_IdUsuario', 'u.email')->selectRaw('mu.user_IdUsuario AS id, u.email, COUNT(*) AS n')->get();
        if ($aplicar && $sobran->isNotEmpty()) {
            DB::table('modulos_usuario')->whereIn('user_IdUsuario', $sobran->pluck('id'))->whereNotIn('mod_id', $empresa)->delete();
        }

        return $sobran->map(fn ($s) => ['usuario' => (string) $s->email, 'quitar' => (int) $s->n])->values()->all();
    }
}
