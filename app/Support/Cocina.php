<?php
namespace App\Support;

use Illuminate\Support\Facades\{Auth, DB};

/**
 * Pantalla de cocina (KDS): cada envío de comanda crea una tarjeta (cocina_tickets) con sus platos (cocina_items).
 * La estación de cada plato (cocina, bar...) es la impresora asignada a su categoría.
 */
class Cocina
{
    /**
     * @param array $nuevos      [['IdProducto','nombre','cantidad','observacion']] lo que hay que preparar
     * @param array $anulaciones [['IdProducto','nombre','cantidad','observacion']] lo que el cliente canceló
     */
    public static function registrar(int $pedId, array $nuevos, array $anulaciones = []): void
    {
        if (!$nuevos && !$anulaciones) {
            return;
        }
        $pedido = DB::table('pedidos as p')->leftJoin('mesas as m', 'm.mes_id', '=', 'p.mes_id')
            ->leftJoin('pisos as pi', 'pi.pis_id', '=', 'p.pis_id')->leftJoin('users as u', 'u.IdUsuario', '=', 'p.mozo')
            ->where('p.ped_id', $pedId)->first(['p.*', 'm.mes_nom', 'pi.pis_nom', 'u.apeusu as mozo_nom']);
        if (!$pedido) {
            return;
        }

        $todos = array_merge($nuevos, $anulaciones);
        $estacion = DB::table('productos as pr')->leftJoin('categorias as c', 'c.cat_id', '=', 'pr.cat_id')
            ->whereIn('pr.IdProducto', array_column($todos, 'IdProducto'))->pluck('c.impresora', 'pr.IdProducto');

        $destino = $pedido->ped_tip === 'Hotel'
            ? trim($pedido->ped_obs . ' - ' . $pedido->ped_cli_nom)   // HAB. 101 - CLIENTE
            : ($pedido->mes_nom
            ? trim(($pedido->pis_nom ? $pedido->pis_nom . ' / ' : '') . $pedido->mes_nom)
            : mb_strtoupper((string) $pedido->ped_tip) . (!in_array($pedido->ped_cli_nom, ['PARA LLEVAR', 'DELIVERY', 'CONSUMO EN SALON', null], true) ? ' - ' . $pedido->ped_cli_nom : ''));

        $crearTicket = fn(string $tipo) => DB::table('cocina_tickets')->insertGetId([
            'ped_id' => $pedId, 'id_empresa_negocio' => $pedido->id_empresa_negocio, 'destino' => mb_substr($destino, 0, 60),
            'ped_tip' => $pedido->ped_tip, 'mozo' => $pedido->mozo_nom ?? Auth::user()?->apeusu, 'tipo' => $tipo, 'creado' => now(),
        ]);

        DB::transaction(function () use ($pedId, $nuevos, $anulaciones, $estacion, $crearTicket) {
            // 1) Lo nuevo: primera tarjeta del pedido = NUEVO; las siguientes = ADICIONAL
            if ($nuevos) {
                $hayPrevias = DB::table('cocina_tickets')->where('ped_id', $pedId)->where('tipo', '!=', 'ANULACION')->exists();
                $ticket = $crearTicket($hayPrevias ? 'ADICIONAL' : 'NUEVO');
                foreach ($nuevos as $n) {
                    DB::table('cocina_items')->insert([
                        'ticket_id' => $ticket, 'IdProducto' => $n['IdProducto'], 'descripcion' => mb_substr($n['nombre'], 0, 150),
                        'cantidad' => $n['cantidad'], 'observacion' => $n['observacion'] ? mb_substr($n['observacion'], 0, 255) : null,
                        'estacion' => $estacion[$n['IdProducto']] ?? null,
                    ]);
                }
            }

            // 2) Lo anulado: si aún no estaba listo se tacha en su tarjeta; si ya estaba listo, aviso aparte en rojo
            $avisos = [];
            foreach ($anulaciones as $a) {
                $falta = (float) $a['cantidad'];
                $pendientes = DB::table('cocina_items as i')->join('cocina_tickets as t', 't.id', '=', 'i.ticket_id')
                    ->where('t.ped_id', $pedId)->where('i.IdProducto', $a['IdProducto'])
                    ->where('i.anulado', 0)->whereNull('i.listo')->orderByDesc('i.id')->lockForUpdate()->get(['i.*']);

                foreach ($pendientes as $p) {
                    if ($falta <= 0) {
                        break;
                    }
                    if ((float) $p->cantidad <= $falta) {
                        DB::table('cocina_items')->where('id', $p->id)->update(['anulado' => 1, 'observacion' => self::motivo($p->observacion, $a)]);
                        $falta -= (float) $p->cantidad;
                    } else {
                        // Anulación parcial: queda lo que sí se prepara y se agrega la parte anulada tachada
                        DB::table('cocina_items')->where('id', $p->id)->update(['cantidad' => (float) $p->cantidad - $falta]);
                        DB::table('cocina_items')->insert([
                            'ticket_id' => $p->ticket_id, 'IdProducto' => $p->IdProducto, 'descripcion' => $p->descripcion,
                            'cantidad' => $falta, 'observacion' => self::motivo(null, $a), 'estacion' => $p->estacion, 'anulado' => 1,
                        ]);
                        $falta = 0;
                    }
                }
                if ($falta > 0) {
                    $avisos[] = $a + ['cantidad' => $falta];
                }
            }

            if ($avisos) {
                $ticket = $crearTicket('ANULACION');
                foreach ($avisos as $a) {
                    DB::table('cocina_items')->insert([
                        'ticket_id' => $ticket, 'IdProducto' => $a['IdProducto'], 'descripcion' => mb_substr($a['nombre'], 0, 150),
                        'cantidad' => $a['cantidad'], 'observacion' => self::motivo(null, $a), 'estacion' => $estacion[$a['IdProducto']] ?? null,
                        'anulado' => 1,
                    ]);
                }
            }
        });
    }

    /** Venta directa (punto de venta): tarjeta sin pedido (ped_id = 0), con el comprobante como referencia */
    public static function registrarDirecta(int $sucursal, string $destino, array $items): void
    {
        $items = array_values(array_filter($items, fn($i) => !empty($i['IdProducto'])));
        if (!$items) {
            return;
        }
        $estacion = DB::table('productos as pr')->leftJoin('categorias as c', 'c.cat_id', '=', 'pr.cat_id')
            ->whereIn('pr.IdProducto', array_column($items, 'IdProducto'))->pluck('c.impresora', 'pr.IdProducto');

        DB::transaction(function () use ($sucursal, $destino, $items, $estacion) {
            $ticket = DB::table('cocina_tickets')->insertGetId([
                'ped_id' => 0, 'id_empresa_negocio' => $sucursal, 'destino' => mb_substr($destino, 0, 60),
                'ped_tip' => 'PV', 'mozo' => Auth::user()?->apeusu, 'tipo' => 'NUEVO', 'creado' => now(),
            ]);
            foreach ($items as $n) {
                DB::table('cocina_items')->insert([
                    'ticket_id' => $ticket, 'IdProducto' => $n['IdProducto'], 'descripcion' => mb_substr($n['nombre'], 0, 150),
                    'cantidad' => $n['cantidad'], 'observacion' => !empty($n['observacion']) ? mb_substr($n['observacion'], 0, 255) : null,
                    'estacion' => $estacion[$n['IdProducto']] ?? null,
                ]);
            }
        });
    }

    private static function motivo(?string $obs, array $anulacion): string
    {
        return mb_substr(trim(($obs ? $obs . ' · ' : '') . 'ANULADO ' . ($anulacion['observacion'] ?? '')), 0, 255);
    }

    /** Marca la tarjeta como lista cuando ya no le queda nada pendiente */
    public static function actualizarTicket(int $ticketId): void
    {
        $pendientes = DB::table('cocina_items')->where('ticket_id', $ticketId)->whereNull('listo')->exists();
        DB::table('cocina_tickets')->where('id', $ticketId)->update(['listo' => $pendientes ? null : now()]);
    }
}
