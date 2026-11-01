<?php

namespace App\Http\Controllers;

use App\Support\Llamador;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Pantalla "llamador" para un televisor a la vista de los clientes: pedidos para llevar "Preparando" y "¡Listo!" */
class LlamadorController extends Controller
{
    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index(): View
    {
        return view('empresas.cocina.llamador', [
            'negocio' => (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal())->value('nombre_comercial'),
        ]);
    }

    public function datos(): JsonResponse
    {
        return response()->json(Llamador::pedidos($this->sucursal()) + ['hora' => now()->toIso8601String()]);
    }
}
