<?php

namespace App\Support\Asistente;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\User;
use App\Support\ImagenProducto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Ventas del cuaderno con foto (Ventas › Pasar cuaderno): la IA lee una hoja escrita a mano (fecha, cada línea con su
 * cantidad, precio e importe, y el total de abajo) y empareja cada línea con un producto de la sucursal. Solo lee: la
 * persona revisa en pantalla, corrige y registra la hoja como una venta. Cuenta como una pregunta del asistente.
 */
class CuadernoFoto
{
    public const EXTENSIONES = 'jpg,jpeg,png,webp';

    /** Lado mayor de la foto: la letra a mano necesita más detalle que una etiqueta */
    private const LADO_FOTO = 2000;

    /** Cuántos productos se le pasan a la IA para emparejar (los activos, primero los más vendidos) */
    private const MAX_PRODUCTOS = 800;

    private const ESQUEMA = [
        'type' => 'object', 'additionalProperties' => false, 'required' => ['es_cuaderno', 'fecha', 'total', 'lineas', 'aviso'],
        'properties' => [
            'es_cuaderno' => ['type' => 'boolean', 'description' => 'false si la foto no es una hoja de ventas anotadas'],
            'fecha' => ['type' => ['string', 'null'], 'description' => 'Fecha escrita en la hoja en formato AAAA-MM-DD (26 = 2026); null si no hay'],
            'total' => ['type' => ['number', 'null'], 'description' => 'El total escrito al final de la hoja (la suma subrayada); null si no hay'],
            'lineas' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['texto', 'cantidad', 'precio', 'importe', 'producto'],
                'properties' => [
                    'texto' => ['type' => 'string', 'description' => 'Lo escrito, corregido para leerse bien: "2 BOXER NIÑO X7"'],
                    'cantidad' => ['type' => 'number', 'description' => 'Cantidad (½ = 0.5). Si no hay número, 1'],
                    'precio' => ['type' => 'number', 'description' => 'Precio de UNA unidad: el que va después de la "x"; si no hay, importe ÷ cantidad'],
                    'importe' => ['type' => 'number', 'description' => 'El monto de la columna derecha de esa línea'],
                    'producto' => ['type' => 'integer', 'description' => 'Id del producto de la lista que corresponde a la línea; 0 si ninguno corresponde'],
                ],
            ]],
            'aviso' => ['type' => 'string', 'description' => 'Qué debe revisar la persona (líneas dudosas, números que no se leen); vacío si nada'],
        ],
    ];

    /**
     * @return array{fecha: ?string, total: ?float, lineas: list<array{texto: string, cantidad: float, precio: float, importe: float, producto: ?int}>, aviso: string}
     */
    public static function leer(User $user, UploadedFile $foto): array
    {
        if ($motivo = Asistente::bloqueoComun()) {
            throw new \RuntimeException($motivo);
        }
        $sucursal = (int) $user->id_empresa_negocio;
        $productos = DB::table('productos as p')->where('p.id_empresa_negocio', $sucursal)->where('p.proest', 'Activo')
            ->leftJoinSub(DB::table('cpe_detalle as d')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
                ->where('c.id_empresa_negocio', $sucursal)->where('c.fecha_hora', '>=', now()->subDays(90))
                ->groupBy('d.IdProducto')->selectRaw('d.IdProducto, COUNT(*) as veces'), 'v', 'v.IdProducto', '=', 'p.IdProducto')
            ->orderByDesc(DB::raw('COALESCE(v.veces, 0)'))->orderBy('p.pronom')->limit(self::MAX_PRODUCTOS)
            ->get(['p.IdProducto', 'p.pronom', 'p.propun']);
        $lista = $productos->map(fn ($p) => "{$p->IdProducto}: {$p->pronom} (S/ ".number_format((float) $p->propun, 2, '.', '').')')->implode("\n");

        $tmp = tempnam(sys_get_temp_dir(), 'cua').'.webp';
        if (! ImagenProducto::redimensionar($foto->getRealPath(), $tmp, self::LADO_FOTO, ImagenProducto::orientacion($foto->getRealPath()))) {
            @unlink($tmp);
            throw new \RuntimeException('No se pudo abrir la foto. Toma una foto JPG o PNG.');
        }
        $imagen = base64_encode((string) file_get_contents($tmp));
        @unlink($tmp);

        $modelo = ConteoFoto::modelo();
        $uso = ['entrada' => 0, 'salida' => 0, 'lectura' => 0, 'escritura' => 0];
        $error = null;
        $datos = null;
        try {
            $r = Asistente::llamar([
                'model' => $modelo, 'maxTokens' => 12000,
                'system' => [['type' => 'text', 'text' => 'Lees hojas de cuaderno donde comercios peruanos (bazares, bodegas, ferreterías) anotan a mano lo que venden en el día. '
                    .'Cada renglón: cantidad, producto y a veces "x precio unitario" (ej. "2 Boxer niño x7"); a la derecha, en columna, el importe de ese renglón (14.00). '
                    .'Al final, la suma subrayada es el total del día. Ojo: a veces la columna de importes está corrida una fila arriba o abajo de los productos; empareja cada importe con su renglón por orden. '
                    .'"½" o "1/2" es 0.5; "dc" o "doc." es docena; "pq" es paquete; "mts" metros. Copia todos los renglones, en orden, aunque se repitan. '
                    .'Para cada renglón elige el producto de la lista que es lo mismo (sinónimos y abreviaturas: "Amaca" = HAMACA, "Valde" = BALDE, "Pila Tokal" = PILA TOKAL (PAR)); '
                    .'si ninguno es claramente el mismo, pon 0 (no fuerces). No inventes números: si uno no se lee, ponlo en el aviso.']],
                'outputConfig' => ['format' => ['type' => 'json_schema', 'schema' => self::ESQUEMA]],
                'messages' => [['role' => 'user', 'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/webp', 'data' => $imagen]],
                    ['type' => 'text', 'text' => "Productos de la tienda (id: nombre (precio)):\n".($lista ?: '(ninguno)')."\n\nLee la hoja del cuaderno."],
                ]]],
                'requestOptions' => ['timeout' => 110],
            ]);
            $uso = ['entrada' => (int) ($r->usage->inputTokens ?? 0), 'salida' => (int) ($r->usage->outputTokens ?? 0),
                'lectura' => (int) ($r->usage->cacheReadInputTokens ?? 0), 'escritura' => (int) ($r->usage->cacheCreationInputTokens ?? 0)];
            $texto = collect($r->content)->first(fn ($b) => $b->type === 'text')?->text;
            $datos = $r->stopReason === 'end_turn' && $texto ? json_decode($texto, true) : null;
            if (! is_array($datos) || empty($datos['es_cuaderno']) || empty($datos['lineas'])) {
                $error = 'No se pudo leer la hoja. Tómale foto de frente, con buena luz y que se vea la hoja completa (con el total de abajo).';
            }
        } catch (APIStatusException|APIConnectionException) {
            $error = 'No se pudo conectar con la IA. Intenta de nuevo en un momento.';
        }
        Asistente::anotar($user, 'Cuaderno con foto', $modelo, $uso, 0, $error);
        if ($error) {
            throw new \RuntimeException($error);
        }

        // Solo productos de esta sucursal; la fecha, solo si es válida y no es futura
        $validos = $productos->pluck('IdProducto')->map(fn ($id) => (int) $id)->flip();
        $fecha = is_string($datos['fecha'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $datos['fecha']) && strtotime($datos['fecha']) && $datos['fecha'] <= now()->toDateString()
            ? $datos['fecha'] : null;

        return [
            'fecha' => $fecha,
            'total' => isset($datos['total']) ? round((float) $datos['total'], 2) : null,
            'lineas' => array_values(array_map(fn ($l) => [
                'texto' => mb_strtoupper(mb_substr(trim((string) $l['texto']), 0, 150)),
                'cantidad' => round(max((float) $l['cantidad'], 0), 3) ?: 1.0,
                'precio' => round(max((float) $l['precio'], 0), 2),
                'importe' => round(max((float) $l['importe'], 0), 2),
                'producto' => isset($validos[(int) $l['producto']]) ? (int) $l['producto'] : null,
            ], array_slice($datos['lineas'], 0, 80))),
            'aviso' => mb_substr(trim((string) ($datos['aviso'] ?? '')), 0, 400),
        ];
    }
}
