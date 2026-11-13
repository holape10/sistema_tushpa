<?php

namespace App\Support\Contabilidad;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ayuda con las cuentas de compras: la lista del plan contable para escribirlas (PCGE + las subcuentas que agregó la
 * empresa) y el centro de costo, obligatorio en las cuentas de gasto 62, 63, 64, 65, 67 y 68.
 */
class CuentasCompras
{
    /** Cuentas de gasto que llevan centro de costo */
    public const CON_CENTRO = ['62', '63', '64', '65', '67', '68'];

    /** Si la empresa no tiene centros de costo propios: los destinos del PCGE */
    public const CENTROS_PCGE = ['94' => 'GASTOS DE ADMINISTRACIÓN', '95' => 'GASTOS DE VENTAS', '97' => 'GASTOS FINANCIEROS'];

    public static function requiereCentro(?string $cuenta): bool
    {
        return in_array(substr(trim((string) $cuenta), 0, 2), self::CON_CENTRO, true);
    }

    /** @return array<string, string> código => nombre */
    public static function centros(string $ruc): array
    {
        $propios = Schema::hasTable('centros_costo')
            ? DB::table('centros_costo')->where('IdEmpresa', $ruc)->where('activo', 1)->orderBy('codigo')->pluck('nombre', 'codigo')->all()
            : [];

        return $propios ?: self::CENTROS_PCGE;
    }

    /** @return array<string, string> cuenta => nombre (el plan de la empresa manda sobre el PCGE) */
    public static function plan(string $ruc): array
    {
        $propias = Schema::hasTable('conta_plan')
            ? DB::table('conta_plan')->where('IdEmpresa', $ruc)->where(fn ($q) => $q->whereNull('estado')->orWhere('estado', '!=', 'INACTIVO'))
                ->pluck('descripcion', 'cuenta')->all()
            : [];
        $todas = $propias + Pcge::CUENTAS;
        ksort($todas, SORT_STRING);

        return $todas;
    }
}
