<?php

namespace App\Support\Asistente;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\Central\Cliente;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asistente IA del panel de soporte: el dueño del sistema le pregunta por la base de una empresa ("¿cuántas boletas
 * pendientes tiene?", "¿por qué no cuadra el turno 15?") y responde consultando esa base. SOLO LEE: usa las mismas
 * herramientas del asistente de las empresas y además SQL de solo lectura (ConsultaSegura). Cuando encuentra algo que
 * corregir, lo explica y dice qué opción del sistema o del panel lo arregla; nunca cambia datos.
 * Su gasto va al mismo tope mensual del asistente, sin contar en el cupo de la empresa.
 */
class Soporte
{
    private const VUELTAS = 12;

    public static function modelo(): string
    {
        return (string) config('services.anthropic.modelo_soporte', 'claude-sonnet-5-5');
    }

    /** Por qué no se puede preguntar ahora (null = sí se puede) */
    public static function bloqueo(): ?string
    {
        if (! Asistente::configurado()) {
            return 'El asistente aún no está configurado en el servidor (falta ANTHROPIC_API_KEY).';
        }
        if (! Schema::connection('central')->hasTable('asistente_uso')) {
            return 'Falta actualizar el sistema para usar el asistente.';
        }
        if (Asistente::gastoDelMes() >= (float) config('services.anthropic.tope_mensual_usd', 20)) {
            return 'Se llegó al tope de gasto del mes del asistente (ASISTENTE_TOPE_USD).';
        }

        return null;
    }

    /**
     * @param  list<array{role: string, content: string}>  $historial
     * @return array{respuesta: string, costo: float, consultas: int, sql: list<string>}
     */
    public static function preguntar(Cliente $cliente, string $quien, array $historial, string $pregunta): array
    {
        if ($motivo = self::bloqueo()) {
            throw new \RuntimeException($motivo);
        }
        $modelo = self::modelo();
        $mensajes = [...array_map(fn ($m) => ['role' => $m['role'], 'content' => (string) $m['content']], array_slice($historial, -Asistente::HISTORIAL)),
            ['role' => 'user', 'content' => $pregunta]];
        $uso = ['entrada' => 0, 'salida' => 0, 'lectura' => 0, 'escritura' => 0];
        $consultas = 0;
        $sqlUsados = [];
        $respuesta = '';
        $error = null;

        // Todo lo que se consulta es de la base de esta empresa, como su administrador (sin abrir sesión)
        $admin = Tenancy::en($cliente->base_datos, fn () => User::join('role_user as ru', 'ru.user_IdUsuario', '=', 'users.IdUsuario')
            ->where('ru.role_id', 2)->orderBy('users.IdUsuario')->first(['users.*']));
        if (! $admin) {
            throw new \RuntimeException('La empresa no tiene un administrador: no hay con quién consultar sus datos.');
        }
        $sistema = self::sistema($cliente, $admin);

        try {
            for ($vuelta = 1; $vuelta <= self::VUELTAS; $vuelta++) {
                $r = Asistente::llamar([
                    'model' => $modelo, 'maxTokens' => 16000, 'system' => $sistema, 'outputConfig' => ['effort' => 'medium'],
                    'messages' => $mensajes, 'tools' => self::herramientas(), 'toolChoice' => $vuelta < self::VUELTAS ? null : ['type' => 'none'],
                ]);
                $uso['entrada'] += (int) ($r->usage->inputTokens ?? 0);
                $uso['salida'] += (int) ($r->usage->outputTokens ?? 0);
                $uso['lectura'] += (int) ($r->usage->cacheReadInputTokens ?? 0);
                $uso['escritura'] += (int) ($r->usage->cacheCreationInputTokens ?? 0);

                if ($r->stopReason === 'refusal') {
                    $respuesta = 'No puedo ayudar con esa pregunta.';
                    break;
                }
                if ($r->stopReason !== 'tool_use') {
                    $texto = collect($r->content)->filter(fn ($b) => $b->type === 'text')->map(fn ($b) => $b->text)->implode("\n\n");
                    $respuesta = trim($texto) ?: 'No encontré una respuesta. ¿Puedes preguntarlo de otra forma?';
                    if ($r->stopReason === 'max_tokens') {
                        $respuesta .= "\n\n_(La respuesta quedó cortada: pide una versión más corta.)_";
                    }
                    break;
                }
                $resultados = [];
                foreach ($r->content as $bloque) {
                    if ($bloque->type !== 'tool_use') {
                        continue;
                    }
                    $consultas++;
                    $input = (array) $bloque->input;
                    if ($bloque->name === 'consulta_sql') {
                        $sqlUsados[] = (string) ($input['sql'] ?? '');
                    }
                    $resultados[] = ['type' => 'tool_result', 'toolUseID' => $bloque->id,
                        'content' => Tenancy::en($cliente->base_datos, fn () => self::ejecutar($admin, (string) $bloque->name, $input))];
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
        // Va al tope mensual de todo el sistema, pero no al cupo de la empresa (cliente_id vacío)
        DB::connection('central')->table('asistente_uso')->insert([
            'cliente_id' => null, 'base' => $cliente->base_datos, 'IdUsuario' => 0, 'usuario' => mb_substr('Soporte: '.$quien, 0, 120),
            'pregunta' => mb_substr($pregunta, 0, 500), 'modelo' => $modelo, 'tokens_entrada' => $uso['entrada'], 'tokens_salida' => $uso['salida'],
            'tokens_cache_lectura' => $uso['lectura'], 'tokens_cache_escritura' => $uso['escritura'], 'consultas' => $consultas,
            'costo_usd' => $costo, 'error' => $error ? mb_substr($error, 0, 255) : null, 'created_at' => now(),
        ]);
        if ($error) {
            throw new \RuntimeException($error);
        }

        return ['respuesta' => $respuesta, 'costo' => $costo, 'consultas' => $consultas, 'sql' => array_values(array_filter($sqlUsados))];
    }

    /** @return list<array<string, mixed>> */
    private static function herramientas(): array
    {
        return [
            ...Herramientas::definiciones(),
            [
                'name' => 'tablas',
                'description' => 'Tablas de la base de la empresa. Con "tabla" devuelve sus columnas (nombre, tipo, comentario); con "buscar" filtra los nombres. '
                    .'Úsala antes de escribir SQL si no conoces bien una tabla.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'tabla' => ['type' => 'string', 'description' => 'Nombre exacto de una tabla para ver sus columnas.'],
                    'buscar' => ['type' => 'string', 'description' => 'Parte del nombre de las tablas (ej. cpe, cuentas, turno).'],
                ]],
            ],
            [
                'name' => 'consulta_sql',
                'description' => 'Una consulta SQL de SOLO LECTURA (MySQL 8) sobre la base de la empresa: SELECT, SHOW, DESCRIBE o EXPLAIN, una sola sentencia. '
                    .'Devuelve hasta '.ConsultaSegura::FILAS.' filas (usa LIMIT, GROUP BY y SUM/COUNT para resumir). Las claves y contraseñas salen tapadas. '
                    .'No se puede escribir: INSERT/UPDATE/DELETE se rechazan.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'sql' => ['type' => 'string', 'description' => 'La consulta.'],
                    'para_que' => ['type' => 'string', 'description' => 'Qué buscas con ella, en pocas palabras.'],
                ], 'required' => ['sql']],
            ],
        ];
    }

    private static function ejecutar(User $admin, string $nombre, array $input): string
    {
        // Algunos cálculos de las herramientas miran al usuario con sesión: es el administrador de la empresa
        Auth::guard('web')->setUser($admin);
        try {
            $datos = match ($nombre) {
                'consulta_sql' => ConsultaSegura::ejecutar((string) ($input['sql'] ?? '')),
                'tablas' => ConsultaSegura::tablas($input['tabla'] ?? null, $input['buscar'] ?? null),
                default => null,
            };
        } catch (\InvalidArgumentException $e) {
            return json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        if ($datos === null) {
            return Herramientas::ejecutar($admin, $nombre, $input);
        }

        return json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** @return list<array<string, mixed>> */
    private static function sistema(Cliente $cliente, User $admin): array
    {
        $sedes = Tenancy::en($cliente->base_datos, fn () => DB::table('empresa_negocios')->orderBy('id_empresa_negocio')->get(['id_empresa_negocio', 'nombre_comercial', 'estado'])
            ->map(fn ($s) => "{$s->id_empresa_negocio}: {$s->nombre_comercial} ({$s->estado})")->implode('; '));

        return [
            ['type' => 'text', 'cacheControl' => ['type' => 'ephemeral'], 'text' => <<<'TXT'
Eres el asistente de SOPORTE TÉCNICO de un sistema de gestión peruano (Laravel + MySQL; facturación electrónica SUNAT, punto de
venta, restaurante, almacén y kardex, compras, cuentas por cobrar y por pagar, caja por turnos, contabilidad PCGE). Hablas con el
dueño del sistema, que da soporte a sus clientes: es técnico y quiere diagnósticos precisos.

Cómo trabajas:
- Responde en español, directo: primero la conclusión, luego la evidencia (las cifras y filas que encontraste).
- Antes de afirmar algo, consúltalo. Para cifras de negocio prefiere las herramientas de reportes (son los mismos cálculos que
  ve el cliente); para revisar datos puntuales o encontrar inconsistencias usa consulta_sql. Si no conoces una tabla, mira sus
  columnas con la herramienta tablas. Resume en SQL (COUNT, SUM, GROUP BY, LIMIT) en vez de traer miles de filas.
- SOLO PUEDES LEER. No tienes forma de cambiar datos y no debes ofrecer hacerlo tú. Cuando encuentres algo mal, explica la causa
  y di cómo se corrige con lo que ya existe (la pantalla y el botón del sistema, o la acción del panel de soporte: borrar
  pruebas, anular, consultar/enviar a SUNAT, corregir fecha, completar costos en Rentabilidad…). Si hace falta un arreglo
  directo en la base, escribe la sentencia SQL propuesta en un bloque de código, marcada como "PROPUESTA — revisar y respaldar
  antes", con qué filas afectaría (cuéntalas antes con un SELECT). Nunca afirmes que algo ya quedó corregido.
- Tablas clave: cpe_cabecera (comprobantes: tdocod 01 factura, 03 boleta, 07/08 notas, 13 nota de venta; serdoc, numdoc,
  ccafem fecha, ccaitv total, ccabaj no nulo = anulado, est_sunat estado SUNAT, id_turno, id_empresa_negocio sede),
  cpe_detalle (cdecan cantidad, cdepuni precio, cdevve total línea, costo por unidad vendida), venta_medio_pago (cobros por
  medio), turnos (caja: monto fondo, estado), movimientoscaja (ingresos y egresos de caja, tiposcaja), productos y
  producto_stock (stock por almacén), movimientos_productos (kardex), cuentas_cobrar / cuentas_pagar (+ _detalle, _medios),
  compras_cabecera, empresa_negocios (sedes, series y correlativos: FseEmpresa/FnuEmpresa, BseEmpresa/BnuEmpresa…), users.
- Las claves y contraseñas salen tapadas a propósito: no intentes obtenerlas.
- Montos en soles (S/), fechas como 15/10/2026. Sé breve; usa tablas cortas cuando ayuden.
TXT],
            ['type' => 'text', 'text' => "Empresa consultada: {$cliente->razon_social} (RUC {$cliente->ruc}). Sedes (id: nombre): {$sedes}. "
                ."Sede por defecto para las herramientas de reportes: {$admin->id_empresa_negocio}. Hoy es ".now()->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY').'.'],
        ];
    }
}
