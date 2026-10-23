<?php

namespace App\Http\Controllers;

use App\Support\CajaSinInternet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Caja sin internet: la pantalla (queda guardada en la PC y funciona sin conexión), el paquete de datos para vender
 * sola y la subida de las ventas al volver el internet.
 */
class CajaSinInternetController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo el Administrador o Caja usan la caja sin internet.');
    }

    /** La pantalla no lleva datos: los lee de la PC (así se abre igual sin internet) */
    public function index(): View
    {
        $this->autorizar();

        return view('empresas.caja_sin_internet.index');
    }

    /** Paquete de datos para vender sin internet (se baja solo cada cierto tiempo mientras hay internet) */
    public function paquete(Request $request): JsonResponse
    {
        $this->autorizar();
        try {
            $pc = CajaSinInternet::pc($request);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'csrf' => csrf_token()] + CajaSinInternet::paquete(Auth::user(), $pc));
    }

    /** Sube las ventas hechas sin internet (de a varias); cada una se responde por separado */
    public function sincronizar(Request $request): JsonResponse
    {
        $this->autorizar();
        $d = $request->validate([
            'pc' => 'nullable|string|max:40',
            'ventas' => 'required|array|min:1|max:50',
            'ventas.*.uuid' => 'required|uuid',
            'ventas.*.tdocod' => 'required|in:'.implode(',', array_keys(CajaSinInternet::TIPOS)),
            'ventas.*.serie' => 'required|string|size:4',
            'ventas.*.numero' => 'required|integer|min:1',
            'ventas.*.fecha' => 'required|date_format:Y-m-d H:i:s|before_or_equal:'.now()->addMinutes(10)->format('Y-m-d H:i:s'),
            'ventas.*.id_turno' => 'nullable|integer',
            'ventas.*.origen' => 'nullable|in:PV,PVCOMANDA',
            'ventas.*.mesa' => 'nullable|integer',
            'ventas.*.mozo' => 'nullable|integer',
            'ventas.*.tdicod' => 'required|string|size:1',
            'ventas.*.clinum' => 'required|string|max:15',
            'ventas.*.clinom' => 'required|string|max:120',
            'ventas.*.clidir' => 'nullable|string|max:150',
            'ventas.*.observaciones' => 'nullable|string|max:100',
            'ventas.*.total' => 'required|numeric|min:0',
            'ventas.*.paga' => 'nullable|numeric|min:0',
            'ventas.*.items' => 'required|array|min:1|max:200',
            'ventas.*.items.*.id' => 'nullable|integer',
            'ventas.*.items.*.descripcion' => 'nullable|string|max:150',
            'ventas.*.items.*.cantidad' => 'required|numeric|min:0.001|max:99999',
            'ventas.*.items.*.precio' => 'required|numeric|min:0.01|max:999999',
            'ventas.*.items.*.presentacion' => 'nullable|integer',
            'ventas.*.pagos' => 'required|array|min:1|max:10',
            'ventas.*.pagos.*.medio' => 'required|integer',
            'ventas.*.pagos.*.monto' => 'required|numeric|min:0',
            'ventas.*.pagos.*.operacion' => 'nullable|string|max:30',
        ]);
        try {
            $pc = CajaSinInternet::pc($request);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        // Una por una y en orden: si una falla, las demás igual se suben (la que falló se puede reintentar)
        $resultados = [];
        foreach ($d['ventas'] as $venta) {
            $resultados[$venta['uuid']] = CajaSinInternet::registrar(Auth::user(), $pc, $venta);
        }

        return response()->json(['ok' => true, 'resultados' => $resultados]);
    }
}
