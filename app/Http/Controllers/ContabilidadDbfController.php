<?php

namespace App\Http\Controllers;

use App\Support\Concar;
use App\Support\Contabilidad\VentasDbf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Otros › Contabilidad DBF: descarga el tienfact.dbf de ventas del mes armado con los comprobantes del sistema
 * (la misma estructura que el del SIRE), para llevarlo al sistema contable en FoxPro.
 */
class ContabilidadDbfController extends Controller
{
    private function autorizar(): string
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->contadorCon('/contabilidad-dbf'), 403, 'Solo el Administrador o el contador descargan el DBF.');

        return (string) Auth::user()->IdEmpresa;
    }

    private function periodo(Request $request): string
    {
        return preg_match('/^\d{4}-\d{2}$/', (string) $request->input('mes')) ? str_replace('-', '', $request->input('mes')) : now()->subMonth()->format('Ym');
    }

    public function index(Request $request): View
    {
        $ruc = $this->autorizar();
        $periodo = $this->periodo($request);
        $comprobantes = VentasDbf::comprobantes($ruc, $periodo);

        return view('empresas.contabilidad_dbf.index', ['periodo' => $periodo, 'comprobantes' => $comprobantes,
            'totales' => VentasDbf::totales($comprobantes), 'cfg' => Concar::config($ruc)]);
    }

    public function descargar(Request $request): Response
    {
        $ruc = $this->autorizar();
        $periodo = $this->periodo($request);
        if (! VentasDbf::comprobantes($ruc, $periodo)) {
            return back()->with('error', 'No hay facturas ni boletas en ese mes.');
        }

        return response(VentasDbf::tienfact($ruc, $periodo), 200, [
            'Content-Type' => 'application/x-dbf',
            'Content-Disposition' => 'attachment; filename="tienfact.dbf"',
        ]);
    }
}
