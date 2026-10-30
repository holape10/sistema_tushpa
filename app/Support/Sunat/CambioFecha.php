<?php

namespace App\Support\Sunat;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Comprobantes que no se enviaron a SUNAT a tiempo: se pasan al primer día que SUNAT todavía acepta (no a hoy), así quedan
 * lo más cerca de su fecha real y antes de los comprobantes más nuevos, sin romper el orden de los números (como se hacía en
 * el sistema anterior). Luego se envían ese mismo día y se le avisa al cliente. Solo lo que SUNAT todavía no tiene
 * (pendiente, error o rechazado), nunca lo aceptado ni lo anulado. Plazos contados desde el día siguiente a la emisión
 * (R.S. 000003-2023/SUNAT): facturas y sus notas 3 días, boletas y sus notas 7 días.
 */
class CambioFecha
{
    /** Días de plazo según la serie: F = factura y sus notas, B = boleta y sus notas */
    public static function plazo(object $cab): int
    {
        $letra = $cab->tdocod === '01' ? 'F' : ($cab->tdocod === '03' ? 'B' : strtoupper(substr((string) $cab->serdoc, 0, 1)));

        return RescateEnvios::PLAZOS[$letra][0] ?? RescateEnvios::PLAZOS['F'][0];
    }

    /** La fecha de emisión más antigua que SUNAT todavía acepta hoy (con hoy 30, una factura del 27: su plazo es 28, 29 y 30) */
    public static function fechaValida(object $cab, ?Carbon $hoy = null): Carbon
    {
        return ($hoy ?? now())->copy()->startOfDay()->subDays(self::plazo($cab));
    }

    /** ¿Ya se le pasó el plazo para enviarlo a SUNAT? */
    public static function vencido(object $cab, ?Carbon $hoy = null): bool
    {
        return Carbon::parse($cab->ccafem)->startOfDay()->lt(self::fechaValida($cab, $hoy));
    }

    /**
     * Pasa el comprobante a la fecha más antigua que SUNAT todavía acepta. El vencimiento (venta al crédito) se corre los
     * mismos días, para que el cliente tenga el mismo plazo de pago.
     *
     * @return array{anterior: string, nueva: string}
     *
     * @throws \RuntimeException
     */
    public static function aFechaValida(int $id, int $sucursal, User $usuario): array
    {
        return DB::transaction(function () use ($id, $sucursal, $usuario) {
            $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->where('id_empresa_negocio', $sucursal)->lockForUpdate()->first();
            if (! $cab || ! in_array($cab->tdocod, SunatService::TIPOS_ELECTRONICOS, true)) {
                throw new \RuntimeException('Comprobante no encontrado.');
            }
            $numero = $cab->serdoc.'-'.$cab->numdoc;
            if ($cab->ccabaj) {
                throw new \RuntimeException("{$numero} está anulado en el sistema: no se envía a SUNAT.");
            }
            if (! in_array(mb_strtoupper(trim((string) $cab->est_sunat)) ?: 'PENDIENTE', SunatService::ESTADOS_REENVIABLES, true)) {
                throw new \RuntimeException("{$numero} ya está {$cab->est_sunat} en SUNAT: su fecha ya no se cambia.");
            }
            if (! self::vencido($cab)) {
                throw new \RuntimeException("{$numero} todavía está dentro del plazo: envíalo a SUNAT con su fecha, sin cambiarla.");
            }
            $anterior = Carbon::parse($cab->ccafem)->startOfDay();
            $nueva = self::fechaValida($cab);
            $dias = (int) $anterior->diffInDays($nueva, false);
            $corre = fn (?string $f) => $f ? Carbon::parse($f)->addDays($dias)->toDateString() : null;

            DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->update([
                'ccafem' => $nueva->toDateString(),
                'ccafve' => $cab->ccafve && $cab->ccafve > $cab->ccafem ? $corre($cab->ccafve) : $nueva->toDateString(),
                'est_sunat' => 'PENDIENTE', 'ccasunrescod' => null,
                'ccadessun' => mb_substr('Fecha cambiada del '.$anterior->format('d/m/Y').' al '.$nueva->format('d/m/Y').' por '
                    .trim((string) ($usuario->apeusu ?: $usuario->name)).' (estaba fuera de plazo). Envíalo a SUNAT hoy mismo.', 0, 500),
            ]);
            // La cuenta por cobrar del crédito vence los mismos días después
            DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $id)->whereNotNull('fec_ven')
                ->update(['fec_ven' => DB::raw('DATE_ADD(fec_ven, INTERVAL '.$dias.' DAY)')]);

            return ['anterior' => $anterior->toDateString(), 'nueva' => $nueva->toDateString()];
        });
    }
}
