<?php

namespace App\Support;

use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Recordatorios de citas por Telegram, gratis: un bot (uno para todas las empresas) le escribe al doctor. Telegram solo
 * deja que un bot le escriba a quien antes le escribió, por eso cada doctor se vincula una vez: abre el enlace
 * t.me/elbot?start=CODIGO y toca Iniciar; el sistema lee ese mensaje (getUpdates) y anota su chat.
 */
class TelegramCitas
{
    public static function token(): ?string
    {
        return config('services.telegram.citas_token') ?: (config('services.telegram.token') ?: null);
    }

    public static function configurado(): bool
    {
        return self::token() !== null;
    }

    /** Usuario del bot (para el enlace t.me/...), se pregunta una vez al día */
    public static function bot(): ?string
    {
        if (! self::configurado()) {
            return null;
        }

        return Cache::remember('telegram-citas-bot:'.md5((string) self::token()), now()->addDay(), function () {
            try {
                return Http::timeout(8)->get('https://api.telegram.org/bot'.self::token().'/getMe')->json('result.username');
            } catch (\Throwable) {
                return null;
            }
        });
    }

    /** El enlace para que el doctor se vincule (cada vez uno nuevo; el anterior deja de servir) */
    public static function enlace(int $usuario): ?string
    {
        $bot = self::bot();
        if (! $bot) {
            return null;
        }
        $codigo = Str::lower(Str::random(16));
        DB::connection('central')->table('telegram_vinculos')->where('base', Tenancy::baseActual())->where('IdUsuario', $usuario)->whereNull('chat_id')->delete();
        DB::connection('central')->table('telegram_vinculos')->insert(['codigo' => $codigo, 'base' => Tenancy::baseActual(), 'IdUsuario' => $usuario, 'created_at' => now()]);

        return "https://t.me/{$bot}?start={$codigo}";
    }

    /**
     * Lee lo que le escribieron al bot: cada "/start CODIGO" vincula al doctor de ese código (de cualquier empresa).
     * Devuelve cuántos se vincularon.
     */
    public static function recibir(): int
    {
        if (! self::configurado() || ! Schema::connection('central')->hasTable('telegram_vinculos')) {
            return 0;
        }
        $clave = 'telegram-citas-offset:'.md5((string) self::token());
        try {
            $r = Http::timeout(10)->get('https://api.telegram.org/bot'.self::token().'/getUpdates', ['offset' => (int) Cache::get($clave, 0), 'timeout' => 0]);
        } catch (\Throwable) {
            return 0;
        }
        $vinculados = 0;
        foreach ((array) $r->json('result') as $u) {
            Cache::forever($clave, (int) $u['update_id'] + 1);
            $mensaje = $u['message'] ?? null;
            if (! $mensaje || ! preg_match('#^/start\s+([a-z0-9]{16})$#i', trim((string) ($mensaje['text'] ?? '')), $m)) {
                if ($mensaje && isset($mensaje['chat']['id'])) {
                    self::enviar((string) $mensaje['chat']['id'], 'Hola 👋 Para recibir aquí tus citas, toca "Vincular Telegram" en el sistema de tu clínica.');
                }

                continue;
            }
            $fila = DB::connection('central')->table('telegram_vinculos')->where('codigo', Str::lower($m[1]))->first();
            if (! $fila) {
                self::enviar((string) $mensaje['chat']['id'], 'Ese enlace ya no sirve. Pide uno nuevo en el sistema de tu clínica.');

                continue;
            }
            $nombre = trim(($mensaje['from']['first_name'] ?? '').' '.($mensaje['from']['last_name'] ?? ''));
            DB::connection('central')->table('telegram_vinculos')->where('id', $fila->id)
                ->update(['chat_id' => (string) $mensaje['chat']['id'], 'nombre' => mb_substr($nombre, 0, 120) ?: null, 'vinculado_at' => now()]);
            self::enviar((string) $mensaje['chat']['id'], '✅ Listo'.($nombre ? ", {$nombre}" : '').'. Aquí te llegarán tus citas: la lista del día siguiente y un aviso antes de cada una.');
            $vinculados++;
        }

        return $vinculados;
    }

    /** Pasa a los usuarios de la empresa conectada los chats que se vincularon */
    public static function sincronizar(): void
    {
        if (! Schema::connection('central')->hasTable('telegram_vinculos') || ! Schema::hasColumn('users', 'telegram_chat_id')) {
            return;
        }
        foreach (DB::connection('central')->table('telegram_vinculos')->where('base', Tenancy::baseActual())->whereNotNull('chat_id')->get() as $v) {
            DB::table('users')->where('IdUsuario', $v->IdUsuario)->update(['telegram_chat_id' => $v->chat_id]);
        }
    }

    public static function desvincular(int $usuario): void
    {
        DB::table('users')->where('IdUsuario', $usuario)->update(['telegram_chat_id' => null]);
        DB::connection('central')->table('telegram_vinculos')->where('base', Tenancy::baseActual())->where('IdUsuario', $usuario)->delete();
    }

    /** @return array{0: bool, 1: string} */
    public static function enviar(string $chat, string $texto): array
    {
        if (! self::configurado()) {
            return [false, 'Telegram no está configurado en el servidor.'];
        }
        try {
            $r = Http::timeout(10)->asForm()->post('https://api.telegram.org/bot'.self::token().'/sendMessage',
                ['chat_id' => $chat, 'text' => mb_substr($texto, 0, 4000), 'disable_web_page_preview' => 'true']);
        } catch (\Throwable $e) {
            return [false, mb_substr('Sin conexión con Telegram: '.$e->getMessage(), 0, 250)];
        }

        return $r->json('ok') ? [true, 'Telegram'] : [false, mb_substr('Telegram: '.($r->json('description') ?: 'error '.$r->status()), 0, 250)];
    }
}
