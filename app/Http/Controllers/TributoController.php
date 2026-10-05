<?php
namespace App\Http\Controllers;

use App\Support\{Excel, Tributos};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class TributoController extends Controller
{
    private function ruc(): string
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador ve el resumen tributario.');
        return (string) Auth::user()->IdEmpresa;
    }

    public function index(Request $request)
    {
        $ruc = $this->ruc();
        $anio = (int) $request->get('anio', now()->year);
        $r = Tributos::resumen($ruc, $anio);

        if ($request->get('excel')) {
            $filas = array_map(fn($m) => [$m['nombre'], $m['ventas'], $m['igv_ventas'], $m['compras'], $m['gastos'], $m['credito'], $m['igv_pagar'],
                $m['saldo_favor'], (float) $m['renta'], $m['essalud'], $m['onp'], $m['quinta'], $m['cuarta'], $m['total_pagar'], $m['utilidad']], $r['meses']);
            $ruta = (new Excel())->hoja("Tributos $anio", ['Mes', 'Ventas netas', 'IGV ventas', 'Compras', 'Gastos', 'Crédito fiscal', 'IGV a pagar',
                'Saldo a favor', 'Renta (pago a cuenta)', 'EsSalud', 'ONP', 'Renta 5ta', 'Renta 4ta', 'Total a pagar', 'Utilidad estimada'], $filas)->guardar();
            return response()->download($ruta, "resumen_tributario_{$anio}.xlsx")->deleteFileAfterSend();
        }

        return view('empresas.tributos.index', $r + ['anio' => $anio, 'regimenes' => Tributos::REGIMENES,
            'empresa' => DB::table('empresa')->where('IdEmpresa', $ruc)->first()]);
    }

    public function config(Request $request)
    {
        $ruc = $this->ruc();
        $d = $request->validate(['regimen' => 'required|in:NRUS,RER,RMT,RG', 'coeficiente' => 'nullable|numeric|min:0|max:1',
            'saldo_favor_inicial' => 'nullable|numeric|min:0']);
        Tributos::config($ruc);
        DB::table('tributos_config')->where('IdEmpresa', $ruc)->update(['regimen' => $d['regimen'], 'coeficiente' => $d['coeficiente'] ?: null,
            'saldo_favor_inicial' => $d['saldo_favor_inicial'] ?? 0, 'exonerado_igv' => $request->boolean('exonerado_igv'), 'updated_at' => now()]);
        return back()->with('success', 'Configuración tributaria guardada.');
    }
}
