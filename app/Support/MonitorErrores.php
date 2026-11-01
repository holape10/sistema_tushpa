<?php

namespace App\Support;

use App\Support\Tenancy\Tenancy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Monitor de errores: cada falla del sistema se anota en la base central (una fila por error, con cuántas veces pasó y
 * en qué empresa) y se avisa al Telegram del dueño la primera vez; si se repite, a lo más un aviso por hora.
 * Nunca rompe nada: si el monitor mismo falla, la página sigue su curso normal.
 */
class MonitorErrores
{
    /** Minutos entre avisos del mismo error que se repite */
    public const MINUTOS_ENTRE_AVISOS = 60;

    /** Lo que no es una falla del sistema: datos mal escritos, sin permiso, sesión vencida, página que no existe */
    private const IGNORADOS = [ValidationException::class, AuthenticationException::class, AuthorizationException::class,
        TokenMismatchException::class, ModelNotFoundException::class];

    private static bool $anotando = false;

    public static function ignorar(\Throwable $e): bool
    {
        foreach (self::IGNORADOS as $clase) {
            if ($e instanceof $clase) {
                return true;
            }
        }

        return $e instanceof HttpExceptionInterface && $e->getStatusCode() < 500;
    }

    /** El mismo error aunque cambien los números del mensaje (ids, montos, horas) */
    public static function huella(\Throwable $e): string
    {
        $mensaje = preg_replace('/\d+/', '#', $e->getMessage());

        return sha1(get_class($e).'|'.$e->getFile().'|'.$e->getLine().'|'.mb_substr((string) $mensaje, 0, 300));
    }

    public static function registrar(\Throwable $e): void
    {
        if (self::$anotando || self::ignorar($e) || app()->runningUnitTests()) {
            return;
        }
        self::$anotando = true;
        try {
            $central = DB::connection('central');
            $huella = self::huella($e);
            $ahora = now();
            $cliente = Tenancy::cliente();
            $usuario = Auth::user();
            $fila = $central->table('errores_sistema')->where('huella', $huella)->first();
            $datos = [
                'url' => mb_substr(app()->runningInConsole() ? 'consola: '.implode(' ', array_slice($_SERVER['argv'] ?? [], 1, 3))
                    : request()->method().' '.request()->fullUrl(), 0, 300),
                'base_datos' => mb_substr((string) DB::connection()->getDatabaseName(), 0, 64),
                'empresa' => $cliente ? mb_substr(($cliente->nombre_comercial ?: $cliente->razon_social).' · '.$cliente->ruc, 0, 150) : null,
                'usuario' => $usuario ? mb_substr(trim(($usuario->apeusu ?? '') ?: ($usuario->name ?? $usuario->nombre ?? '')), 0, 120) : null,
                'ultima_vez' => $ahora,
            ];

            if (! $fila) {
                $id = $central->table('errores_sistema')->insertGetId($datos + [
                    'huella' => $huella, 'tipo' => mb_substr(get_class($e), 0, 150), 'mensaje' => mb_substr($e->getMessage(), 0, 2000),
                    'archivo' => mb_substr(str_replace(base_path().DIRECTORY_SEPARATOR, '', $e->getFile()), 0, 255), 'linea' => $e->getLine(),
                    'veces' => 1, 'primera_vez' => $ahora,
                ]);
                $avisar = true;
            } else {
                $id = $fila->id;
                // Si ya se había marcado como resuelto y vuelve a pasar, se reabre y se avisa
                $central->table('errores_sistema')->where('id', $id)->update($datos + ['veces' => $fila->veces + 1, 'resuelto' => false]);
                $avisar = $fila->resuelto || ! $fila->avisado_el || now()->parse($fila->avisado_el)->lt($ahora->copy()->subMinutes(self::MINUTOS_ENTRE_AVISOS));
            }

            if ($avisar && self::avisar(self::texto($central->table('errores_sistema')->where('id', $id)->first()))) {
                $central->table('errores_sistema')->where('id', $id)->update(['avisado_el' => $ahora]);
            }
        } catch (\Throwable) {
            // El monitor nunca debe tumbar la página
        } finally {
            self::$anotando = false;
        }
    }

    /** Mensaje para Telegram (texto simple, sin formato: así ningún símbolo del error lo rompe) */
    public static function texto(object $f): string
    {
        return '🚨 Error en TUSHPA'.($f->veces > 1 ? " (pasó {$f->veces} veces)" : '')."\n\n"
            .($f->empresa ? "🏢 {$f->empresa}\n" : '').($f->usuario ? "👤 {$f->usuario}\n" : '').($f->url ? "🔗 {$f->url}\n" : '')
            ."\n❌ ".mb_substr($f->mensaje, 0, 500)."\n📄 {$f->archivo}:{$f->linea}\n\nRevísalo en el panel → Errores del sistema.";
    }

    /** Manda el mensaje al Telegram configurado (TELEGRAM_BOT_TOKEN y TELEGRAM_CHAT_ID). false = no se pudo o no está configurado */
    public static function avisar(string $texto): bool
    {
        $token = config('services.telegram.token');
        $chat = config('services.telegram.chat_id');
        if (! $token || ! $chat) {
            return false;
        }
        try {
            return Http::timeout(5)->asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chat, 'text' => mb_substr($texto, 0, 4000), 'disable_web_page_preview' => 'true',
            ])->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
