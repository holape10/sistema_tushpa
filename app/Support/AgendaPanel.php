<?php

namespace App\Support;

use App\Models\Central\Superadmin;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agenda del panel TUSHPA: cada usuario ve la suya (personal) y la general de su marca (TUSHPA o la de su revendedor;
 * nunca la de otra marca). Cada evento avisa por Telegram las veces que se eligieron antes: al dueño del evento si es
 * personal, o a todos los de su marca si es general. Cada aviso sale una sola vez por persona.
 */
class AgendaPanel
{
    /** Minutos antes => cómo se ve en pantalla */
    public const AVISOS = [0 => 'A la hora', 15 => '15 min antes', 60 => '1 hora antes', 120 => '2 horas antes',
        1440 => '1 día antes', 2880 => '2 días antes', 10080 => '1 semana antes'];

    public const COLORES = ['indigo' => '#6366f1', 'verde' => '#10b981', 'ambar' => '#f59e0b', 'rojo' => '#ef4444', 'celeste' => '#06b6d4', 'morado' => '#8b5cf6'];

    private static function db(): Builder
    {
        return DB::connection('central')->table('agenda_eventos');
    }

    /** Los eventos que ve este usuario: los suyos y los generales de su marca */
    public static function visibles(Superadmin $yo): Builder
    {
        return self::db()->where(fn ($q) => $q->where(fn ($w) => $w->where('general', false)->where('superadmin_id', $yo->id))
            ->orWhere(fn ($w) => $w->where('general', true)->when($yo->marca_id, fn ($m) => $m->where('marca_id', $yo->marca_id), fn ($m) => $m->whereNull('marca_id'))));
    }

    /** @return Collection<int, object> eventos de un rango con el nombre de quien los creó */
    public static function entre(Superadmin $yo, Carbon $desde, Carbon $hasta): Collection
    {
        $eventos = self::visibles($yo)->whereBetween('inicio', [$desde, $hasta])->orderBy('inicio')->get();
        $nombres = DB::connection('central')->table('superadmins')->whereIn('id', $eventos->pluck('superadmin_id'))->pluck('nombre', 'id');

        return $eventos->map(function ($e) use ($nombres) {
            $e->avisos = array_map('intval', json_decode((string) $e->avisos, true) ?: []);
            $e->autor = $nombres[$e->superadmin_id] ?? '';

            return $e;
        });
    }

    /** ¿Lo puede cambiar? Quien lo creó o, si es general, el dueño del sistema */
    public static function puedeEditar(Superadmin $yo, object $e): bool
    {
        return (int) $e->superadmin_id === (int) $yo->id || ($e->general && $yo->es_dueno && (int) ($e->marca_id ?? 0) === (int) ($yo->marca_id ?? 0));
    }

    /**
     * Guarda un evento nuevo o editado. Devuelve su id.
     *
     * @param  array{titulo: string, detalle?: ?string, fecha: string, hora?: ?string, todo_el_dia?: bool, general?: bool, color?: ?string, avisos?: array<int, mixed>}  $d
     */
    public static function guardar(Superadmin $yo, array $d, ?int $id = null): int
    {
        $todoElDia = ! empty($d['todo_el_dia']);
        // Todo el día: avisa contando desde las 8 a. m. de ese día
        $inicio = Carbon::parse($d['fecha'].' '.($todoElDia ? '08:00' : ($d['hora'] ?? '09:00')));
        $avisos = collect($d['avisos'] ?? [])->map(fn ($m) => (int) $m)->filter(fn ($m) => array_key_exists($m, self::AVISOS))->unique()->sort()->values()->all();
        $datos = [
            'titulo' => mb_substr(trim($d['titulo']), 0, 150), 'detalle' => trim((string) ($d['detalle'] ?? '')) ?: null,
            'inicio' => $inicio, 'todo_el_dia' => $todoElDia, 'general' => ! empty($d['general']), 'marca_id' => $yo->marca_id,
            'color' => array_key_exists($d['color'] ?? '', self::COLORES) ? $d['color'] : 'indigo', 'avisos' => json_encode($avisos), 'updated_at' => now(),
        ];
        if ($id) {
            $e = self::db()->find($id);
            abort_unless($e && self::puedeEditar($yo, $e), 404);
            self::db()->where('id', $id)->update($datos);
            // Si cambió la fecha, los avisos vuelven a salir
            if (Carbon::parse($e->inicio)->ne($inicio)) {
                DB::connection('central')->table('agenda_avisos_enviados')->where('evento_id', $id)->delete();
            }

            return $id;
        }

        return self::db()->insertGetId($datos + ['superadmin_id' => $yo->id, 'created_at' => now()]);
    }

    public static function eliminar(Superadmin $yo, int $id): void
    {
        $e = self::db()->find($id);
        abort_unless($e && self::puedeEditar($yo, $e), 404);
        self::db()->where('id', $id)->delete();
        DB::connection('central')->table('agenda_avisos_enviados')->where('evento_id', $id)->delete();
    }

    /**
     * Manda los avisos que tocan ahora (lo corre el programador de tareas cada 5 minutos). Devuelve cuántos salieron.
     */
    public static function avisar(?Carbon $ahora = null): int
    {
        $ahora ??= now();
        if (! TelegramCitas::configurado()) {
            return 0;
        }
        $enviados = 0;
        // Eventos que aún no empiezan (o empezaron hace muy poco) y que tienen avisos en la próxima semana
        $eventos = self::db()->whereNotNull('avisos')->where('inicio', '>', $ahora->copy()->subMinutes(10))
            ->where('inicio', '<=', $ahora->copy()->addMinutes(max(array_keys(self::AVISOS))))->get();
        foreach ($eventos as $e) {
            $inicio = Carbon::parse($e->inicio);
            $toca = collect(json_decode((string) $e->avisos, true) ?: [])->map(fn ($m) => (int) $m)
                ->filter(fn ($m) => $ahora->gte($inicio->copy()->subMinutes($m)) && $ahora->lt($inicio->copy()->addMinutes($m === 0 ? 10 : 0)));
            if ($toca->isEmpty()) {
                continue;
            }
            // Solo el aviso más cercano que toca (si se juntaron varios, no se manda la ráfaga)
            $minutos = $toca->min();
            $destinos = DB::connection('central')->table('superadmins')->where('activo', true)->whereNotNull('telegram_chat_id')
                ->when(! $e->general, fn ($q) => $q->where('id', $e->superadmin_id))
                ->when($e->general, fn ($q) => $e->marca_id ? $q->where('marca_id', $e->marca_id) : $q->whereNull('marca_id'))
                ->get(['id', 'telegram_chat_id']);
            foreach ($destinos as $s) {
                // Cada aviso (1 día antes, 1 hora antes…) sale una vez; siempre el más cercano de los que ya tocan
                $ya = DB::connection('central')->table('agenda_avisos_enviados')
                    ->where(['evento_id' => $e->id, 'superadmin_id' => $s->id, 'minutos' => $minutos])->exists();
                if ($ya) {
                    continue;
                }
                [$ok] = TelegramCitas::enviar((string) $s->telegram_chat_id, self::texto($e, $inicio, $minutos));
                if ($ok) {
                    DB::connection('central')->table('agenda_avisos_enviados')->insertOrIgnore(['evento_id' => $e->id, 'superadmin_id' => $s->id, 'minutos' => $minutos, 'enviado_at' => now()]);
                    $enviados++;
                }
            }
        }

        return $enviados;
    }

    public static function texto(object $e, Carbon $inicio, int $minutos): string
    {
        $cuando = $e->todo_el_dia ? $inicio->locale('es')->isoFormat('dddd D [de] MMMM') : $inicio->locale('es')->isoFormat('dddd D [de] MMMM [a las] h:mm a');

        return ($minutos === 0 ? '🔔 Ahora: ' : '📅 '.(self::AVISOS[$minutos] ?? 'Pronto').': ').$e->titulo."\n🗓 ".ucfirst($cuando)
            .($e->general ? "\n👥 Agenda general" : '').($e->detalle ? "\n\n".$e->detalle : '');
    }
}
