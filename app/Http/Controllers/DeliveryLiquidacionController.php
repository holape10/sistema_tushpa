<?php

namespace App\Http\Controllers;

use App\Support\LiquidacionDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Liquidación de motorizados: lo que cada uno entregó, el efectivo que debe rendir y el historial de liquidaciones.
 */
class DeliveryLiquidacionController extends Controller
{
    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index(Request $request): View
    {
        $suc = $this->sucursal();
        $motorizados = DB::table('motorizados')->where('id_empresa_negocio', $suc)->orderByDesc('activo')->orderBy('nombre')->get(['mot_id', 'nombre', 'activo']);
        $porLiquidar = DB::table('pedidos')->where('id_empresa_negocio', $suc)->where('ped_tip', 'Delivery')->where('entrega_estado', 'ENTREGADO')
            ->whereNull('liq_id')->whereNotIn('ped_est', ['Anulado', 'Unido'])->where('fecha_hora', '>=', now()->subDays(7)->startOfDay())
            ->groupBy('mot_id')->pluck(DB::raw('count(*)'), 'mot_id');
        $motId = (int) $request->get('mot') ?: (int) ($porLiquidar->keys()->first() ?? 0);
        $motorizado = $motorizados->firstWhere('mot_id', $motId);

        return view('empresas.delivery.liquidacion', [
            'motorizados' => $motorizados, 'porLiquidar' => $porLiquidar, 'motorizado' => $motorizado,
            'pendientes' => $motorizado ? LiquidacionDelivery::pendientes($suc, $motId) : null,
            'historial' => DB::table('delivery_liquidaciones as l')->join('motorizados as m', 'm.mot_id', '=', 'l.mot_id')
                ->leftJoin('users as u', 'u.IdUsuario', '=', 'l.IdUsuario')->where('l.id_empresa_negocio', $suc)
                ->orderByDesc('l.id')->limit(30)->get(['l.*', 'm.nombre', 'u.apeusu']),
        ]);
    }

    public function liquidar(Request $request): RedirectResponse
    {
        $suc = $this->sucursal();
        $d = $request->validate([
            'mot_id' => 'required|integer', 'pedidos' => 'required|array|min:1', 'pedidos.*' => 'integer',
            'recibido' => 'required|numeric|min:0|max:999999', 'observacion' => 'nullable|string|max:255',
        ], ['pedidos.required' => 'Marca al menos un pedido.'], ['recibido' => 'efectivo recibido']);
        abort_unless(DB::table('motorizados')->where('mot_id', $d['mot_id'])->where('id_empresa_negocio', $suc)->exists(), 404);
        try {
            $id = LiquidacionDelivery::liquidar($suc, (int) $d['mot_id'], $d['pedidos'], (float) $d['recibido'], $d['observacion'] ?? null, Auth::id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $l = DB::table('delivery_liquidaciones')->find($id);
        $diferencia = (float) $l->diferencia;

        return redirect()->route('delivery.liquidacion', ['mot' => $d['mot_id']])->with('success', "Liquidación N° {$id} registrada: {$l->pedidos} pedido(s), efectivo S/ ".number_format((float) $l->efectivo, 2)
            .($diferencia == 0 ? ' (cuadrado).' : ($diferencia < 0 ? ', FALTAN S/ '.number_format(-$diferencia, 2).'.' : ', SOBRAN S/ '.number_format($diferencia, 2).'.')));
    }
}
