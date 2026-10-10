<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use App\Support\Recetas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Entradas del menú (SOPA, TEQUEÑOS, ENSALADA...): se crean aquí, cada una con su receta (insumos y costo),
 * y se marcan los platos que "llevan entrada". En la comanda el mozo elige la entrada de cada plato.
 */
class EntradaController extends Controller
{
    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador configura las entradas.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index(): View
    {
        $suc = $this->sucursal();
        $entradas = Producto::where('id_empresa_negocio', $suc)->where('promocion', Producto::OPCION)
            ->orderByRaw("proest = 'Activo' DESC")->orderBy('pronom')->get(['IdProducto', 'pronom', 'proest']);
        $costos = Recetas::costos($entradas->pluck('IdProducto'));
        $ingredientes = DB::table('producto_receta')->whereIn('IdProducto', $entradas->pluck('IdProducto'))
            ->groupBy('IdProducto')->select('IdProducto', DB::raw('COUNT(*) as n'))->pluck('n', 'IdProducto');

        return view('empresas.entradas.index', [
            'entradas' => $entradas->map(fn ($e) => (object) [
                'id' => $e->IdProducto, 'nombre' => $e->pronom, 'activa' => $e->proest === 'Activo',
                'costo' => $costos[$e->IdProducto] ?? null, 'ingredientes' => (int) ($ingredientes[$e->IdProducto] ?? 0),
            ]),
            'platos' => DB::table('productos as p')->leftJoin('categorias as c', 'c.cat_id', '=', 'p.cat_id')
                ->where('p.id_empresa_negocio', $suc)->where('p.proest', 'Activo')->where('p.promocion', 2)
                ->orderByDesc('p.lleva_entrada')->orderBy('c.cat_nom')->orderBy('p.pronom')->get(['p.IdProducto', 'p.pronom', 'p.lleva_entrada', 'c.cat_nom']),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $suc = $this->sucursal();
        $d = $request->validate(['nombre' => 'required|string|max:150'], [], ['nombre' => 'nombre de la entrada']);
        $nombre = mb_strtoupper(trim($d['nombre']));
        if (Producto::where('id_empresa_negocio', $suc)->where('pronom', $nombre)->exists()) {
            return back()->withInput()->with('error', "Ya existe un producto llamado {$nombre}.");
        }
        // Las entradas van en su categoría, oculta en las pantallas de venta
        $categoria = Categoria::firstOrCreate(['cat_nom' => 'ENTRADAS', 'id_empresa_negocio' => $suc],
            ['IdEmpresa' => Auth::user()->IdEmpresa, 'visible' => 0]);
        $entrada = Producto::create([
            'procod' => mb_substr('E'.time().random_int(10, 99), 0, 20), 'pronom' => $nombre, 'promocion' => Producto::OPCION,
            'umecod' => 'NIU', 'costo' => 0, 'propun' => 0, 'cat_id' => $categoria->cat_id, 'stock_min' => 0, 'proest' => 'Activo',
            'IdEmpresa' => Auth::user()->IdEmpresa, 'id_empresa_negocio' => $suc,
        ]);

        return $request->boolean('con_receta')
            ? redirect()->route('recetas.editar', $entrada->IdProducto)->with('success', "Entrada {$nombre} creada. Ahora escribe sus insumos.")
            : back()->with('success', "Entrada {$nombre} creada.");
    }

    public function actualizar(Request $request, int $id): RedirectResponse
    {
        $entrada = Producto::where('IdProducto', $id)->where('id_empresa_negocio', $this->sucursal())->where('promocion', Producto::OPCION)->firstOrFail();
        if ($request->has('nombre')) {
            $d = $request->validate(['nombre' => 'required|string|max:150'], [], ['nombre' => 'nombre de la entrada']);
            $entrada->update(['pronom' => mb_strtoupper(trim($d['nombre']))]);

            return back()->with('success', 'Nombre actualizado.');
        }
        $activa = $entrada->proest !== 'Activo';
        $entrada->update(['proest' => $activa ? 'Activo' : 'Inactivo']);

        return back()->with('success', $activa ? "{$entrada->pronom} vuelve a salir en las comandas." : "{$entrada->pronom} ya no sale en las comandas (hoy no hay).");
    }

    /** Qué platos llevan entrada (marcados en la lista) */
    public function platos(Request $request): RedirectResponse
    {
        $suc = $this->sucursal();
        $ids = array_map('intval', (array) $request->input('platos', []));
        $base = Producto::where('id_empresa_negocio', $suc)->where('promocion', 2);
        (clone $base)->whereIn('IdProducto', $ids)->update(['lleva_entrada' => 1]);
        (clone $base)->whereNotIn('IdProducto', $ids ?: [0])->update(['lleva_entrada' => 0]);

        return back()->with('success', count($ids).' plato(s) llevan entrada.');
    }
}
