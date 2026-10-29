<?php

namespace App\Http\Controllers;

use App\Support\Courier;
use App\Support\CourierReparto;
use App\Support\Delivery;
use App\Support\VentaDirecta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Reparto a domicilio del courier: la agencia asigna los envíos a sus motorizados y liquida lo que cobraron; el motorizado
 * entra con su enlace personal (sin usuario), entrega con foto y firma o marca que no se pudo entregar.
 */
class CourierRepartoController extends Controller
{
    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo el administrador o la caja usan los envíos.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    private function json(callable $accion): JsonResponse
    {
        try {
            return response()->json(['ok' => true] + (array) $accion());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function index(): View
    {
        $suc = $this->sucursal();
        $user = Auth::user();
        $motorizados = CourierReparto::motorizados($suc);
        $enAgencia = Courier::llegan($user, DB::table('cou_envios as e'))->where('e.estado', 'EN_DESTINO')->where('e.entrega', 'DOMICILIO')->orderBy('e.env_id')->get(['e.*']);
        $enRuta = Courier::llegan($user, DB::table('cou_envios as e'))->where('e.estado', 'EN_REPARTO')->orderBy('e.env_id')->get(['e.*'])->groupBy('mot_id');

        return view('empresas.courier.reparto.index', [
            'motorizados' => $motorizados->map(fn ($m) => (object) ((array) $m + ['enlace' => route('courier.repartidor', Delivery::tokenMotorizado((int) $m->mot_id)),
                'envios' => $enRuta[$m->mot_id] ?? collect()])),
            'enAgencia' => $enAgencia, 'porLiquidar' => CourierReparto::porLiquidar($user),
        ]);
    }

    public function asignar(Request $request): JsonResponse
    {
        $this->sucursal();
        $d = $request->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'integer', 'mot_id' => 'required|integer']);

        return $this->json(function () use ($d) {
            $n = CourierReparto::asignar(Auth::user(), $d['ids'], (int) $d['mot_id']);
            if (! $n) {
                throw new \RuntimeException('Ninguno de los envíos elegidos se puede asignar.');
            }

            return ['mensaje' => "{$n} envío(s) salieron a reparto."];
        });
    }

    /** La caja recibe lo que cobró el motorizado y emite los comprobantes */
    public function liquidar(Request $request): JsonResponse
    {
        $this->sucursal();
        $d = $request->validate(['ids' => 'required|array|min:1|max:200', 'ids.*' => 'integer']);

        return $this->json(function () use ($d) {
            $cabs = CourierReparto::liquidar(Auth::user(), $d['ids']);

            return ['mensaje' => count($cabs).' comprobante(s) emitido(s).', 'tickets' => array_map(fn ($c) => VentaDirecta::respuesta($c)['ticket'], $cabs)];
        });
    }

    // ------------------------------------------------------------------ pantalla del motorizado (sin sesión)

    private function motorizado(string $token): object
    {
        $m = DB::table('motorizados')->where('token', $token)->where('activo', 1)->first();
        abort_unless($m, 404, 'Este enlace ya no es válido. Pide uno nuevo en la agencia.');

        return $m;
    }

    public function ruta(string $token): View
    {
        $m = $this->motorizado($token);

        return view('empresas.courier.reparto.motorizado', [
            'm' => $m, 'token' => $token, 'envios' => CourierReparto::ruta($m),
            'negocio' => DB::table('empresa_negocios')->where('id_empresa_negocio', $m->id_empresa_negocio)->first(['nombre_comercial', 'telefono']),
            'medios' => CourierReparto::MEDIOS, 'motivos' => CourierReparto::MOTIVOS,
        ]);
    }

    public function entregar(Request $request, string $token, int $id): JsonResponse
    {
        $m = $this->motorizado($token);
        $d = $request->validate(['recibe_nombre' => 'required|string|max:150', 'recibe_doc' => 'required|string|min:8|max:15',
            'cobrado' => 'nullable|numeric|min:0|max:99999', 'medio' => 'nullable|in:'.implode(',', CourierReparto::MEDIOS),
            'firma' => 'nullable|string|max:420000', 'foto' => 'nullable|image|max:12288'],
            [], ['recibe_nombre' => 'nombre de quien recibe', 'recibe_doc' => 'DNI de quien recibe']);

        return $this->json(function () use ($m, $id, $d, $request) {
            CourierReparto::entregar($m, $id, $d, $request->file('foto'));

            return ['mensaje' => 'Entregado.'];
        });
    }

    public function noEntregado(Request $request, string $token, int $id): JsonResponse
    {
        $m = $this->motorizado($token);
        $d = $request->validate(['motivo' => 'required|string|max:120']);

        return $this->json(function () use ($m, $id, $d) {
            CourierReparto::noEntregado($m, $id, $d['motivo']);

            return ['mensaje' => 'Anotado: vuelve a la agencia.'];
        });
    }
}
