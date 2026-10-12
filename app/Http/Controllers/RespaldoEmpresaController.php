<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\Respaldos;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function descargar()
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador puede descargar la copia de seguridad.');
        set_time_limit(1800);
        try {
            $ruta = Respaldos::crear(Tenancy::baseActual(), 'descarga', storage_path('app/respaldos-temporales'));
        } catch (\RuntimeException $e) {
            report($e);

            return back()->with('error', 'No se pudo preparar la copia en este momento. Inténtalo otra vez o escríbenos a soporte.');
        }
        $ruc = (string) DB::table('empresa')->value('IdEmpresa');

        return response()->download($ruta, 'copia-tushpa-'.$ruc.'-'.now()->format('Y-m-d-Hi').'.sql.gz', ['Content-Type' => 'application/gzip'])
            ->deleteFileAfterSend();
    }
}
