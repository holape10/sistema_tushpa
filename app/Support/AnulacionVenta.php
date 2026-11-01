<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Anula una venta en el sistema: marca la baja, anula los cobros de la cuenta por cobrar,
 * revierte las cuotas de socio pagadas con ella y devuelve el stock al almacén.
 * La usan la anulación de notas de venta y la comunicación de baja aceptada por SUNAT.
 */
class AnulacionVenta
{
    /** @return int|null movimientos de kardex devueltos, o null si la venta ya estaba anulada */
    public static function anular(int $cabId, string $motivo, ?int $usuarioId, string $etiqueta = 'ANULADO'): ?int
    {
        return DB::transaction(function () use ($cabId, $motivo, $usuarioId, $etiqueta) {
            $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cabId)->lockForUpdate()->first();
            if (! $cab || $cab->ccabaj) {
                return null;
            }
            // Venta al crédito con cobros registrados: primero se anulan los cobros
            Cuentas::anularPorDocumento('cobrar', $cabId);
            DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cabId)->update([
                'ccabaj' => $etiqueta.' '.now()->format('d/m/Y H:i'),
                'motivo_baja' => mb_substr(trim($motivo), 0, 70),
                'IdUsuario_baja' => $usuarioId,
            ]);
            Socios::revertirComprobante($cabId);   // cuotas de socio pagadas con esta venta
            Gimnasio::revertirComprobante($cabId); // membresía de gimnasio pagada con esta venta
            Estacionamiento::revertirComprobante($cabId); // pensión o cobro de estacionamiento
            Colegio::revertirComprobante($cabId); // pensiones del colegio pagadas con él vuelven a deberse
            Courier::revertirComprobante($cabId); // el envío que pagó vuelve a quedar por cobrar
            Fidelizacion::revertir($cabId);       // los puntos que ganó con esta compra
            Porciones::revertir($cabId);           // las porciones del día que se vendieron
            Series::revertirVenta($cabId);         // los equipos vendidos (número de serie) vuelven a estar disponibles
            Cupones::devolver($cabId);             // el uso del cupón de descuento vuelve a quedar libre

            // Al celular del dueño (si lo activó): una venta anulada puede ser plata que no entró a caja
            $quien = $usuarioId ? (string) DB::table('users')->where('IdUsuario', $usuarioId)->value('apeusu') : '';
            AvisosCelular::alDueno((int) $cab->id_empresa_negocio, '🚫 Anularon una venta de S/ '.number_format((float) $cab->ccaitv, 2),
                trim(($quien ?: 'Alguien').' anuló '.$cab->serdoc.'-'.$cab->numdoc.' ('.trim((string) $cab->ccanom).'). Motivo: '.trim($motivo)),
                route('actividad.index'));

            // El stock que salió con esta venta vuelve al almacén
            return Kardex::revertirVenta($cabId, [
                'cliente' => $cab->ccanom, 'descripcion' => 'ANULACIÓN: '.trim($motivo), 'fecha_mov' => now()->toDateString(),
            ]);
        });
    }
}
