<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ProcesosLinux;
use App\Support\Servidor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Estado del servidor (solo el dueño): memoria, disco, MySQL y el tamaño de la base de cada empresa. Solo lee. */
class ServidorController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403, 'Solo el dueño del sistema ve el servidor.');
        $bases = Servidor::bases();
        $elegida = (string) $request->get('base', '');

        return view('admin.servidor', [
            'memoria' => Servidor::memoria(), 'disco' => Servidor::disco(), 'mysql' => Servidor::mysql(), 'bases' => $bases,
            'consultas' => Servidor::consultasActivas(), 'trabajos' => Servidor::trabajos(), 'procesos' => ProcesosLinux::agrupados(),
            'elegida' => $bases->firstWhere('base', $elegida),
            'tablas' => $bases->contains('base', $elegida) ? Servidor::tablasDe($elegida) : collect(),
        ]);
    }
}
