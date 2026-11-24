<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Guía de primeros pasos: el administrador la oculta ("ya tengo todo") y el usuario nuevo marca su bienvenida como vista */
class PrimerosPasosController extends Controller
{
    public function ocultar(): RedirectResponse
    {
        abort_unless(Auth::user()->esAdmin(), 403);
        DB::table('empresa')->where('IdEmpresa', Auth::user()->IdEmpresa)->update(['guia_oculta' => true]);

        return back();
    }

    public function bienvenida(): RedirectResponse
    {
        DB::table('users')->where('IdUsuario', Auth::id())->update(['bienvenida_vista' => true]);

        return back();
    }
}
