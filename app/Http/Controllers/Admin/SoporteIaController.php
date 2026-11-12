<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Support\Asistente\Asistente;
use App\Support\Asistente\Soporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Asistente IA de soporte (solo el dueño del sistema): pregunta por la base de una empresa; solo consulta, nunca cambia datos */
class SoporteIaController extends Controller
{
    private function autorizar(): void
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403, 'Solo el dueño del sistema usa el asistente de soporte.');
    }

    private static function sesion(Cliente $cliente): string
    {
        return 'soporte_ia.'.$cliente->id;
    }

    public function index(Request $request): View
    {
        $this->autorizar();
        $cliente = $request->filled('cliente') ? Cliente::find((int) $request->get('cliente')) : null;

        return view('admin.soporte_ia', [
            'clientes' => Cliente::orderBy('razon_social')->get(['id', 'razon_social', 'ruc']),
            'cliente' => $cliente, 'bloqueo' => Soporte::bloqueo(), 'modelo' => Soporte::modelo(),
            'historial' => $cliente ? collect(session(self::sesion($cliente), []))->map(fn ($m) => ['role' => $m['role'],
                'html' => $m['role'] === 'assistant' ? self::html($m['content']) : e($m['content']), 'sql' => $m['sql'] ?? []])->all() : [],
        ]);
    }

    public function preguntar(Request $request, Cliente $cliente): JsonResponse
    {
        $this->autorizar();
        $d = $request->validate(['pregunta' => 'required|string|max:2000']);
        $historial = session(self::sesion($cliente), []);
        try {
            $r = Soporte::preguntar($cliente, (string) Auth::guard('superadmin')->user()->nombre, $historial, trim($d['pregunta']));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
        $historial[] = ['role' => 'user', 'content' => trim($d['pregunta'])];
        $historial[] = ['role' => 'assistant', 'content' => $r['respuesta'], 'sql' => $r['sql']];
        session([self::sesion($cliente) => array_slice($historial, -Asistente::HISTORIAL)]);

        return response()->json(['ok' => true, 'html' => self::html($r['respuesta']), 'sql' => $r['sql'],
            'costo' => round($r['costo'], 4), 'consultas' => $r['consultas']]);
    }

    public function nueva(Cliente $cliente): JsonResponse
    {
        $this->autorizar();
        session()->forget(self::sesion($cliente));

        return response()->json(['ok' => true]);
    }

    /** La respuesta (markdown) a HTML seguro */
    private static function html(string $texto): string
    {
        return (string) Str::markdown($texto, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }
}
