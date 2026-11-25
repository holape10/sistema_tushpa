<?php

namespace App\Support\Asistente;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\User;
use App\Support\ImagenProducto;
use Illuminate\Http\UploadedFile;

/**
 * Pedidos de WhatsApp (Proformas › Pedido de WhatsApp): el cliente manda su lista por WhatsApp (texto o foto de un papel);
 * se pega o se sube aquí y la IA arma el pedido con los productos de la tienda. Los precios son los de la tienda, nunca de
 * la IA. La persona revisa, lo guarda como proforma y le responde al cliente con el total. Cuenta como una pregunta.
 */
class PedidoWhatsapp
{
    public const EXTENSIONES = 'jpg,jpeg,png,webp';

    private const ESQUEMA = [
        'type' => 'object', 'additionalProperties' => false, 'required' => ['cliente', 'lineas', 'aviso'],
        'properties' => [
            'cliente' => ['type' => ['string', 'null'], 'description' => 'Nombre del cliente si aparece en el mensaje ("soy Rosa", la firma); null si no'],
            'lineas' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['texto', 'cantidad', 'producto'],
                'properties' => [
                    'texto' => ['type' => 'string', 'description' => 'Lo que pidió, tal cual se entiende: "2 KILOS DE ARROZ"'],
                    'cantidad' => ['type' => 'number', 'description' => 'Cantidad en la unidad del producto (½ kilo = 0.5; "un paquete" = 1). Si no dice, 1'],
                    'producto' => ['type' => 'integer', 'description' => 'Id del producto de la lista que es lo que pidió; 0 si ninguno es claramente lo mismo'],
                ],
            ]],
            'aviso' => ['type' => 'string', 'description' => 'Lo que debe preguntarle la tienda al cliente (marca, tamaño, algo que no se entiende); vacío si nada'],
        ],
    ];

    /**
     * @return array{cliente: ?string, lineas: list<array{texto: string, cantidad: float, producto: ?int}>, aviso: string}
     */
    public static function leer(User $user, string $texto, ?UploadedFile $foto): array
    {
        if ($motivo = Asistente::bloqueoComun()) {
            throw new \RuntimeException($motivo);
        }
        $texto = trim(mb_substr($texto, 0, 3000));
        $productos = CuadernoFoto::productos((int) $user->id_empresa_negocio);
        $contenido = [];
        if ($foto) {
            $tmp = tempnam(sys_get_temp_dir(), 'ped').'.webp';
            if (! ImagenProducto::redimensionar($foto->getRealPath(), $tmp, 1800, ImagenProducto::orientacion($foto->getRealPath()))) {
                @unlink($tmp);
                throw new \RuntimeException('No se pudo abrir la foto. Usa una foto JPG o PNG.');
            }
            $contenido[] = ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/webp', 'data' => base64_encode((string) file_get_contents($tmp))]];
            @unlink($tmp);
        }
        $contenido[] = ['type' => 'text', 'text' => "Productos de la tienda (id: nombre (precio)):\n".(CuadernoFoto::lista($productos) ?: '(ninguno)')
            ."\n\n".($texto !== '' ? "Mensaje del cliente:\n\"{$texto}\"" : 'El pedido está en la foto.')."\n\nArma el pedido."];

        $modelo = $foto ? ConteoFoto::modelo() : Asistente::modelo();
        $uso = ['entrada' => 0, 'salida' => 0, 'lectura' => 0, 'escritura' => 0];
        $error = null;
        $datos = null;
        try {
            $r = Asistente::llamar([
                'model' => $modelo, 'maxTokens' => 6000,
                'system' => [['type' => 'text', 'text' => 'Atiendes pedidos que los clientes de bodegas, bazares y ferreterías peruanas mandan por WhatsApp, escritos como hablan '
                    .'("mandame 2 kilos de arroz, un aceite primor y 3 jaboncitos bolivar", "1/2 de azúcar"). Cada cosa pedida es una línea, en orden. '
                    .'Empareja cada línea con el producto de la lista que es lo mismo (sinónimos, diminutivos y abreviaturas); si hay varios parecidos y no sabes cuál '
                    .'(marca o tamaño), elige el más vendido (los primeros de la lista) y dilo en el aviso. Si ninguno es lo mismo, pon 0. Ignora saludos y conversación.']],
                'outputConfig' => ['format' => ['type' => 'json_schema', 'schema' => self::ESQUEMA]],
                'messages' => [['role' => 'user', 'content' => $contenido]],
                'requestOptions' => ['timeout' => 90],
            ]);
            $uso = ['entrada' => (int) ($r->usage->inputTokens ?? 0), 'salida' => (int) ($r->usage->outputTokens ?? 0),
                'lectura' => (int) ($r->usage->cacheReadInputTokens ?? 0), 'escritura' => (int) ($r->usage->cacheCreationInputTokens ?? 0)];
            $json = collect($r->content)->first(fn ($b) => $b->type === 'text')?->text;
            $datos = $r->stopReason === 'end_turn' && $json ? json_decode($json, true) : null;
            if (! is_array($datos) || empty($datos['lineas'])) {
                $error = 'No encontré ningún producto en el pedido. Pega el mensaje del cliente o sube la foto de su lista.';
            }
        } catch (APIStatusException|APIConnectionException) {
            $error = 'No se pudo conectar con la IA. Intenta de nuevo en un momento.';
        }
        Asistente::anotar($user, 'Pedido de WhatsApp'.($texto !== '' ? ': '.mb_substr($texto, 0, 100) : ' (foto)'), $modelo, $uso, 0, $error);
        if ($error) {
            throw new \RuntimeException($error);
        }

        $validos = $productos->pluck('IdProducto')->map(fn ($id) => (int) $id)->flip();

        return [
            'cliente' => ($c = trim((string) ($datos['cliente'] ?? ''))) !== '' ? mb_strtoupper(mb_substr($c, 0, 120)) : null,
            'lineas' => array_values(array_map(fn ($l) => [
                'texto' => mb_strtoupper(mb_substr(trim((string) $l['texto']), 0, 150)),
                'cantidad' => round(max((float) $l['cantidad'], 0), 3) ?: 1.0,
                'producto' => isset($validos[(int) $l['producto']]) ? (int) $l['producto'] : null,
            ], array_slice($datos['lineas'], 0, 100))),
            'aviso' => mb_substr(trim((string) ($datos['aviso'] ?? '')), 0, 400),
        ];
    }
}
