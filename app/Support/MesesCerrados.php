<?php

namespace App\Support;

use App\Support\Contabilidad\Contabilidad;
use Illuminate\Support\Facades\DB;

/**
 * Candado de meses: un mes cerrado (ya declarado) no se toca. Mientras esté cerrado no se registran, editan ni anulan
 * compras ni gastos con fecha de ese mes, ni se vuelve a centralizar su contabilidad.
 *
 * Es el mismo cierre del Libro Diario (conta_periodos): cerrarlo en Compras o en Contabilidad es lo mismo. Solo el
 * administrador cierra y reabre, y las dos cosas quedan en la bitácora.
 */
class MesesCerrados
{
    /** ¿El mes de esta fecha (Y-m-d) está cerrado? */
    public static function cerrado(string $ruc, ?string $fecha): bool
    {
        $periodo = self::periodo($fecha);

        return $periodo !== null && Contabilidad::cerrado($ruc, $periodo);
    }

    /**
     * Detiene la operación si alguna de las fechas cae en un mes cerrado.
     *
     * @param  array<int, string|null>  $fechas
     */
    public static function validar(string $ruc, array $fechas, string $que): void
    {
        foreach (array_unique(array_filter(array_map(fn ($f) => self::periodo($f), $fechas))) as $periodo) {
            if (Contabilidad::cerrado($ruc, $periodo)) {
                throw new \RuntimeException("{$que} es de ".Contabilidad::nombrePeriodo($periodo).' y ese mes está CERRADO (ya declarado). '
                    .'Si de verdad hay que corregirlo, el administrador debe reabrir el mes.');
            }
        }
    }

    /** @return array<int, string> periodos cerrados (AAAAMM), del más reciente al más antiguo */
    public static function lista(string $ruc, int $maximo = 36): array
    {
        return DB::table('conta_periodos')->where('IdEmpresa', $ruc)->where('estado', 'CERRADO')
            ->orderByDesc('periodo')->limit($maximo)->pluck('periodo')->map(fn ($p) => (string) $p)->all();
    }

    public static function cerrar(string $ruc, string $periodo, ?int $usuario): void
    {
        DB::table('conta_periodos')->updateOrInsert(['IdEmpresa' => $ruc, 'periodo' => $periodo],
            ['estado' => 'CERRADO', 'IdUsuario' => $usuario, 'updated_at' => now(), 'created_at' => now()]);
    }

    /** Reabre el mes; false si no estaba cerrado */
    public static function abrir(string $ruc, string $periodo): bool
    {
        return DB::table('conta_periodos')->where('IdEmpresa', $ruc)->where('periodo', $periodo)->delete() > 0;
    }

    /** "2026-10-15" → "202610" */
    public static function periodo(?string $fecha): ?string
    {
        return $fecha && preg_match('/^(\d{4})-(\d{2})/', $fecha, $m) ? $m[1].$m[2] : null;
    }
}
