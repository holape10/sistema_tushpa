<?php

namespace App\Support;

use App\Models\MedioPago;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Propina que deja el cliente al pagar. No es venta: no va en el comprobante ni a SUNAT, ni suma al total.
 * Queda anotada con el cobro (y por él, con su mozo y su turno) para entregarla a los mozos y verla en caja.
 */
class Propinas
{
    /** Tope por cobro: más que esto seguro es un error de tipeo */
    public const MAXIMO = 2000;

    /**
     * Anota la propina de un cobro (dentro de la transacción del cobro).
     *
     * @param  array  $datos  propina (monto en soles), propina_medio (id_med_pag; vacío = el primero del cobro)
     */
    public static function registrar(User $user, Turno $turno, int $cabId, array $datos): void
    {
        $monto = round((float) ($datos['propina'] ?? 0), 2);
        if ($monto <= 0) {
            return;
        }
        if (! Descuentos::config((int) $user->id_empresa_negocio)->propina) {
            throw new \RuntimeException('Esta sucursal no tiene activada la propina.');
        }
        if ($monto > self::MAXIMO) {
            throw new \RuntimeException('La propina de S/ '.number_format($monto, 2).' es muy alta. Revisa el monto.');
        }
        $medios = MedioPago::where('id_empresa_negocio', $user->id_empresa_negocio)->pluck('id_med_pag')->map(fn ($id) => (int) $id);
        $medio = (int) ($datos['propina_medio'] ?? 0) ?: (int) (((array) ($datos['id_med_pag'] ?? []))[0] ?? 0);
        if (! $medios->contains($medio)) {
            $medio = (int) MedioPago::where('id_empresa_negocio', $user->id_empresa_negocio)->orderByDesc('predeterminado')->value('id_med_pag');
        }

        DB::table('propinas')->insert([
            'IdCpe_cabecera' => $cabId, 'id_turno' => $turno->id_turno, 'id_med_pag' => $medio ?: null, 'monto' => $monto,
            'id_empresa_negocio' => $user->id_empresa_negocio, 'created_at' => now(),
        ]);
    }

    public static function delComprobante(int $cabId): float
    {
        return round((float) DB::table('propinas')->where('IdCpe_cabecera', $cabId)->sum('monto'), 2);
    }

    /**
     * Propinas de un turno por medio de pago (sin las de ventas anuladas).
     *
     * @return Collection<int, object{nom_med_pag: ?string, monto: float, cobros: int}>
     */
    public static function delTurno(int $idTurno): Collection
    {
        return DB::table('propinas as p')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'p.IdCpe_cabecera')
            ->leftJoin('medios_pagos as m', 'm.id_med_pag', '=', 'p.id_med_pag')
            ->where('p.id_turno', $idTurno)->whereNull('c.ccabaj')
            ->groupBy('p.id_med_pag', 'm.nom_med_pag')
            ->get(['m.nom_med_pag', DB::raw('SUM(p.monto) as monto'), DB::raw('COUNT(*) as cobros')])
            ->map(fn ($p) => (object) ['nom_med_pag' => $p->nom_med_pag, 'monto' => round((float) $p->monto, 2), 'cobros' => (int) $p->cobros]);
    }
}
