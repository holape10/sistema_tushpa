<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mantenimiento › Avisos por Telegram: la central de lo que le llega a cada administrador (gratis, el bot de siempre).
 * Engancha los dos embudos que ya existen, sin tocar cada pantalla:
 *  - Alertas al momento: lo que AvisosCelular::alDueno avisa (anulaciones, descuentos y cortesías, faltantes de caja,
 *    platos quitados, precios que bajaron mucho).
 *  - Cambios en el sistema: lo que queda en la Bitácora (cambios de precios; y lo que la bitácora marca como importante).
 *  - El resumen de ventas a la hora que eligió cada uno (telegram:resumenes, cada 15 minutos).
 * Los pedidos de almacén y las calificaciones malas siguen saliendo de su pantalla; aquí solo se prenden o apagan.
 * Los mensajes salen al terminar la petición (como los avisos al celular), para no hacer esperar a nadie.
 */
class AvisosTelegram
{
    /** columna => [nombre, explicación] (el orden en que se muestran) */
    public const TIPOS = [
        'telegram_resumen' => ['📊 Resumen de ventas del día', 'Lo vendido y en qué medios, lo más vendido, lo que se acaba, lo que te deben y los separados. A la hora que elijas.'],
        'telegram_alertas' => ['🚨 Alertas al momento', 'Si anulan una venta, dan descuentos o cortesías, falta plata en caja o quitan un plato de cocina.'],
        'telegram_precios' => ['🏷️ Cambios de precios', 'Cada vez que alguien cambia un precio (a mano, por Excel o por el costo nuevo de una compra), con el antes y el después.'],
        'telegram_cambios' => ['📝 Movimientos importantes', 'Lo que la bitácora marca como importante: ventas editadas, compras corregidas, usuarios y permisos, ventas cobradas más barato…'],
        'telegram_pedidos' => ['🛒 Pedidos de almacén', 'Cada pedido nuevo o cambiado de cocina o del almacén (Requerimientos).'],
        'telegram_encuestas' => ['😟 Clientes descontentos', 'Cuando un cliente califica mal la atención en la encuesta.'],
    ];

    /** Horas para elegir el resumen */
    public const HORAS = ['07:00', '08:00', '12:00', '13:00', '14:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00', '23:30'];

    public const HORA_DEFECTO = '23:00';

    /** @var list<array{chats: list<string>, texto: string}> */
    private static array $pendientes = [];

    /** @var array<string, bool> por base: se revisa una vez por petición (la bitácora anota muchas veces) */
    private static array $disponible = [];

    public static function disponible(): bool
    {
        if (! TelegramCitas::configurado()) {
            return false;
        }
        $base = (string) DB::connection()->getDatabaseName();

        return self::$disponible[$base] ??= Schema::hasTable('users') && Schema::hasColumn('users', 'telegram_alertas');
    }

    /** Los tipos que existen en esta base (algunas columnas vienen de migraciones de otros módulos) */
    public static function tipos(): array
    {
        return array_filter(self::TIPOS, fn ($t, $col) => Schema::hasColumn('users', $col), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Administradores activos de la empresa con su Telegram y lo que reciben.
     *
     * @return Collection<int, object>
     */
    public static function administradores(string $idEmpresa): Collection
    {
        $columnas = array_merge(['u.IdUsuario', 'u.apeusu', 'u.name', 'u.telegram_chat_id'], array_map(fn ($c) => 'u.'.$c, array_keys(self::tipos())),
            Schema::hasColumn('users', 'telegram_resumen_hora') ? ['u.telegram_resumen_hora'] : []);

        return DB::table('users as u')->join('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')
            ->where('r.role_id', 2)->where('u.IdEmpresa', $idEmpresa)->where('u.estusu', 1)
            ->orderBy('u.IdUsuario')->distinct()->get($columnas);
    }

    /** Chats de los administradores de la empresa de esa sucursal que reciben ese tipo de aviso */
    private static function chats(int $sucursal, string $tipo): array
    {
        if (! self::disponible() || ! Schema::hasColumn('users', $tipo)) {
            return [];
        }
        $empresa = DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('IdEmpresa');

        return $empresa ? self::administradores((string) $empresa)->filter(fn ($a) => $a->telegram_chat_id && $a->$tipo)
            ->pluck('telegram_chat_id')->map(fn ($c) => (string) $c)->unique()->values()->all() : [];
    }

    private static function encolar(array $chats, string $texto): void
    {
        if (! $chats) {
            return;
        }
        if (! self::$pendientes) {
            app()->terminating(fn () => self::enviar());
        }
        self::$pendientes[] = ['chats' => $chats, 'texto' => $texto];
    }

    /** Manda lo acumulado (al terminar la petición o la tarea) */
    public static function enviar(): void
    {
        $pendientes = self::$pendientes;
        self::$pendientes = [];
        foreach ($pendientes as $p) {
            foreach ($p['chats'] as $chat) {
                try {
                    TelegramCitas::enviar($chat, $p['texto']);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
    }

    private static function sucursalNombre(int $sucursal): string
    {
        return (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('nombre_comercial');
    }

    /** Embudo 1: una alerta al dueño (AvisosCelular::alDueno) */
    public static function alerta(int $sucursal, string $titulo, string $cuerpo): void
    {
        try {
            self::encolar(self::chats($sucursal, 'telegram_alertas'), $titulo."\n".$cuerpo."\n\n🏪 ".self::sucursalNombre($sucursal).' · '.now()->format('d/m H:i'));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Embudo 2: lo que se anota en la bitácora. Cambios de precios a quien los pidió; lo marcado como importante, a
     * quien pidió los movimientos importantes (sin repetir a quien recibe los dos).
     *
     * @param  array<string, array{etiqueta: string, antes: mixed, despues: mixed}>|null  $cambios
     */
    public static function deBitacora(string $accion, string $descripcion, ?array $cambios, bool $alerta, ?int $sucursal, ?string $usuario): void
    {
        if (! $sucursal || ! self::disponible()) {
            return;
        }
        try {
            $precio = collect($cambios ?? [])->filter(fn ($c, $campo) => in_array($campo, ['propun', 'precio2', 'precio3'], true)
                || preg_match('/precio/i', (string) ($c['etiqueta'] ?? '')));
            $esPrecio = $precio->isNotEmpty() || in_array($accion, ['producto.precio', 'producto.importar'], true);
            $chats = [];
            if ($esPrecio) {
                $chats = self::chats($sucursal, 'telegram_precios');
            }
            if ($alerta) {
                $chats = array_values(array_unique([...$chats, ...self::chats($sucursal, 'telegram_cambios')]));
            }
            if (! $chats) {
                return;
            }
            $lineas = [($esPrecio ? '🏷️ ' : '📝 ').$descripcion];
            foreach ($precio as $c) {
                $lineas[] = '• '.($c['etiqueta'] ?? 'Precio').': '.self::valor($c['antes'] ?? null).' → '.self::valor($c['despues'] ?? null);
            }
            $lineas[] = '';
            $lineas[] = '👤 '.($usuario ?: 'Sistema').' · 🏪 '.self::sucursalNombre($sucursal).' · '.now()->format('d/m H:i');
            self::encolar($chats, implode("\n", $lineas));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function valor(mixed $v): string
    {
        return is_numeric($v) ? 'S/ '.number_format((float) $v, 2) : (trim((string) $v) !== '' ? (string) $v : '—');
    }

    /**
     * Embudo 3: el resumen de ventas a la hora que eligió cada administrador (una vez al día). Devuelve a cuántos les llegó.
     */
    public static function resumenesProgramados(?Carbon $ahora = null): int
    {
        $ahora ??= now();
        if (! self::disponible() || ! Schema::hasColumn('users', 'telegram_resumen') || ! Schema::hasColumn('users', 'telegram_resumen_hora')) {
            return 0;
        }
        TelegramCitas::sincronizar();
        $hoy = $ahora->toDateString();
        $hora = $ahora->format('H:i');
        $enviados = 0;
        $usuarios = DB::table('users as u')->join('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')->where('r.role_id', 2)->where('u.estusu', 1)
            ->where('u.telegram_resumen', true)->whereNotNull('u.telegram_chat_id')
            ->where(fn ($q) => $q->whereNull('u.telegram_resumen_enviado')->orWhere('u.telegram_resumen_enviado', '<', $hoy))
            ->distinct()->get(['u.IdUsuario', 'u.IdEmpresa', 'u.telegram_chat_id', 'u.telegram_resumen_hora']);
        foreach ($usuarios as $u) {
            if (($u->telegram_resumen_hora ?: self::HORA_DEFECTO) > $hora) {
                continue;
            }
            DB::table('users')->where('IdUsuario', $u->IdUsuario)->update(['telegram_resumen_enviado' => $hoy]);
            // Por la mañana, el día anterior completo; desde el mediodía, el de hoy
            $fecha = $ahora->hour < 12 ? $ahora->copy()->subDay() : $ahora;
            // Un resumen por cada sucursal de su empresa
            foreach (DB::table('empresa_negocios')->where('IdEmpresa', $u->IdEmpresa)->orderBy('id_empresa_negocio')->pluck('id_empresa_negocio') as $suc) {
                if (TelegramCitas::enviar((string) $u->telegram_chat_id, ResumenTelegram::mensaje((int) $suc, $fecha))[0]) {
                    $enviados++;
                }
            }
        }

        return $enviados;
    }
}
