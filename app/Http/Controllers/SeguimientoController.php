<?php

namespace App\Http\Controllers;

use App\Support\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Seguimiento del delivery para el cliente (enlace /pedido/{código}, sin usuario): estado del pedido, el motorizado
 * y su ubicación en el mapa mientras va en camino.
 */
class SeguimientoController extends Controller
{
    private function pedido(string $token): object
    {
        $p = DB::table('pedidos')->where('entrega_token', strtoupper($token))->where('ped_tip', 'Delivery')->first();
        abort_unless($p && ! in_array($p->ped_est, ['Anulado', 'Unido'], true), 404, 'No encontramos este pedido.');

        return $p;
    }

    public function index(string $token): View
    {
        $p = $this->pedido($token);
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $p->id_empresa_negocio)->first(['nombre_comercial', 'telefono', 'logo_suc']);
        $logo = collect([$negocio->logo_suc ?? null, DB::table('empresa')->value('LogEmpresa')])->first(fn ($l) => $l && is_file(public_path($l)));

        $detalle = DB::table('pedidos_detalle')->where('ped_id', $p->ped_id)->where('estadoitem', '!=', 'Eliminado')->orderBy('ped_det_id')
            ->get(['descripcion', 'ped_det_can', 'ped_det_pre']);

        return view('empresas.delivery.seguimiento', ['token' => strtoupper($token), 'pedido' => $p, 'negocio' => $negocio, 'logo' => $logo ? asset($logo) : null,
            'detalle' => $detalle, 'pago' => Delivery::textoPago($p)]);
    }

    public function estado(string $token): JsonResponse
    {
        $p = $this->pedido($token);
        $m = $p->mot_id ? DB::table('motorizados')->where('mot_id', $p->mot_id)->first(['nombre', 'telefono', 'lat', 'lng', 'posicion_at']) : null;
        $local = DB::table('empresa_negocios')->where('id_empresa_negocio', $p->id_empresa_negocio)->first(['local_lat', 'local_lng']);
        $enCamino = $p->entrega_estado === 'EN_CAMINO';
        // La moto se ve solo mientras lleva este pedido y con una señal reciente (menos de 3 minutos)
        $moto = $enCamino && $m && $m->lat !== null && $m->posicion_at && Carbon::parse($m->posicion_at)->diffInSeconds(now()) < 180
            ? ['lat' => (float) $m->lat, 'lng' => (float) $m->lng, 'hace' => (int) Carbon::parse($m->posicion_at)->diffInSeconds(now())] : null;

        return response()->json([
            'estado' => $p->entrega_estado ?? 'PREPARANDO', 'texto' => Delivery::ESTADOS[$p->entrega_estado ?? 'PREPARANDO'],
            'horas' => collect(['PREPARANDO' => $p->fecha_hora, 'LISTO' => $p->entrega_listo, 'EN_LOCAL' => $p->entrega_local, 'EN_CAMINO' => $p->entrega_salida, 'ENTREGADO' => $p->entrega_fin])
                ->map(fn ($h) => $h ? Carbon::parse($h)->format('H:i') : null),
            'motorizado' => $m && in_array($p->entrega_estado, ['EN_LOCAL', 'EN_CAMINO'], true) ? ['nombre' => $m->nombre, 'telefono' => $m->telefono] : null,
            'moto' => $moto,
            'recorrido' => $enCamino ? Delivery::recorrido($p) : [],
            'destino' => $p->entrega_lat !== null ? ['lat' => (float) $p->entrega_lat, 'lng' => (float) $p->entrega_lng] : null,
            'local' => $local && $local->local_lat !== null ? ['lat' => (float) $local->local_lat, 'lng' => (float) $local->local_lng] : null,
        ]);
    }
}
