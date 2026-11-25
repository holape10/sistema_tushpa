<?php

namespace App\Http\Controllers;

use App\Support\Campanas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Campañas: qué comprar para Navidad, la campaña escolar, el Día de la Madre… según lo vendido el año pasado */
class CampanaController extends Controller
{
    public function index(Request $request): View
    {
        $u = Auth::user();
        abort_unless($u->esAdmin() || $u->tieneModulo('/compras'), 403, 'Solo el administrador o quien hace las compras ve las campañas.');
        $clave = array_key_exists((string) $request->get('campana'), Campanas::LISTA)
            ? (string) $request->get('campana') : (Campanas::proxima()['clave'] ?? 'navidad');

        return view('empresas.campanas.index', [
            'plan' => Campanas::plan((int) $u->id_empresa_negocio, $clave),
            'negocio' => (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $u->id_empresa_negocio)->value('nombre_comercial'),
        ]);
    }
}
