<?php

namespace App\Http\Controllers;

use App\Jobs\GenerarRespaldo;
use App\Support\Procesos;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Copia de seguridad para el administrador de la empresa: descarga toda su información en un archivo.
 * Restaurar lo hace soporte desde el panel (cargar un archivo en la base es una operación delicada).
 */
class RespaldoEmpresaController extends Controller
{
    public function index(): View
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador puede descargar la copia de seguridad.');

        return view('empresas.respaldo');
    }

    /**
     * La copia se prepara en segundo plano (si el trabajador de colas está corriendo) y la pantalla de avance la deja
     * descargar al terminar; sin trabajador, se prepara en el momento como antes.
     */
    public function descargar()
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador puede descargar la copia de seguridad.');
        set_time_limit(1800);   // por si se hace en el momento (sin trabajador de colas)
        $id = Procesos::lanzar(Auth::user(), 'respaldo', 'Copia de seguridad de la empresa',
            fn (string $base, int $proceso, int $usuario) => new GenerarRespaldo($base, $proceso, $usuario));

        return redirect()->route('procesos.show', $id);
    }
}
