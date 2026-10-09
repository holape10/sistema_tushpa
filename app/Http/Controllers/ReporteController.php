<?php

namespace App\Http\Controllers;

use App\Models\EmpresaNegocio;
use App\Support\Excel;
use App\Support\Reportes;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReporteController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja ven los reportes.');
    }

    /** Catálogo de reportes de un grupo (Ventas o Compras) */
    public function index(Request $request)
    {
        $this->autorizar();
        $grupo = $request->get('grupo');
        $reportes = collect(Reportes::CATALOGO)->filter(fn ($r) => ! $grupo || $r[1] === $grupo);

        return view('empresas.reportes.index', compact('reportes', 'grupo'));
    }

    public function ver(Request $request, string $clave)
    {
        $this->autorizar();
        abort_unless(isset(Reportes::CATALOGO[$clave]), 404);
        $sucursales = EmpresaNegocio::where('IdEmpresa', Auth::user()->IdEmpresa)->orderBy('id_empresa_negocio')->get();
        $sucursal = (int) $request->get('sucursal', Auth::user()->id_empresa_negocio);
        abort_unless($sucursales->contains('id_empresa_negocio', $sucursal), 403);

        $desde = Carbon::parse($request->get('desde', now()->startOfMonth()->toDateString()))->toDateString();
        $hasta = Carbon::parse($request->get('hasta', now()->toDateString()))->toDateString();
        if ($hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $filtros = ['sucursal' => $sucursal, 'desde' => $desde, 'hasta' => $hasta]
            + $request->only(['tipo', 'estado', 'agrupar', 'categoria', 'orden', 'limite', 'vendedor', 'medio', 'cliente', 'motorizado', 'estado_sunat']);
        $r = Reportes::generar($clave, $filtros);
        $nombreSucursal = $sucursales->firstWhere('id_empresa_negocio', $sucursal)->nombre_comercial ?? '';
        $archivo = "reporte_{$clave}_{$desde}_{$hasta}";

        if ($request->get('formato') === 'excel') {
            return $this->excel($r, $archivo, $desde, $hasta, $nombreSucursal);
        }
        if ($request->get('formato') === 'pdf') {
            return $this->pdf($r, $archivo, $desde, $hasta, $nombreSucursal);
        }

        return view('empresas.reportes.ver', $r + [
            'clave' => $clave, 'filtros' => $filtros, 'sucursales' => $sucursales, 'otros' => collect(Reportes::CATALOGO)->where(1, $r['grupo']),
            'categorias' => DB::table('categorias')->where('id_empresa_negocio', $sucursal)->orderBy('cat_nom')->get(['cat_id', 'cat_nom']),
            'tipos' => ['sunat' => 'Solo SUNAT (sin notas de venta)', '01' => 'Factura', '03' => 'Boleta', '13' => 'Nota de venta', '07' => 'Nota de crédito', '08' => 'Nota de débito'],
            'vendedores' => DB::table('users')->where('IdEmpresa', Auth::user()->IdEmpresa)->orderBy('apeusu')->get(['IdUsuario', 'apeusu', 'name', 'estusu']),
            'medios' => DB::table('medios_pagos')->where('id_empresa_negocio', $sucursal)->orderBy('nom_med_pag')->get(['id_med_pag', 'nom_med_pag']),
            'motorizados' => Schema::hasTable('motorizados')
                ? DB::table('motorizados')->where('id_empresa_negocio', $sucursal)->orderBy('nombre')->get(['mot_id', 'nombre']) : collect(),
        ]);
    }

    /** Valor tal como se muestra (fechas en d/m/Y, porcentajes) */
    public static function texto($valor, string $tipo): string
    {
        return match ($tipo) {
            'date' => $valor ? Carbon::parse($valor)->format('d/m/Y') : '',
            'datetime' => $valor ? Carbon::parse($valor)->format('d/m/Y H:i') : '',
            'money' => number_format((float) $valor, 2),
            'num' => rtrim(rtrim(number_format((float) $valor, 2), '0'), '.'),
            'pct' => number_format((float) $valor, 1).' %',
            default => (string) $valor,
        };
    }

    private function excel(array $r, string $archivo, string $desde, string $hasta, string $sucursal)
    {
        $cols = $r['columnas'];
        $filas = array_map(function ($f) use ($cols) {
            $out = [];
            foreach ($cols as $k => [$t, $tipo]) {
                $v = $f[$k] ?? '';
                $out[] = in_array($tipo, ['money', 'num'], true) ? round((float) $v, 2)
                    : ($tipo === 'pct' ? round((float) $v, 2) : self::texto($v, $tipo));
            }

            return $out;
        }, $r['filas']);
        // Fila de totales
        $tot = [];
        foreach (array_keys($cols) as $i => $k) {
            $tot[] = $i === 0 ? 'TOTALES' : (isset($r['totales'][$k]) ? (float) $r['totales'][$k] : '');
        }
        $filas[] = $tot;
        $titulos = [[$r['titulo'].' · '.$sucursal.' · del '.Carbon::parse($desde)->format('d/m/Y').' al '.Carbon::parse($hasta)->format('d/m/Y')],
            array_map(fn ($c) => $c[0].($c[1] === 'pct' ? ' (%)' : ''), array_values($cols))];
        $ruta = (new Excel)->hoja(mb_substr($r['titulo'], 0, 31), $titulos, $filas)->guardar();

        return response()->download($ruta, "$archivo.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    private function pdf(array $r, string $archivo, string $desde, string $hasta, string $sucursal)
    {
        $html = view('empresas.reportes.pdf', $r + ['desde' => $desde, 'hasta' => $hasta, 'sucursal' => $sucursal,
            'empresa' => DB::table('empresa')->where('IdEmpresa', Auth::user()->IdEmpresa)->first()])->render();
        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml($html, 'UTF-8');
        // Muchas columnas → horizontal
        $pdf->setPaper('A4', count($r['columnas']) > 6 ? 'landscape' : 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename=\"$archivo.pdf\""]);
    }
}
