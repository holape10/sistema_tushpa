<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use App\Support\Precios;
use App\Support\Recetas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Recetas y food cost: lista de platos con su costo y % de food cost, editor de la receta de cada plato
 * (buscar o crear el insumo, cantidad en g/kg/ml/L/unid.) y el costo de cada insumo.
 */
class RecetaController extends Controller
{
    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador ve los costos y arma las recetas.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index(Request $request): View
    {
        $platos = Recetas::platos($this->sucursal());
        $conReceta = $platos->where('tiene_receta', true);

        return view('empresas.recetas.index', [
            'platos' => $platos,
            'resumen' => [
                'total' => $platos->count(),
                'con_receta' => $conReceta->count(),
                'promedio' => $conReceta->whereNotNull('food_cost')->avg('food_cost'),
                'altos' => $conReceta->where('estado.nivel', 'alto')->count(),
            ],
            'filtro' => in_array($request->get('ver'), ['sin', 'alto', 'con'], true) ? $request->get('ver') : '',
        ]);
    }

    public function editar(int $id): View
    {
        $plato = $this->plato($id);
        $precio = Precios::vigentes([$plato])[$plato->IdProducto];

        return view('empresas.recetas.editar', [
            'plato' => $plato,
            'precio' => $precio,
            'lineas' => Recetas::de($plato->IdProducto)->map(fn ($r) => [
                'insumo' => $r->IdInsumo, 'nombre' => $r->nombre, 'cantidad' => $r->cantidad, 'umecod' => $r->umecod,
                'ume_insumo' => $r->ume_insumo, 'costo' => $r->costo_unitario, 'unidades' => $this->unidades($r->ume_insumo),
            ])->values(),
            'otros' => DB::table('producto_receta as r')->join('productos as p', 'p.IdProducto', '=', 'r.IdProducto')
                ->where('r.id_empresa_negocio', $plato->id_empresa_negocio)->where('r.IdProducto', '!=', $plato->IdProducto)
                ->distinct()->orderBy('p.pronom')->get(['p.IdProducto', 'p.pronom']),
            'siguiente' => Recetas::platos($plato->id_empresa_negocio)->first(fn ($p) => ! $p->tiene_receta && $p->IdProducto !== $plato->IdProducto),
        ]);
    }

    /** Insumos (y productos) que pueden ir en una receta */
    public function insumos(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q'));
        $filas = Producto::where('id_empresa_negocio', $this->sucursal())->where('proest', 'Activo')->whereIn('promocion', [0, 4])
            ->when($q !== '', fn ($w) => $w->where('pronom', 'like', '%'.$q.'%'))
            ->orderByDesc('promocion')->orderBy('pronom')->limit(15)->get(['IdProducto', 'pronom', 'umecod', 'costo']);

        return response()->json($filas->map(fn ($p) => $this->insumo($p)));
    }

    /** Receta de otro plato, para copiarla y ajustarla */
    public function receta(int $id): JsonResponse
    {
        $plato = $this->plato($id);

        return response()->json(Recetas::de($plato->IdProducto)->map(fn ($r) => [
            'insumo' => $r->IdInsumo, 'nombre' => $r->nombre, 'cantidad' => $r->cantidad, 'umecod' => $r->umecod,
            'ume_insumo' => $r->ume_insumo, 'costo' => $r->costo_unitario, 'unidades' => $this->unidades($r->ume_insumo),
        ])->values());
    }

    /** Crea un insumo nuevo desde la receta (sin salir de la pantalla) */
    public function crearInsumo(Request $request): JsonResponse
    {
        $suc = $this->sucursal();
        $d = $request->validate([
            'nombre' => 'required|string|max:150',
            'umecod' => 'required|in:KGM,LTR,NIU,GRM,MLT',
            'costo' => 'nullable|numeric|min:0|max:999999',
        ], [], ['nombre' => 'nombre del insumo', 'umecod' => 'unidad', 'costo' => 'costo']);
        $nombre = mb_strtoupper(trim($d['nombre']));
        if (Producto::where('id_empresa_negocio', $suc)->where('pronom', $nombre)->exists()) {
            return response()->json(['ok' => false, 'mensaje' => "Ya existe \"{$nombre}\": búscalo en la lista."], 422);
        }

        // Los insumos van en su categoría, oculta en las pantallas de venta
        $categoria = Categoria::firstOrCreate(['cat_nom' => 'INSUMOS', 'id_empresa_negocio' => $suc],
            ['IdEmpresa' => Auth::user()->IdEmpresa, 'visible' => 0]);
        $insumo = Producto::create([
            'procod' => mb_substr('I'.time().random_int(10, 99), 0, 20), 'pronom' => $nombre, 'promocion' => 4,
            'umecod' => $d['umecod'], 'costo' => $d['costo'] ?? 0, 'propun' => 0, 'cat_id' => $categoria->cat_id,
            'stock_min' => 0, 'proest' => 'Activo', 'IdEmpresa' => Auth::user()->IdEmpresa, 'id_empresa_negocio' => $suc,
        ]);

        return response()->json(['ok' => true, 'insumo' => $this->insumo($insumo)]);
    }

    public function guardar(Request $request, int $id): JsonResponse
    {
        $plato = $this->plato($id);
        $d = $request->validate([
            'lineas' => 'array|max:60',
            'lineas.*.insumo' => 'required|integer',
            'lineas.*.cantidad' => 'required|numeric|gt:0|max:99999',
            'lineas.*.umecod' => 'required|string|max:3',
            'lineas.*.costo' => 'nullable|numeric|min:0|max:999999',
        ], [], ['lineas.*.cantidad' => 'cantidad', 'lineas.*.costo' => 'costo del insumo']);
        $lineas = collect($d['lineas'] ?? []);

        $insumos = Producto::where('id_empresa_negocio', $plato->id_empresa_negocio)->whereIn('promocion', [0, 4])
            ->whereIn('IdProducto', $lineas->pluck('insumo'))->get()->keyBy('IdProducto');
        foreach ($lineas as $l) {
            $insumo = $insumos[$l['insumo']] ?? null;
            if (! $insumo) {
                return response()->json(['ok' => false, 'mensaje' => 'Un ingrediente ya no existe. Vuelve a buscarlo.'], 422);
            }
            if (! array_key_exists($l['umecod'], Recetas::unidadesPara($insumo->umecod))) {
                return response()->json(['ok' => false, 'mensaje' => "La unidad de {$insumo->pronom} no es válida."], 422);
            }
        }
        if ($lineas->pluck('insumo')->duplicates()->isNotEmpty()) {
            return response()->json(['ok' => false, 'mensaje' => 'Hay un ingrediente repetido: déjalo en una sola fila.'], 422);
        }

        // El costo escrito en la receta actualiza el costo del insumo (vale para todos los platos que lo usan)
        foreach ($lineas as $l) {
            if (isset($l['costo']) && round((float) $l['costo'], 2) !== round((float) $insumos[$l['insumo']]->costo, 2)) {
                $insumos[$l['insumo']]->update(['costo' => round((float) $l['costo'], 2)]);
            }
        }
        $costo = Recetas::guardar($plato->IdProducto, $plato->id_empresa_negocio, $lineas->map(fn ($l) => [
            'insumo' => (int) $l['insumo'], 'cantidad' => (float) $l['cantidad'], 'umecod' => $l['umecod'],
        ])->all());

        return response()->json(['ok' => true, 'costo' => $costo, 'mensaje' => $lineas->isEmpty()
            ? 'Receta borrada: este plato ya no descuenta insumos.'
            : 'Receta guardada. Desde ahora, cada '.mb_strtolower($plato->pronom).' vendido descuenta sus insumos del almacén.']);
    }

    private function plato(int $id): Producto
    {
        return Producto::where('IdProducto', $id)->where('id_empresa_negocio', $this->sucursal())->where('promocion', 2)->firstOrFail();
    }

    /**
     * @return array<int, array{ume: string, nombre: string, factor: float}>
     */
    private function unidades(string $umeInsumo): array
    {
        return collect(Recetas::unidadesPara($umeInsumo))
            ->map(fn ($factor, $ume) => ['ume' => $ume, 'nombre' => Recetas::nombreUnidad($ume), 'factor' => $factor])->values()->all();
    }

    /**
     * @return array{insumo: int, nombre: string, ume_insumo: string, costo: float, unidades: array<int, array{ume: string, nombre: string, factor: float}>, umecod: string, cantidad: string}
     */
    private function insumo(Producto $p): array
    {
        $unidades = $this->unidades($p->umecod ?: 'NIU');

        return [
            'insumo' => (int) $p->IdProducto, 'nombre' => $p->pronom, 'ume_insumo' => $p->umecod ?: 'NIU', 'costo' => (float) $p->costo,
            'unidades' => $unidades,
            // Lo más común: el kilo se escribe en gramos y el litro en mililitros
            'umecod' => $unidades[1]['ume'] ?? $unidades[0]['ume'], 'cantidad' => '',
        ];
    }
}
