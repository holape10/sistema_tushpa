<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Producto;
use App\Support\Bitacora;
use App\Support\Kardex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            ->when($request->get('almacen'), fn ($q, $a) => $q->where(fn ($w) => $w->where('mc.part_alm', $a)->orWhere('mc.des_alm', $a)))
            ->orderByDesc('mc.mov_cab_id')
            ->select('mc.*', 'o.descripcion as origen', 'd.descripcion as destino', 'u.apeusu',
                DB::raw("(SELECT COUNT(*) FROM movimientos_productos mp WHERE mp.mov_cab_id = mc.mov_cab_id AND mp.mov_tip = 'E' AND mp.id_almacen = mc.part_alm) as items"))
            ->paginate(20)->withQueryString();

        $almacenes = $this->almacenes();
        $enTransito = DB::table('movimientos_cabecera')->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->where('mov_tip', 'T')
            ->where('estado', self::EN_TRANSITO)->count();

        return view('empresas.transferencias.index', compact('transferencias', 'almacenes', 'enTransito'));
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
            ->groupBy('id_almacen')->map(fn ($g) => $g->pluck('stock', 'IdProducto')->map(fn ($s) => (float) $s));

        return view('empresas.transferencias.create', compact('almacenes', 'productos', 'stocks'));
    }

    public function store(Request $request)
    {
        $this->autorizar();
        $user = Auth::user();
        $request->validate([
            'part_alm' => 'required|integer',
            'des_alm' => 'required|integer|different:part_alm',
            'fecha' => 'required|date|before_or_equal:today',
            'observaciones' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.IdProducto' => 'required|integer|distinct',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'modo' => 'nullable|in:inmediata,transito',
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

        // En tránsito: sale ahora del origen y entra al destino recién cuando la reciben
        $enTransito = $request->input('modo') === 'transito';
        try {
            $id = DB::transaction(function () use ($request, $user, $origen, $destino, $productos, $enTransito) {
                $cabId = DB::table('movimientos_cabecera')->insertGetId([
                    'mov_tip' => 'T', 'cod_tip_ope' => '11', 'fecha' => $request->fecha, 'observaciones' => $request->observaciones,
                    'estado' => $enTransito ? self::EN_TRANSITO : 'REGISTRADO', 'usu_ent' => $user->IdUsuario,
                    'usu_rec' => $enTransito ? null : $user->IdUsuario, 'fecha_recep' => $enTransito ? null : $request->fecha,
                    'part_alm' => $origen->id_almacen, 'des_alm' => $destino->id_almacen,
                    'part_suc' => $origen->id_empresa_negocio, 'des_suc' => $destino->id_empresa_negocio,
                    'id_empresa_negocio' => $user->id_empresa_negocio,
                ]);

                foreach ($request->items as $it) {
                    $prod = $productos[$it['IdProducto']];
                    $cantidad = (float) $it['cantidad'];
                    $partes = $this->sacar($prod, $origen, $destino, $cantidad, (float) $prod->costo, $cabId, $request->fecha, 'TRANSFERENCIA TRF-'.$cabId);
                    if (! $enTransito) {
                        $this->meter($prod->IdProducto, $destino, $origen, $partes, (float) $prod->costo, $cabId, $request->fecha, 'TRANSFERENCIA TRF-'.$cabId);
                    }
                }

                return $cabId;
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        Bitacora::registrar('almacen.transferencia', ($enTransito ? 'Envió en tránsito' : 'Transfirió').' TRF-'.$id." de {$origen->descripcion} a {$destino->descripcion} ("
            .count($request->items).' producto(s))', null, ['referencia' => ['transferencia', $id]]);

        return redirect()->route('transferencias.show', $id)
            ->with('success', $enTransito ? "🚚 Transferencia en tránsito: ya salió de {$origen->descripcion}; entrará a {$destino->descripcion} cuando la reciban."
                : "Transferencia registrada: {$origen->descripcion} → {$destino->descripcion}.");
    }

    /** Sale del origen (11) y entra al destino (21). No deja el origen con stock negativo. */
    /** Estado de la transferencia que salió del origen y aún no llega al destino */
    public const EN_TRANSITO = 'EN TRANSITO';

    /** Sale del origen y entra al destino en el mismo momento */
    private function moverStock($prod, Almacen $origen, Almacen $destino, float $cantidad, float $costo, int $cabId, string $fecha, string $glosa, ?string $lote = null): void
    {
        $this->meter($prod->IdProducto, $destino, $origen, $this->sacar($prod, $origen, $destino, $cantidad, $costo, $cabId, $fecha, $glosa, $lote), $costo, $cabId, $fecha, $glosa);
    }

    /**
     * Salida del almacén de origen, por FEFO (o del lote indicado, al anular). Devuelve los lotes que salieron.
     *
     * @return array<int, array{cantidad: float, lote: ?string, vencimiento: ?string}>
     */
    private function sacar($prod, Almacen $origen, Almacen $destino, float $cantidad, float $costo, int $cabId, string $fecha, string $glosa, ?string $lote = null): array
    {
        $disponible = (float) (DB::table('producto_stock')->where('IdProducto', $prod->IdProducto)
            ->where('id_almacen', $origen->id_almacen)->lockForUpdate()->value('stock') ?? 0);
        if ($cantidad > $disponible + 0.00001) {
            throw new \RuntimeException("Stock insuficiente de {$prod->pronom} en {$origen->descripcion}: disponible "
                .rtrim(rtrim(number_format($disponible, 3, '.', ''), '0'), '.').'.');
        }

        $doc = ['mov_cab_id' => $cabId, 'fecha_mov' => $fecha, 'costo' => $costo, 'numero' => 'TRF-'.$cabId, 'descripcion' => $glosa,
            'id_almacen_origen' => $origen->id_almacen, 'id_almacen_destino' => $destino->id_almacen];

        return Kardex::registrar($prod->IdProducto, $origen->id_almacen, $cantidad, 'E',
            $doc + ['cod_tip_ope' => '11', 'id_empresa_negocio' => $origen->id_empresa_negocio, 'lote' => $lote]);
    }

    /** Entrada al almacén de destino: cada lote entra igual (mismo lote y vencimiento) que como salió */
    private function meter(int $productoId, Almacen $destino, Almacen $origen, array $partes, float $costo, int $cabId, string $fecha, string $glosa): void
    {
        $doc = ['mov_cab_id' => $cabId, 'fecha_mov' => $fecha, 'costo' => $costo, 'numero' => 'TRF-'.$cabId, 'descripcion' => $glosa,
            'id_almacen_origen' => $origen->id_almacen, 'id_almacen_destino' => $destino->id_almacen];
        foreach ($partes as $p) {
            Kardex::registrar($productoId, $destino->id_almacen, (float) $p['cantidad'], 'I',
                ['lote' => $p['lote'] ?? null, 'vencimiento' => $p['vencimiento'] ?? null] + $doc
                + ['cod_tip_ope' => '21', 'id_empresa_negocio' => $destino->id_empresa_negocio]);
        }
    }

    /** Llegó lo que estaba en tránsito: entra al destino con los mismos lotes que salieron del origen */
    public function recibir(Request $request, int $id)
    {
        $this->autorizar();
        $d = $request->validate(['fecha' => 'required|date|before_or_equal:today'], [], ['fecha' => 'fecha de recepción']);
        $t = $this->cabecera($id);
        if ($t->estado !== self::EN_TRANSITO) {
            return back()->withErrors(['estado' => 'Esta transferencia no está en tránsito.']);
        }
        if ($d['fecha'] < $t->fecha) {
            return back()->withErrors(['estado' => 'La fecha de recepción no puede ser anterior a la de envío.']);
        }
        $almacenes = Almacen::whereIn('id_almacen', [$t->part_alm, $t->des_alm])->get()->keyBy('id_almacen');

        try {
            DB::transaction(function () use ($t, $almacenes, $d) {
                // Bloquea la cabecera para que dos usuarios no la reciban a la vez
                if (DB::table('movimientos_cabecera')->where('mov_cab_id', $t->mov_cab_id)->lockForUpdate()->value('estado') !== self::EN_TRANSITO) {
                    throw new \RuntimeException('La transferencia ya fue recibida o anulada.');
                }
                foreach ($this->items($t) as $it) {
                    $this->meter((int) $it->IdProducto, $almacenes[$t->des_alm], $almacenes[$t->part_alm],
                        [['cantidad' => (float) $it->cantidad, 'lote' => $it->mov_lote, 'vencimiento' => $it->mov_vencimiento]], (float) $it->costo,
                        $t->mov_cab_id, $d['fecha'], 'RECEPCIÓN TRF-'.$t->mov_cab_id);
                }
                DB::table('movimientos_cabecera')->where('mov_cab_id', $t->mov_cab_id)->update([
                    'estado' => 'REGISTRADO', 'fecha_recep' => $d['fecha'], 'usu_rec' => Auth::id(),
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['estado' => $e->getMessage()]);
        }
        Bitacora::registrar('almacen.transferencia_recibir', "Recibió en {$t->destino} la transferencia TRF-{$t->mov_cab_id} (estaba en tránsito)", null,
            ['referencia' => ['transferencia', $t->mov_cab_id]]);

        return back()->with('success', "Transferencia recibida: los productos ya están en {$t->destino}.");
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
                    if ($estado === self::EN_TRANSITO) {
                        // Aún no llegaba: lo que salió vuelve al origen tal cual (mismo lote)
                        $this->meter((int) $it->IdProducto, $almacenes[$t->part_alm], $almacenes[$t->des_alm],
                            [['cantidad' => (float) $it->cantidad, 'lote' => $it->mov_lote, 'vencimiento' => $it->mov_vencimiento]], (float) $it->costo,
                            $t->mov_cab_id, now()->toDateString(), 'ANULACIÓN TRF-'.$t->mov_cab_id);
                    } else {
                        $this->moverStock($productos[$it->IdProducto], $almacenes[$t->des_alm], $almacenes[$t->part_alm],
                            (float) $it->cantidad, (float) $it->costo, $t->mov_cab_id, now()->toDateString(), 'ANULACIÓN TRF-'.$t->mov_cab_id, $it->mov_lote);
                    }
                }
                DB::table('movimientos_cabecera')->where('mov_cab_id', $t->mov_cab_id)->update(['estado' => 'ANULADO']);
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['estado' => 'No se puede anular: '.$e->getMessage()]);
        }
        Bitacora::registrar('almacen.transferencia_anular', 'Anuló la transferencia TRF-'.$t->mov_cab_id.' de '.($almacenes[$t->part_alm]->descripcion ?? '')
            .' a '.($almacenes[$t->des_alm]->descripcion ?? '').' ('.$items->count().' producto(s))', null,
            ['referencia' => ['transferencia', $t->mov_cab_id], 'alerta' => true]);

        return back()->with('success', 'Transferencia anulada: los productos regresaron al almacén de origen.');
    }
}
