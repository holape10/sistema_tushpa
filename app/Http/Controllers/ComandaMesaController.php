<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Support\MesasUnidas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Comandas: pasar el pedido a otra mesa, juntar cuentas, sumar mesas libres al grupo y separar mesas del grupo */
class ComandaMesaController extends Controller
{
    /**
     * Mesas para elegir al pasar o juntar. tipo: 'libres' (pasar a otra mesa) o 'juntar' (ocupadas para juntar
     * cuentas + libres para sumar al grupo). La mesa actual y las que ya son de su grupo no aparecen.
     */
    public function mesasDisponibles(Request $request)
    {
        $sucursal = (int) Auth::user()->id_empresa_negocio;
        $enUso = MesasUnidas::ocupadas($sucursal);
        // Pedido abierto de cada mesa principal, con su total y hora de inicio (para que el mozo reconozca la mesa)
        $abiertos = Pedido::where('id_empresa_negocio', $sucursal)->where('ped_est', 'Aperturado')
            ->whereNotNull('mes_id')->get(['ped_id', 'mes_id', 'ped_tot', 'fecha_hora'])->keyBy('mes_id');

        $mesas = Mesa::leftJoin('pisos', 'pisos.pis_id', '=', 'mesas.pis_id')
            ->where('mesas.id_empresa_negocio', $sucursal)
            ->orderBy('pisos.pis_nom')->orderBy('mesas.mes_nom')
            ->get(['mesas.mes_id', 'mesas.mes_nom', 'pisos.pis_nom'])
            ->map(function ($m) use ($enUso, $abiertos) {
                $propio = $abiertos[$m->mes_id] ?? null;

                return ['mes_id' => $m->mes_id, 'nombre' => $m->mes_nom, 'piso' => $m->pis_nom, 'etiqueta' => Mesa::etiqueta($m->pis_nom, $m->mes_nom),
                    'estado' => $propio ? 'ocupada' : (isset($enUso[$m->mes_id]) ? 'en_grupo' : 'libre'),
                    'ped_id' => $propio?->ped_id, 'total' => $propio ? (float) $propio->ped_tot : null,
                    'desde' => $propio?->fecha_hora ? Carbon::parse($propio->fecha_hora)->format('H:i') : null];
            })
            ->filter(fn ($m) => $request->tipo === 'juntar'
                ? $m['estado'] === 'libre' || ($m['estado'] === 'ocupada' && (int) $m['ped_id'] !== (int) $request->ped_id)
                : $m['estado'] === 'libre')
            ->values();

        $actual = Pedido::where('ped_id', $request->ped_id)->where('id_empresa_negocio', $sucursal)->value('ped_tot');

        return response()->json(['success' => true, 'mesas' => $mesas, 'total_actual' => (float) $actual,
            'unidas' => MesasUnidas::delPedido((int) $request->ped_id)->pluck('etiqueta')]);
    }

    public function cambiarMesa(Request $request)
    {
        $request->validate(['ped_id' => 'required|integer', 'mes_id' => 'required|integer']);
        $sucursal = Auth::user()->id_empresa_negocio;

        try {
            DB::transaction(function () use ($request, $sucursal) {
                $pedido = Pedido::where('ped_id', $request->ped_id)->where('id_empresa_negocio', $sucursal)
                    ->where('ped_est', 'Aperturado')->lockForUpdate()->first();
                $destino = Mesa::where('mes_id', $request->mes_id)->where('id_empresa_negocio', $sucursal)->lockForUpdate()->first();
                if (! $pedido || ! $pedido->mes_id) {
                    throw new \RuntimeException('El pedido ya no está abierto o no es de salón.');
                }
                if (! $destino) {
                    throw new \RuntimeException('Mesa no válida.');
                }
                if (MesasUnidas::pedidoDe($destino->mes_id)) {
                    throw new \RuntimeException("La {$destino->mes_nom} ya está ocupada. Usa Juntar mesas.");
                }

                $origen = $pedido->mes_id;
                $pedido->update(['mes_id' => $destino->mes_id, 'pis_id' => $destino->pis_id, 'fecha_hora_modificacion' => now()]);
                Mesa::where('mes_id', $origen)->update(['mes_est' => 'Libre']);
                $destino->update(['mes_est' => 'Ocupado']);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Junta las cuentas de dos mesas ocupadas: el pedido de la otra mesa (origen) pasa al pedido destino.
     * Las dos mesas siguen ocupadas como un solo grupo; la que se desocupe se libera desde la mesa.
     */
    public function unirMesa(Request $request)
    {
        $request->validate(['ped_id' => 'required|integer', 'ped_id_origen' => 'required|integer|different:ped_id']);
        $sucursal = Auth::user()->id_empresa_negocio;

        try {
            DB::transaction(function () use ($request, $sucursal) {
                $pedidos = Pedido::whereIn('ped_id', [$request->ped_id, $request->ped_id_origen])
                    ->where('id_empresa_negocio', $sucursal)->where('ped_est', 'Aperturado')
                    ->orderBy('ped_id')->lockForUpdate()->get()->keyBy('ped_id');
                $destino = $pedidos[$request->ped_id] ?? null;
                $origen = $pedidos[$request->ped_id_origen] ?? null;
                if (! $destino || ! $origen) {
                    throw new \RuntimeException('Alguno de los pedidos ya no está abierto.');
                }
                if (PedidoDetalle::where('ped_id', $origen->ped_id)->where('item_facturado', '>', 0)->exists()) {
                    throw new \RuntimeException('La otra mesa ya tiene cuentas separadas cobradas; termina de cobrarla antes de juntarla.');
                }

                PedidoDetalle::where('ped_id', $origen->ped_id)->update(['ped_id' => $destino->ped_id]);

                $total = (float) PedidoDetalle::where('ped_id', $destino->ped_id)->where('estadoitem', '!=', 'Eliminado')
                    ->sum(DB::raw('ped_det_can * ped_det_pre'));
                $destino->update(['ped_tot' => $total, 'fecha_hora_modificacion' => now()]);

                $origen->update(['ped_est' => 'Unido', 'ped_tot' => 0, 'fecha_hora_modificacion' => now(),
                    'ped_obs' => mb_substr("Unido al pedido {$destino->ped_id}", 0, 255)]);
                // La mesa de origen y las que ya estaban en su grupo pasan al grupo del destino
                Mesa::where('unida_ped_id', $origen->ped_id)->update(['unida_ped_id' => $destino->ped_id]);
                if ($origen->mes_id && (int) $origen->mes_id !== (int) $destino->mes_id) {
                    Mesa::where('mes_id', $origen->mes_id)->update(['unida_ped_id' => $destino->ped_id, 'mes_est' => 'Ocupado']);
                }
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    /** Suma mesas libres al pedido de una mesa (grupo grande): quedan ocupadas con la misma cuenta */
    public function juntarLibres(Request $request)
    {
        $request->validate(['ped_id' => 'required|integer', 'mesas' => 'required|array|min:1|max:50', 'mesas.*' => 'integer']);
        $sucursal = Auth::user()->id_empresa_negocio;

        try {
            DB::transaction(function () use ($request, $sucursal) {
                $pedido = Pedido::where('ped_id', $request->ped_id)->where('id_empresa_negocio', $sucursal)
                    ->where('ped_est', 'Aperturado')->whereNotNull('mes_id')->lockForUpdate()->first();
                if (! $pedido) {
                    throw new \RuntimeException('El pedido ya no está abierto.');
                }
                $ids = array_unique(array_map('intval', $request->mesas));
                $mesas = Mesa::whereIn('mes_id', $ids)->where('id_empresa_negocio', $sucursal)->lockForUpdate()->get();
                if ($mesas->count() !== count($ids)) {
                    throw new \RuntimeException('Alguna mesa no es válida.');
                }
                foreach ($mesas as $mesa) {
                    if (MesasUnidas::pedidoDe($mesa->mes_id)) {
                        throw new \RuntimeException("La {$mesa->mes_nom} acaba de ocuparse. Vuelve a elegir.");
                    }
                }
                Mesa::whereIn('mes_id', $ids)->update(['unida_ped_id' => $pedido->ped_id, 'mes_est' => 'Ocupado']);
                $pedido->update(['fecha_hora_modificacion' => now()]);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    /** Quita una mesa del grupo (ya se desocupó): queda libre y la cuenta sigue en la mesa principal */
    public function separarMesa(Request $request)
    {
        $request->validate(['ped_id' => 'required|integer', 'mes_id' => 'required|integer']);
        $actualizadas = Mesa::where('mes_id', $request->mes_id)->where('unida_ped_id', $request->ped_id)
            ->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->update(['unida_ped_id' => null, 'mes_est' => 'Libre']);

        return response()->json($actualizadas
            ? ['success' => true]
            : ['success' => false, 'message' => 'Esa mesa ya no está en el grupo.']);
    }
}
