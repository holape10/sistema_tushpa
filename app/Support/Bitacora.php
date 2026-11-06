<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de auditoría: deja escrito quién hizo un cambio sensible, cuándo, desde qué equipo y qué valor había antes.
 *
 * Reglas para que nunca cause problemas:
 *  - Registrar nunca hace fallar la operación: si algo sale mal aquí, la venta/compra/cambio sigue y el error va al log.
 *  - Se escribe en la misma conexión: si la operación está en una transacción y se deshace, su registro también
 *    (no quedan registros de cosas que no pasaron).
 *  - Las claves, tokens y certificados nunca se guardan: solo se anota que cambiaron.
 *  - Solo se agregan filas: no hay pantalla ni ruta para editarlas o borrarlas.
 *  - Si la base aún no tiene la tabla (migración pendiente), no se registra nada y todo sigue igual.
 *
 * Acciones: "modulo.que" (ej. producto.editar). El módulo es lo de antes del punto.
 */
class Bitacora
{
    /** Módulo => nombre para el filtro */
    public const MODULOS = [
        'sesion' => 'Inicios de sesión',
        'usuario' => 'Usuarios y permisos',
        'venta' => 'Ventas',
        'caja' => 'Caja',
        'cuenta' => 'Cobros y pagos',
        'compra' => 'Compras',
        'gasto' => 'Gastos',
        'producto' => 'Productos y precios',
        'almacen' => 'Almacén y stock',
        'config' => 'Configuración',
        'contabilidad' => 'Contabilidad',
        'respaldo' => 'Respaldos',
        'sistema' => 'Sistema',
    ];

    /**
     * Lo que la Línea de tiempo ya muestra desde sus propias tablas (para no repetirlo allí): el comprobante anulado
     * (nota de venta o baja aceptada por SUNAT) y la compra registrada.
     */
    public const YA_EN_LINEA_TIEMPO = ['venta.anular', 'venta.baja_aceptada', 'compra.registrar'];

    /** Ícono de cada módulo (Línea de tiempo y pantalla de la bitácora) */
    public const ICONOS = ['sesion' => '🔑', 'usuario' => '👤', 'venta' => '🧾', 'caja' => '💵', 'cuenta' => '💳', 'compra' => '📦', 'gasto' => '🧾',
        'producto' => '🏷️', 'almacen' => '📦', 'config' => '⚙️', 'contabilidad' => '📒', 'respaldo' => '💾', 'sistema' => '🛠️'];

    /** Campos que nunca se comparan (cambian solos) */
    private const IGNORAR = ['created_at', 'updated_at', 'remember_token', 'deleted_at'];

    /** Campos cuyo valor nunca se guarda: solo se anota "(cambió)" */
    private const SECRETO = '/(password|clave|secret|token|passcert|certificado|cert_|pfx|firma_digital|api_key|pin$)/i';

    /** Tope de texto por valor y por registro (que una descripción larga no rompa el insert) */
    private const MAX_VALOR = 500;

    private const MAX_JSON = 60000;

    /** @var array<string, bool> base => ¿tiene la tabla? */
    private static array $disponible = [];

    /** @var array<int, string|null> usuario => rol (una consulta por usuario en cada petición) */
    private static array $roles = [];

    /**
     * Registra una acción.
     *
     * @param  array<string, array{etiqueta?: string, antes: mixed, despues: mixed}>|null  $cambios  lo que devuelve diferencias()
     * @param  array{referencia?: array{0: string, 1: int|string}, datos?: array, alerta?: bool, usuario?: User|null, nombre?: string, sucursal?: int|null, empresa?: string|null}  $extra
     */
    public static function registrar(string $accion, string $descripcion, ?array $cambios = null, array $extra = []): void
    {
        try {
            if (! self::disponible()) {
                return;
            }
            $usuario = array_key_exists('usuario', $extra) ? $extra['usuario'] : self::usuarioActual();
            $peticion = app()->runningInConsole() ? null : request();
            [$refTipo, $refId] = $extra['referencia'] ?? [null, null];

            DB::table('bitacora')->insert([
                'fecha' => now(),
                'IdUsuario' => $usuario?->IdUsuario,
                'usuario' => self::corto($extra['nombre'] ?? ($usuario ? (trim((string) $usuario->apeusu) ?: $usuario->name) : (app()->runningInConsole() ? 'Sistema' : null)), 120),
                'rol' => $usuario ? self::rol((int) $usuario->IdUsuario) : null,
                'id_empresa_negocio' => array_key_exists('sucursal', $extra) ? $extra['sucursal'] : $usuario?->id_empresa_negocio,
                'IdEmpresa' => self::corto(array_key_exists('empresa', $extra) ? $extra['empresa'] : $usuario?->IdEmpresa, 11),
                'modulo' => self::corto(strtok($accion, '.') ?: $accion, 30),
                'accion' => self::corto($accion, 60),
                'descripcion' => self::corto($descripcion, 255) ?? '',
                'referencia_tipo' => self::corto($refTipo, 40),
                'referencia_id' => $refId !== null ? self::corto((string) $refId, 40) : null,
                'cambios' => $cambios ? self::json($cambios) : null,
                'datos' => ! empty($extra['datos']) ? self::json($extra['datos']) : null,
                'alerta' => (bool) ($extra['alerta'] ?? false),
                'ip' => self::corto($peticion?->ip(), 45),
                'agente' => self::corto($peticion?->userAgent(), 255),
                'ruta' => self::corto($peticion?->route()?->getName() ?? ($peticion ? $peticion->method().' '.$peticion->path() : null), 120),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Registra solo si hubo cambios entre las dos fotos. Devuelve los cambios (vacío si no hubo).
     *
     * @param  array<string, string>  $etiquetas  campo => nombre para mostrar; si se pasa, solo se comparan esos campos
     *                                            (salvo $extra['todos_los_campos'] = true: se comparan todos)
     * @param  array<string, mixed>  $extra  como en registrar()
     * @return array<string, array{etiqueta: string, antes: mixed, despues: mixed}>
     */
    public static function siCambio(string $accion, string $descripcion, ?array $antes, ?array $despues, array $etiquetas = [], array $extra = []): array
    {
        $cambios = self::diferencias($antes, $despues, $etiquetas, (bool) ($extra['todos_los_campos'] ?? false));
        unset($extra['todos_los_campos']);
        if ($cambios) {
            self::registrar($accion, $descripcion, $cambios, $extra);
        }

        return $cambios;
    }

    /**
     * La fila tal como está ahora (para comparar después), o null si no existe o no se pudo leer.
     *
     * @param  array<string, mixed>  $donde
     * @return array<string, mixed>|null
     */
    public static function foto(string $tabla, array $donde): ?array
    {
        try {
            if (! self::disponible()) {
                return null;
            }
            $fila = DB::table($tabla)->where($donde)->first();

            return $fila ? (array) $fila : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Qué cambió entre dos fotos: los números se comparan como números ("3.00" = "3"), vacío y null son lo mismo, las
     * listas se comparan como conjuntos (agregados y quitados) y los secretos se anotan sin su valor.
     *
     * Con etiquetas solo se comparan esos campos; con $todosLosCampos se comparan todos y las etiquetas solo ponen nombre.
     *
     * @param  array<string, string>  $etiquetas
     * @return array<string, array{etiqueta: string, antes: mixed, despues: mixed}>
     */
    public static function diferencias(?array $antes, ?array $despues, array $etiquetas = [], bool $todosLosCampos = false): array
    {
        $antes ??= [];
        $despues ??= [];
        $campos = $etiquetas && ! $todosLosCampos ? array_keys($etiquetas) : array_unique(array_merge(array_keys($antes), array_keys($despues)));
        $cambios = [];
        foreach ($campos as $campo) {
            if (in_array($campo, self::IGNORAR, true)) {
                continue;
            }
            $a = $antes[$campo] ?? null;
            $d = $despues[$campo] ?? null;
            if (self::iguales($a, $d)) {
                continue;
            }
            $etiqueta = $etiquetas[$campo] ?? $campo;
            if (preg_match(self::SECRETO, $campo)) {
                $cambios[$campo] = ['etiqueta' => $etiqueta, 'antes' => null, 'despues' => '(cambió)'];

                continue;
            }
            if (is_array($a) || is_array($d)) {
                $a = array_values(array_map('strval', (array) $a));
                $d = array_values(array_map('strval', (array) $d));
                $cambios[$campo] = ['etiqueta' => $etiqueta, 'agrego' => array_values(array_diff($d, $a)), 'quito' => array_values(array_diff($a, $d)),
                    'antes' => null, 'despues' => null];

                continue;
            }
            $cambios[$campo] = ['etiqueta' => $etiqueta, 'antes' => self::valor($a), 'despues' => self::valor($d)];
        }

        return $cambios;
    }

    /** "Precio: 3.00 → 3.50 · Estado: Activo → Inactivo" (para la Línea de tiempo y las búsquedas) */
    public static function resumen(?array $cambios, int $max = 3): string
    {
        $partes = [];
        foreach (array_slice($cambios ?? [], 0, $max) as $c) {
            if (isset($c['agrego']) || isset($c['quito'])) {
                $partes[] = $c['etiqueta'].': '.trim(($c['agrego'] ? '+'.implode(', +', $c['agrego']).' ' : '').($c['quito'] ? '−'.implode(', −', $c['quito']) : ''));
            } elseif ($c['despues'] === '(cambió)' && $c['antes'] === null) {
                $partes[] = $c['etiqueta'].' cambió';
            } else {
                $partes[] = $c['etiqueta'].': '.self::mostrar($c['antes']).' → '.self::mostrar($c['despues']);
            }
        }
        $resto = count($cambios ?? []) - $max;

        return implode(' · ', $partes).($resto > 0 ? " · y {$resto} cambio(s) más" : '');
    }

    public static function mostrar(mixed $v): string
    {
        return $v === null || $v === '' ? '(vacío)' : (string) $v;
    }

    /** La base actual tiene la tabla (se pregunta una vez por base) */
    public static function disponible(): bool
    {
        try {
            $base = (string) DB::connection()->getDatabaseName();

            return self::$disponible[$base] ??= Schema::hasTable('bitacora');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Para las pruebas y para cuando una migración crea la tabla en la misma petición */
    public static function olvidar(): void
    {
        self::$disponible = [];
        self::$roles = [];
    }

    private static function usuarioActual(): ?User
    {
        try {
            $u = Auth::guard('web')->user();

            return $u instanceof User ? $u : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function rol(int $usuario): ?string
    {
        if (! array_key_exists($usuario, self::$roles)) {
            self::$roles[$usuario] = self::corto(DB::table('role_user as r')->join('roles as ro', 'ro.id', '=', 'r.role_id')
                ->where('r.user_IdUsuario', $usuario)->value('ro.description'), 60);
        }

        return self::$roles[$usuario];
    }

    private static function iguales(mixed $a, mixed $b): bool
    {
        if (is_array($a) || is_array($b)) {
            $a = array_map('strval', (array) $a);
            $b = array_map('strval', (array) $b);
            sort($a);
            sort($b);

            return $a === $b;
        }
        $a = is_bool($a) ? (int) $a : $a;
        $b = is_bool($b) ? (int) $b : $b;
        if (($a === null || $a === '') && ($b === null || $b === '')) {
            return true;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.000001;
        }

        return trim((string) $a) === trim((string) $b);
    }

    private static function valor(mixed $v): mixed
    {
        if ($v === null || is_int($v) || is_float($v)) {
            return $v;
        }
        $v = is_bool($v) ? ($v ? '1' : '0') : (string) $v;
        if (! mb_check_encoding($v, 'UTF-8')) {
            return '(archivo)';
        }

        return mb_strlen($v) > self::MAX_VALOR ? mb_substr($v, 0, self::MAX_VALOR).'…' : $v;
    }

    private static function corto(mixed $v, int $largo): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = (string) $v;
        if (! mb_check_encoding($v, 'UTF-8')) {
            $v = mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
        }

        return mb_substr($v, 0, $largo);
    }

    private static function json(array $datos): string
    {
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json === false || strlen($json) > self::MAX_JSON) {
            return json_encode(['recortado' => true, 'campos' => array_slice(array_keys($datos), 0, 100)], JSON_UNESCAPED_UNICODE);
        }

        return $json;
    }
}
