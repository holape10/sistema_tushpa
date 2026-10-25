<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Cupones de descuento del restaurante: el administrador crea un código (VERANO10, CUMPLE20...) con su descuento en %
 * o en soles, consumo mínimo, vigencia y cuántas veces se puede usar. El cajero lo escribe al cobrar y el descuento
 * se aplica solo (no necesita la clave del administrador: el cupón ya está autorizado).
 */
class Cupones
{
    public static function normalizar(string $codigo): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', trim($codigo)));
    }

    /**
     * El cupón vigente con ese código, listo para usarse en una cuenta de $bruto soles.
     * Con $bloquear (al cobrar, dentro de la transacción) dos cajas no gastan el último uso a la vez.
     *
     * @throws \RuntimeException con el motivo para el cajero
     */
    public static function vigente(int $sucursal, string $codigo, float $bruto, bool $bloquear = false): object
    {
        $codigo = self::normalizar($codigo);
        $cupon = DB::table('cupones')->where('id_empresa_negocio', $sucursal)->where('codigo', $codigo)
            ->when($bloquear, fn ($q) => $q->lockForUpdate())->first();
        if (! $cupon || ! $cupon->activo) {
            throw new \RuntimeException("El cupón {$codigo} no existe o está desactivado.");
        }
        $hoy = now()->toDateString();
        if ($cupon->desde && $hoy < $cupon->desde) {
            throw new \RuntimeException("El cupón {$codigo} vale desde el ".date('d/m/Y', strtotime($cupon->desde)).'.');
        }
        if ($cupon->hasta && $hoy > $cupon->hasta) {
            throw new \RuntimeException("El cupón {$codigo} venció el ".date('d/m/Y', strtotime($cupon->hasta)).'.');
        }
        if ($cupon->usos_max !== null && (int) $cupon->usos >= (int) $cupon->usos_max) {
            throw new \RuntimeException("El cupón {$codigo} ya llegó a su límite de usos ({$cupon->usos_max}).");
        }
        if ((float) $cupon->minimo > 0 && $bruto + 0.001 < (float) $cupon->minimo) {
            throw new \RuntimeException("El cupón {$codigo} es para consumos desde S/ ".number_format((float) $cupon->minimo, 2)
                .' (esta cuenta es S/ '.number_format($bruto, 2).').');
        }
        if (self::monto($cupon, $bruto) >= $bruto) {
            throw new \RuntimeException("El cupón {$codigo} (S/ ".number_format((float) $cupon->valor, 2).') es igual o mayor que la cuenta: no se emite un comprobante en cero.');
        }

        return $cupon;
    }

    /** Cuánto descuenta el cupón en una cuenta de $bruto soles */
    public static function monto(object $cupon, float $bruto): float
    {
        return $cupon->tipo === 'P' ? round($bruto * (float) $cupon->valor / 100, 2) : round((float) $cupon->valor, 2);
    }

    /** "10%" o "S/ 5.00" */
    public static function texto(object $cupon): string
    {
        return $cupon->tipo === 'P' ? rtrim(rtrim(number_format((float) $cupon->valor, 2), '0'), '.').'%' : 'S/ '.number_format((float) $cupon->valor, 2);
    }

    /** ¿La sucursal tiene algún cupón activo? (si no, el cobro no muestra la casilla) */
    public static function hayActivos(int $sucursal): bool
    {
        return DB::table('cupones')->where('id_empresa_negocio', $sucursal)->where('activo', 1)
            ->where(fn ($q) => $q->whereNull('hasta')->orWhere('hasta', '>=', now()->toDateString()))->exists();
    }

    public static function usar(int $id): void
    {
        DB::table('cupones')->where('id', $id)->increment('usos');
    }

    /** Al anular una venta su cupón vuelve a quedar disponible */
    public static function devolver(int $cabId): void
    {
        $id = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cabId)->value('cupon_id');
        if ($id) {
            DB::table('cupones')->where('id', $id)->where('usos', '>', 0)->decrement('usos');
        }
    }
}
