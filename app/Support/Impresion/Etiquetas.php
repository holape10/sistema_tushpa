<?php

namespace App\Support\Impresion;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Etiquetas con código de barras de los productos: en la ticketera (el código lo dibuja la impresora, una etiqueta tras
 * otra en el rollo) o en PDF del tamaño de la etiqueta (una por hoja) para impresoras de etiquetas o el navegador.
 * Cada etiqueta: nombre del producto, código de barras con su número debajo y precio (lo que se elija mostrar).
 */
class Etiquetas
{
    /** Tamaños de etiqueta (ancho x alto en mm) */
    public const TAMANOS = ['50x25' => '50 x 25 mm', '40x30' => '40 x 30 mm', '60x40' => '60 x 40 mm', '38x25' => '38 x 25 mm', '32x25' => '32 x 25 mm', '30x20' => '30 x 20 mm'];

    /**
     * Una etiqueta por cada copia pedida.
     *
     * @param  list<array{nombre: string, codigo: string, precio: float, cantidad: int}>  $productos
     * @return list<array{nombre: string, codigo: string, precio: float}>
     */
    public static function lista(array $productos): array
    {
        $res = [];
        foreach ($productos as $p) {
            if (CodigoBarras::limpiar($p['codigo']) === '') {
                continue;
            }
            for ($i = 0; $i < max(0, (int) $p['cantidad']); $i++) {
                $res[] = ['nombre' => $p['nombre'], 'codigo' => CodigoBarras::limpiar($p['codigo']), 'precio' => (float) $p['precio']];
            }
        }

        return $res;
    }

    /** Las etiquetas para la ticketera (ESC/POS): una debajo de otra, separadas por una línea y un corte al final */
    public static function ticketera(object $imp, array $etiquetas, bool $conNombre, bool $conPrecio): Escpos
    {
        $p = new Escpos((int) $imp->columnas);
        $ancho = $p->columnas();
        foreach ($etiquetas as $i => $e) {
            $p->alinear('centro');
            if ($conNombre) {
                $p->negrita()->parrafo(mb_substr($e['nombre'], 0, $ancho * 2))->negrita(false);
            }
            // Barras más angostas si el código es largo (para que entre en el papel)
            $p->codigoBarras($e['codigo'], 70, strlen($e['codigo']) > 14 ? 1 : 2);
            if ($conPrecio) {
                $p->negrita()->tamano(1, 2)->texto('S/ '.number_format($e['precio'], 2))->tamano()->negrita(false);
            }
            $p->texto($i < count($etiquetas) - 1 ? str_repeat('- ', intdiv($ancho, 2)) : '');
        }

        return $p->avanzar(3)->cortar();
    }

    /** Las etiquetas en PDF: una por hoja del tamaño de la etiqueta */
    public static function pdf(array $etiquetas, string $tamano, bool $conNombre, bool $conPrecio): string
    {
        [$w, $h] = array_map('floatval', explode('x', isset(self::TAMANOS[$tamano]) ? $tamano : '50x25'));
        $pt = fn (float $mm) => $mm * 72 / 25.4;
        $imagenes = [];
        foreach ($etiquetas as $e) {
            $imagenes[$e['codigo']] ??= 'data:image/png;base64,'.base64_encode((string) CodigoBarras::png($e['codigo'], 70, 2));
        }
        $html = view('empresas.productos.etiquetas_pdf', ['etiquetas' => $etiquetas, 'imagenes' => $imagenes, 'w' => $w, 'h' => $h,
            'conNombre' => $conNombre, 'conPrecio' => $conPrecio])->render();

        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper([0, 0, $pt($w), $pt($h)]);
        $pdf->render();

        return (string) $pdf->output();
    }
}
