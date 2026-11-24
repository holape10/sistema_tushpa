<?php

namespace App\Support\Asistente;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\User;
use App\Support\CatalogoVenta;

/**
 * Punto Venta IA: el cajero dice lo que quiere ("2 jabones y una inca kola", "paga 30 en efectivo y 20 con yape",
 * "boleta", "cobra") y la IA lo pasa a acciones sobre la venta. Los productos se buscan en el catálogo de la sucursal;
 * si uno no existe se devuelve para que la pantalla ofrezca crearlo. La pantalla aplica las acciones y nada se registra
 * sin que el cajero lo vea. Cuenta como una pregunta del asistente (con su límite y su costo).
 */
class VentaVoz
{
    private const ESQUEMA = [
        'type' => 'object', 'additionalProperties' => false, 'required' => ['acciones', 'respuesta'],
        'properties' => [
            'acciones' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false,
                'required' => ['tipo', 'producto', 'cantidad', 'precio', 'linea', 'medio', 'monto', 'valor'],
                'properties' => [
                    'tipo' => ['type' => 'string', 'enum' => ['agregar', 'quitar', 'cantidad', 'precio', 'pago', 'comprobante', 'cliente', 'cobrar', 'cancelar']],
                    'producto' => ['type' => ['string', 'null'], 'description' => 'agregar: el producto en singular y sin artículos, como se buscaría en el catálogo ("jabon bolivar", "inca kola 500 ml")'],
                    'cantidad' => ['type' => ['number', 'null'], 'description' => 'agregar / cantidad: cuántos (1 si no lo dice)'],
                    'precio' => ['type' => ['number', 'null'], 'description' => 'agregar / precio: precio unitario si lo dice ("a 3 soles"); si no, null'],
                    'linea' => ['type' => ['integer', 'null'], 'description' => 'quitar / cantidad / precio: número de línea del detalle actual'],
                    'medio' => ['type' => ['string', 'null'], 'description' => 'pago: el nombre EXACTO de uno de los medios de pago de la lista'],
                    'monto' => ['type' => ['number', 'null'], 'description' => 'pago: cuánto con ese medio; null = todo lo que falta'],
                    'valor' => ['type' => ['string', 'null'], 'description' => 'comprobante: boleta | factura | nota. cliente: el DNI o RUC dictado (solo dígitos)'],
                ],
            ]],
            'respuesta' => ['type' => 'string', 'description' => 'Frase corta en español para el cajero: qué hiciste o qué falta (máx. 20 palabras)'],
        ],
    ];

    /**
     * @param  array{detalle: list<array{nombre: string, cantidad: float, precio: float}>, total: float, medios: list<string>}  $estado
     * @return array{acciones: list<array<string, mixed>>, respuesta: string}
     */
    public static function interpretar(User $user, string $texto, array $estado): array
    {
        if ($motivo = Asistente::bloqueoComun()) {
            throw new \RuntimeException($motivo);
        }
        $detalle = collect($estado['detalle'] ?? [])->values()
            ->map(fn ($l, $i) => ($i + 1).'. '.$l['nombre'].' × '.$l['cantidad'].' a S/ '.number_format((float) $l['precio'], 2))->implode("\n") ?: '(vacío)';
        $medios = implode(', ', $estado['medios'] ?? []);

        $modelo = Asistente::modelo();
        $uso = ['entrada' => 0, 'salida' => 0, 'lectura' => 0, 'escritura' => 0];
        $error = null;
        $datos = null;
        try {
            $r = Asistente::llamar([
                'model' => $modelo, 'maxTokens' => 1500,
                'system' => [['type' => 'text', 'cacheControl' => ['type' => 'ephemeral'], 'text' => 'Eres el asistente de caja de un comercio peruano (bodega, ferretería, minimarket). '
                    .'El cajero te habla por voz para armar y cobrar una venta; conviertes lo que dice en acciones. Reglas: '
                    .'"quiero 2 jabones" / "dame tres inca kola" → agregar (producto en singular, cantidad). "a 3 soles" es el precio unitario. '
                    .'"quita el jabón" o "saca la línea 2" → quitar (usa el número de línea del detalle). "que sean 5" sobre un producto ya agregado → cantidad. '
                    .'"paga 30 en efectivo y 20 con yape" → un pago por cada medio, con el nombre EXACTO de la lista de medios (si dice "todo en yape", monto null). '
                    .'"boleta", "factura", "nota de venta" → comprobante. "con DNI 4766..." → cliente. "cobra", "termina", "registra la venta" → cobrar. "cancela todo" → cancelar. '
                    .'Los números dichos en palabras pásalos a número. Si no entiendes, no inventes: devuelve acciones vacías y pregunta en la respuesta.']],
                'outputConfig' => ['format' => ['type' => 'json_schema', 'schema' => self::ESQUEMA]],
                'messages' => [['role' => 'user', 'content' => "Detalle actual:\n{$detalle}\nTotal: S/ ".number_format((float) ($estado['total'] ?? 0), 2)
                    ."\nMedios de pago: {$medios}\n\nEl cajero dice: \"".mb_substr($texto, 0, 500).'"'],
                ],
            ]);
            $uso = ['entrada' => (int) ($r->usage->inputTokens ?? 0), 'salida' => (int) ($r->usage->outputTokens ?? 0),
                'lectura' => (int) ($r->usage->cacheReadInputTokens ?? 0), 'escritura' => (int) ($r->usage->cacheCreationInputTokens ?? 0)];
            $json = collect($r->content)->first(fn ($b) => $b->type === 'text')?->text;
            $datos = $r->stopReason === 'end_turn' && $json ? json_decode($json, true) : null;
            if (! is_array($datos)) {
                $error = 'No te entendí. Dilo otra vez, por ejemplo: «2 jabones y una inca kola».';
            }
        } catch (APIStatusException|APIConnectionException) {
            $error = 'No se pudo conectar con la IA. Intenta de nuevo en un momento.';
        }
        Asistente::anotar($user, 'Punto Venta IA: '.mb_substr($texto, 0, 120), $modelo, $uso, 0, $error);
        if ($error) {
            throw new \RuntimeException($error);
        }

        $acciones = [];
        foreach ($datos['acciones'] ?? [] as $a) {
            if (($a['tipo'] ?? '') === 'agregar') {
                $a += self::buscarProducto($user, (string) ($a['producto'] ?? ''));
                $a['cantidad'] = max(0.001, (float) ($a['cantidad'] ?? 1) ?: 1);
            }
            $acciones[] = $a;
        }

        return ['acciones' => $acciones, 'respuesta' => mb_substr(trim((string) ($datos['respuesta'] ?? '')), 0, 200)];
    }

    /**
     * El producto dicho en el catálogo de la sucursal: uno solo → ese; varios → opciones para que el cajero elija;
     * ninguno → para ofrecer crearlo. Si con todas las palabras no hay nada, se prueba con cada palabra ("jabon bolivar" → "jabon").
     *
     * @return array{encontrado: ?array, opciones: list<array>}
     */
    public static function buscarProducto(User $user, string $producto): array
    {
        $producto = trim(preg_replace('/\s+/', ' ', $producto));
        if (mb_strlen($producto) < 2) {
            return ['encontrado' => null, 'opciones' => []];
        }
        $lista = CatalogoVenta::buscar($user, '', $producto);
        if (! $lista) {
            foreach (array_filter(explode(' ', $producto), fn ($p) => mb_strlen($p) >= 3) as $palabra) {
                if ($lista = CatalogoVenta::buscar($user, '', $palabra)) {
                    break;
                }
            }
        }
        $lista = array_values($lista);
        // Uno solo, o uno cuyo nombre es exactamente lo dicho
        $exacto = collect($lista)->first(fn ($p) => mb_strtolower(trim($p['nombre'])) === mb_strtolower($producto));
        if (count($lista) === 1 || $exacto) {
            return ['encontrado' => $exacto ?? $lista[0], 'opciones' => []];
        }

        return ['encontrado' => null, 'opciones' => array_slice($lista, 0, 6)];
    }
}
