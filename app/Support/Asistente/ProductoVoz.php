<?php

namespace App\Support\Asistente;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crear productos hablando (Productos › Crear con voz): "jabón bolívar a 3.50, stock 24, la caja de 12 a 38 soles"
 * → nombre, precio, costo, stock inicial, unidad, categoría y presentaciones. Solo interpreta: la persona revisa en
 * pantalla y al aceptar se guardan con el mismo guardado del formulario de Nuevo Producto. Cuenta como una pregunta.
 */
class ProductoVoz
{
    private const ESQUEMA = [
        'type' => 'object', 'additionalProperties' => false, 'required' => ['productos', 'respuesta'],
        'properties' => [
            'productos' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false,
                'required' => ['nombre', 'precio', 'costo', 'stock', 'unidad', 'categoria', 'presentaciones'],
                'properties' => [
                    'nombre' => ['type' => 'string', 'description' => 'Nombre del producto en MAYÚSCULAS, claro y completo (marca y medida si las dice): "JABON BOLIVAR 190 G"'],
                    'precio' => ['type' => ['number', 'null'], 'description' => 'Precio de venta de UNA unidad base'],
                    'costo' => ['type' => ['number', 'null'], 'description' => 'Costo / precio de compra de una unidad base, si lo dice'],
                    'stock' => ['type' => ['number', 'null'], 'description' => 'Stock inicial en unidades base. "2 cajas de 12" = 24'],
                    'unidad' => ['type' => 'string', 'description' => 'Código de la unidad base de la lista (NIU si es por unidad)'],
                    'categoria' => ['type' => ['string', 'null'], 'description' => 'Categoría si la dice, en MAYÚSCULAS'],
                    'presentaciones' => ['type' => 'array', 'items' => [
                        'type' => 'object', 'additionalProperties' => false, 'required' => ['nombre', 'unidad', 'factor', 'precio'],
                        'properties' => [
                            'nombre' => ['type' => 'string', 'description' => '"CAJA X12", "PAQUETE X6", "SACO 50 KG"'],
                            'unidad' => ['type' => 'string', 'description' => 'Código de la unidad de la presentación (BX caja, PK paquete, DZN docena…)'],
                            'factor' => ['type' => 'number', 'description' => 'Cuántas unidades base trae'],
                            'precio' => ['type' => 'number', 'description' => 'Precio de venta de la presentación completa'],
                        ],
                    ]],
                ],
            ]],
            'respuesta' => ['type' => 'string', 'description' => 'Frase corta: qué entendiste o qué falta (por ejemplo, el precio)'],
        ],
    ];

    /** @return array{productos: list<array<string, mixed>>, respuesta: string} */
    public static function interpretar(User $user, string $texto): array
    {
        if ($motivo = Asistente::bloqueoComun()) {
            throw new \RuntimeException($motivo);
        }
        $unidades = DB::table('unidad_medida')->orderBy('umenom')->pluck('umenom', 'umecod');
        $listaUnidades = $unidades->map(fn ($n, $c) => "{$c}={$n}")->implode(', ');
        $categorias = DB::table('categorias')->where('id_empresa_negocio', $user->id_empresa_negocio)->orderBy('cat_nom')->limit(80)->pluck('cat_nom')->implode(', ');

        $modelo = Asistente::modelo();
        $uso = ['entrada' => 0, 'salida' => 0, 'lectura' => 0, 'escritura' => 0];
        $error = null;
        $datos = null;
        try {
            $r = Asistente::llamar([
                'model' => $modelo, 'maxTokens' => 3000,
                'system' => [['type' => 'text', 'text' => 'Registras productos de comercios peruanos (bodegas, ferreterías, minimarkets) a partir de lo que la persona dicta. '
                    .'Uno o varios productos por frase. El precio de la unidad base y el de cada presentación son distintos: "la caja de 12 a 38 soles" es una presentación (factor 12, precio 38). '
                    .'El stock inicial va en unidades base. Si no dice algo, déjalo null (no inventes precios). Los números dichos en palabras pásalos a número. '
                    .'Usa SOLO códigos de unidad de la lista. Si dice una categoría parecida a una existente, usa la existente.']],
                'outputConfig' => ['format' => ['type' => 'json_schema', 'schema' => self::ESQUEMA]],
                'messages' => [['role' => 'user', 'content' => "Unidades (código=nombre): {$listaUnidades}\nCategorías existentes: ".($categorias ?: '(ninguna)')
                    ."\n\nLa persona dice: \"".mb_substr($texto, 0, 1500).'"']],
            ]);
            $uso = ['entrada' => (int) ($r->usage->inputTokens ?? 0), 'salida' => (int) ($r->usage->outputTokens ?? 0),
                'lectura' => (int) ($r->usage->cacheReadInputTokens ?? 0), 'escritura' => (int) ($r->usage->cacheCreationInputTokens ?? 0)];
            $json = collect($r->content)->first(fn ($b) => $b->type === 'text')?->text;
            $datos = $r->stopReason === 'end_turn' && $json ? json_decode($json, true) : null;
            if (! is_array($datos) || empty($datos['productos'])) {
                $error = 'No entendí ningún producto. Dilo así: «jabón bolívar a 3.50, stock 24, la caja de 12 a 38 soles».';
            }
        } catch (APIStatusException|APIConnectionException) {
            $error = 'No se pudo conectar con la IA. Intenta de nuevo en un momento.';
        }
        Asistente::anotar($user, 'Crear productos con voz: '.mb_substr($texto, 0, 120), $modelo, $uso, 0, $error);
        if ($error) {
            throw new \RuntimeException($error);
        }

        // Una unidad que no está en la lista se toma como NIU (unidad)
        $unidad = fn ($c) => $unidades->has(strtoupper((string) $c)) ? strtoupper((string) $c) : 'NIU';
        $productos = array_map(fn ($p) => [
            'nombre' => mb_strtoupper(mb_substr(trim((string) $p['nombre']), 0, 150)),
            'precio' => $p['precio'] !== null ? round((float) $p['precio'], 2) : null,
            'costo' => $p['costo'] !== null ? round((float) $p['costo'], 2) : null,
            'stock' => $p['stock'] !== null ? max(0, round((float) $p['stock'], 3)) : null,
            'unidad' => $unidad($p['unidad']),
            'categoria' => $p['categoria'] ? mb_strtoupper(mb_substr(trim((string) $p['categoria']), 0, 50)) : null,
            'presentaciones' => array_values(array_map(fn ($x) => ['nombre' => mb_strtoupper(mb_substr(trim((string) $x['nombre']), 0, 60)), 'unidad' => $unidad($x['unidad']),
                'factor' => round((float) $x['factor'], 3), 'precio' => round((float) $x['precio'], 2)],
                array_filter($p['presentaciones'] ?? [], fn ($x) => (float) $x['factor'] > 0 && (float) $x['precio'] > 0))),
        ], array_slice($datos['productos'], 0, 20));

        return ['productos' => $productos, 'respuesta' => mb_substr(trim((string) ($datos['respuesta'] ?? '')), 0, 200)];
    }
}
