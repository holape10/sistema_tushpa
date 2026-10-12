<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Números de serie (garantías): buscar una serie y ver de qué compra o ingreso vino, en qué comprobante se vendió,
 * a quién y hasta cuándo tiene garantía. También lista lo disponible por producto.
 */
class SerieController extends Controller
{
    public function index(Request $request): View
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        $q = trim((string) $request->get('q'));
        $estado = in_array($request->get('estado'), ['DISPONIBLE', 'VENDIDO', 'BAJA'], true) ? $request->get('estado') : null;

        $series = DB::table('producto_series as s')
            ->join('productos as p', 'p.IdProducto', '=', 's.IdProducto')
            ->leftJoin('almacenes as a', 'a.id_almacen', '=', 's.id_almacen')
            ->leftJoin('compras_cabecera as c', 'c.com_cab_id', '=', 's.com_cab_id')
            ->leftJoin('proveedor as pr', 'pr.prov_id', '=', 'c.prov_id')
            ->leftJoin('cpe_cabecera as v', 'v.IdCpe_cabecera', '=', 's.IdCpe_cabecera')
            ->where('s.id_empresa_negocio', $sucursal)
            ->when($estado, fn ($w) => $w->where('s.estado', $estado))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('s.serie', 'like', '%'.$q.'%')
                ->orWhere('p.pronom', 'like', '%'.$q.'%')->orWhere('s.cliente', 'like', '%'.$q.'%')))
            ->orderByRaw('s.serie = ? DESC', [mb_strtoupper($q)])->orderByDesc('s.id')
            ->select('s.*', 'p.pronom', 'p.procod', 'a.descripcion as almacen', 'c.com_doc_ser', 'c.com_doc_num', 'pr.prov_raz',
                'v.serdoc', 'v.numdoc')
            ->paginate(30)->withQueryString();

        $totales = DB::table('producto_series')->where('id_empresa_negocio', $sucursal)
            ->groupBy('estado')->selectRaw('estado, COUNT(*) n')->pluck('n', 'estado');

        return view('empresas.series.index', compact('series', 'q', 'estado', 'totales'));
    }
}
