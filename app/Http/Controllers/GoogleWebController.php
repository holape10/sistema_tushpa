<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * "Continuar con Google" en tushpa.app (Google One Tap): Google entrega una credencial firmada, se verifica con
 * Google y se guardan el nombre y el correo para llenar el registro (o Crea tu carta). No crea ninguna cuenta.
 * Se activa poniendo GOOGLE_CLIENT_ID en el .env.
 */
class GoogleWebController extends Controller
{
    public function entrar(Request $request): JsonResponse
    {
        $credencial = (string) $request->validate(['credential' => 'required|string|max:4096'])['credential'];
        $clienteId = (string) config('services.google.client_id');
        if ($clienteId === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Google no está configurado.'], 422);
        }
        try {
            $d = Http::timeout(8)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $credencial])->json();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => 'No se pudo verificar con Google. Escribe tus datos.'], 422);
        }
        $valido = is_array($d) && ($d['aud'] ?? null) === $clienteId && in_array($d['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
            && ($d['email_verified'] ?? 'false') === 'true' && (int) ($d['exp'] ?? 0) > time();
        if (! $valido) {
            return response()->json(['ok' => false, 'mensaje' => 'La cuenta de Google no se pudo verificar.'], 422);
        }
        session(['google' => ['nombre' => mb_substr((string) ($d['name'] ?? ''), 0, 120), 'email' => mb_strtolower((string) $d['email']), 'foto' => $d['picture'] ?? null]]);

        // Desde Crea tu carta vuelve ahí; desde las demás páginas va al registro con sus datos
        $destino = str_contains((string) $request->input('desde'), 'crea-tu-carta') ? route('sitio.carta.crear') : route('sitio.registro');

        return response()->json(['ok' => true, 'redirect' => $destino, 'nombre' => session('google.nombre')]);
    }
}
