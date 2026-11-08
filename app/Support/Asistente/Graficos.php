<?php

namespace App\Support\Asistente;

/**
 * Gráficos del asistente: cuando el usuario pide ver algo de forma visual, el asistente manda los datos (que ya consultó)
 * y la pantalla del chat los dibuja con Chart.js, igual que el dashboard. Aquí se revisa que vengan bien armados.
 */
class Graficos
{
    public const TIPOS = ['barras', 'lineas', 'torta'];

    public const FORMATOS = ['soles', 'numero', 'porcentaje'];

    /** Como mucho por respuesta, y su tamaño */
    private const MAXIMO = 3;

    private const ETIQUETAS = 62;

    private const SERIES = 4;

    public static function definicion(): array
    {
        return [
            'name' => 'mostrar_grafico',
            'description' => 'Muestra un gráfico al usuario debajo de tu respuesta (barras, líneas o torta) con datos que ya consultaste. '
                .'Úsalo solo si el usuario pide un gráfico o ver algo de forma visual. Las etiquetas son el eje (días, productos, sedes…) '
                .'y cada serie trae un valor por etiqueta, en el mismo orden.',
            'inputSchema' => ['type' => 'object', 'properties' => [
                'tipo' => ['type' => 'string', 'enum' => self::TIPOS, 'description' => 'barras para comparar, lineas para la evolución en el tiempo, torta para partes de un total.'],
                'titulo' => ['type' => 'string', 'description' => 'Título corto, p. ej. "Ventas por día · octubre 2026".'],
                'formato' => ['type' => 'string', 'enum' => self::FORMATOS, 'description' => 'Cómo mostrar los valores.'],
                'etiquetas' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Hasta '.self::ETIQUETAS.' etiquetas.'],
                'series' => ['type' => 'array', 'description' => 'Hasta '.self::SERIES.' series (p. ej. "Este mes" y "Mes pasado"). Torta: una sola serie.',
                    'items' => ['type' => 'object', 'properties' => [
                        'nombre' => ['type' => 'string'],
                        'valores' => ['type' => 'array', 'items' => ['type' => 'number']],
                    ], 'required' => ['nombre', 'valores']]],
            ], 'required' => ['tipo', 'titulo', 'etiquetas', 'series']],
        ];
    }

    /**
     * Revisa el gráfico pedido. Devuelve [gráfico listo para la pantalla o null, mensaje para el asistente].
     *
     * @return array{0: ?array<string, mixed>, 1: string}
     */
    public static function validar(array $g, int $yaHay): array
    {
        if ($yaHay >= self::MAXIMO) {
            return [null, 'Ya mostraste '.self::MAXIMO.' gráficos en esta respuesta: no se muestran más.'];
        }
        $etiquetas = array_map(fn ($e) => mb_substr(trim((string) $e), 0, 40), array_values((array) ($g['etiquetas'] ?? [])));
        $series = array_values(array_filter((array) ($g['series'] ?? []), 'is_array'));
        $tipo = in_array($g['tipo'] ?? '', self::TIPOS, true) ? $g['tipo'] : 'barras';
        if (! $etiquetas || count($etiquetas) > self::ETIQUETAS) {
            return [null, 'No se mostró: pon entre 1 y '.self::ETIQUETAS.' etiquetas (agrupa por semana o mes si son más).'];
        }
        if (! $series || count($series) > self::SERIES || ($tipo === 'torta' && count($series) > 1)) {
            return [null, 'No se mostró: pon entre 1 y '.self::SERIES.' series (la torta lleva una sola).'];
        }
        $limpias = [];
        foreach ($series as $s) {
            $valores = array_values((array) ($s['valores'] ?? []));
            if (count($valores) !== count($etiquetas) || array_filter($valores, fn ($v) => ! is_numeric($v))) {
                return [null, 'No se mostró: cada serie debe traer un número por etiqueta, en el mismo orden ('.count($etiquetas).' valores).'];
            }
            $limpias[] = ['nombre' => mb_substr(trim((string) ($s['nombre'] ?? '')), 0, 40) ?: 'Serie', 'valores' => array_map(fn ($v) => round((float) $v, 2), $valores)];
        }

        return [[
            'tipo' => $tipo, 'titulo' => mb_substr(trim((string) ($g['titulo'] ?? '')), 0, 80),
            'formato' => in_array($g['formato'] ?? '', self::FORMATOS, true) ? $g['formato'] : 'numero',
            'etiquetas' => $etiquetas, 'series' => $limpias,
        ], 'Listo: el gráfico se muestra debajo de tu respuesta. No repitas todos los números en el texto; da la conclusión.'];
    }
}
