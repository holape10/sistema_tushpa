<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Encuesta de satisfacción: el ticket lleva un QR; el cliente lo escanea y califica la comida, la atención y la rapidez
 * (de 1 a 5) y puede dejar un comentario. Una encuesta por comprobante, hasta 7 días después de la compra.
 * El enlace va firmado: nadie puede calificar otra venta cambiando el número.
 */
class Encuestas
{
    public const DIAS_PARA_RESPONDER = 7;

    public const PREGUNTAS = ['comida' => '¿Qué tal la comida?', 'atencion' => '¿Qué tal la atención?', 'rapidez' => '¿Qué tal la rapidez?'];

    public static function activa(int $sucursal): bool
    {
        return (bool) DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('encuesta_activa');
    }

    public static function firma(int $cabId): string
    {
        return substr(hash_hmac('sha256', 'encuesta:'.$cabId, (string) config('app.key')), 0, 10);
    }

    /** Código del enlace: "1234-a1b2c3d4e5" */
    public static function codigo(int $cabId): string
    {
        return $cabId.'-'.self::firma($cabId);
    }

    public static function url(int $cabId): string
    {
        return url('/encuesta/'.self::codigo($cabId));
    }

    /** ¿Este comprobante lleva el QR de la encuesta en su ticket? */
    public static function paraTicket(object $cab): ?string
    {
        if ($cab->ccabaj || ! in_array($cab->tdocod, ['01', '03', '13'], true) || ! self::activa((int) $cab->id_empresa_negocio)) {
            return null;
        }

        return self::url((int) $cab->IdCpe_cabecera);
    }

    /**
     * La venta de un código válido, o el motivo por el que ya no se puede responder.
     *
     * @return array{0: ?object, 1: ?string} [venta, motivo]
     */
    public static function venta(string $codigo): array
    {
        [$id, $firma] = array_pad(explode('-', $codigo, 2), 2, '');
        if (! ctype_digit($id) || ! hash_equals(self::firma((int) $id), $firma)) {
            return [null, 'Este enlace no es válido.'];
        }
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', (int) $id)->first();
        if (! $cab || $cab->ccabaj || ! self::activa((int) $cab->id_empresa_negocio)) {
            return [null, 'Esta encuesta ya no está disponible.'];
        }
        if (DB::table('encuestas')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->exists()) {
            return [$cab, 'Ya respondiste esta encuesta. ¡Gracias!'];
        }
        if (now()->diffInDays($cab->fecha_hora, true) > self::DIAS_PARA_RESPONDER) {
            return [$cab, 'El plazo para responder esta encuesta ya pasó.'];
        }

        return [$cab, null];
    }

    /** Promedio general de una encuesta (las tres preguntas) */
    public static function promedio(object $e): float
    {
        return round(((int) $e->comida + (int) $e->atencion + (int) $e->rapidez) / 3, 1);
    }
}
