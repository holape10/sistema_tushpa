<?php

namespace App\Http\Controllers;

use App\Support\CourierWeb;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Reclamos de los envíos (los que llegan por la web y los que se registran en la agencia): revisión y respuesta */
class CourierReclamoController extends Controller
{
    private function ruc(): string
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo el administrador o la caja ven los reclamos.');

        return (string) Auth::user()->IdEmpresa;
    }

    public function index(Request $request): View
    {
        $ruc = $this->ruc();
        $estado = array_key_exists($request->query('estado'), CourierWeb::ESTADOS_RECLAMO) ? $request->query('estado') : null;
        $reclamos = DB::table('cou_reclamos as r')->join('cou_envios as e', 'e.env_id', '=', 'r.env_id')->where('r.IdEmpresa', $ruc)
            ->when($estado, fn ($q) => $q->where('r.estado', $estado), fn ($q) => $q->whereIn('r.estado', ['ABIERTO', 'EN_PROCESO']))
            ->orderByRaw("r.estado = 'ABIERTO' DESC")->orderByDesc('r.rec_id')->limit(200)
            ->get(['r.*', 'e.codigo', 'e.rem_nombre', 'e.des_nombre', 'e.total', 'e.valor_declarado', 'e.estado as estado_envio']);

        return view('empresas.courier.reclamos', [
            'reclamos' => $reclamos, 'estado' => $estado, 'tipos' => CourierWeb::TIPOS_RECLAMO, 'estados' => CourierWeb::ESTADOS_RECLAMO,
            'conteo' => DB::table('cou_reclamos')->where('IdEmpresa', $ruc)->get()->countBy('estado'),
        ]);
    }

    public function responder(Request $request, int $id): JsonResponse
    {
        $ruc = $this->ruc();
        $d = $request->validate(['estado' => 'required|in:'.implode(',', array_keys(CourierWeb::ESTADOS_RECLAMO)), 'respuesta' => 'nullable|string|max:2000',
            'compensacion' => 'nullable|numeric|min:0|max:999999'], [], ['respuesta' => 'respuesta']);
        if (in_array($d['estado'], ['RESUELTO', 'RECHAZADO'], true) && trim((string) ($d['respuesta'] ?? '')) === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Escribe la respuesta para el cliente antes de cerrar el reclamo.']);
        }
        $n = DB::table('cou_reclamos')->where('rec_id', $id)->where('IdEmpresa', $ruc)->update([
            'estado' => $d['estado'], 'respuesta' => $d['respuesta'] ?? null, 'compensacion' => round((float) ($d['compensacion'] ?? 0), 2),
            'IdUsuario' => Auth::id(), 'resuelto' => in_array($d['estado'], ['RESUELTO', 'RECHAZADO'], true) ? now() : null,
        ]);

        return response()->json($n ? ['ok' => true, 'mensaje' => 'Reclamo actualizado.'] : ['ok' => false, 'mensaje' => 'Reclamo no encontrado.']);
    }
}
