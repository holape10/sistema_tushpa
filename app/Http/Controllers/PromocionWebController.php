<?php

namespace App\Http\Controllers;

use App\Support\Promociones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Promociones en tushpa.app: su página de oferta y el conteo de vistas y clics */
class PromocionWebController extends Controller
{
    public function oferta(string $slug): View
    {
        $p = Promociones::db()->where('slug', $slug)->where('con_pagina', true)->first();
        abort_unless($p && $p->activo && (! $p->hasta || $p->hasta >= now()->toDateString()), 404);
        Promociones::db()->where('id', $p->id)->increment('vistas');

        return view('sitio.oferta', ['p' => $p]);
    }

    public function vista(int $id): JsonResponse
    {
        Promociones::db()->where('id', $id)->where('activo', true)->increment('vistas');

        return response()->json(['ok' => true]);
    }

    /** Clic en el botón: se cuenta y se va a su destino */
    public function clic(int $id): RedirectResponse
    {
        $p = Promociones::db()->where('id', $id)->first();
        abort_unless($p, 404);
        Promociones::db()->where('id', $id)->increment('clics');

        return redirect()->to(Promociones::destino($p) ?: route('sitio.registro'));
    }
}
