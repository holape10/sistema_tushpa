<?php
namespace App\Http\Controllers;

use App\Models\{Almacen, Producto};
use App\Support\Kardex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class KardexController extends Controller
{
    // Operaciones SUNAT (tabla 12) permitidas en ingresos y salidas manuales
    private const OPERACIONES = [
        'I' => ['02', '05', '16', '19', '21', '24', '28', '99'],
        'E' => ['10', '11', '12', '13', '14', '15', '25', '28', '99'],
    ];

    // Solo productos simples (0) e insumos (4) manejan stock; preparados y combos no
    private function productosConStock()
    {
        return Producto::where('productos.id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->whereIn('productos.promocion', [0, 4])
            ->orderBy('productos.pronom');
    }

    private function almacenes()
    {
        return Almacen::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->orderByDesc('predeterminado')->get();
    }

    public function index(Request $request)
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        $productos = $this->productosConStock()->get(['IdProducto', 'pronom', 'procod', 'umecod']);
        $almacenes = $this->almacenes();

        $idProducto = $request->get('producto');
        $idAlmacen = $request->get('almacen', $almacenes->first()?->id_almacen);
        $desde = $request->get('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());

        $producto = null;
        $movimientos = collect();
        $saldoInicial = 0;
        $stockActual = 0;

        if ($idProducto) {
            $producto = Producto::where('IdProducto', $idProducto)->where('id_empresa_negocio', $sucursal)->firstOrFail();

            $base = DB::table('movimientos_productos')
                ->where('movimientos_productos.IdProducto', $producto->IdProducto)
                ->where('movimientos_productos.id_almacen', $idAlmacen)
                ->where('movimientos_productos.id_empresa_negocio', $sucursal);

            // Saldo con el que arranca el rango = stock final del último movimiento anterior a "desde"
            $saldoInicial = (float) ((clone $base)->where('fecha_mov', '<', $desde)->orderByDesc('mov_pro_id')->value('stock') ?? 0);

            $movimientos = (clone $base)
                ->leftJoin('tipo_operacion_sunat as t', 't.cod_tip_ope', '=', 'movimientos_productos.cod_tip_ope')
                ->leftJoin('productos as rel', 'rel.IdProducto', '=', 'movimientos_productos.IdProducto_rel')
                ->leftJoin('users as u', 'u.IdUsuario', '=', 'movimientos_productos.IdUsuario')
                ->whereBetween('movimientos_productos.fecha_mov', [$desde, $hasta])
                ->orderBy('movimientos_productos.mov_pro_id')
                ->select('movimientos_productos.*', 't.des_tip_ope', 'rel.pronom as combo_nom', 'u.apeusu')
                ->get();

            $stockActual = (float) (DB::table('producto_stock')
                ->where('IdProducto', $producto->IdProducto)->where('id_almacen', $idAlmacen)->value('stock') ?? 0);
        }

        return view('empresas.kardex.index', compact(
            'productos', 'almacenes', 'producto', 'idAlmacen', 'desde', 'hasta', 'movimientos', 'saldoInicial', 'stockActual'
        ));
    }

    public function stock(Request $request)
    {
        $almacenes = $this->almacenes();
        $idAlmacen = $request->get('almacen', $almacenes->first()?->id_almacen);
        $q = trim((string) $request->get('q'));

        $productos = $this->productosConStock()
            ->leftJoin('producto_stock as ps', function ($j) use ($idAlmacen) {
                $j->on('ps.IdProducto', '=', 'productos.IdProducto')->where('ps.id_almacen', $idAlmacen);
            })
            ->when($q, fn($qq) => $qq->where('productos.pronom', 'like', "%{$q}%"))
            ->select('productos.IdProducto', 'productos.procod', 'productos.pronom', 'productos.umecod',
                'productos.promocion', 'productos.stock_min', 'productos.costo', DB::raw('COALESCE(ps.stock, 0) as stock'))
            ->paginate(30)->withQueryString();

        return view('empresas.kardex.stock', compact('productos', 'almacenes', 'idAlmacen', 'q'));
    }

    public function movimiento(Request $request)
    {
        $tipo = $request->get('tipo') === 'E' ? 'E' : 'I';
        $productos = $this->productosConStock()->get(['IdProducto', 'pronom', 'procod', 'costo', 'control_lote']);
        $almacenes = $this->almacenes();
        $operaciones = DB::table('tipo_operacion_sunat')->whereIn('cod_tip_ope', self::OPERACIONES[$tipo])->get();

        // Últimos movimientos manuales registrados
        $recientes = DB::table('movimientos_cabecera as mc')
            ->leftJoin('tipo_operacion_sunat as t', 't.cod_tip_ope', '=', 'mc.cod_tip_ope')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'mc.usu_ent')
            ->where('mc.id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->where('mc.mov_tip', $tipo)
            ->orderByDesc('mc.mov_cab_id')->limit(10)
            ->select('mc.*', 't.des_tip_ope', 'u.apeusu',
                DB::raw('(SELECT COUNT(*) FROM movimientos_productos mp WHERE mp.mov_cab_id = mc.mov_cab_id) as items'))
            ->get();

        return view('empresas.kardex.movimiento', compact('tipo', 'productos', 'almacenes', 'operaciones', 'recientes'));
    }

    public function guardarMovimiento(Request $request)
    {
        $user = Auth::user();
        $tipo = $request->input('mov_tip') === 'E' ? 'E' : 'I';

        $request->validate([
            'id_almacen'          => 'required|integer',
            'cod_tip_ope'         => 'required|in:' . implode(',', self::OPERACIONES[$tipo]),
            'fecha'               => 'required|date|before_or_equal:today',
            'observaciones'       => 'nullable|string|max:255',
            'items'               => 'required|array|min:1',
            'items.*.IdProducto'  => 'required|integer',
            'items.*.cantidad'    => 'required|numeric|min:0.01',
            'items.*.costo'       => 'nullable|numeric|min:0',
            'items.*.lote'        => 'nullable|string|max:50',
            'items.*.vencimiento' => 'nullable|date',
        ], [], [
            'cod_tip_ope' => 'Tipo de operación', 'items' => 'Productos',
            'items.*.cantidad' => 'Cantidad', 'items.*.costo' => 'Costo',
        ]);

        $almacen = Almacen::where('id_almacen', $request->id_almacen)->where('id_empresa_negocio', $user->id_empresa_negocio)->first();
        if (!$almacen) {
            return back()->withInput()->withErrors(['id_almacen' => 'Almacén no válido.']);
        }

        $ids = collect($request->items)->pluck('IdProducto')->unique();
        $productos = $this->productosConStock()->whereIn('IdProducto', $ids)->get()->keyBy('IdProducto');
        if ($productos->count() !== $ids->count()) {
            return back()->withInput()->withErrors(['items' => 'Hay productos que no existen o no manejan stock.']);
        }

        // Farmacia: en ingresos, los productos con control de lote necesitan lote y vencimiento
        if ($tipo === 'I') {
            foreach ($request->items as $it) {
                $p = $productos[$it['IdProducto']];
                if ($p->control_lote && (empty(trim($it['lote'] ?? '')) || empty($it['vencimiento']))) {
                    return back()->withInput()->withErrors(['items' => "Ingresa el lote y la fecha de vencimiento de {$p->pronom}."]);
                }
            }
        }

        DB::transaction(function () use ($request, $user, $tipo, $almacen, $productos) {
            $cabId = DB::table('movimientos_cabecera')->insertGetId([
                'mov_tip' => $tipo, 'cod_tip_ope' => $request->cod_tip_ope,
                'fecha' => $request->fecha, 'observaciones' => $request->observaciones,
                'estado' => 'REGISTRADO', 'usu_ent' => $user->IdUsuario,
                'part_alm' => $tipo === 'E' ? $almacen->id_almacen : null,
                'des_alm' => $tipo === 'I' ? $almacen->id_almacen : null,
                'id_empresa_negocio' => $user->id_empresa_negocio,
            ]);

            foreach ($request->items as $it) {
                $prod = $productos[$it['IdProducto']];
                $costo = isset($it['costo']) && $it['costo'] !== '' ? (float) $it['costo'] : (float) $prod->costo;

                Kardex::registrar($prod->IdProducto, $almacen->id_almacen, (float) $it['cantidad'], $tipo, [
                    'cod_tip_ope' => $request->cod_tip_ope, 'mov_cab_id' => $cabId, 'fecha_mov' => $request->fecha,
                    'costo' => $costo, 'descripcion' => $request->observaciones,
                    'numero' => (string) $cabId,
                    'lote' => $tipo === 'I' ? mb_strtoupper(trim($it['lote'] ?? '')) : null,
                    'vencimiento' => $tipo === 'I' ? ($it['vencimiento'] ?? null) : null,
                ]);

                // En compras se actualiza el costo del producto con el último costo de ingreso
                if ($tipo === 'I' && $request->cod_tip_ope === '02' && $costo > 0) {
                    Producto::where('IdProducto', $prod->IdProducto)->update(['costo' => $costo]);
                }
            }
        });

        return redirect()->route('kardex.movimiento', ['tipo' => $tipo])
            ->with('success', ($tipo === 'I' ? 'Ingreso' : 'Salida') . ' registrado y kardex actualizado.');
    }
}
