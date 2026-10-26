<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Certificados digitales para facturación electrónica que se venden en tushpa.app (se editan en el panel) */
class Certificados
{
    public static function db(): Builder
    {
        return DB::connection('central')->table('web_certificados');
    }

    /** Los que se muestran en la web, en su orden (vacío si aún no existe la tabla) */
    public static function activos(): Collection
    {
        try {
            return Schema::connection('central')->hasTable('web_certificados')
                ? self::db()->where('activo', true)->orderBy('orden')->orderBy('anios')->get()
                : collect();
        } catch (\Throwable) {
            return collect();
        }
    }

    /** Cuánto sale cada año (para mostrar el ahorro de los de varios años) */
    public static function porAnio(object $c): float
    {
        return round((float) $c->precio / max(1, (int) $c->anios), 2);
    }

    /** WhatsApp de ventas con el pedido escrito */
    public static function whatsapp(object $c): string
    {
        return 'https://wa.me/'.config('soporte.whatsapp').'?text='.rawurlencode(
            'Hola, quiero el certificado digital para facturación electrónica de '.$c->nombre.' (S/ '.number_format((float) $c->precio, 2).').');
    }
}
