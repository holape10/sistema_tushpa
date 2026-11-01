<?php

namespace App\Http\Controllers;

use App\Support\AvisosCelular;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** La pantalla de mesas registra el celular para los avisos (o lo quita si el navegador dejó de aceptarlos) */
class AvisoCelularController extends Controller
{
    public function suscribir(Request $request): JsonResponse
    {
        abort_unless(AvisosCelular::configurado() && AvisosCelular::usaQrDeMesas((int) Auth::user()->id_empresa_negocio), 404);
        $d = $request->validate([
            'endpoint' => 'required|url|starts_with:https://|max:1000',
            'keys.p256dh' => 'required|string|max:200',
            'keys.auth' => 'required|string|max:100',
        ]);
        $usuario = Auth::user();
        AvisosCelular::suscribir((int) $usuario->IdUsuario, (int) $usuario->id_empresa_negocio, $d, $request->userAgent());

        return response()->json(['ok' => true]);
    }

    public function quitar(Request $request): JsonResponse
    {
        $d = $request->validate(['endpoint' => 'required|string|max:1000']);
        AvisosCelular::quitar($d['endpoint']);

        return response()->json(['ok' => true]);
    }
}
