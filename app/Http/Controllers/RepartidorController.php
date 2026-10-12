<?php

namespace App\Http\Controllers;

use App\Support\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pantalla del motorizado (sin usuario: entra con su enlace personal). Ve sus pedidos, marca
 * "En el local" / "En camino" / "Entregado" y, mientras tiene pedidos, manda su ubicación cada ~10 s.
 */
class RepartidorController extends Controller
{
    private function motorizado(string $token): object
    {
        $m = DB::table('motorizados')->where('token', $token)->where('activo', 1)->first();
        abort_unless($m, 404, 'Este enlace ya no es válido. Pide uno nuevo en el local.');

        return $m;
    }

    /** Usuario con rol Motorizado: entra a su pantalla de repartos (con su enlace personal) */
    public function mio(): RedirectResponse
    {
        $usuario = Auth::user();
        abort_unless($usuario->tieneRol([12]), 403, 'Esta pantalla es para los usuarios con rol Motorizado.');
        $motId = Delivery::motorizadoDeUsuario((int) $usuario->IdUsuario);
        abort_unless($motId && DB::table('motorizados')->where('mot_id', $motId)->value('activo'), 403, 'Tu ficha de motorizado está inactiva. Consulta en el local.');

        return redirect()->route('repartidor.index', Delivery::tokenMotorizado($motId));
    }

    public function index(string $token): View
    {
        $m = $this->motorizado($token);
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $m->id_empresa_negocio)->first(['nombre_comercial', 'local_lat', 'local_lng']);

        return view('empresas.delivery.repartidor', ['motorizado' => $m, 'negocio' => $negocio, 'token' => $token]);
    }

    /** Pedidos asignados que faltan entregar (y los entregados en las últimas 2 horas) */
    public function pedidos(string $token): JsonResponse
    {
        $m = $this->motorizado($token);
        $pedidos = DB::table('pedidos')->where('mot_id', $m->mot_id)->where('ped_tip', 'Delivery')->whereNotIn('ped_est', ['Anulado', 'Unido'])
            ->where('fecha_hora', '>=', now()->subDays(2))
            ->where(fn ($q) => $q->whereNull('entrega_estado')->orWhere('entrega_estado', '!=', 'ENTREGADO')->orWhere('entrega_fin', '>=', now()->subHours(2)))
            ->orderByRaw("entrega_estado = 'ENTREGADO'")->orderBy('fecha_hora')->get();
        foreach ($pedidos->whereNull('entrega_estado') as $p) {
            Delivery::iniciar($p->ped_id);
            $p->entrega_estado = 'PREPARANDO';
        }

        return response()->json(['pedidos' => $pedidos->map(fn ($p) => [
            'id' => $p->ped_id, 'cliente' => $p->ped_cli_nom !== 'DELIVERY' ? $p->ped_cli_nom : 'Cliente', 'telefono' => $p->ped_tel,
            'direccion' => $p->ped_dir, 'referencia' => $p->entrega_ref,
            'lat' => $p->entrega_lat !== null ? (float) $p->entrega_lat : null, 'lng' => $p->entrega_lng !== null ? (float) $p->entrega_lng : null,
            'total' => (float) $p->ped_tot, 'cobrado' => $p->ped_est === 'Cerrado' || $p->entrega_pago === 'PAGADO', 'estado' => $p->entrega_estado,
            'pago' => Delivery::textoPago($p), 'nota' => $p->entrega_origen === 'CARTA' ? $p->ped_obs : null,
        ])->values()]);
    }

    public function cambiarEstado(Request $request, string $token): JsonResponse
    {
        $m = $this->motorizado($token);
        $d = $request->validate(['ped_id' => 'required|integer', 'estado' => 'required|in:'.implode(',', Delivery::DEL_MOTORIZADO)]);
        abort_unless(DB::table('pedidos')->where('ped_id', $d['ped_id'])->where('mot_id', $m->mot_id)->where('ped_tip', 'Delivery')->exists(), 404);
        Delivery::cambiarEstado((int) $d['ped_id'], $d['estado']);

        return response()->json(['ok' => true]);
    }

    /** Ubicación del celular. Solo se guarda si tiene algún pedido por entregar (no se rastrea fuera del reparto) */
    public function posicion(Request $request, string $token): JsonResponse
    {
        $m = $this->motorizado($token);
        $d = $request->validate(['lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180', 'precision' => 'nullable|numeric|min:0']);
        $repartiendo = DB::table('pedidos')->where('mot_id', $m->mot_id)->where('ped_tip', 'Delivery')
            ->whereIn('entrega_estado', ['LISTO', 'EN_LOCAL', 'EN_CAMINO', 'PREPARANDO'])->where('fecha_hora', '>=', now()->subDays(2))->exists();
        $guardada = $repartiendo && Delivery::registrarPosicion($m->mot_id, (float) $d['lat'], (float) $d['lng'], isset($d['precision']) ? (int) $d['precision'] : null);

        return response()->json(['ok' => true, 'guardada' => $guardada, 'repartiendo' => $repartiendo]);
    }
}
