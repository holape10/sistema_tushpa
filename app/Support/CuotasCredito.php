<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cuotas de una venta al crédito. Sin cuotas escritas, es una sola por el total a la fecha de vencimiento (como siempre).
 * La suma de las cuotas debe ser el total (SUNAT rechaza la factura si no: "la suma de las cuotas debe ser igual al monto
 * neto pendiente de pago") y el vencimiento del comprobante es el de la última cuota.
 */
class CuotasCredito
{
    public const MAXIMO = 36;

    /**
     * @param  array<int, array{fecha?: mixed, monto?: mixed}>|null  $escritas
     * @return list<array{numero: int, fecha: string, monto: float}>
     */
    public static function normalizar(?array $escritas, float $total, string $fecEmi, ?string $fecVen): array
    {
        $cuotas = collect($escritas ?? [])->map(fn ($c) => ['fecha' => trim((string) ($c['fecha'] ?? '')), 'monto' => round((float) ($c['monto'] ?? 0), 2)])
            ->filter(fn ($c) => $c['fecha'] !== '' || $c['monto'] > 0)->values();
        if ($cuotas->isEmpty()) {
            return [['numero' => 1, 'fecha' => (string) $fecVen, 'monto' => round($total, 2)]];
        }
        if ($cuotas->count() > self::MAXIMO) {
            throw new \RuntimeException('Como mucho '.self::MAXIMO.' cuotas.');
        }
        $anterior = $fecEmi;
        foreach ($cuotas as $i => $c) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $c['fecha']) || $c['monto'] <= 0) {
                throw new \RuntimeException('Cuota '.($i + 1).': pon su fecha y un monto mayor a 0.');
            }
            if ($c['fecha'] <= $anterior) {
                throw new \RuntimeException('Cuota '.($i + 1).': su fecha debe ser posterior a la emisión y a la cuota anterior.');
            }
            $anterior = $c['fecha'];
        }
        $suma = round($cuotas->sum('monto'), 2);
        if (abs($suma - round($total, 2)) > 0.009) {
            throw new \RuntimeException('Las cuotas suman S/ '.number_format($suma, 2).' y el total es S/ '.number_format($total, 2).': deben ser iguales.');
        }

        return $cuotas->map(fn ($c, $i) => ['numero' => $i + 1] + $c)->all();
    }

    /** @param  list<array{numero: int, fecha: string, monto: float}>  $cuotas */
    public static function guardar(int $cabId, array $cuotas): void
    {
        DB::table('cpe_cuotas')->where('IdCpe_cabecera', $cabId)->delete();
        DB::table('cpe_cuotas')->insert(array_map(fn ($c) => ['IdCpe_cabecera' => $cabId] + $c, $cuotas));
    }

    /**
     * Las cuotas de un comprobante al crédito (las de antes de esta tabla: una sola, por el total al vencimiento).
     *
     * @return Collection<int, object{numero: int, fecha: string, monto: float}>
     */
    public static function de(object $cab): Collection
    {
        if (($cab->estadopago ?? '') !== 'CREDITO' || ! $cab->ccafve) {
            return collect();
        }
        $guardadas = DB::table('cpe_cuotas')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->orderBy('numero')->get(['numero', 'fecha', 'monto']);

        return $guardadas->isNotEmpty() ? $guardadas->map(fn ($c) => (object) ['numero' => (int) $c->numero, 'fecha' => (string) $c->fecha, 'monto' => (float) $c->monto])
            : collect([(object) ['numero' => 1, 'fecha' => (string) $cab->ccafve, 'monto' => (float) $cab->ccaitv]]);
    }
}
