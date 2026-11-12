<?php

namespace App\Support\Asistente;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\User;
use App\Support\Compras;
use App\Support\ImagenProducto;
use App\Support\Unidades;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Contar un producto con una foto (inventario y conteo ciego): la IA ve el producto suelto, las cajas o la ruma
 * (filas × columnas × pisos) y dice cuántas hay en la presentación que ve; el sistema lo pasa a la unidad del stock con el
 * factor de esa presentación (12 cajas × 24 = 288 unidades). Nunca recibe el stock del sistema (el conteo ciego sigue
 * ciego). Es una ayuda: la persona revisa y confirma la cantidad. Cuenta como una pregunta del asistente.
 */
class ConteoFoto
{
    public const EXTENSIONES = 'jpg,jpeg,png,webp';

    /** Lado mayor de la foto que se manda (la IA no ve más detalle con una más grande) */
    private const LADO_FOTO = 1600;

    private const ESQUEMA = [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['es_producto', 'calculo', 'presentacion', 'cantidad', 'sueltos', 'confianza', 'supone_oculto', 'aviso'],
        'properties' => [
            'es_producto' => ['type' => 'boolean', 'description' => 'false si en la foto no se ve el producto indicado o no se puede contar'],
            'calculo' => ['type' => 'string', 'description' => 'Cómo contaste, corto: "5 filas × 4 columnas × 6 pisos = 120" o "18 botellas en 3 filas de 6". Escríbelo ANTES de decidir la cantidad.'],
            'presentacion' => ['type' => 'integer', 'description' => 'Número de la presentación que contaste según la lista (0 = la unidad base)'],
            'cantidad' => ['type' => 'number', 'description' => 'Cuántas hay de esa presentación (cajas completas, sacos, unidades…)'],
            'sueltos' => ['type' => 'number', 'description' => 'Unidades base sueltas además de lo anterior (0 si no hay). Ej.: 12 cajas y 5 botellas sueltas → sueltos 5'],
            'confianza' => ['type' => 'string', 'enum' => ['alta', 'media', 'baja']],
            'supone_oculto' => ['type' => 'boolean', 'description' => 'true si asumiste que la parte que no se ve (atrás o al medio de la ruma) está completa'],
            'aviso' => ['type' => 'string', 'description' => 'Qué debe revisar la persona (vacío si nada). Ej.: "No se ve la parte de atrás", "Hay cajas abiertas".'],
        ],
    ];

    /**
     * Cuenta el producto en la foto.
     *
     * @return array{cantidad: float, detalle: string, presentacion: string, confianza: string, aviso: string}
     */
    public static function contar(User $user, int $idProducto, UploadedFile $foto): array
    {
        $producto = DB::table('productos')->where('IdProducto', $idProducto)->where('id_empresa_negocio', $user->id_empresa_negocio)
            ->whereIn('promocion', Compras::TIPOS_CON_STOCK)->first(['IdProducto', 'pronom', 'umecod']);
        if (! $producto) {
            throw new \RuntimeException('Producto no encontrado.');
        }
        if ($motivo = Asistente::bloqueoComun()) {
            throw new \RuntimeException($motivo);
        }

        $unidad = $producto->umecod ?: 'NIU';
        // Presentaciones: 0 = la unidad base; las demás con su factor (1 caja = 24 unidades)
        $presentaciones = [0 => ['nombre' => Unidades::nombre($unidad, 1), 'factor' => 1.0]];
        foreach (DB::table('producto_presentacion')->where('IdProducto', $producto->IdProducto)->where('estado', 1)->where('factor', '>', 0)
            ->orderBy('factor')->get(['id_presentacion', 'nombre', 'factor']) as $p) {
            $presentaciones[(int) $p->id_presentacion] = ['nombre' => trim((string) $p->nombre) ?: 'Presentación', 'factor' => (float) $p->factor];
        }
        $lista = collect(array_values($presentaciones))->map(fn ($p, $i) => $i === 0
            ? "0: {$p['nombre']} (unidad base)"
            : "{$i}: {$p['nombre']} = ".rtrim(rtrim(number_format($p['factor'], 3, '.', ''), '0'), '.').' '.Unidades::nombre($unidad))->implode("\n");

        $tmp = tempnam(sys_get_temp_dir(), 'cnt').'.webp';
        if (! ImagenProducto::redimensionar($foto->getRealPath(), $tmp, self::LADO_FOTO, ImagenProducto::orientacion($foto->getRealPath()))) {
            @unlink($tmp);
            throw new \RuntimeException('No se pudo abrir la foto. Toma una foto JPG o PNG.');
        }
        $imagen = base64_encode((string) file_get_contents($tmp));
        @unlink($tmp);

        $modelo = self::modelo();
        $uso = ['entrada' => 0, 'salida' => 0, 'lectura' => 0, 'escritura' => 0];
        $error = null;
        $datos = null;
        try {
            $r = Asistente::llamar([
                'model' => $modelo, 'maxTokens' => 2000,
                'system' => [['type' => 'text', 'text' => 'Cuentas mercadería para inventarios de tiendas y almacenes peruanos (ferreterías, bodegas, distribuidoras). '
                    .'Cuenta solo el producto indicado. En rumas o pilas ordenadas cuenta filas × columnas × pisos con lo que se ve en el frente, el costado y arriba. '
                    .'Elige la presentación que realmente se ve (si son cajas cerradas, cuenta cajas; si son unidades sueltas, la unidad base). '
                    .'No inventes: si algo no se ve, dilo en el aviso y baja la confianza.']],
                'outputConfig' => ['format' => ['type' => 'json_schema', 'schema' => self::ESQUEMA]],
                'messages' => [['role' => 'user', 'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/webp', 'data' => $imagen]],
                    ['type' => 'text', 'text' => "Producto a contar: {$producto->pronom}\nPresentaciones (número: nombre = equivalencia):\n{$lista}\n\n¿Cuántos hay en la foto?"],
                ]]],
            ]);
            $uso = ['entrada' => (int) ($r->usage->inputTokens ?? 0), 'salida' => (int) ($r->usage->outputTokens ?? 0),
                'lectura' => (int) ($r->usage->cacheReadInputTokens ?? 0), 'escritura' => (int) ($r->usage->cacheCreationInputTokens ?? 0)];
            $texto = collect($r->content)->first(fn ($b) => $b->type === 'text')?->text;
            $datos = $r->stopReason === 'end_turn' && $texto ? json_decode($texto, true) : null;
            if (! is_array($datos) || empty($datos['es_producto'])) {
                $error = 'No se pudo contar ese producto en la foto. Tómala con buena luz, que se vea todo y de frente o en diagonal (frente y costado de la ruma).';
            }
        } catch (APIStatusException|APIConnectionException) {
            $error = 'No se pudo conectar con la IA. Intenta de nuevo en un momento.';
        }
        Asistente::anotar($user, 'Conteo con foto: '.mb_substr((string) $producto->pronom, 0, 120), $modelo, $uso, 0, $error);
        if ($error) {
            throw new \RuntimeException($error);
        }

        // La presentación viene por su número en la lista; uno que no existe se toma como la unidad base
        $elegida = array_values($presentaciones)[(int) $datos['presentacion']] ?? $presentaciones[0];
        $cantidad = max(0, (float) $datos['cantidad']);
        $sueltos = max(0, (float) $datos['sueltos']);
        $total = round($cantidad * $elegida['factor'] + $sueltos, 3);

        $nombreBase = fn (float $n) => Unidades::nombre($unidad, abs($n) == 1 ? 1 : null);
        $num = fn (float $n) => rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
        $detalle = $elegida['factor'] != 1.0
            ? "{$num($cantidad)} {$elegida['nombre']} × {$num($elegida['factor'])}".($sueltos ? " + {$num($sueltos)} sueltos" : '')." = {$num($total)} {$nombreBase($total)}"
            : "{$num($total)} {$nombreBase($total)}";

        return [
            'cantidad' => $total,
            'detalle' => $detalle,
            'calculo' => mb_substr(trim((string) $datos['calculo']), 0, 300),
            'presentacion' => $elegida['nombre'],
            'confianza' => in_array($datos['confianza'], ['alta', 'media', 'baja'], true) ? $datos['confianza'] : 'baja',
            'aviso' => mb_substr(trim((string) $datos['aviso'].(! empty($datos['supone_oculto']) ? ' Se supuso que la parte que no se ve está completa.' : '')), 0, 300),
        ];
    }

    /** Contar en una foto pide un modelo con buena vista: por defecto Sonnet (configurable) */
    public static function modelo(): string
    {
        return (string) config('services.anthropic.modelo_conteo', 'claude-sonnet-5-5');
    }
}
