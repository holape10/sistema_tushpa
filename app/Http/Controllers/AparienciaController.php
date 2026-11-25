<?php

namespace App\Http\Controllers;

use App\Support\Apariencia;
use App\Support\Bitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Mantenimiento › Apariencia: el administrador elige el color, el fondo y el tamaño de letra del sistema de su empresa */
class AparienciaController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador cambia la apariencia del sistema.');
        abort_unless(Apariencia::disponible(), 404);
    }

    public function index(): View
    {
        $this->autorizar();

        return view('empresas.apariencia.index', [
            'actual' => Apariencia::de((string) Auth::user()->IdEmpresa),
            'paletas' => collect(Apariencia::PALETAS)->map(fn ($p, $clave) => ['nombre' => $p[0], 'vars' => Apariencia::variables($clave)]),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate([
            'color' => ['required', Rule::in(array_keys(Apariencia::PALETAS))],
            'fondo' => ['required', Rule::in(array_keys(Apariencia::FONDOS))],
            'letra' => ['required', Rule::in(array_keys(Apariencia::LETRAS))],
        ]);
        $empresa = (string) Auth::user()->IdEmpresa;
        $antes = Apariencia::de($empresa);
        Apariencia::guardar($empresa, $d);
        Bitacora::siCambio('config.apariencia', 'Cambió la apariencia del sistema', $antes, Apariencia::de($empresa),
            ['color' => 'Color', 'fondo' => 'Fondo', 'letra' => 'Tamaño de letra']);

        return redirect()->route('apariencia.index')->with('success', 'Listo: así se verá el sistema para todos en tu empresa.');
    }
}
