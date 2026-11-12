<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encuesta de satisfacción: el cliente califica la comida, la atención y la rapidez (de 1 a 5) y puede dejar un comentario.
 * Se responde con un QR, de tres formas:
 *  - TICKET: el QR impreso en el ticket (se puede apagar para no gastar papel: encuesta_qr_ticket);
 *  - MESA: el mozo muestra el QR del pedido en su celular antes de cobrar;
 *  - CARTA: el cliente que pide por la carta QR de la mesa la abre desde "Mi consumo".
 * Una sola encuesta por pedido (o por comprobante si no vino de una mesa), hasta 7 días después. Los enlaces van firmados:
 * nadie puede calificar otra venta cambiando el número.
 */
class Encuestas
{
    public const DIAS_PARA_RESPONDER = 7;

    public const PREGUNTAS = ['comida' => '¿Qué tal la comida?', 'atencion' => '¿Qué tal la atención?', 'rapidez' => '¿Qué tal la rapidez?'];

    /** Nota promedio desde la que se avisa al dueño (cliente descontento) */
    public const NOTA_MALA = 2.5;

    public static function activa(int $sucursal): bool
    {
        return (bool) DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('encuesta_activa');
    }

    /** ¿El QR va impreso en el ticket? (con la encuesta activa; se apaga para ahorrar papel) */
    public static function qrEnTicket(int $sucursal): bool
    {
        $n = DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->first(['encuesta_activa', 'encuesta_qr_ticket']);

        return $n && $n->encuesta_activa && ($n->encuesta_qr_ticket ?? true);
    }

    public static function firma(string $clave): string
    {
        return substr(hash_hmac('sha256', 'encuesta:'.$clave, (string) config('app.key')), 0, 10);
    }

    /** Código del enlace: "1234-a1b2c3d4e5" (comprobante) o "P567-a1b2c3d4e5" (pedido de una mesa) */
    public static function codigo(int $cabId): string
    {
        return $cabId.'-'.self::firma((string) $cabId);
    }

    public static function codigoPedido(int $pedId): string
    {
        return 'P'.$pedId.'-'.self::firma('P'.$pedId);
    }

    public static function url(int $cabId): string
    {
        return url('/encuesta/'.self::codigo($cabId));
    }

    public static function urlPedido(int $pedId): string
    {
        return url('/encuesta/'.self::codigoPedido($pedId));
    }

    /** ¿Este comprobante lleva el QR de la encuesta en su ticket? (no, si su mesa ya la respondió antes de cobrar) */
    public static function paraTicket(object $cab): ?string
    {
        if ($cab->ccabaj || ! in_array($cab->tdocod, ['01', '03', '13'], true) || ! self::qrEnTicket((int) $cab->id_empresa_negocio)) {
            return null;
        }
        if (! empty($cab->ped_id) && DB::table('encuestas')->where('ped_id', $cab->ped_id)->exists()) {
            return null;
        }

        return self::url((int) $cab->IdCpe_cabecera);
    }

    /** El QR de un pedido abierto (lo muestra el mozo o la carta QR), si la encuesta está activa y no se respondió */
    public static function paraPedido(int $pedId): ?string
    {
        $p = DB::table('pedidos')->where('ped_id', $pedId)->first(['ped_id', 'id_empresa_negocio']);
        if (! $p || ! self::activa((int) $p->id_empresa_negocio) || DB::table('encuestas')->where('ped_id', $pedId)->exists()) {
            return null;
        }

        return self::urlPedido($pedId);
    }

    /**
     * Qué se califica con un código, o el motivo por el que ya no se puede responder.
     *
     * @return array{0: ?object, 1: ?string} [{sucursal, cab, ped_id, origen}, motivo]
     */
    public static function objetivo(string $codigo): array
    {
        [$clave, $firma] = array_pad(explode('-', $codigo, 2), 2, '');
        if (! preg_match('/^P?\d+$/', $clave) || ! hash_equals(self::firma($clave), $firma)) {
            return [null, 'Este enlace no es válido.'];
        }
        if ($clave[0] === 'P') {
            $p = DB::table('pedidos')->where('ped_id', (int) substr($clave, 1))->first(['ped_id', 'id_empresa_negocio', 'fecha_hora', 'ped_est']);
            if (! $p || $p->ped_est === 'Anulado' || ! self::activa((int) $p->id_empresa_negocio)) {
                return [null, 'Esta encuesta ya no está disponible.'];
            }
            $cab = DB::table('cpe_cabecera')->where('ped_id', $p->ped_id)->whereNull('ccabaj')->orderBy('IdCpe_cabecera')->first();
            $o = (object) ['sucursal' => (int) $p->id_empresa_negocio, 'cab' => $cab, 'ped_id' => (int) $p->ped_id, 'origen' => 'MESA', 'fecha' => $p->fecha_hora];
        } else {
            $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', (int) $clave)->first();
            if (! $cab || $cab->ccabaj || ! self::activa((int) $cab->id_empresa_negocio)) {
                return [null, 'Esta encuesta ya no está disponible.'];
            }
            $o = (object) ['sucursal' => (int) $cab->id_empresa_negocio, 'cab' => $cab, 'ped_id' => $cab->ped_id ? (int) $cab->ped_id : null,
                'origen' => 'TICKET', 'fecha' => $cab->fecha_hora];
        }
        $ya = DB::table('encuestas')->where(fn ($q) => $q->when($o->cab, fn ($w) => $w->orWhere('IdCpe_cabecera', $o->cab->IdCpe_cabecera))
            ->when($o->ped_id, fn ($w) => $w->orWhere('ped_id', $o->ped_id)))->exists();
        if ($ya) {
            return [$o, 'Ya respondiste esta encuesta. ¡Gracias!'];
        }
        if ($o->fecha && now()->diffInDays($o->fecha, true) > self::DIAS_PARA_RESPONDER) {
            return [$o, 'El plazo para responder esta encuesta ya pasó.'];
        }

        return [$o, null];
    }

    /**
     * Guarda la respuesta; si la nota es mala, avisa al celular del dueño.
     *
     * @param  array{comida: int, atencion: int, rapidez: int, comentario?: ?string, telefono?: ?string}  $d
     */
    public static function guardar(object $o, array $d, string $origen): void
    {
        $fila = ['id_empresa_negocio' => $o->sucursal, 'IdCpe_cabecera' => $o->cab?->IdCpe_cabecera, 'ped_id' => $o->ped_id, 'origen' => $origen,
            'comida' => $d['comida'], 'atencion' => $d['atencion'], 'rapidez' => $d['rapidez'],
            'comentario' => trim((string) ($d['comentario'] ?? '')) ?: null,
            'telefono' => preg_replace('/\D/', '', (string) ($d['telefono'] ?? '')) ?: null, 'created_at' => now()];
        if (! DB::table('encuestas')->insertOrIgnore($fila)) {
            return;
        }
        $nota = self::promedio((object) $d);
        if ($nota <= self::NOTA_MALA) {
            $mesa = $o->ped_id ? DB::table('pedidos as p')->leftJoin('mesas as m', 'm.mes_id', '=', 'p.mes_id')->where('p.ped_id', $o->ped_id)->value('m.mes_nom') : null;
            AvisosCelular::alDueno($o->sucursal, '😟 Un cliente calificó '.number_format($nota, 1).' de 5',
                trim(($mesa ? 'Mesa '.$mesa.'. ' : '').'Comida '.$d['comida'].', atención '.$d['atencion'].', rapidez '.$d['rapidez'].'.'
                    .($fila['comentario'] ? ' "'.$fila['comentario'].'"' : '').($fila['telefono'] ? ' Cel. '.$fila['telefono'] : '')), route('encuestas.index'));
            self::alTelegram($o->sucursal, $nota, $fila, $mesa);
        }
    }

    /**
     * Calificación mala al Telegram (el mismo bot de los pedidos) de los administradores de la empresa que lo activaron en
     * Encuestas. Si Telegram falla, la encuesta igual queda guardada.
     *
     * @param  array<string, mixed>  $fila
     */
    public static function alTelegram(int $sucursal, float $nota, array $fila, ?string $mesa): int
    {
        if (! TelegramCitas::configurado() || ! Schema::hasColumn('users', 'telegram_encuestas')) {
            return 0;
        }
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->first(['IdEmpresa', 'nombre_comercial']);
        $chats = DB::table('users as u')->join('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')
            ->where('r.role_id', 2)->where('u.IdEmpresa', $negocio->IdEmpresa ?? '')->where('u.estusu', 1)->where('u.telegram_encuestas', true)
            ->whereNotNull('u.telegram_chat_id')->distinct()->pluck('u.telegram_chat_id');
        $cara = fn ($n) => ['', '😡', '🙁', '😐', '🙂', '😍'][(int) $n] ?? '';
        $texto = '😟 Calificación baja: '.number_format($nota, 1)." de 5\n"
            .'🏪 '.($negocio->nombre_comercial ?? '').($mesa ? ' · Mesa '.$mesa : '')."\n"
            .'🍽 Comida '.$fila['comida'].' '.$cara($fila['comida']).' · 🙋 Atención '.$fila['atencion'].' '.$cara($fila['atencion'])
            .' · ⏱ Rapidez '.$fila['rapidez'].' '.$cara($fila['rapidez'])
            .($fila['comentario'] ? "\n💬 \"".$fila['comentario'].'"' : '')
            .($fila['telefono'] ? "\n📞 ".$fila['telefono'].' (wa.me/'.(strlen($fila['telefono']) === 9 ? '51' : '').$fila['telefono'].')' : '')
            ."\n\n".route('encuestas.index');
        $enviados = 0;
        foreach ($chats as $chat) {
            [$ok] = TelegramCitas::enviar((string) $chat, $texto);
            $enviados += $ok ? 1 : 0;
        }

        return $enviados;
    }

    /** Promedio general de una encuesta (las tres preguntas) */
    public static function promedio(object $e): float
    {
        return round(((int) $e->comida + (int) $e->atencion + (int) $e->rapidez) / 3, 1);
    }
}
