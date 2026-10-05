<?php
namespace App\Http\Controllers;

use App\Support\{Concar, Excel};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/** Exportación de ventas y compras a la plantilla de importación de asientos de CONCAR */
class ConcarController extends Controller
{
    private function autorizar(): string
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador puede exportar a CONCAR.');
        return Auth::user()->IdEmpresa;
    }

    public function index(Request $request)
    {
        $ruc = $this->autorizar();
        $periodo = preg_match('/^\d{6}$/', (string) $request->get('periodo')) ? $request->get('periodo') : now()->subMonth()->format('Ym');

        return view('empresas.concar.index', [
            'periodo' => $periodo,
            'cfg' => Concar::config($ruc),
            'ventas' => Concar::ventas($ruc, $periodo),
            'compras' => Concar::compras($ruc, $periodo),
        ]);
    }

    public function guardarConfig(Request $request)
    {
        $ruc = $this->autorizar();
        $cuenta = 'required|string|max:12|regex:/^[0-9A-Za-z]+$/';
        $d = $request->validate([
            'subdiario_ventas' => 'required|string|max:4|regex:/^[0-9A-Za-z]+$/',
            'subdiario_compras' => 'required|string|max:4|regex:/^[0-9A-Za-z]+$/',
            'cta_por_cobrar' => $cuenta, 'cta_igv' => $cuenta, 'cta_ventas' => $cuenta, 'cta_ventas_exo' => $cuenta,
            'cta_compras' => $cuenta, 'cta_por_pagar' => $cuenta,
            'anexo_varios' => 'required|string|max:18',
            'tipo_conversion' => 'required|in:V,M,F',
        ], ['regex' => 'Solo letras y números, sin espacios ni puntos.'], [
            'cta_por_cobrar' => 'cuenta por cobrar', 'cta_igv' => 'cuenta de IGV', 'cta_ventas' => 'cuenta de ventas gravadas',
            'cta_ventas_exo' => 'cuenta de ventas exoneradas', 'cta_compras' => 'cuenta de compras', 'cta_por_pagar' => 'cuenta por pagar',
        ]);

        DB::table('concar_config')->updateOrInsert(['IdEmpresa' => $ruc], $d + ['updated_at' => now()]);
        return back()->with('success', 'Configuración de CONCAR guardada.');
    }

    public function descargar(Request $request, string $libro)
    {
        $ruc = $this->autorizar();
        $d = $request->validate(['periodo' => 'required|digits:6', 'desde' => 'nullable|integer|min:1|max:9999']);
        $desde = (int) ($d['desde'] ?? 1);

        $datos = $libro === 'ventas' ? Concar::ventas($ruc, $d['periodo'], $desde) : Concar::compras($ruc, $d['periodo'], $desde);
        if (!$datos['filas']) {
            return back()->with('error', 'No hay ' . $libro . ' para exportar en ese periodo.');
        }
        if ($datos['asientos'] + $desde - 1 > 9999) {
            return back()->with('error', 'El correlativo pasa de 9999 asientos en el mes; CONCAR solo admite 4 dígitos.');
        }

        $ruta = (new Excel())->hoja('Asientos', Concar::titulos(), $datos['filas'])->guardar();
        $nombre = 'CONCAR_' . strtoupper($libro) . "_{$ruc}_{$d['periodo']}.xlsx";

        return response()->download($ruta, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend();
    }
}
