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

    /** Apagado por ahora (CENTRO_COSTO_COMPRAS=true en el .env lo enciende): sin el campo en su sistema no dejaba avanzar */
    public static function activo(): bool
    {
        return (bool) config('services.contabilidad.centro_costo', false);
    }

    public static function requiereCentro(?string $cuenta): bool
    {
        return self::activo() && in_array(substr(trim((string) $cuenta), 0, 2), self::CON_CENTRO, true);
    }

    /** @return array<string, string> código => nombre */
    public static function centros(string $ruc): array
    {
        $propios = Schema::hasTable('centros_costo')
            ? DB::table('centros_costo')->where('IdEmpresa', $ruc)->where('activo', 1)->orderBy('codigo')->pluck('nombre', 'codigo')->all()
            : [];

        return $propios ?: self::CENTROS_PCGE;
    }

    /**
     * Solo las cuentas de nivel 3 (las de movimiento, las que se usan en los asientos), para la ayuda al escribir.
     * Con el plan importado del sistema contable (Tipcta), las de Tipcta 3. Si no, las que no tienen subcuentas
     * (el plan de la empresa manda sobre el PCGE).
     *
     * @return array<string, string> cuenta => nombre
     */
    public static function plan(string $ruc): array
    {
        $activas = fn () => DB::table('conta_plan')->where('IdEmpresa', $ruc)->where(fn ($q) => $q->whereNull('estado')->orWhere('estado', '!=', 'INACTIVO'));
        if (Schema::hasTable('conta_plan') && Schema::hasColumn('conta_plan', 'tipcta') && $activas()->whereNotNull('tipcta')->exists()) {
            return $activas()->where('tipcta', 3)->orderBy('cuenta')->pluck('descripcion', 'cuenta')->all();
        }
        $propias = Schema::hasTable('conta_plan') ? $activas()->pluck('descripcion', 'cuenta')->all() : [];
        $todas = $propias + Pcge::CUENTAS;
        ksort($todas, SORT_STRING);
        $numeros = array_map('strval', array_keys($todas));
        $res = [];
        foreach ($numeros as $i => $cuenta) {
            $siguiente = $numeros[$i + 1] ?? '';
            // De título: la que tiene subcuentas (la siguiente empieza igual) o la de 2 dígitos (elemento)
            if (strlen($cuenta) > 2 && ! ($siguiente !== '' && str_starts_with($siguiente, $cuenta))) {
                $res[$cuenta] = $todas[$cuenta];
            }
        }

        return $res;
    }
}
