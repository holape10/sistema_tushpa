<?php

namespace App\Support\Asistente;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\User;
use App\Support\ImagenProducto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crear productos hablando (Productos › Crear con voz): "jabón bolívar a 3.50, stock 24, la caja de 12 a 38 soles"
 * → nombre, precio, costo, stock inicial, unidad, categoría y presentaciones. Solo interpreta: la persona revisa en
 * pantalla y al aceptar se guardan con el mismo guardado del formulario de Nuevo Producto. Cuenta como una pregunta.
 * Con foto (Productos › Crear con foto): la IA lee el paquete (marca, contenido, código de barras) y la foto queda
 * guardada aparte hasta que se crea el producto, para que sea su imagen.
 */
class ProductoVoz
{
    public const EXTENSIONES = 'jpg,jpeg,png,webp';

    /** Fotos leídas que esperan a que se cree su producto (storage/app/…); las de más de un día se borran solas */
    public const CARPETA_FOTOS = 'productos-foto';

    /** Lado mayor de la foto que se manda y se guarda */
    private const LADO_FOTO = 1600;

    private const ESQUEMA = [
        'type' => 'object', 'additionalProperties' => false, 'required' => ['productos', 'respuesta'],
        'properties' => [
            'productos' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false,
                'required' => ['nombre', 'precio', 'costo', 'stock', 'unidad', 'categoria', 'codigo_barra', 'presentaciones'],
                'properties' => [
                    'nombre' => ['type' => 'string', 'description' => 'Nombre del producto en MAYÚSCULAS, claro y completo (marca y medida si las dice): "JABON BOLIVAR 190 G"'],
                    'precio' => ['type' => ['number', 'null'], 'description' => 'Precio de venta de UNA unidad base'],
                    'costo' => ['type' => ['number', 'null'], 'description' => 'Costo / precio de compra de una unidad base, si lo dice'],
                    'stock' => ['type' => ['number', 'null'], 'description' => 'Stock inicial en unidades base. "2 cajas de 12" = 24'],
                    'unidad' => ['type' => 'string', 'description' => 'Código de la unidad base de la lista (NIU si es por unidad)'],
                    'categoria' => ['type' => ['string', 'null'], 'description' => 'Categoría si la dice, en MAYÚSCULAS'],
                    'codigo_barra' => ['type' => ['string', 'null'], 'description' => 'Solo los dígitos del código de barras si se leen completos (EAN-13, EAN-8 o UPC); si no, null'],
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

        return self::pedir($user, Asistente::modelo(), 'Crear productos con voz: '.mb_substr($texto, 0, 120),
            'Registras productos de comercios peruanos (bodegas, ferreterías, minimarkets) a partir de lo que la persona dicta. '
            .'Uno o varios productos por frase. El precio de la unidad base y el de cada presentación son distintos: "la caja de 12 a 38 soles" es una presentación (factor 12, precio 38). '
            .'El stock inicial va en unidades base. Si no dice algo, déjalo null (no inventes precios). Los números dichos en palabras pásalos a número. '
            .'Usa SOLO códigos de unidad de la lista. Si dice una categoría parecida a una existente, usa la existente.',
            fn (string $contexto) => $contexto.'La persona dice: "'.mb_substr($texto, 0, 1500).'"',
            'No entendí ningún producto. Dilo así: «jabón bolívar a 3.50, stock 24, la caja de 12 a 38 soles».');
    }

    /**
     * Lee el paquete de la foto (un producto por foto). $texto: lo que la persona agrega (precio, stock…), puede ir vacío.
     * La foto queda guardada con un código (foto) para que sea la imagen del producto al crearlo.
     *
     * @return array{productos: list<array<string, mixed>>, respuesta: string}
     */
    public static function leerFoto(User $user, UploadedFile $foto, string $texto = ''): array
    {
        if ($motivo = Asistente::bloqueoComun()) {
            throw new \RuntimeException($motivo);
        }
        $carpeta = storage_path('app/'.self::CARPETA_FOTOS);
        if (! is_dir($carpeta)) {
            @mkdir($carpeta, 0775, true);
        }
        self::limpiarFotos($carpeta);
        $codigo = (string) Str::uuid();
        $ruta = "{$carpeta}/{$codigo}.webp";
        if (! ImagenProducto::redimensionar($foto->getRealPath(), $ruta, self::LADO_FOTO, ImagenProducto::orientacion($foto->getRealPath()))) {
            @unlink($ruta);
            throw new \RuntimeException('No se pudo abrir la foto. Toma una foto JPG o PNG.');
        }
        $imagen = base64_encode((string) file_get_contents($ruta));
        $texto = trim(mb_substr($texto, 0, 500));

        try {
            $leido = self::pedir($user, ConteoFoto::modelo(), 'Crear producto con foto'.($texto !== '' ? ': '.mb_substr($texto, 0, 100) : ''),
                'Registras productos de comercios peruanos (bodegas, ferreterías, minimarkets, farmacias) leyendo la foto de su paquete o etiqueta. '
                .'Devuelve UN producto: el que se ve. Nombre = tipo de producto + marca + variedad + contenido, como se pide en la tienda: "LECHE EVAPORADA GLORIA 400 G", "ACEITE PRIMOR 900 ML". '
                .'Copia la marca y el contenido neto tal como están impresos, no los adivines. '
                .'Precio, costo, stock y presentaciones: solo si la persona los dice o hay un precio de venta impreso; si no, null o vacío (no inventes precios). '
                .'Elige la categoría de la lista existente que corresponda; si ninguna sirve, propone una corta y común (ABARROTES, BEBIDAS, LIMPIEZA, LACTEOS…). '
                .'Usa SOLO códigos de unidad de la lista (NIU si se vende por unidad).',
                fn (string $contexto) => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/webp', 'data' => $imagen]],
                    ['type' => 'text', 'text' => $contexto.($texto !== '' ? 'La persona agrega: "'.$texto.'"' : 'La persona no agregó nada.')."\n\n¿Qué producto es?"],
                ],
                'No se reconoce el producto en la foto. Tómala de frente, con buena luz y que se lea la etiqueta.');
        } catch (\RuntimeException $e) {
            @unlink($ruta);
            throw $e;
        }
        $leido['productos'] = [array_merge($leido['productos'][0], ['foto' => $codigo])];

        return $leido;
    }

    /** La foto leída que espera su producto (null si el código no es válido o ya no existe) */
    public static function rutaFoto(?string $codigo): ?string
    {
        if (! $codigo || ! Str::isUuid($codigo)) {
            return null;
        }
        $ruta = storage_path('app/'.self::CARPETA_FOTOS."/{$codigo}.webp");

        return is_file($ruta) ? $ruta : null;
    }

    /** Borra las fotos leídas de más de un día (las que nunca llegaron a ser producto) */
    private static function limpiarFotos(string $carpeta): void
    {
        foreach (glob("{$carpeta}/*.webp") ?: [] as $vieja) {
            if (filemtime($vieja) < time() - 86400) {
                @unlink($vieja);
            }
        }
    }

    /**
     * Llama a la IA con las unidades y categorías de la empresa y deja los productos listos para revisar en pantalla.
     *
     * @param  \Closure(string): (string|list<array<string, mixed>>)  $contenido  lo que se manda, a partir del contexto
     * @return array{productos: list<array<string, mixed>>, respuesta: string}
     */
    private static function pedir(User $user, string $modelo, string $que, string $instrucciones, \Closure $contenido, string $sinProductos): array
    {
        $unidades = DB::table('unidad_medida')->orderBy('umenom')->pluck('umenom', 'umecod');
        $listaUnidades = $unidades->map(fn ($n, $c) => "{$c}={$n}")->implode(', ');
        $categorias = DB::table('categorias')->where('id_empresa_negocio', $user->id_empresa_negocio)->orderBy('cat_nom')->limit(80)->pluck('cat_nom')->implode(', ');
        $contexto = "Unidades (código=nombre): {$listaUnidades}\nCategorías existentes: ".($categorias ?: '(ninguna)')."\n\n";

        $uso = ['entrada' => 0, 'salida' => 0, 'lectura' => 0, 'escritura' => 0];
        $error = null;
        $datos = null;
        try {
            $r = Asistente::llamar([
                'model' => $modelo, 'maxTokens' => 3000,
                'system' => [['type' => 'text', 'text' => $instrucciones]],
                'outputConfig' => ['format' => ['type' => 'json_schema', 'schema' => self::ESQUEMA]],
                'messages' => [['role' => 'user', 'content' => $contenido($contexto)]],
            ]);
            $uso = ['entrada' => (int) ($r->usage->inputTokens ?? 0), 'salida' => (int) ($r->usage->outputTokens ?? 0),
                'lectura' => (int) ($r->usage->cacheReadInputTokens ?? 0), 'escritura' => (int) ($r->usage->cacheCreationInputTokens ?? 0)];
            $json = collect($r->content)->first(fn ($b) => $b->type === 'text')?->text;
            $datos = $r->stopReason === 'end_turn' && $json ? json_decode($json, true) : null;
            if (! is_array($datos) || empty($datos['productos'])) {
                $error = $sinProductos;
            }
        } catch (APIStatusException|APIConnectionException) {
            $error = 'No se pudo conectar con la IA. Intenta de nuevo en un momento.';
        }
        Asistente::anotar($user, $que, $modelo, $uso, 0, $error);
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
            // Solo un código de barras que se lee completo (8 a 14 dígitos)
            'codigo_barra' => preg_match('/^\d{8,14}$/', $barra = preg_replace('/\D/', '', (string) ($p['codigo_barra'] ?? ''))) ? $barra : null,
            'presentaciones' => array_values(array_map(fn ($x) => ['nombre' => mb_strtoupper(mb_substr(trim((string) $x['nombre']), 0, 60)), 'unidad' => $unidad($x['unidad']),
                'factor' => round((float) $x['factor'], 3), 'precio' => round((float) $x['precio'], 2)],
                array_filter($p['presentaciones'] ?? [], fn ($x) => (float) $x['factor'] > 0 && (float) $x['precio'] > 0))),
        ], array_slice($datos['productos'], 0, 20));

        return ['productos' => $productos, 'respuesta' => mb_substr(trim((string) ($datos['respuesta'] ?? '')), 0, 200)];
    }
}
