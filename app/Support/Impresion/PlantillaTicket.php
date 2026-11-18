<?php

namespace App\Support\Impresion;

/**
 * Diseño de ticket "Personalizado": la empresa arma su comprobante como era antes (cada sistema tenía el suyo). Es una
 * lista ordenada de partes; cada una se puede mostrar u ocultar, alinear, poner en negrita y en letra alta o grande. Los
 * textos libres (pie, avisos) y las líneas separadoras se agregan las veces que se quiera.
 */
class PlantillaTicket
{
    /** clave => [nombre, ayuda] (las que se pueden repetir: texto y separador) */
    public const PARTES = [
        'logo' => ['Logo', 'El de la sucursal o el de la empresa.'],
        'nombre_comercial' => ['Nombre comercial', 'El nombre del negocio.'],
        'razon_social' => ['Razón social', 'El nombre de la empresa ante SUNAT.'],
        'ruc' => ['RUC de la empresa', 'RUC: 20…'],
        'direccion' => ['Dirección de la sucursal', 'Con distrito, provincia y departamento.'],
        'telefono' => ['Teléfono', 'El de la sucursal.'],
        'documento' => ['Tipo y número del comprobante', 'BOLETA DE VENTA ELECTRÓNICA · B001-123'],
        'fecha' => ['Fecha y hora', 'De emisión.'],
        'cliente' => ['Cliente', 'Documento, nombre y dirección.'],
        'atencion' => ['Mesa, mozo y cajero', 'Quién atendió (en restaurantes).'],
        'condicion' => ['Condición de pago', 'Contado o crédito (con sus cuotas).'],
        'observacion' => ['Observación', 'Si la venta tiene.'],
        'productos' => ['Productos', 'Elige cómo se ven las columnas.'],
        'totales' => ['Subtotales e IGV', 'Descuentos, gravada, exonerada, IGV, ICBPER.'],
        'total' => ['TOTAL', 'El total a pagar.'],
        'pagos' => ['Medios de pago y vuelto', 'Efectivo, Yape, paga con, vuelto, propina.'],
        'son' => ['Monto en letras', 'Son: CIENTO VEINTE CON 00/100 SOLES'],
        'puntos' => ['Puntos del cliente', 'Si usa puntos (fidelización).'],
        'qr' => ['QR de SUNAT', 'Representación impresa, QR y dónde consultar.'],
        'amazonia' => ['Leyenda de la Amazonía', 'Solo en sucursales exoneradas.'],
        'texto' => ['Texto libre', 'Lo que quieras: un aviso, "Gracias por su compra"…'],
        'separador' => ['Línea separadora', '----- o ===== o una línea en blanco.'],
    ];

    public const REPETIBLES = ['texto', 'separador'];

    /** Cómo se ven los productos */
    public const FORMATOS_PRODUCTOS = [
        'clasico' => 'Nombre y debajo Cant. / UDM / P.U. / Total',
        'en_linea' => 'Cant. / U.M. / Descrip. / P.U. / Total en una fila',
        'simple' => 'Nombre y debajo "2 x 9.00 = 18.00"',
    ];

    public const TAMANOS = ['normal' => 'Normal', 'alto' => 'Alta', 'grande' => 'Grande'];

    /**
     * El diseño de partida: parecido al clásico, para cambiar desde ahí.
     *
     * @return list<array<string, mixed>>
     */
    public static function inicial(): array
    {
        $p = fn (string $tipo, array $o = []) => $o + ['tipo' => $tipo, 'visible' => true, 'alinear' => 'izq', 'negrita' => false, 'tamano' => 'normal'];

        return [
            $p('logo', ['alinear' => 'centro']),
            $p('nombre_comercial', ['alinear' => 'centro', 'negrita' => true, 'tamano' => 'alto']),
            $p('razon_social', ['alinear' => 'centro']),
            $p('ruc', ['alinear' => 'centro']),
            $p('direccion', ['alinear' => 'centro']),
            $p('telefono', ['alinear' => 'centro']),
            $p('separador', ['caracter' => '=']),
            $p('documento', ['alinear' => 'centro', 'negrita' => true]),
            $p('separador', ['caracter' => '=']),
            $p('fecha'),
            $p('cliente'),
            $p('atencion'),
            $p('condicion'),
            $p('observacion'),
            $p('productos', ['formato' => 'clasico']),
            $p('totales'),
            $p('total', ['negrita' => true, 'tamano' => 'alto']),
            $p('pagos'),
            $p('son'),
            $p('puntos'),
            $p('qr', ['alinear' => 'centro']),
            $p('amazonia', ['alinear' => 'centro']),
            $p('texto', ['alinear' => 'centro', 'texto' => '¡Gracias por su preferencia!']),
        ];
    }

    /**
     * El diseño guardado, limpio: solo partes conocidas y opciones válidas (si no hay, el de partida).
     *
     * @return list<array<string, mixed>>
     */
    public static function leer(mixed $guardado): array
    {
        $lista = is_string($guardado) ? json_decode($guardado, true) : $guardado;
        if (! is_array($lista) || ! $lista) {
            return self::inicial();
        }
        $res = [];
        foreach (array_slice($lista, 0, 60) as $parte) {
            if (! is_array($parte) || ! isset(self::PARTES[$parte['tipo'] ?? ''])) {
                continue;
            }
            $tipo = $parte['tipo'];
            $limpia = [
                'tipo' => $tipo,
                'visible' => filter_var($parte['visible'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'alinear' => in_array($parte['alinear'] ?? '', ['izq', 'centro', 'der'], true) ? $parte['alinear'] : 'izq',
                'negrita' => filter_var($parte['negrita'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'tamano' => isset(self::TAMANOS[$parte['tamano'] ?? '']) ? $parte['tamano'] : 'normal',
            ];
            if ($tipo === 'texto') {
                $limpia['texto'] = mb_substr(trim((string) ($parte['texto'] ?? '')), 0, 500);
            }
            if ($tipo === 'separador') {
                $limpia['caracter'] = in_array($parte['caracter'] ?? '-', ['-', '=', '*', '_', '.', ' '], true) ? $parte['caracter'] : '-';
            }
            if ($tipo === 'productos') {
                $limpia['formato'] = isset(self::FORMATOS_PRODUCTOS[$parte['formato'] ?? '']) ? $parte['formato'] : 'clasico';
            }
            $res[] = $limpia;
        }

        if (! $res) {
            return self::inicial();
        }
        // Las partes que no estaban (agregadas al sistema después) van al final, ocultas
        $tiene = array_column($res, 'tipo');
        foreach (array_diff(array_keys(self::PARTES), self::REPETIBLES, $tiene) as $falta) {
            $res[] = ['tipo' => $falta, 'visible' => false, 'alinear' => 'izq', 'negrita' => false, 'tamano' => 'normal'] + ($falta === 'productos' ? ['formato' => 'clasico'] : []);
        }

        return $res;
    }
}
