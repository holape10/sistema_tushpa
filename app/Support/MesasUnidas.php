<?php

namespace App\Support;

use App\Models\Mesa;
use App\Models\Pedido;
use Illuminate\Support\Collection;

/**
 * Una mesa está en uso si tiene su propio pedido abierto o si está unida al pedido abierto de otra mesa
 * (grupo grande que ocupa varias mesas con una sola cuenta). Todo lo que pregunta "¿esta mesa está libre?" pasa por aquí.
 */
class MesasUnidas
{
    /**
     * Mesas en uso de la sucursal.
     *
     * @return array<int, int> mes_id => ped_id del pedido abierto (el propio o el del grupo)
     */
    public static function ocupadas(int $sucursal): array
    {
        $propias = Pedido::where('id_empresa_negocio', $sucursal)->where('ped_est', 'Aperturado')
            ->whereNotNull('mes_id')->pluck('ped_id', 'mes_id')->all();
        $unidas = Mesa::where('mesas.id_empresa_negocio', $sucursal)
            ->join('pedidos', fn ($j) => $j->on('pedidos.ped_id', '=', 'mesas.unida_ped_id')->where('pedidos.ped_est', 'Aperturado'))
            ->pluck('mesas.unida_ped_id', 'mesas.mes_id')->all();

        return $propias + $unidas;
    }

    /** Pedido abierto que ocupa la mesa (propio o del grupo), o null si está libre */
    public static function pedidoDe(int $mesId): ?int
    {
        $propio = Pedido::where('mes_id', $mesId)->where('ped_est', 'Aperturado')->value('ped_id');
        if ($propio) {
            return (int) $propio;
        }
        $unida = Mesa::where('mes_id', $mesId)->value('unida_ped_id');

        return $unida && Pedido::where('ped_id', $unida)->where('ped_est', 'Aperturado')->exists() ? (int) $unida : null;
    }

    /**
     * Mesas extra unidas a un pedido (sin contar su mesa principal).
     *
     * @return Collection<int, Mesa>
     */
    public static function delPedido(int $pedId): Collection
    {
        return Mesa::leftJoin('pisos', 'pisos.pis_id', '=', 'mesas.pis_id')->where('mesas.unida_ped_id', $pedId)
            ->orderBy('pisos.pis_nom')->orderBy('mesas.mes_nom')->get(['mesas.mes_id', 'mesas.mes_nom', 'mesas.pis_id', 'pisos.pis_nom'])
            ->each(fn ($m) => $m->etiqueta = Mesa::etiqueta($m->pis_nom, $m->mes_nom));
    }

    /** El pedido se cerró o anuló: sus mesas extra quedan libres */
    public static function liberar(int $pedId): void
    {
        Mesa::where('unida_ped_id', $pedId)->update(['unida_ped_id' => null, 'mes_est' => 'Libre']);
    }
}
