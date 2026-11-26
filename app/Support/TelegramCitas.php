<?php

namespace App\Support;

use App\Support\Asistente\TelegramAsistente;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Collection;
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

    /** Base "de mentira" de los usuarios del panel TUSHPA (su agenda), para no confundirlos con los de una empresa */
    public const PANEL = 'panel';

    /**
     * El enlace para que el doctor (o un usuario del panel, con $base = PANEL) se vincule. Cada vez uno nuevo; el
     * anterior deja de servir.
     */
    public static function enlace(int $usuario, ?string $base = null): ?string
    {
        $bot = self::bot();
        if (! $bot) {
            return null;
        }
        $base ??= Tenancy::baseActual();
        $codigo = Str::lower(Str::random(16));
        DB::connection('central')->table('telegram_vinculos')->where('base', $base)->where('IdUsuario', $usuario)->whereNull('chat_id')->delete();
        DB::connection('central')->table('telegram_vinculos')->insert(['codigo' => $codigo, 'base' => $base, 'IdUsuario' => $usuario, 'created_at' => now()]);

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
                // Un Telegram ya vinculado le pregunta al Asistente IA de su empresa; si no, se le pide vincularlo
                if ($mensaje && isset($mensaje['chat']['id'])
                    && ! TelegramAsistente::recibir((string) $mensaje['chat']['id'], (string) ($mensaje['text'] ?? ''))) {
                    self::enviar((string) $mensaje['chat']['id'], 'Hola 👋 Para recibir aquí tus avisos, toca "Vincular Telegram" en el sistema.');
                }

                continue;
            }
            $fila = DB::connection('central')->table('telegram_vinculos')->where('codigo', Str::lower($m[1]))->first();
            if (! $fila) {
                self::enviar((string) $mensaje['chat']['id'], 'Ese enlace ya no sirve. Pide uno nuevo en el sistema de tu clínica.');

                continue;
            }
            $nombre = trim(($mensaje['from']['first_name'] ?? '').' '.($mensaje['from']['last_name'] ?? ''));
            $chat = (string) $mensaje['chat']['id'];
            if ($fila->base === self::PANEL) {
                // El mismo Telegram vinculado otra vez: se queda el nuevo vínculo (no se cuenta dos veces)
                DB::connection('central')->table('telegram_vinculos')->where('base', self::PANEL)->where('IdUsuario', $fila->IdUsuario)
                    ->where('chat_id', $chat)->where('id', '!=', $fila->id)->delete();
                if (count(self::chatsPanel((int) $fila->IdUsuario)) >= self::MAX_PANEL) {
                    DB::connection('central')->table('telegram_vinculos')->where('id', $fila->id)->delete();
                    self::enviar($chat, 'Ese usuario ya tiene '.self::MAX_PANEL.' Telegram vinculados. Quita uno en la Agenda del panel y vuelve a intentarlo.');

                    continue;
                }
            }
            DB::connection('central')->table('telegram_vinculos')->where('id', $fila->id)
                ->update(['chat_id' => (string) $mensaje['chat']['id'], 'nombre' => mb_substr($nombre, 0, 120) ?: null, 'vinculado_at' => now()]);
            self::enviar((string) $mensaje['chat']['id'], '✅ Listo'.($nombre ? ", {$nombre}" : '').'. Desde ahora te llegarán aquí tus avisos: '
                .($fila->base === self::PANEL ? 'los recordatorios de tu agenda y las alertas de tus empresas.'
                    : 'tus avisos del sistema. Si eres el administrador, también puedes preguntarme por tu negocio: "¿cuánto vendí hoy?"'));
            if ($fila->base === self::PANEL) {
                DB::connection('central')->table('superadmins')->where('id', $fila->IdUsuario)->whereNull('telegram_chat_id')->update(['telegram_chat_id' => $chat]);
            }
            $vinculados++;
        }

        return $vinculados;
    }

    /** Cuántos Telegram puede vincular cada usuario del panel (por ejemplo su celular, el de su socio y el de su asistente) */
    public const MAX_PANEL = 3;

    /**
     * Los Telegram vinculados de un usuario del panel (id, chat_id, nombre, vinculado_at).
     *
     * @return Collection<int, object>
     */
    public static function vinculosPanel(int $usuario): Collection
    {
        if (! Schema::connection('central')->hasTable('telegram_vinculos')) {
            return collect();
        }

        return DB::connection('central')->table('telegram_vinculos')->where('base', self::PANEL)->where('IdUsuario', $usuario)
            ->whereNotNull('chat_id')->orderBy('id')->get(['id', 'chat_id', 'nombre', 'vinculado_at'])->unique('chat_id')->values();
    }

    /**
     * Los chats a los que se le avisa a un usuario del panel: sus Telegram vinculados (y el antiguo, si quedó solo en su ficha).
     *
     * @return list<string>
     */
    public static function chatsPanel(int $usuario): array
    {
        $chats = self::vinculosPanel($usuario)->pluck('chat_id')->map(fn ($c) => (string) $c);
        if ($chats->isEmpty() && ($antiguo = DB::connection('central')->table('superadmins')->where('id', $usuario)->value('telegram_chat_id'))) {
            $chats->push((string) $antiguo);
        }

        return $chats->unique()->values()->all();
    }

    /** Quita uno de los Telegram del usuario del panel (o todos); su ficha queda con otro que siga vinculado */
    public static function quitarPanel(int $usuario, ?int $vinculo = null): void
    {
        DB::connection('central')->table('telegram_vinculos')->where('base', self::PANEL)->where('IdUsuario', $usuario)
            ->when($vinculo, fn ($q) => $q->where('id', $vinculo))->delete();
        DB::connection('central')->table('superadmins')->where('id', $usuario)
            ->update(['telegram_chat_id' => self::vinculosPanel($usuario)->first()?->chat_id]);
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
