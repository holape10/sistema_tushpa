<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Kardex valorizado por costo promedio ponderado (como el formato 13.1 de SUNAT), calculado a partir de los movimientos
 * del kardex (movimientos_productos), que ya guardan la cantidad y el costo de cada entrada y salida:
 *  - Entrada: entra a su costo (si no lo tiene, como una devolución, al costo promedio del momento).
 *  - Salida: sale al costo promedio del momento.
 * No cambia nada de lo que se registra; solo valoriza.
 */
class KardexValorizado
{
    /**
     * Kardex valorizado de un producto en un almacén.
     *
     * @return array{inicial: array{cantidad: float, costo: float, valor: float}, filas: array<int, array<string, mixed>>,
     *     final: array{cantidad: float, costo: float, valor: float}, entradas: float, salidas: float}
     */
    public static function producto(int $idProducto, int $idAlmacen, int $sucursal, string $desde, string $hasta): array
    {
        $respaldo = (float) DB::table('productos')->where('IdProducto', $idProducto)->value('costo');
        $saldo = ['cantidad' => 0.0, 'valor' => 0.0];
        $inicial = null;
        $filas = [];
        $entradas = $salidas = 0.0;

        $movimientos = DB::table('movimientos_productos as m')
            ->leftJoin('tipo_operacion_sunat as t', 't.cod_tip_ope', '=', 'm.cod_tip_ope')
            ->where('m.IdProducto', $idProducto)->where('m.id_almacen', $idAlmacen)->where('m.id_empresa_negocio', $sucursal)
            ->where('m.fecha_mov', '<=', $hasta)
            ->orderBy('m.fecha_mov')->orderBy('m.mov_pro_id')
            ->select('m.*', 't.des_tip_ope')
            ->cursor();

        foreach ($movimientos as $m) {
            $enRango = $m->fecha_mov >= $desde;
            if ($enRango && $inicial === null) {
                $inicial = self::foto($saldo);
            }
            [$costo, $total] = self::aplicar($saldo, $m->mov_tip, (float) $m->cantidad, (float) $m->costo, $respaldo);
            if (! $enRango) {
                continue;
            }
            $m->mov_tip === 'I' ? $entradas += $total : $salidas += $total;
            $filas[] = ['mov' => $m, 'cantidad' => (float) $m->cantidad, 'costo' => $costo, 'total' => $total, 'saldo' => self::foto($saldo)];
        }

        return ['inicial' => $inicial ?? self::foto($saldo), 'filas' => $filas, 'final' => self::foto($saldo),
            'entradas' => round($entradas, 2), 'salidas' => round($salidas, 2)];
    }

    /**
     * Resumen de todos los productos de un almacén en un rango: saldo inicial, entradas, salidas y saldo final,
     * en cantidades y en soles (costo promedio). Solo productos con movimientos en el rango o con saldo.
     *
     * @return array<int, array{inicial: array, final: array, entradas: array{cantidad: float, valor: float}, salidas: array{cantidad: float, valor: float}}>
     */
    public static function resumen(int $sucursal, int $idAlmacen, string $desde, string $hasta): array
    {
        $respaldos = DB::table('productos')->where('id_empresa_negocio', $sucursal)->pluck('costo', 'IdProducto');
        $datos = [];
        $movimientos = DB::table('movimientos_productos')
            ->where('id_empresa_negocio', $sucursal)->where('id_almacen', $idAlmacen)->where('fecha_mov', '<=', $hasta)
            ->orderBy('IdProducto')->orderBy('fecha_mov')->orderBy('mov_pro_id')
            ->select('IdProducto', 'mov_tip', 'cantidad', 'costo', 'fecha_mov')
            ->cursor();

        foreach ($movimientos as $m) {
            $d = &$datos[$m->IdProducto];
            $d ??= ['saldo' => ['cantidad' => 0.0, 'valor' => 0.0], 'inicial' => null,
                'entradas' => ['cantidad' => 0.0, 'valor' => 0.0], 'salidas' => ['cantidad' => 0.0, 'valor' => 0.0]];
            $enRango = $m->fecha_mov >= $desde;
            if ($enRango && $d['inicial'] === null) {
                $d['inicial'] = self::foto($d['saldo']);
            }
            [, $total] = self::aplicar($d['saldo'], $m->mov_tip, (float) $m->cantidad, (float) $m->costo, (float) ($respaldos[$m->IdProducto] ?? 0));
            if ($enRango) {
                $lado = $m->mov_tip === 'I' ? 'entradas' : 'salidas';
                $d[$lado]['cantidad'] += (float) $m->cantidad;
                $d[$lado]['valor'] += $total;
            }
            unset($d);
        }

        $resumen = [];
        foreach ($datos as $id => $d) {
            $final = self::foto($d['saldo']);
            $hubo = $d['inicial'] !== null;
            if (! $hubo && abs($final['cantidad']) < 0.000001) {
                continue;   // sin movimientos en el rango y sin saldo
            }
            $resumen[$id] = ['inicial' => $d['inicial'] ?? $final, 'final' => $final,
                'entradas' => ['cantidad' => round($d['entradas']['cantidad'], 4), 'valor' => round($d['entradas']['valor'], 2)],
                'salidas' => ['cantidad' => round($d['salidas']['cantidad'], 4), 'valor' => round($d['salidas']['valor'], 2)]];
        }

        return $resumen;
    }

    /**
     * Existencias y su valor por producto y almacén a una fecha.
     *
     * @return array<string, array{cantidad: float, valor: float, costo: float}> clave "IdProducto-id_almacen"
     */
    public static function existencias(int $sucursal, ?int $idAlmacen, string $hasta): array
    {
        $respaldos = DB::table('productos')->where('id_empresa_negocio', $sucursal)->pluck('costo', 'IdProducto');
        $saldos = [];
        $movimientos = DB::table('movimientos_productos')
            ->where('id_empresa_negocio', $sucursal)->where('fecha_mov', '<=', $hasta)
            ->when($idAlmacen, fn ($q) => $q->where('id_almacen', $idAlmacen))
            ->orderBy('IdProducto')->orderBy('id_almacen')->orderBy('fecha_mov')->orderBy('mov_pro_id')
            ->select('IdProducto', 'id_almacen', 'mov_tip', 'cantidad', 'costo')
            ->cursor();

        foreach ($movimientos as $m) {
            $clave = $m->IdProducto.'-'.$m->id_almacen;
            $saldos[$clave] ??= ['cantidad' => 0.0, 'valor' => 0.0];
            self::aplicar($saldos[$clave], $m->mov_tip, (float) $m->cantidad, (float) $m->costo, (float) ($respaldos[$m->IdProducto] ?? 0));
        }

        return array_map(fn ($s) => self::foto($s), $saldos);
    }

    /**
     * Aplica un movimiento al saldo y devuelve [costo unitario, total] con que entró o salió.
     *
     * @param  array{cantidad: float, valor: float}  $saldo
     * @return array{0: float, 1: float}
     */
    private static function aplicar(array &$saldo, string $tipo, float $cantidad, float $costo, float $respaldo): array
    {
        $promedio = $saldo['cantidad'] > 0.000001 ? $saldo['valor'] / $saldo['cantidad'] : null;

        if ($tipo === 'I') {
            $unitario = $costo > 0 ? $costo : ($promedio ?? $respaldo);
            $total = round($cantidad * $unitario, 4);
            $saldo['cantidad'] += $cantidad;
            $saldo['valor'] += $total;
        } else {
            $unitario = $promedio ?? ($costo > 0 ? $costo : $respaldo);
            $total = round($cantidad * $unitario, 4);
            $saldo['cantidad'] -= $cantidad;
            $saldo['valor'] -= $total;
        }

        // Sin existencias no queda valor; con stock negativo (vendió sin stock) se valoriza al último costo
        if (abs($saldo['cantidad']) < 0.000001) {
            $saldo['cantidad'] = 0.0;
            $saldo['valor'] = 0.0;
        } elseif ($saldo['cantidad'] < 0) {
            $saldo['valor'] = $saldo['cantidad'] * $unitario;
        }

        return [round($unitario, 4), round($total, 2)];
    }

    /** @return array{cantidad: float, costo: float, valor: float} */
    private static function foto(array $saldo): array
    {
        $cantidad = round($saldo['cantidad'], 4);

        return ['cantidad' => $cantidad, 'valor' => round($saldo['valor'], 2),
            'costo' => abs($cantidad) > 0.000001 ? round($saldo['valor'] / $cantidad, 4) : 0.0];
    }
}
