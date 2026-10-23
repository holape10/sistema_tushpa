<?php

namespace App\Support;

/**
 * Una receta explicada en palabras simples, igual en el ticket, el PDF y el Excel:
 * qué lleva cada ingrediente, a cuánto se compra, cuánto cuesta en el plato y qué parte del costo es.
 */
class FichaReceta
{
    /**
     * @return array<int, array{nombre: string, lleva: string, compra: ?string, equivale: ?string, costo: float, parte: ?float, sin_precio: bool, cantidad: float, unidad: string, precio_compra: float, unidad_compra: string}>
     */
    public static function lineas(object $plato): array
    {
        $total = (float) $plato->costo;

        return $plato->lineas->map(function ($l) use ($total) {
            $sinPrecio = (float) $l->costo_unitario <= 0;
            $equivale = $l->ume_equivalente && $l->ume_equivalente !== $l->ume_insumo && (float) $l->factor_equivalente > 1
                ? '1 '.Unidades::nombre($l->ume_insumo, 1).' = '.Unidades::texto((float) $l->factor_equivalente, $l->ume_equivalente) : null;

            [$cantidad, $ume] = self::legible((float) $l->cantidad, $l->umecod);

            return [
                'nombre' => mb_strtoupper(trim($l->nombre)),
                'lleva' => Unidades::texto($cantidad, $ume),
                'compra' => $sinPrecio ? null : 'S/ '.number_format((float) $l->costo_unitario, 2).' por '.Unidades::nombre($l->ume_insumo, 1),
                'equivale' => $equivale,
                'costo' => round((float) $l->costo, 2),
                'parte' => $total > 0 ? round($l->costo / $total * 100, 0) : null,
                'sin_precio' => $sinPrecio,
                'cantidad' => $cantidad,
                'unidad' => Unidades::nombre($ume, $cantidad),
                'precio_compra' => (float) $l->costo_unitario,
                'unidad_compra' => Unidades::nombre($l->ume_insumo, 1),
            ];
        })->all();
    }

    /**
     * En la cocina se piensa en gramos y mililitros: 0.15 kg => 150 g, 0.04 L => 40 ml.
     *
     * @return array{0: float, 1: string}
     */
    public static function legible(float $cantidad, string $ume): array
    {
        return match (true) {
            $ume === 'KGM' && $cantidad > 0 && $cantidad < 1 => [round($cantidad * 1000, 1), 'GRM'],
            $ume === 'LTR' && $cantidad > 0 && $cantidad < 1 => [round($cantidad * 1000, 1), 'MLT'],
            default => [$cantidad, $ume],
        };
    }

    /**
     * El resumen del plato en frases cortas.
     *
     * @return array{cuesta: string, vende: string, ganas: string, food_cost: string, explica: ?string}
     */
    public static function resumen(object $plato): array
    {
        $fc = $plato->food_cost;

        return [
            'cuesta' => 'S/ '.number_format((float) $plato->costo, 2),
            'vende' => 'S/ '.number_format((float) $plato->precio, 2),
            'ganas' => 'S/ '.number_format((float) $plato->ganancia, 2),
            'food_cost' => $fc !== null ? $fc.'% · '.$plato->estado['texto'] : 'Sin precio de venta',
            'explica' => $fc !== null ? 'De cada S/ 10 que cobras, S/ '.number_format($fc / 10, 2).' se van en ingredientes.' : null,
        ];
    }
}
