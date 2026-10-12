<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ImpresionController;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Instalador del agente de impresión (panel admin, solo el dueño): se compila con deploy/agente/construir.bat y se sube
 * aquí. Es el mismo .exe para todas las empresas; cada una lo descarga desde Impresoras con su código de instalación.
 */
class AgenteController extends Controller
{
    public function index(): View
    {
        $this->soloDueno();
        $ruta = ImpresionController::rutaInstalador();

        return view('admin.agente', ['existe' => is_file($ruta), 'tamano' => is_file($ruta) ? filesize($ruta) : 0,
            'fecha' => is_file($ruta) ? Carbon::createFromTimestamp(filemtime($ruta)) : null]);
    }

    public function subir(Request $request)
    {
        $this->soloDueno();
        $request->validate(['instalador' => 'required|file|max:204800'], ['instalador.max' => 'El instalador puede pesar hasta 200 MB.'], ['instalador' => 'instalador']);
        $archivo = $request->file('instalador');
        // Un .exe de Windows empieza con "MZ"
        if (! str_ends_with(mb_strtolower($archivo->getClientOriginalName()), '.exe') || file_get_contents($archivo->getRealPath(), false, null, 0, 2) !== 'MZ') {
            return back()->with('error', 'Sube el instalador .exe que genera construir.bat (Instalar_Agente_TUSHPA.exe).');
        }
        $ruta = ImpresionController::rutaInstalador();
        @mkdir(dirname($ruta), 0775, true);
        $archivo->move(dirname($ruta), basename($ruta));

        return back()->with('ok', 'Instalador actualizado. Desde ahora las empresas lo descargan en Impresoras › "Descargar instalador".');
    }

    private function soloDueno(): void
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403);
    }
}
