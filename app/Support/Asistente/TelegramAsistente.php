<?php

namespace App\Support\Asistente;

use App\Models\User;
use App\Support\TelegramCitas;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El Asistente IA por Telegram: el administrador le escribe al bot ("¿cuánto vendí hoy?", "¿qué se está acabando?")
 * desde su celular y le responde con los datos de su empresa, sin abrir el sistema. Usa el mismo bot de los avisos
 * (ya vinculado) y el mismo Asistente IA (mismas herramientas y el mismo cupo de preguntas).
 *  - El bot solo lee los mensajes (getUpdates); aquí la pregunta se anota y telegram:responder la contesta (cada minuto).
 *  - Si el Telegram está vinculado a varias empresas: /empresas las lista y /usar 2 elige de cuál preguntar.
 */
class TelegramAsistente
{
    /** Mensajes de la conversación que se recuerdan por chat (para preguntas como "¿y ayer?") */
    private const HISTORIAL = 6;

    public static function disponible(): bool
    {
        return Schema::connection('central')->hasTable('telegram_preguntas');
    }

    /**
     * Las empresas a las que está vinculado un chat (no el panel), la más reciente primero.
     *
     * @return Collection<int, object{base: string, IdUsuario: int, nombre: string}>
     */
    public static function empresas(string $chat): Collection
    {
        $vinculos = DB::connection('central')->table('telegram_vinculos')->where('chat_id', $chat)->where('base', '!=', TelegramCitas::PANEL)
            ->orderByDesc('vinculado_at')->get(['base', 'IdUsuario'])->unique('base')->values();
        $nombres = Schema::connection('central')->hasTable('clientes')
            ? DB::connection('central')->table('clientes')->whereIn('base_datos', $vinculos->pluck('base'))->get(['base_datos', 'nombre_comercial', 'razon_social'])->keyBy('base_datos')
            : collect();

        return $vinculos->map(fn ($v) => (object) ['base' => $v->base, 'IdUsuario' => (int) $v->IdUsuario,
            'nombre' => trim((string) ($nombres[$v->base]->nombre_comercial ?? '') ?: ($nombres[$v->base]->razon_social ?? '') ?: $v->base)]);
    }

    /** La empresa con la que conversa el chat (la que eligió con /usar, o la más reciente) */
    private static function actual(string $chat, Collection $empresas): ?object
    {
        $elegida = Cache::get('tg-empresa:'.$chat);

        return $empresas->firstWhere('base', $elegida) ?? $empresas->first();
    }

    /**
     * Lo que escribió alguien al bot (que no es /start CODIGO). Devuelve false si no es un Telegram vinculado a una
     * empresa (entonces el bot responde lo de siempre: que lo vincule).
     */
    public static function recibir(string $chat, string $texto): bool
    {
        if (! self::disponible()) {
            return false;
        }
        $empresas = self::empresas($chat);
        if ($empresas->isEmpty()) {
            return false;
        }
        $texto = trim($texto);
        if ($texto === '' || preg_match('#^/(start|ayuda|help)\b#i', $texto)) {
            TelegramCitas::enviar($chat, "👋 Pregúntame lo que quieras de tu negocio, por ejemplo:\n• ¿Cuánto vendí hoy?\n• ¿Qué se está acabando?\n• ¿Quién me debe más?\n• ¿Qué fue lo más vendido esta semana?"
                .($empresas->count() > 1 ? "\n\nTienes varias empresas: /empresas para verlas y /usar 2 para elegir." : ''));

            return true;
        }
        if (preg_match('#^/empresas\b#i', $texto)) {
            $actual = self::actual($chat, $empresas);
            TelegramCitas::enviar($chat, "🏢 Tus empresas:\n".$empresas->values()->map(fn ($e, $i) => ($i + 1).'. '.$e->nombre.($e->base === $actual->base ? ' ✓ (la de ahora)' : ''))->implode("\n")
                ."\n\nPara cambiar escribe /usar y el número, por ejemplo /usar 2");

            return true;
        }
        if (preg_match('#^/usar\s+(\d+)#i', $texto, $m)) {
            $e = $empresas->values()->get((int) $m[1] - 1);
            if (! $e) {
                TelegramCitas::enviar($chat, 'No tienes la empresa '.$m[1].'. Escribe /empresas para ver la lista.');

                return true;
            }
            Cache::forever('tg-empresa:'.$chat, $e->base);
            TelegramCitas::enviar($chat, '✓ Ahora te respondo sobre '.$e->nombre.'.');

            return true;
        }
        DB::connection('central')->table('telegram_preguntas')->insert(['chat_id' => $chat, 'texto' => mb_substr($texto, 0, 1000), 'created_at' => now()]);
        TelegramCitas::enviar($chat, '🔎 Lo reviso y te respondo en un momento…');

        return true;
    }

    /** Contesta las preguntas pendientes. Devuelve cuántas respondió. */
    public static function responder(int $maximo = 20): int
    {
        if (! self::disponible()) {
            return 0;
        }
        $respondidas = 0;
        $pendientes = DB::connection('central')->table('telegram_preguntas')->whereNull('respondida_at')
            ->where('created_at', '>=', now()->subHour())->orderBy('id')->limit($maximo)->get();
        foreach ($pendientes as $p) {
            DB::connection('central')->table('telegram_preguntas')->where('id', $p->id)->update(['respondida_at' => now()]);
            $empresas = self::empresas($p->chat_id);
            $e = self::actual($p->chat_id, $empresas);
            if (! $e) {
                continue;
            }
            try {
                $respuesta = self::preguntar($e, $p->chat_id, $p->texto);
            } catch (\Throwable $ex) {
                report($ex);
                $respuesta = 'No pude responder ahora. Intenta de nuevo en un momento.';
            }
            if ($empresas->count() > 1) {
                $respuesta = '🏢 '.$e->nombre."\n\n".$respuesta;
            }
            foreach (self::partes($respuesta) as $parte) {
                TelegramCitas::enviar($p->chat_id, $parte);
            }
            $respondidas++;
        }

        return $respondidas;
    }

    /** La pregunta al Asistente IA de la empresa, como su administrador */
    private static function preguntar(object $e, string $chat, string $pregunta): string
    {
        $hacer = function () use ($e, $chat, $pregunta) {
            $user = User::find($e->IdUsuario);
            if (! $user || ! $user->esAdmin()) {
                return 'Solo el administrador de la empresa puede preguntarme por el negocio.';
            }
            Auth::setUser($user);
            $clave = 'tg-historial:'.$chat.':'.$e->base;
            $historial = Cache::get($clave, []);
            try {
                $r = Asistente::preguntar($user, $historial, $pregunta);
            } catch (\RuntimeException $ex) {
                return $ex->getMessage();
            }
            $texto = self::sinFormato($r['respuesta']).(! empty($r['graficos']) ? "\n\n📊 El gráfico lo ves en el sistema, en Asistente IA." : '');
            Cache::put($clave, array_slice([...$historial, ['role' => 'user', 'content' => $pregunta], ['role' => 'assistant', 'content' => $r['respuesta']]],
                -self::HISTORIAL), now()->addHours(2));

            return $texto;
        };

        return $e->base === Tenancy::baseActual() ? $hacer() : Tenancy::en($e->base, $hacer);
    }

    /** Telegram muestra texto plano: sin los ** ni # del formato del sistema */
    public static function sinFormato(string $texto): string
    {
        $texto = preg_replace('/^#{1,6}\s*/m', '', $texto);
        $texto = preg_replace('/\*\*(.+?)\*\*/s', '$1', $texto);
        $texto = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '$1', $texto);
        $texto = preg_replace('/^\s*[-*]\s+/m', '• ', $texto);

        return trim(preg_replace("/\n{3,}/", "\n\n", $texto));
    }

    /** Telegram acepta hasta 4096 caracteres por mensaje: los largos se parten por párrafos */
    private static function partes(string $texto): array
    {
        $partes = [];
        $actual = '';
        foreach (preg_split("/\n\n/", $texto) as $parrafo) {
            if (mb_strlen($actual."\n\n".$parrafo) > 3800 && $actual !== '') {
                $partes[] = $actual;
                $actual = '';
            }
            $actual = $actual === '' ? $parrafo : $actual."\n\n".$parrafo;
        }

        return array_map(fn ($p) => mb_substr($p, 0, 4000), array_merge($partes, [$actual]));
    }
}
