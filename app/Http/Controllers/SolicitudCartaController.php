<?php

namespace App\Http\Controllers;

use App\Support\CartaMesa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lo que piden los clientes desde el QR de su mesa, en la pantalla de mesas del mozo y la caja:
 * aceptar un pedido (se suma a la mesa y va a cocina), rechazarlo, o marcar como atendido "llamar al mozo" y "la cuenta".
 */
class SolicitudCartaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(CartaMesa::pendientes((int) Auth::user()->id_empresa_negocio));
    }

    public function aceptar(int $id): JsonResponse
    {
        try {
            return response()->json(['ok' => true, 'mensaje' => CartaMesa::aceptar(Auth::user(), $id)]);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function rechazar(Request $request, int $id): JsonResponse
    {
        $d = $request->validate(['motivo' => 'nullable|string|max:150']);
        try {
            CartaMesa::rechazar(Auth::user(), $id, $d['motivo'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Pedido rechazado: el cliente lo ve en su celular.']);
    }
}
