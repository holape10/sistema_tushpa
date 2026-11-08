<?php

namespace App\Http\Controllers;

use App\Support\Asistente\Asistente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Asistente IA: el chat del administrador con su negocio. La conversación vive en la sesión (se borra al cerrarla). */
class AsistenteController extends Controller
{
    private const SESION = 'asistente_historial';

    public function index(): View
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Por ahora el asistente es solo para el administrador.');

        return view('empresas.asistente.index', [
            'historial' => collect(session(self::SESION, []))->map(fn ($m) => ['role' => $m['role'],
                'html' => $m['role'] === 'assistant' ? self::html($m['content']) : e($m['content']), 'graficos' => $m['graficos'] ?? []])->all(),
            'bloqueo' => Asistente::bloqueo(Auth::user()),
            'cupo' => Asistente::configurado() ? rescue(fn () => Asistente::cupo(), null, false) : null,
        ]);
    }

    public function preguntar(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->esAdmin(), 403);
        $d = $request->validate(['pregunta' => 'required|string|min:2|max:1500']);
        set_time_limit(240);
        $historial = session(self::SESION, []);
        try {
            $r = Asistente::preguntar(Auth::user(), $historial, trim($d['pregunta']));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        $historial[] = ['role' => 'user', 'content' => trim($d['pregunta'])];
        $historial[] = ['role' => 'assistant', 'content' => $r['respuesta'], 'graficos' => $r['graficos']];
        session([self::SESION => array_slice($historial, -Asistente::HISTORIAL)]);

        return response()->json(['ok' => true, 'html' => self::html($r['respuesta']), 'graficos' => $r['graficos'], 'cupo' => Asistente::cupo()]);
    }

    public function nueva(): JsonResponse
    {
        abort_unless(Auth::user()->esAdmin(), 403);
        session()->forget(self::SESION);

        return response()->json(['ok' => true]);
    }

    /** La respuesta (markdown) a HTML seguro: sin HTML propio ni enlaces peligrosos */
    private static function html(string $texto): string
    {
        return (string) Str::markdown($texto, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }
}
