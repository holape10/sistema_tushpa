<?php

namespace App\Http\Controllers;

use App\Support\AvisosCelular;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** La pantalla de mesas (avisos de mozo) o el panel del dueño (avisos del dueño) registran el celular para los avisos */
class AvisoCelularController extends Controller
{
    public function suscribir(Request $request): JsonResponse
    {
        $usuario = Auth::user();
        $para = $request->input('para') === 'dueno' ? 'dueno' : 'mozo';
        // Avisos de mozo: solo con QR de mesas · avisos del dueño: solo el administrador
        abort_unless(AvisosCelular::configurado() && ($para === 'dueno' ? $usuario->esAdmin() : AvisosCelular::usaQrDeMesas((int) $usuario->id_empresa_negocio)), 404);
        $d = $request->validate([
            'endpoint' => 'required|url|starts_with:https://|max:1000',
            'keys.p256dh' => 'required|string|max:200',
            'keys.auth' => 'required|string|max:100',
        ]);
        AvisosCelular::suscribir((int) $usuario->IdUsuario, (int) $usuario->id_empresa_negocio, $d, $request->userAgent(), $para);

        return response()->json(['ok' => true]);
    }

    public function quitar(Request $request): JsonResponse
    {
        $d = $request->validate(['endpoint' => 'required|string|max:1000']);
        AvisosCelular::quitar($d['endpoint']);

        return response()->json(['ok' => true]);
    }
}
