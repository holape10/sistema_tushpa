<?php
namespace App\Http\Controllers;

use App\Models\{Almacen, Producto};
use App\Support\Kardex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Transferencia de productos entre almacenes de la sucursal.
 * Cabecera en movimientos_cabecera (mov_tip 'T', part_alm → des_alm) y en el kardex:
 * salida 11 en el almacén de origen + ingreso 21 en el de destino.
 */
class TransferenciaController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja registran transferencias.');
    }

    private function almacenes()
    {
        return Almacen::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->orderByDesc('predeterminado')->orderBy('descripcion')->get();
    }

    private function cabecera(int $id)
    {
        $t = DB::table('movimientos_cabecera as mc')
            ->leftJoin('almacenes as o', 'o.id_almacen', '=', 'mc.part_alm')
            ->leftJoin('almacenes as d', 'd.id_almacen', '=', 'mc.des_alm')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'mc.usu_ent')
            ->where('mc.mov_cab_id', $id)->where('mc.mov_tip', 'T')
            ->where('mc.id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->select('mc.*', 'o.descripcion as origen', 'd.descripcion as destino', 'u.apeusu')->first();
        abort_unless($t, 404);
        return $t;
    }

    /** Líneas originales de la transferencia (las salidas del almacén de origen) */
    private function items(object $t)
    {
        return DB::table('movimientos_productos as mp')
            ->join('productos as p', 'p.IdProducto', '=', 'mp.IdProducto')
            ->where('mp.mov_cab_id', $t->mov_cab_id)->where('mp.mov_tip', 'E')->where('mp.id_almacen', $t->part_alm)
            ->orderBy('mp.mov_pro_id')
            ->select('mp.IdProducto', 'mp.cantidad', 'mp.costo', 'mp.mov_lote', 'mp.mov_vencimiento', 'p.procod', 'p.pronom', 'p.umecod')->get();
    }

    public function index(Request $request)
    {
        $transferencias = DB::table('movimientos_cabecera as mc')
            ->leftJoin('almacenes as o', 'o.id_almacen', '=', 'mc.part_alm')
            ->leftJoin('almacenes as d', 'd.id_almacen', '=', 'mc.des_alm')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'mc.usu_ent')
            ->where('mc.id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->where('mc.mov_tip', 'T')
            ->when($request->get('almacen'), fn($q, $a) => $q->where(fn($w) => $w->where('mc.part_alm', $a)->orWhere('mc.des_alm', $a)))
            ->orderByDesc('mc.mov_cab_id')
            ->select('mc.*', 'o.descripcion as origen', 'd.descripcion as destino', 'u.apeusu',
                DB::raw("(SELECT COUNT(*) FROM movimientos_productos mp WHERE mp.mov_cab_id = mc.mov_cab_id AND mp.mov_tip = 'E' AND mp.id_almacen = mc.part_alm) as items"))
            ->paginate(20)->withQueryString();

        $almacenes = $this->almacenes();
        return view('empresas.transferencias.index', compact('transferencias', 'almacenes'));
    }

    public function create()
    {
        $this->autorizar();
        $almacenes = $this->almacenes();
        if ($almacenes->count() < 2) {
            return redirect()->route('almacenes.index')
                ->withErrors(['almacen' => 'Para transferir necesitas al menos dos almacenes. Registra otro almacén primero.']);
        }

        $productos = Producto::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->whereIn('promocion', [0, 4])
            ->orderBy('pronom')->get(['IdProducto', 'procod', 'pronom', 'umecod', 'costo']);

        // Stock de cada producto en cada almacén: { id_almacen: { IdProducto: stock } }
        $stocks = DB::table('producto_stock')->whereIn('id_almacen', $almacenes->pluck('id_almacen'))
            ->get(['id_almacen', 'IdProducto', 'stock'])
            ->groupBy('id_almacen')->map(fn($g) => $g->pluck('stock', 'IdProducto')->map(fn($s) => (float) $s));

        return view('empresas.transferencias.create', compact('almacenes', 'productos', 'stocks'));
    }

    public function store(Request $request)
    {
        $this->autorizar();
        $user = Auth::user();
        $request->validate([
            'part_alm'           => 'required|integer',
            'des_alm'            => 'required|integer|different:part_alm',
            'fecha'              => 'required|date|before_or_equal:today',
            'observaciones'      => 'nullable|string|max:255',
            'items'              => 'required|array|min:1',
            'items.*.IdProducto' => 'required|integer|distinct',
            'items.*.cantidad'   => 'required|numeric|min:0.01',
        ], ['des_alm.different' => 'El almacén de destino debe ser distinto al de origen.', 'items.*.IdProducto.distinct' => 'Hay productos repetidos.'],
            ['part_alm' => 'Almacén de origen', 'des_alm' => 'Almacén de destino', 'items' => 'Productos', 'items.*.cantidad' => 'Cantidad']);

        $almacenes = Almacen::where('id_empresa_negocio', $user->id_empresa_negocio)
            ->whereIn('id_almacen', [$request->part_alm, $request->des_alm])->get()->keyBy('id_almacen');
        if ($almacenes->count() !== 2) {
            return back()->withInput()->withErrors(['part_alm' => 'Almacén no válido.']);
        }
        $origen = $almacenes[$request->part_alm];
        $destino = $almacenes[$request->des_alm];

        $ids = collect($request->items)->pluck('IdProducto');
        $productos = Producto::where('id_empresa_negocio', $user->id_empresa_negocio)->whereIn('promocion', [0, 4])
            ->whereIn('IdProducto', $ids)->get()->keyBy('IdProducto');
        if ($productos->count() !== $ids->count()) {
            return back()->withInput()->withErrors(['items' => 'Hay productos que no existen o no manejan stock.']);
        }

        try {
            $id = DB::transaction(function () use ($request, $user, $origen, $destino, $productos) {
                $cabId = DB::table('movimientos_cabecera')->insertGetId([
                    'mov_tip' => 'T', 'cod_tip_ope' => '11', 'fecha' => $request->fecha, 'observaciones' => $request->observaciones,
                    'estado' => 'REGISTRADO', 'usu_ent' => $user->IdUsuario, 'usu_rec' => $user->IdUsuario, 'fecha_recep' => $request->fecha,
                    'part_alm' => $origen->id_almacen, 'des_alm' => $destino->id_almacen,
                    'part_suc' => $origen->id_empresa_negocio, 'des_suc' => $destino->id_empresa_negocio,
                    'id_empresa_negocio' => $user->id_empresa_negocio,
                ]);

                foreach ($request->items as $it) {
                    $prod = $productos[$it['IdProducto']];
                    $cantidad = (float) $it['cantidad'];
                    $this->moverStock($prod, $origen, $destino, $cantidad, (float) $prod->costo, $cabId, $request->fecha, 'TRANSFERENCIA TRF-' . $cabId);
                }
                return $cabId;
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()->route('transferencias.show', $id)
            ->with('success', "Transferencia registrada: {$origen->descripcion} → {$destino->descripcion}.");
    }

    /** Sale del origen (11) y entra al destino (21). No deja el origen con stock negativo. */
    private function moverStock($prod, Almacen $origen, Almacen $destino, float $cantidad, float $costo, int $cabId, string $fecha, string $glosa, ?string $lote = null): void
    {
        $disponible = (float) (DB::table('producto_stock')->where('IdProducto', $prod->IdProducto)
            ->where('id_almacen', $origen->id_almacen)->lockForUpdate()->value('stock') ?? 0);
        if ($cantidad > $disponible + 0.00001) {
            throw new \RuntimeException("Stock insuficiente de {$prod->pronom} en {$origen->descripcion}: disponible "
                . rtrim(rtrim(number_format($disponible, 3, '.', ''), '0'), '.') . '.');
        }

        $doc = ['mov_cab_id' => $cabId, 'fecha_mov' => $fecha, 'costo' => $costo, 'numero' => 'TRF-' . $cabId, 'descripcion' => $glosa,
            'id_almacen_origen' => $origen->id_almacen, 'id_almacen_destino' => $destino->id_almacen];

        // Sale por FEFO del origen (o del lote indicado, al anular) y cada lote entra igual (mismo lote y vencimiento) al destino
        $partes = Kardex::registrar($prod->IdProducto, $origen->id_almacen, $cantidad, 'E',
            $doc + ['cod_tip_ope' => '11', 'id_empresa_negocio' => $origen->id_empresa_negocio, 'lote' => $lote]);
        foreach ($partes as $p) {
            Kardex::registrar($prod->IdProducto, $destino->id_almacen, $p['cantidad'], 'I',
                ['lote' => $p['lote'], 'vencimiento' => $p['vencimiento']] + $doc
                + ['cod_tip_ope' => '21', 'id_empresa_negocio' => $destino->id_empresa_negocio]);
        }
    }

    public function show(int $id)
    {
        $transferencia = $this->cabecera($id);
        $items = $this->items($transferencia);
        return view('empresas.transferencias.show', compact('transferencia', 'items'));
    }

    /** Anula devolviendo los productos del destino al origen (queda la huella en el kardex) */
    public function anular(Request $request, int $id)
    {
        $this->autorizar();
        $t = $this->cabecera($id);
        if ($t->estado === 'ANULADO') {
            return back()->withErrors(['estado' => 'La transferencia ya está anulada.']);
        }

        $almacenes = Almacen::whereIn('id_almacen', [$t->part_alm, $t->des_alm])->get()->keyBy('id_almacen');
        $items = $this->items($t);
        $productos = Producto::whereIn('IdProducto', $items->pluck('IdProducto'))->get()->keyBy('IdProducto');

        try {
            DB::transaction(function () use ($t, $almacenes, $items, $productos) {
                // Bloquea la cabecera para que dos usuarios no la anulen a la vez
                $estado = DB::table('movimientos_cabecera')->where('mov_cab_id', $t->mov_cab_id)->lockForUpdate()->value('estado');
                if ($estado === 'ANULADO') {
                    throw new \RuntimeException('La transferencia ya está anulada.');
                }
                foreach ($items as $it) {
                    $this->moverStock($productos[$it->IdProducto], $almacenes[$t->des_alm], $almacenes[$t->part_alm],
                        (float) $it->cantidad, (float) $it->costo, $t->mov_cab_id, now()->toDateString(), 'ANULACIÓN TRF-' . $t->mov_cab_id, $it->mov_lote);
                }
                DB::table('movimientos_cabecera')->where('mov_cab_id', $t->mov_cab_id)->update(['estado' => 'ANULADO']);
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['estado' => 'No se puede anular: ' . $e->getMessage()]);
        }

        return back()->with('success', 'Transferencia anulada: los productos regresaron al almacén de origen.');
    }
}
