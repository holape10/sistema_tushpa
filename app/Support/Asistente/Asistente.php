<?php

namespace App\Support\Asistente;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\User;
use App\Support\SucursalesVisibles;
use App\Support\Tenancy\Tenancy;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asistente IA (Claude, de Anthropic) para el administrador: responde preguntas del negocio consultando el sistema con
 * herramientas de solo lectura (Herramientas). Cada pregunta queda anotada en la base central con sus tokens y su costo,
 * para que el panel TUSHPA muestre el gasto y nunca se pase: hay un límite de preguntas al mes por empresa y un tope
 * de gasto mensual de todo el sistema (config/services.php › anthropic).
 */
class Asistente
{
    /** Vueltas como mucho por pregunta (cada vuelta: el asistente pide datos y los recibe) */
    private const VUELTAS = 8;

    /** Precios por millón de tokens en dólares: entrada, salida, lectura de caché, escritura de caché */
    private const PRECIOS = [
        'claude-opus-5-5' => [4.00, 20.00, 0.20, 5.00],
        'claude-sonnet-5-5' => [2.00, 10.00, 0.20, 2.50],
        'claude-haiku-5-5' => [0.10, 0.50, 0.01, 0.125],
    ];

    /** Mensajes de la conversación que se recuerdan (los más recientes) */
    public const HISTORIAL = 12;

    /** Para las pruebas: reemplaza la llamada a la API (recibe los parámetros, devuelve la respuesta) */
    public static ?Closure $falso = null;

    public static function configurado(): bool
    {
        return self::$falso !== null || (string) config('services.anthropic.key') !== '';
    }

    public static function modelo(): string
    {
        return (string) config('services.anthropic.modelo', 'claude-opus-5-5');
    }

    /** Costo en dólares de lo usado */
    public static function costo(string $modelo, int $entrada, int $salida, int $cacheLectura, int $cacheEscritura): float
    {
        [$pe, $ps, $pl, $pc] = self::PRECIOS[$modelo] ?? self::PRECIOS['claude-opus-5-5'];

        return round(($entrada * $pe + $salida * $ps + $cacheLectura * $pl + $cacheEscritura * $pc) / 1_000_000, 5);
    }

    /** Mes en curso: lo gastado por todo el sistema */
    public static function gastoDelMes(): float
    {
        return (float) DB::connection('central')->table('asistente_uso')->where('created_at', '>=', now()->startOfMonth())->sum('costo_usd');
    }

    /** Preguntas de esta empresa en el mes, y su límite */
    public static function cupo(): array
    {
        $cliente = Tenancy::cliente();
        $usadas = DB::connection('central')->table('asistente_uso')->where('created_at', '>=', now()->startOfMonth())->whereNull('error')
            ->when($cliente, fn ($q) => $q->where('cliente_id', $cliente->id), fn ($q) => $q->where('base', Tenancy::baseActual()))->count();
        $limite = (int) ($cliente->asistente_limite ?? 0) ?: (int) config('services.anthropic.limite_empresa', 100);

        return ['usadas' => $usadas, 'limite' => $limite, 'quedan' => max($limite - $usadas, 0)];
    }

    /** Por qué no se puede preguntar ahora (null = sí se puede) */
    public static function bloqueo(User $user): ?string
    {
        if (! $user->esAdmin()) {
            return 'Por ahora el asistente es solo para el administrador.';
        }
        if (! self::configurado()) {
            return 'El asistente aún no está configurado en el servidor.';
        }
        if (! Schema::connection('central')->hasTable('asistente_uso')) {
            return 'Falta actualizar el sistema para usar el asistente.';
        }
        $cliente = Tenancy::cliente();
        if ($cliente && ! $cliente->asistente_activo) {
            return 'El asistente no está activado para tu empresa. Pídelo a soporte.';
        }
        if (self::cupo()['quedan'] <= 0) {
            return 'Ya usaste todas las preguntas de este mes. Se renuevan el 1 del próximo mes.';
        }
        if (self::gastoDelMes() >= (float) config('services.anthropic.tope_mensual_usd', 20)) {
            return 'El asistente está en pausa por este mes. Vuelve el 1 del próximo mes.';
        }

        return null;
    }

    /**
     * Responde una pregunta (con lo conversado antes). Devuelve la respuesta en texto (markdown simple).
     *
     * @param  list<array{role: string, content: string}>  $historial
     * @return array{respuesta: string, costo: float, consultas: int}
     */
    public static function preguntar(User $user, array $historial, string $pregunta): array
    {
        if ($motivo = self::bloqueo($user)) {
            throw new \RuntimeException($motivo);
        }
        $modelo = self::modelo();
        $mensajes = [...array_slice($historial, -self::HISTORIAL), ['role' => 'user', 'content' => $pregunta]];
        $uso = ['entrada' => 0, 'salida' => 0, 'lectura' => 0, 'escritura' => 0];
        $consultas = 0;
        $respuesta = '';
        $error = null;

        try {
            for ($vuelta = 1; $vuelta <= self::VUELTAS; $vuelta++) {
                // En la última vuelta ya no puede consultar más: tiene que responder con lo que tiene
                $r = self::llamar([
                    'model' => $modelo, 'maxTokens' => 16000, 'system' => self::sistema($user),
                    'outputConfig' => ['effort' => 'medium'], 'messages' => $mensajes, 'tools' => Herramientas::definiciones(),
                    'toolChoice' => $vuelta < self::VUELTAS ? null : ['type' => 'none'],
                ]);
                $uso['entrada'] += (int) ($r->usage->inputTokens ?? 0);
                $uso['salida'] += (int) ($r->usage->outputTokens ?? 0);
                $uso['lectura'] += (int) ($r->usage->cacheReadInputTokens ?? 0);
                $uso['escritura'] += (int) ($r->usage->cacheCreationInputTokens ?? 0);

                if ($r->stopReason === 'refusal') {
                    $respuesta = 'No puedo ayudar con esa pregunta. Prueba preguntando de otra forma sobre tus ventas, stock o cuentas.';
                    break;
                }
                $texto = collect($r->content)->filter(fn ($b) => $b->type === 'text')->map(fn ($b) => $b->text)->implode("\n\n");
                if ($r->stopReason !== 'tool_use') {
                    $respuesta = trim($texto) ?: 'No encontré una respuesta. ¿Puedes preguntarlo de otra forma?';
                    if ($r->stopReason === 'max_tokens') {
                        $respuesta .= "\n\n_(La respuesta quedó cortada: pide una versión más corta.)_";
                    }
                    break;
                }
                // Pidió datos: se los damos (todas las consultas de esta vuelta van juntas en un solo mensaje)
                $resultados = [];
                foreach ($r->content as $bloque) {
                    if ($bloque->type === 'tool_use') {
                        $consultas++;
                        $resultados[] = ['type' => 'tool_result', 'toolUseID' => $bloque->id,
                            'content' => Herramientas::ejecutar($user, (string) $bloque->name, (array) $bloque->input)];
                    }
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
            $error = 'No se pudo conectar con el asistente. Revisa la conexión e intenta de nuevo.';
        }

        $costo = self::costo($modelo, $uso['entrada'], $uso['salida'], $uso['lectura'], $uso['escritura']);
        $cliente = Tenancy::cliente();
        DB::connection('central')->table('asistente_uso')->insert([
            'cliente_id' => $cliente?->id, 'base' => Tenancy::baseActual(), 'IdUsuario' => $user->IdUsuario, 'usuario' => mb_substr((string) $user->apeusu, 0, 120),
            'pregunta' => mb_substr($pregunta, 0, 500), 'modelo' => $modelo, 'tokens_entrada' => $uso['entrada'], 'tokens_salida' => $uso['salida'],
            'tokens_cache_lectura' => $uso['lectura'], 'tokens_cache_escritura' => $uso['escritura'], 'consultas' => $consultas,
            'costo_usd' => $costo, 'error' => $error ? mb_substr($error, 0, 255) : null, 'created_at' => now(),
        ]);
        if ($error) {
            throw new \RuntimeException($error);
        }

        return ['respuesta' => $respuesta, 'costo' => $costo, 'consultas' => $consultas];
    }

    /** Llama a la API (o al reemplazo de las pruebas) */
    private static function llamar(array $p): object
    {
        if (self::$falso) {
            return (self::$falso)($p);
        }
        // La clave se pasa directo: con la configuración en caché, Laravel no deja el .env en el entorno del proceso
        $cliente = new Client(apiKey: (string) config('services.anthropic.key'), baseUrl: config('services.anthropic.base_url') ?: null);

        return $cliente->messages->create(
            maxTokens: $p['maxTokens'], messages: $p['messages'], model: $p['model'],
            outputConfig: $p['outputConfig'], system: $p['system'], toolChoice: $p['toolChoice'], tools: $p['tools'],
        );
    }

    /**
     * Instrucciones: la parte fija primero (se guarda en caché y abarata cada pregunta) y luego el contexto de la empresa.
     *
     * @return list<array<string, mixed>>
     */
    private static function sistema(User $user): array
    {
        $empresa = DB::table('empresa')->where('IdEmpresa', $user->IdEmpresa)->first(['NomEmpresa', 'IdEmpresa']);
        $sedes = SucursalesVisibles::de($user)->map(fn ($s) => "{$s->id_empresa_negocio}: {$s->nombre_comercial}")->implode('; ');

        return [
            ['type' => 'text', 'cacheControl' => ['type' => 'ephemeral'], 'text' => <<<'TXT'
Eres el asistente de negocios dentro de un sistema de gestión peruano (ventas con facturación electrónica SUNAT, restaurante,
almacén, compras, cuentas por cobrar y por pagar, y contabilidad con el PCGE). Hablas con el administrador o dueño del negocio.

Cómo trabajas:
- Responde en español claro y sencillo, como un contador o analista de confianza que le explica a un dueño de negocio.
- Usa las herramientas para obtener los datos reales antes de afirmar cualquier número. Nunca inventes cifras: si un dato
  no está en las herramientas, dilo y sugiere dónde verlo en el sistema.
- Montos en soles (S/) salvo que el dato diga otra moneda. Fechas como 15/10/2026.
- Empieza por la respuesta directa y luego el detalle. Usa listas o tablas cortas cuando ayuden. Sé breve.
- Cuando des una conclusión o recomendación, di en qué datos te basas y, si hay incertidumbre, dilo.
- Solo puedes consultar: no registras, cambias ni borras nada. Si te piden hacer algo, explica cómo hacerlo en el sistema.
- Si la pregunta es ambigua (qué sede, qué periodo), asume lo razonable (la sede actual, el mes en curso) y dilo.
- Los temas tributarios dependen de cada caso: da orientación general y recomienda confirmarlo con su contador.
TXT],
            ['type' => 'text', 'text' => 'Empresa: '.($empresa->NomEmpresa ?? '').' (RUC '.($empresa->IdEmpresa ?? '').'). '
                ."Sedes que puedes consultar (id: nombre): {$sedes}. Sede actual del usuario: {$user->id_empresa_negocio}. "
                .'Hoy es '.now()->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY').'.'],
        ];
    }
}
