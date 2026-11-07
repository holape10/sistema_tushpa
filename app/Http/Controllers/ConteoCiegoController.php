<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Support\Bitacora;
use App\Support\Compras;
use App\Support\ConteosCiegos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Inventario ciego: quien cuenta no ve el stock del sistema; el administrador revisa las diferencias y ajusta */
class ConteoCiegoController extends Controller
{
    public function index(): View
    {
        $this->autorizar();
        $sucursal = (int) Auth::user()->id_empresa_negocio;

        return view('empresas.conteo_ciego.index', [
            'conteos' => DB::table('conteos_ciegos as c')->leftJoin('users as u', 'u.IdUsuario', '=', 'c.IdUsuario')
                ->leftJoin('almacenes as a', 'a.id_almacen', '=', 'c.id_almacen')->where('c.id_empresa_negocio', $sucursal)
                ->select('c.*', 'u.apeusu as conto', 'a.descripcion as almacen',
                    DB::raw('(SELECT COUNT(*) FROM conteos_ciegos_detalle d WHERE d.conteo_id = c.id) as lineas'),
                    DB::raw('(SELECT COALESCE(SUM((d.contado - d.sistema) * d.costo), 0) FROM conteos_ciegos_detalle d WHERE d.conteo_id = c.id) as valor'))
                ->orderByRaw("c.estado = 'PENDIENTE' DESC")->orderByDesc('c.id')->paginate(25),
            'revisa' => ConteosCiegos::revisa(Auth::user()),
        ]);
    }

    public function create(Request $request): View
    {
        $this->autorizar();
        $sucursal = (int) Auth::user()->id_empresa_negocio;
        $almacenes = Almacen::where('id_empresa_negocio', $sucursal)->orderByDesc('predeterminado')->orderBy('descripcion')->get(['id_almacen', 'descripcion']);
        abort_if($almacenes->isEmpty(), 404, 'La sucursal no tiene almacén.');

        // Sin el stock: quien cuenta no debe saber cuánto "debería" haber
        return view('empresas.conteo_ciego.create', [
            'almacenes' => $almacenes,
            'productos' => DB::table('productos as p')->leftJoin('categorias as c', 'c.cat_id', '=', 'p.cat_id')
                ->where('p.id_empresa_negocio', $sucursal)->where('p.proest', 'Activo')->whereIn('p.promocion', Compras::TIPOS_CON_STOCK)
                ->orderBy('c.cat_nom')->orderBy('p.pronom')->get(['p.IdProducto', 'p.pronom', 'p.umecod', 'c.cat_nom']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate(['id_almacen' => 'required|integer', 'observaciones' => 'nullable|string|max:255',
            'contados' => 'required|array|max:3000', 'contados.*' => 'nullable|numeric|min:0|max:9999999'],
            ['contados.required' => 'Pon lo que contaste de al menos un producto.']);
        try {
            $id = ConteosCiegos::guardar(Auth::user(), (int) $d['id_almacen'], $d['contados'], $d['observaciones'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        Bitacora::registrar('almacen.conteo_ciego', "Registró el inventario ciego N° {$id}", null, ['referencia' => ['conteo_ciego', $id]]);

        return redirect()->route('conteo_ciego.index')->with('success', "Conteo N° {$id} enviado: el administrador revisa las diferencias.");
    }

    public function show(int $id): View
    {
        $this->autorizar();
        abort_unless(ConteosCiegos::revisa(Auth::user()), 403, 'Las diferencias las ve solo el administrador.');
        try {
            $c = ConteosCiegos::deSucursal(Auth::user(), $id);
        } catch (\RuntimeException) {
            abort(404);
        }

        return view('empresas.conteo_ciego.show', [
            'c' => $c, 'lineas' => ConteosCiegos::detalle($id),
            'conto' => DB::table('users')->where('IdUsuario', $c->IdUsuario)->value('apeusu'),
            'almacen' => DB::table('almacenes')->where('id_almacen', $c->id_almacen)->value('descripcion'),
        ]);
    }

    public function aplicar(int $id): RedirectResponse
    {
        $this->autorizar();
        abort_unless(ConteosCiegos::revisa(Auth::user()), 403);
        try {
            $lineas = ConteosCiegos::detalle($id);
            $invId = ConteosCiegos::aplicar(Auth::user(), $id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $faltante = $lineas->where('valor', '<', 0)->sum('valor');
        Bitacora::registrar('almacen.conteo_ciego_aplicar', "Ajustó el stock con el inventario ciego N° {$id}"
            .($faltante < 0 ? ' (faltante de S/ '.number_format(abs($faltante), 2).')' : ''), null,
            ['referencia' => ['conteo_ciego', $id], 'alerta' => $faltante < 0]);

        return back()->with('success', $invId ? "Stock ajustado (inventario N° {$invId})." : 'No había diferencias: el stock ya cuadraba.');
    }

    public function descartar(int $id): RedirectResponse
    {
        $this->autorizar();
        abort_unless(ConteosCiegos::revisa(Auth::user()), 403);
        try {
            ConteosCiegos::descartar(Auth::user(), $id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Conteo descartado: el stock no cambió.');
    }

    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->tieneModulo('/conteo-ciego'), 403, 'No tienes permiso para el inventario ciego.');
    }
}
