<?php

namespace App\Support\Asistente;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * Asistente IA del explorador de bases del panel: el dueño le pregunta qué consulta escribir ("¿cómo veo las boletas
 * pendientes?", "¿cómo le pongo precio 25 al producto AF00016?") y responde con la orden MySQL lista para copiar.
 * Para entender la base puede MIRAR (tablas, columnas y SELECT de solo lectura), pero NUNCA ejecuta cambios: el dueño
 * revisa la orden y la ejecuta él mismo, en modo edición, con sus frenos.
 */
class AsistenteSql
{
    private const VUELTAS = 8;

    private const SISTEMA = <<<'TXT'
Eres un experto en MySQL 8 que ayuda al dueño de un sistema de gestión peruano (ventas con SUNAT, productos, stock,
caja, compras, contabilidad) a escribir consultas sobre la base de UNA empresa. Respondes en español, breve y claro.

Cómo trabajas:
- Antes de escribir una consulta, mira las tablas y columnas reales con la herramienta "tablas" (no inventes nombres).
  Si hace falta, revisa datos con "consulta_sql" (solo lectura) para confirmar valores (por ejemplo el IdProducto exacto).
- Entrega la consulta en un bloque ```sql … ``` listo para copiar, una orden por bloque.
- Si piden CAMBIAR datos (UPDATE, DELETE, INSERT):
  1. Primero da un SELECT que muestre exactamente las filas que se van a cambiar, para revisarlas.
  2. Luego el UPDATE/DELETE con un WHERE preciso (por llave: IdProducto, IdCpe_cabecera…). NUNCA un UPDATE o DELETE
     sin WHERE ni con un WHERE que abarque toda la tabla.
  3. Avisa qué otras tablas podrían quedar desalineadas (por ejemplo, el stock va en producto_stock y su historia en
     movimientos_productos: cambiar uno sin el otro descuadra el kardex) y si conviene hacerlo mejor desde el sistema.
- Nunca propongas DROP, TRUNCATE, ALTER, CREATE ni cambios de estructura, ni tocar comprobantes ya enviados a SUNAT
  (cpe_cabecera con est_sunat aceptado): eso se corrige con notas de crédito desde el sistema.
- Tú no ejecutas cambios: el dueño los revisa y los ejecuta en el modo edición del panel.
TXT;

    /**
     * @return array{respuesta: string, costo: float}
     *
     * @throws \RuntimeException si la IA no está disponible
     */
    public static function sugerir(string $base, string $quien, string $pregunta): array
    {
        if ($motivo = Soporte::bloqueo()) {
            throw new \RuntimeException($motivo);
        }
        $modelo = Soporte::modelo();
        $mensajes = [['role' => 'user', 'content' => $pregunta]];
        $sistema = [['type' => 'text', 'cacheControl' => ['type' => 'ephemeral'], 'text' => self::SISTEMA],
            ['type' => 'text', 'text' => "Base de datos: {$base}. Hoy es ".now()->format('d/m/Y').'.']];
        $herramientas = [
            ['name' => 'tablas', 'description' => 'Tablas de la base. Con "tabla" devuelve sus columnas; con "buscar" filtra los nombres.',
                'inputSchema' => ['type' => 'object', 'properties' => ['tabla' => ['type' => 'string'], 'buscar' => ['type' => 'string']]]],
            ['name' => 'consulta_sql', 'description' => 'Un SELECT de SOLO LECTURA sobre la base (una sentencia, hasta '.ConsultaSegura::FILAS.' filas). Para revisar datos antes de proponer la consulta.',
                'inputSchema' => ['type' => 'object', 'properties' => ['sql' => ['type' => 'string']], 'required' => ['sql']]],
        ];
        $uso = ['entrada' => 0, 'salida' => 0, 'lectura' => 0, 'escritura' => 0];
        $respuesta = '';
        $error = null;
        try {
            for ($vuelta = 1; $vuelta <= self::VUELTAS; $vuelta++) {
                $r = Asistente::llamar(['model' => $modelo, 'maxTokens' => 8000, 'system' => $sistema, 'outputConfig' => ['effort' => 'medium'],
                    'messages' => $mensajes, 'tools' => $herramientas, 'toolChoice' => $vuelta < self::VUELTAS ? null : ['type' => 'none']]);
                $uso['entrada'] += (int) ($r->usage->inputTokens ?? 0);
                $uso['salida'] += (int) ($r->usage->outputTokens ?? 0);
                $uso['lectura'] += (int) ($r->usage->cacheReadInputTokens ?? 0);
                $uso['escritura'] += (int) ($r->usage->cacheCreationInputTokens ?? 0);
                if ($r->stopReason !== 'tool_use') {
                    $respuesta = trim(collect($r->content)->filter(fn ($b) => $b->type === 'text')->map(fn ($b) => $b->text)->implode("\n\n"))
                        ?: 'No encontré una respuesta. ¿Puedes preguntarlo de otra forma?';
                    break;
                }
                $resultados = [];
                foreach ($r->content as $bloque) {
                    if ($bloque->type !== 'tool_use') {
                        continue;
                    }
                    $input = (array) $bloque->input;
                    $resultado = Tenancy::en($base, function () use ($bloque, $input) {
                        try {
                            return json_encode($bloque->name === 'consulta_sql'
                                ? ConsultaSegura::ejecutar((string) ($input['sql'] ?? ''))
                                : ConsultaSegura::tablas($input['tabla'] ?? null, $input['buscar'] ?? null), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                        } catch (\InvalidArgumentException $e) {
                            return json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                        }
                    });
                    $resultados[] = ['type' => 'tool_result', 'toolUseID' => $bloque->id, 'content' => mb_substr((string) $resultado, 0, 60000)];
                }
                $mensajes[] = ['role' => 'assistant', 'content' => $r->content];
                $mensajes[] = ['role' => 'user', 'content' => $resultados];
            }
        } catch (AuthenticationException) {
            $error = 'La clave del asistente no es válida.';
        } catch (RateLimitException) {
            $error = 'El asistente está ocupado. Intenta en un minuto.';
        } catch (APIStatusException $e) {
            $error = 'El asistente no respondió ('.$e->getMessage().').';
        } catch (APIConnectionException) {
            $error = 'No se pudo conectar con el asistente.';
        }

        $costo = Asistente::costo($modelo, $uso['entrada'], $uso['salida'], $uso['lectura'], $uso['escritura']);
        DB::connection('central')->table('asistente_uso')->insert([
            'cliente_id' => null, 'base' => $base, 'IdUsuario' => 0, 'usuario' => mb_substr('Base de datos: '.$quien, 0, 120),
            'pregunta' => mb_substr($pregunta, 0, 500), 'modelo' => $modelo, 'tokens_entrada' => $uso['entrada'], 'tokens_salida' => $uso['salida'],
            'tokens_cache_lectura' => $uso['lectura'], 'tokens_cache_escritura' => $uso['escritura'], 'consultas' => 0,
            'costo_usd' => $costo, 'error' => $error ? mb_substr($error, 0, 255) : null, 'created_at' => now(),
        ]);
        if ($error) {
            throw new \RuntimeException($error);
        }

        return ['respuesta' => $respuesta, 'costo' => $costo];
    }
}
