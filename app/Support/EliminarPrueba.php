<?php

namespace App\Support;

use App\Http\Controllers\SucursalController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Borra un comprobante de PRUEBA (de las pruebas de impresión) como si nunca se hubiera emitido, y el correlativo
 * regresa: el próximo comprobante vuelve a usar ese número. Solo el último de su serie y solo si SUNAT no lo tiene
 * (una nota de venta nunca va a SUNAT). Primero se anula (devuelve stock, cobros, series, puntos…) y luego se borra
 * todo su rastro. Lo usa el panel de soporte.
 */
class EliminarPrueba
{
    /** Estados en que SUNAT ya lo tiene: ese número ya existe para SUNAT y no se puede volver a usar */
    public const EN_SUNAT = ['ACEPTADO', 'OBSERVADO', 'EN RESUMEN'];

    /** Tablas con datos propios de la venta: se borran con ella */
    private const SE_BORRAN = ['cpe_detalle', 'venta_medio_pago', 'movimientos_productos', 'cpe_promociones', 'fid_movimientos',
        'porciones_movimientos', 'propinas', 'encuestas', 'ventas_offline'];

    /** Registros de otros módulos que solo apuntan a la venta (ya revertidos al anularla): quedan, sin la venta */
    private const SE_SUELTAN = ['col_pagos', 'cou_envios', 'est_abonados', 'est_tickets', 'gym_membresias', 'producto_series', 'socio_pagos'];

    /** Por qué no se puede borrar (null = sí se puede) */
    public static function impedimento(object $cab): ?string
    {
        if ($cab->tdocod !== '13' && (in_array($cab->est_sunat, self::EN_SUNAT, true) || ($cab->est_sunat === 'ANULADO' && (int) $cab->enviado === 1))) {
            return 'SUNAT ya lo tiene: ese número no se puede volver a usar (se anula con nota de crédito o baja).';
        }
        $ultimo = (int) DB::table('cpe_cabecera')->where('id_empresa_negocio', $cab->id_empresa_negocio)->where('tdocod', $cab->tdocod)
            ->where('serdoc', $cab->serdoc)->max('numdoc');
        if ((int) $cab->numdoc !== $ultimo) {
            return "solo se borra el último de la serie {$cab->serdoc} (va en el {$ultimo}): borra primero los posteriores.";
        }
        if (DB::table('cpe_cabecera')->where('IdCpe_cabecera_ref', $cab->IdCpe_cabecera)->exists()) {
            return 'tiene una nota de crédito o débito: borra primero la nota.';
        }
        if (DB::table('gre_cabecera')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->exists()) {
            return 'tiene una guía de remisión.';
        }

        return null;
    }

    /** @return string el número que quedó libre (ej. B001-54326) */
    public static function ejecutar(int $cabId, ?int $usuarioId): string
    {
        return DB::transaction(function () use ($cabId, $usuarioId) {
            $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cabId)->lockForUpdate()->first();
            if (! $cab) {
                throw new \RuntimeException('el comprobante no existe.');
            }
            if ($motivo = self::impedimento($cab)) {
                throw new \RuntimeException($motivo);
            }

            // Anularlo devuelve el stock, los cobros, las series, los puntos, las cuotas… (si ya estaba anulado, ya se devolvió)
            AnulacionVenta::anular($cabId, 'PRUEBA ELIMINADA DESDE EL PANEL', $usuarioId);

            $cuentas = DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $cabId)->pluck('cue_cob_id');
            if ($cuentas->isNotEmpty()) {
                $pagos = DB::table('cuentas_cobrar_detalle')->whereIn('cue_cob_id', $cuentas)->pluck('cue_cob_det_id');
                DB::table('cuentas_cobrar_medios')->whereIn('cue_cob_det_id', $pagos)->delete();
                DB::table('cuentas_cobrar_detalle')->whereIn('cue_cob_id', $cuentas)->delete();
                DB::table('cuentas_cobrar')->whereIn('cue_cob_id', $cuentas)->delete();
            }
            foreach (self::SE_BORRAN as $tabla) {
                if (Schema::hasTable($tabla)) {
                    DB::table($tabla)->where('IdCpe_cabecera', $cabId)->delete();
                }
            }
            foreach (self::SE_SUELTAN as $tabla) {
                if (Schema::hasTable($tabla)) {
                    DB::table($tabla)->where('IdCpe_cabecera', $cabId)->update(['IdCpe_cabecera' => null]);
                }
            }
            // La proforma que se cobró con ella vuelve a estar pendiente
            DB::table('proformas')->where('IdCpe_cabecera', $cabId)->update(['IdCpe_cabecera' => null, 'estado' => 'PENDIENTE']);
            DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cabId)->delete();

            self::regresarCorrelativo($cab);

            return $cab->serdoc.'-'.$cab->numdoc;
        });
    }

    /** El correlativo de la sucursal regresa un número: el próximo comprobante vuelve a usar el que se borró */
    private static function regresarCorrelativo(object $cab): void
    {
        $clave = in_array($cab->tdocod, ['07', '08'], true) ? $cab->tdocod.(str_starts_with((string) $cab->serdoc, 'F') ? 'F' : 'B') : $cab->tdocod;
        $c = SucursalController::SERIES[$clave] ?? null;
        if (! $c) {
            return;
        }
        $sucursal = DB::table('empresa_negocios')->where('id_empresa_negocio', $cab->id_empresa_negocio)->lockForUpdate()->first([$c['serie'], $c['numero']]);
        // Solo si la sucursal sigue en esa serie y su contador iba en este número (si ya cambió de serie, no se toca)
        if (! $sucursal || $sucursal->{$c['serie']} !== $cab->serdoc || (int) $sucursal->{$c['numero']} !== (int) $cab->numdoc) {
            return;
        }
        DB::table('empresa_negocios')->where('id_empresa_negocio', $cab->id_empresa_negocio)->update([$c['numero'] => max(0, (int) $cab->numdoc - 1)]);
    }
}
