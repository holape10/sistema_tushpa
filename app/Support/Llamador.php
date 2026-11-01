<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pantalla "llamador" (un televisor a la vista de los clientes): los pedidos para llevar, del kiosko y del punto de
 * venta, en "Preparando" y "¡Listo!", con lo que cocina ya marca en su pantalla. No hay que hacer nada aparte.
 * Un pedido listo sale de la pantalla cuando el mozo lo marca entregado o a los MINUTOS_LISTO minutos.
 */
class Llamador
{
    public const MINUTOS_LISTO = 15;

    /** Solo lo que el cliente espera en el mostrador (las mesas y el delivery no) */
    private const TIPOS = ['Llevar', 'PV'];

    /**
     * @return array{preparando: Collection<int, object{clave: string, numero: string, nombre: string}>, listos: Collection<int, object{clave: string, numero: string, nombre: string, listo: string}>}
     */
    public static function pedidos(int $sucursal): array
    {
        $tickets = DB::table('cocina_tickets as t')->leftJoin('pedidos as p', fn ($j) => $j->on('p.ped_id', '=', 't.ped_id')->where('t.ped_id', '>', 0))
            ->where('t.id_empresa_negocio', $sucursal)->whereIn('t.ped_tip', self::TIPOS)->where('t.tipo', '!=', 'ANULACION')
            ->where('t.creado', '>=', now()->subHours(6))
            // Tarjetas con algo que preparar (si anularon todo, no se llama a nadie)
            ->whereExists(fn ($q) => $q->from('cocina_items as i')->whereColumn('i.ticket_id', 't.id')->where('i.anulado', 0))
            ->orderBy('t.creado')
            ->get(['t.id', 't.ped_id', 't.destino', 't.listo', 't.entregado', 'p.ped_cli_nom', 'p.ped_est']);

        $pedidos = $tickets->groupBy(fn ($t) => $t->ped_id > 0 ? 'p'.$t->ped_id : 't'.$t->id)->map(function (Collection $g, string $clave) {
            $t = $g->first();
            [$numero, $nombre] = self::numeroYNombre($t);

            return (object) ['clave' => $clave, 'numero' => $numero, 'nombre' => $nombre,
                'listo' => $g->every(fn ($x) => $x->listo) ? (string) $g->max('listo') : null,
                'entregado' => $g->every(fn ($x) => $x->entregado), 'anulado' => $t->ped_est === 'Anulado'];
        })->reject(fn ($p) => $p->entregado || $p->anulado);

        $limite = now()->subMinutes(self::MINUTOS_LISTO)->toDateTimeString();

        return [
            'preparando' => $pedidos->whereNull('listo')->values(),
            'listos' => $pedidos->whereNotNull('listo')->filter(fn ($p) => $p->listo >= $limite)->sortByDesc('listo')->values(),
        ];
    }

    /**
     * Lo que se anuncia: el número del kiosko (KIOSKO N° 3 · PEPE), el del comprobante del punto de venta
     * (PUNTO DE VENTA B001-245 → 245) o el del pedido para llevar, con el nombre del cliente si lo dio.
     *
     * @return array{0: string, 1: string}
     */
    private static function numeroYNombre(object $t): array
    {
        $cliente = trim((string) $t->ped_cli_nom);
        if (preg_match('/KIOSKO N° (\d+)(?: · ([^(]+))?/u', $cliente, $m)) {
            return [$m[1], trim($m[2] ?? '')];
        }
        if ((int) $t->ped_id === 0) {
            return [preg_match('/(\d+)\s*$/', (string) $t->destino, $m) ? ltrim($m[1], '0') ?: '0' : (string) $t->id, ''];
        }
        $generico = in_array(mb_strtoupper($cliente), ['', 'PARA LLEVAR', 'CLIENTES VARIOS', 'VENTA AL PORTADOR'], true);

        return [(string) $t->ped_id, $generico ? '' : mb_strimwidth($cliente, 0, 24, '…')];
    }
}
