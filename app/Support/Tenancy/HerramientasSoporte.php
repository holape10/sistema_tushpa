<?php

namespace App\Support\Tenancy;

use App\Http\Controllers\SucursalController;
use App\Http\Controllers\TurnoController;
use App\Models\Mesa;
use App\Models\Turno;
use App\Support\Bitacora;
use Illuminate\Support\Facades\DB;

/**
 * Arreglos de un clic desde el panel (🩺 Diagnóstico), para lo que antes se arreglaba a mano en la base. Corren con la
 * conexión de la empresa ya abierta, cada uno en su transacción, y quedan en su bitácora como hechos por soporte.
 */
class HerramientasSoporte
{
    public const ACCIONES = ['cerrar_turno', 'igualar_correlativo'];

    /**
     * @param  array<string, mixed>  $datos
     * @return string qué se hizo, para mostrarlo
     */
    public static function ejecutar(string $accion, array $datos, string $quien): string
    {
        return match ($accion) {
            'cerrar_turno' => self::cerrarTurno((int) ($datos['turno'] ?? 0), $quien),
            'igualar_correlativo' => self::igualarCorrelativo((int) ($datos['sucursal'] ?? 0), (string) ($datos['clave'] ?? ''), $quien),
            default => throw new \InvalidArgumentException('Ese arreglo no existe.'),
        };
    }

    /** Cierra un turno que se quedó abierto, igual que el cierre de caja pero sin contar el efectivo */
    public static function cerrarTurno(int $idTurno, string $quien): string
    {
        return DB::transaction(function () use ($idTurno, $quien) {
            $turno = Turno::where('id_turno', $idTurno)->lockForUpdate()->first();
            if (! $turno || $turno->estado !== 'ABIERTO') {
                throw new \RuntimeException('Ese turno ya no está abierto.');
            }
            $r = TurnoController::resumen($turno);
            $mesas = Mesa::where('id_empresa_negocio', $turno->id_empresa_negocio)->get();
            $turno->update(['estado' => 'CERRADO', 'cierre' => now(), 'montocierre' => null, 'total_ingresos' => $r['ingresos'], 'total_gastos' => $r['egresos'],
                'totalocupados' => $mesas->where('mes_est', '!=', 'Libre')->count(), 'totallibres' => $mesas->where('mes_est', 'Libre')->count()]);
            DB::table('turno_medio_pago')->where('id_turno', $turno->id_turno)->delete();
            foreach ($r['ventasPorMedio'] as $v) {
                DB::table('turno_medio_pago')->insert(['id_turno' => $turno->id_turno, 'id_med_pag' => $v->id_med_pag, 'monto' => $v->monto,
                    'id_empresa_negocio' => $turno->id_empresa_negocio]);
            }
            $texto = 'Soporte cerró el turno N° '.($turno->turno ?? $turno->id_turno).' que estaba abierto desde el '.$turno->apertura->format('d/m/Y H:i').' (sin contar el efectivo)';
            Bitacora::registrar('caja.cierre_soporte', $texto, null, ['usuario' => null, 'nombre' => $quien, 'sucursal' => $turno->id_empresa_negocio,
                'referencia' => ['turno', $turno->id_turno]]);

            return $texto.'.';
        });
    }

    /** Pone el correlativo de una serie en el último número emitido: así el próximo no sale repetido */
    public static function igualarCorrelativo(int $sucursal, string $clave, string $quien): string
    {
        $c = SucursalController::SERIES[$clave] ?? null;
        if (! $c || in_array((string) ($c['tdocod'] ?? $clave), ['PR', '09'], true)) {
            throw new \InvalidArgumentException('Esa serie no se puede corregir desde aquí.');
        }
        $atrasado = collect(SaludCliente::correlativosAtrasados())->first(fn ($a) => $a['sucursal'] === $sucursal && $a['clave'] === $clave);
        if (! $atrasado) {
            throw new \RuntimeException('Ese correlativo ya está bien.');
        }
        DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->update([$c['numero'] => $atrasado['emitido']]);
        $texto = "Soporte puso el correlativo de {$atrasado['serie']} en {$atrasado['emitido']} (estaba en {$atrasado['correlativo']})";
        Bitacora::registrar('config.correlativo_soporte', $texto, [['campo' => $c['numero'], 'etiqueta' => 'Correlativo '.$atrasado['serie'],
            'antes' => $atrasado['correlativo'], 'despues' => $atrasado['emitido']]], ['usuario' => null, 'nombre' => $quien, 'sucursal' => $sucursal]);

        return $texto.'.';
    }
}
