<?php

namespace App\Http\Controllers;

use App\Support\Asistente\ConteoFoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Contar un producto con una foto (inventario y conteo ciego): la IA propone la cantidad y la persona la confirma */
class ConteoFotoController extends Controller
{
    public function contar(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->tieneModulo('/conteo-ciego'), 403, 'No tienes permiso para contar inventario.');
        $request->validate([
            'producto' => 'required|integer',
            'foto' => 'required|file|max:12288|extensions:'.ConteoFoto::EXTENSIONES,
        ], ['foto.extensions' => 'Toma una foto (JPG o PNG).', 'foto.max' => 'La foto pesa más de 12 MB.']);
        set_time_limit(90);

        try {
            return response()->json(['ok' => true] + ConteoFoto::contar(Auth::user(), (int) $request->producto, $request->file('foto')));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }
}
