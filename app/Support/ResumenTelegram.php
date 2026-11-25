<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El resumen del día por Telegram (gratis, el mismo bot de los pedidos de almacén): lo vendido, en qué medios, lo más
 * vendido, lo que se acaba y lo que deben los clientes (ventas al crédito). Le llega a los administradores que lo activan
 * en el Panel del dueño, a la misma hora que el resumen al celular (dueno:resumen).
 */
class ResumenTelegram
{
    /** Cuántos clientes con deuda se nombran en el mensaje */
    private const DEUDORES = 5;

    /**
     * Administradores de la empresa (todas sus sedes) con su Telegram y si reciben el resumen.
     *
     * @return Collection<int, object>
     */
    public static function administradores(int|string $idEmpresa): Collection
    {
        return DB::table('users as u')->join('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')
            ->where('r.role_id', 2)->where('u.IdEmpresa', $idEmpresa)->where('u.estusu', 1)
            ->orderBy('u.IdUsuario')->distinct()->get(['u.IdUsuario', 'u.apeusu', 'u.name', 'u.telegram_chat_id', 'u.telegram_resumen']);
    }

    /** El resumen del día de la sede (el del Panel del dueño, sin los * y _ de WhatsApp) con lo que le deben */
    public static function mensaje(int $sucursal, Carbon $fecha): string
    {
        $texto = str_replace(['*', '_'], '', PanelDueno::mensajeDia($sucursal, $fecha));
        $deudas = self::deudas($sucursal, $fecha);
        if ($deudas === '') {
            return $texto;
        }
        // Las deudas van antes de la firma del final
        $partes = explode("\n\n", $texto);
        $firma = count($partes) > 1 ? array_pop($partes) : null;

        return implode("\n\n", array_merge($partes, [$deudas], $firma ? [$firma] : []));
    }

    /** Lo que deben los clientes: total, vencido, lo cobrado en el día y quiénes deben más ('' si nadie debe) */
    public static function deudas(int $sucursal, Carbon $fecha): string
    {
        if (! Schema::hasTable('cuentas_cobrar')) {
            return '';
        }
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $hoy = $fecha->toDateString();
        $abiertas = Cuentas::consulta('cobrar', $sucursal)->whereIn('cc.estado_cob', ['PENDIENTE', 'PARCIAL'])->where('cc.saldo', '>', 0)->get();
        $cobrado = (float) DB::table('cuentas_cobrar_detalle as p')->join('cuentas_cobrar as c', 'c.cue_cob_id', '=', 'p.cue_cob_id')
            ->where('c.id_empresa_negocio', $sucursal)->where('p.est_cue_cob_det', 'REGISTRADO')->whereDate('p.fec_dep', $hoy)->sum('p.abono');
        if ($abiertas->isEmpty() && $cobrado <= 0) {
            return '';
        }

        $lineas = ['💸 Te deben '.$soles($abiertas->sum('saldo')).' ('.$abiertas->unique('doc_persona')->count().' cliente(s))'];
        $vencido = $abiertas->filter(fn ($c) => $c->fec_ven && $c->fec_ven < $hoy);
        if ($vencido->isNotEmpty()) {
            $lineas[0] .= ' · vencido '.$soles($vencido->sum('saldo'));
        }
        if ($cobrado > 0) {
            $lineas[] = '📥 Hoy te pagaron '.$soles($cobrado);
        }
        $porCliente = $abiertas->groupBy(fn ($c) => $c->doc_persona ?: $c->persona)
            ->map(fn ($g) => (object) ['nombre' => trim((string) $g->first()->persona) ?: 'SIN NOMBRE', 'saldo' => $g->sum('saldo'),
                'atraso' => $g->filter(fn ($c) => $c->fec_ven && $c->fec_ven < $hoy)->map(fn ($c) => Carbon::parse($c->fec_ven)->diffInDays($fecha->copy()->startOfDay()))->max()])
            ->sortByDesc('saldo')->take(self::DEUDORES);
        foreach ($porCliente as $c) {
            $lineas[] = '• '.mb_strimwidth($c->nombre, 0, 40, '…').': '.$soles($c->saldo).($c->atraso ? ' (vencido hace '.(int) $c->atraso.' día(s))' : '');
        }

        return implode("\n", $lineas);
    }

    /**
     * Manda el resumen de la sede a los administradores de la empresa que lo activaron. Devuelve a cuántos les llegó.
     */
    public static function enviar(int $sucursal, Carbon $fecha): int
    {
        if (! TelegramCitas::configurado() || ! Schema::hasColumn('users', 'telegram_resumen')) {
            return 0;
        }
        $empresa = DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('IdEmpresa');
        $chats = self::administradores((string) $empresa)->filter(fn ($a) => $a->telegram_resumen && $a->telegram_chat_id)->pluck('telegram_chat_id')->unique();
        if ($chats->isEmpty()) {
            return 0;
        }
        $texto = self::mensaje($sucursal, $fecha);

        return $chats->filter(fn ($chat) => TelegramCitas::enviar((string) $chat, $texto)[0])->count();
    }
}
