<?php

namespace App\Http\Controllers;

use App\Support\Excel;
use App\Support\KardexSunat;
use App\Support\Unidades;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kardex oficial de SUNAT: Registro de Inventario Permanente 12.1 (unidades físicas) y 13.1 (valorizado) del mes, con el
 * archivo de texto para el PLE, el Excel en el formato de SUNAT y la carga de los códigos UNSPSC de los productos.
 */
class KardexSunatController extends Controller
{
    /** @return array{periodo: string, formato: string, metodo: string, almacen: ?int} */
    private function filtros(Request $request): array
    {
        return [
            'periodo' => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('periodo')) ? $request->query('periodo') : now()->subMonth()->format('Y-m'),
            'formato' => array_key_exists($request->query('formato'), KardexSunat::FORMATOS) ? $request->query('formato') : '131',
            'metodo' => array_key_exists($request->query('metodo'), KardexSunat::METODOS) ? $request->query('metodo') : '1',
            'almacen' => $request->integer('almacen') ?: null,
        ];
    }

    private function registro(array $f): array
    {
        return KardexSunat::generar((int) Auth::user()->id_empresa_negocio, $f['periodo'], $f['metodo'], $f['almacen']);
    }

    private function empresa(): object
    {
        return DB::table('empresa')->where('IdEmpresa', Auth::user()->IdEmpresa)->first(['IdEmpresa', 'NomEmpresa']);
    }

    public function index(Request $request): View
    {
        $f = $this->filtros($request);
        $registro = $this->registro($f);
        $grupos = collect($registro['grupos']);
        $pagina = LengthAwarePaginator::resolveCurrentPage();

        return view('empresas.kardex.sunat', $f + [
            'registro' => $registro, 'empresa' => $this->empresa(),
            'grupos' => new LengthAwarePaginator($grupos->forPage($pagina, 25)->values(), $grupos->count(), 25, $pagina, ['path' => $request->url(), 'query' => $request->query()]),
            'totales' => ['productos' => $grupos->count(), 'movimientos' => $grupos->sum(fn ($g) => count($g['filas'])),
                'inicial' => round($grupos->sum(fn ($g) => $g['inicial'][2]), 2), 'final' => round($grupos->sum(fn ($g) => $g['final'][2]), 2)],
            'almacenes' => DB::table('almacenes')->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->orderBy('id_almacen')->get(['id_almacen', 'descripcion']),
            'archivo' => KardexSunat::nombreArchivo($this->empresa()->IdEmpresa, $f['periodo'], $f['formato'], $grupos->isNotEmpty()),
            // De a 100 por vez (con muchos productos, lo rápido es ponerle el código a toda una categoría)
            'sinCodigo' => DB::table('productos')->whereIn('IdProducto', array_keys($registro['avisos']['sin_unspsc']))->orderBy('pronom')->limit(100)
                ->get(['IdProducto', 'procod', 'pronom', 'promocion', 'unspsc', 'tipo_existencia', 'cat_id']),
            'categorias' => DB::table('categorias')->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->orderBy('cat_nom')->pluck('cat_nom', 'cat_id'),
            'operaciones' => DB::table('tipo_operacion_sunat')->pluck('des_tip_ope', 'cod_tip_ope'),
            'codigosSunat' => KardexSunat::codigosUsados((int) Auth::user()->id_empresa_negocio),
        ]);
    }

    /** Archivo de texto para subir al PLE de SUNAT */
    public function txt(Request $request): Response
    {
        $f = $this->filtros($request);
        $registro = $this->registro($f);

        return response(KardexSunat::ple($registro, $f['formato'], $f['metodo']), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.KardexSunat::nombreArchivo($this->empresa()->IdEmpresa, $f['periodo'], $f['formato'], (bool) $registro['grupos']).'"',
        ]);
    }

    /** El registro en Excel con el formato impreso de SUNAT (encabezado por producto y sus columnas) */
    public function excel(Request $request)
    {
        $f = $this->filtros($request);
        $registro = $this->registro($f);
        $empresa = $this->empresa();
        [$codigo, $nombre] = KardexSunat::FORMATOS[$f['formato']];
        $valorizado = $f['formato'] === '131';
        $filas = [['PERIODO:', Carbon::parse($f['periodo'].'-01')->translatedFormat('F Y')], ['RUC:', $empresa->IdEmpresa],
            ['APELLIDOS Y NOMBRES, DENOMINACIÓN O RAZÓN SOCIAL:', $empresa->NomEmpresa]];
        if ($valorizado) {
            $filas[] = ['MÉTODO DE VALUACIÓN:', KardexSunat::METODOS[$f['metodo']]];
        }
        foreach ($registro['grupos'] as $g) {
            $p = $g['producto'];
            $filas[] = [''];
            $filas[] = ['ESTABLECIMIENTO (1):', $g['almacen']->codigo.' · '.$g['almacen']->nombre];
            $filas[] = ['CÓDIGO DE LA EXISTENCIA:', $p->procod ?: (string) $p->IdProducto, 'TIPO (TABLA 5):', KardexSunat::tipoExistencia($p).' '.(KardexSunat::TIPOS_EXISTENCIA[KardexSunat::tipoExistencia($p)] ?? '')];
            $filas[] = ['DESCRIPCIÓN:', $p->pronom, 'UNIDAD DE MEDIDA (TABLA 6):', Unidades::sunat($p->umecod)];
            $filas[] = $valorizado
                ? ['FECHA', 'TIPO (TABLA 10)', 'SERIE', 'NÚMERO', 'OPERACIÓN (TABLA 12)', 'ENTRADA CANT.', 'ENTRADA C.U.', 'ENTRADA TOTAL', 'SALIDA CANT.', 'SALIDA C.U.', 'SALIDA TOTAL', 'SALDO CANT.', 'SALDO C.U.', 'SALDO TOTAL']
                : ['FECHA', 'TIPO (TABLA 10)', 'SERIE', 'NÚMERO', 'OPERACIÓN (TABLA 12)', 'ENTRADAS', 'SALIDAS', 'SALDO FINAL'];
            [$q0, $u0, $t0] = $g['inicial'];
            $filas[] = $valorizado ? [$registro['desde']->format('d/m/Y'), '00', '0', '0', '16 SALDO INICIAL', $q0, $u0, $t0, '', '', '', $q0, $u0, $t0]
                : [$registro['desde']->format('d/m/Y'), '00', '0', '0', '16 SALDO INICIAL', $q0, '', $q0];
            foreach ($g['filas'] as $r) {
                $entra = $r['tipo'] === 'I';
                $fila = [Carbon::parse($r['fecha'])->format('d/m/Y'), $r['tdoc'], $r['serie'], $r['numero'], $r['op']];
                $filas[] = $valorizado
                    ? [...$fila, $entra ? $r['cantidad'] : '', $entra ? $r['unitario'] : '', $entra ? $r['total'] : '', $entra ? '' : $r['cantidad'], $entra ? '' : $r['unitario'], $entra ? '' : $r['total'], ...$r['saldo']]
                    : [...$fila, $entra ? $r['cantidad'] : '', $entra ? '' : $r['cantidad'], $r['saldo'][0]];
            }
            [$qf, $uf, $tf] = $g['final'];
            $filas[] = $valorizado ? ['', '', '', '', 'TOTALES', round(array_sum(array_map(fn ($r) => $r['tipo'] === 'I' ? $r['cantidad'] : 0, $g['filas'])) + $q0, 8), '',
                round(array_sum(array_map(fn ($r) => $r['tipo'] === 'I' ? $r['total'] : 0, $g['filas'])) + $t0, 2), round(array_sum(array_map(fn ($r) => $r['tipo'] === 'E' ? $r['cantidad'] : 0, $g['filas'])), 8), '',
                round(array_sum(array_map(fn ($r) => $r['tipo'] === 'E' ? $r['total'] : 0, $g['filas'])), 2), $qf, $uf, $tf]
                : ['', '', '', '', 'TOTALES', round(array_sum(array_map(fn ($r) => $r['tipo'] === 'I' ? $r['cantidad'] : 0, $g['filas'])) + $q0, 8),
                    round(array_sum(array_map(fn ($r) => $r['tipo'] === 'E' ? $r['cantidad'] : 0, $g['filas'])), 8), $qf];
        }
        $ruta = (new Excel)->hoja('Formato '.$codigo, [['FORMATO '.$codigo.': "'.mb_strtoupper($nombre).'"']], $filas)->guardar();

        return response()->download($ruta, 'formato_'.str_replace('.', '_', $codigo).'_'.str_replace('-', '', $f['periodo']).'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /** Códigos UNSPSC y tipo de existencia de los productos (uno por uno, o el mismo para todos los de una categoría) */
    public function codigos(Request $request): RedirectResponse
    {
        $suc = (int) Auth::user()->id_empresa_negocio;
        $d = $request->validate(['productos' => 'nullable|array', 'productos.*.unspsc' => ['nullable', 'regex:'.KardexSunat::REGLA_CODIGO],
            'productos.*.tipo_existencia' => 'nullable|in:'.implode(',', array_keys(KardexSunat::TIPOS_EXISTENCIA)),
            'categoria' => 'nullable|integer', 'unspsc_categoria' => ['nullable', 'required_with:categoria', 'regex:'.KardexSunat::REGLA_CODIGO]],
            ['productos.*.unspsc.regex' => 'El código tiene 8 dígitos (UNSPSC) o 16 (ítem CUBSO).', 'unspsc_categoria.regex' => 'El código tiene 8 dígitos (UNSPSC) o 16 (ítem CUBSO).']);
        $n = 0;
        foreach ($d['productos'] ?? [] as $id => $p) {
            $n += DB::table('productos')->where('IdProducto', $id)->where('id_empresa_negocio', $suc)
                ->update(['unspsc' => ($p['unspsc'] ?? null) ?: null, 'tipo_existencia' => ($p['tipo_existencia'] ?? null) ?: null]);
        }
        if (! empty($d['categoria'])) {
            $n += DB::table('productos')->where('id_empresa_negocio', $suc)->where('cat_id', $d['categoria'])
                ->where(fn ($q) => $q->whereNull('unspsc')->orWhere('unspsc', ''))->update(['unspsc' => $d['unspsc_categoria']]);
        }

        return back()->with('success', "Códigos guardados en {$n} producto(s).");
    }
}
