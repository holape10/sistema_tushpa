<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Support\Buscar;
use App\Support\Mermas;
use App\Support\Recetas;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Mermas: registrar lo que se pierde (cocina, barra o almacén), ver cuánto se perdió y por qué, y anular un error.
 */
class MermaController extends Controller
{
    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index(Request $request): View
    {
        $suc = $this->sucursal();
        $desde = Carbon::parse($request->get('desde', now()->startOfMonth()->toDateString()))->toDateString();
        $hasta = Carbon::parse($request->get('hasta', now()->toDateString()))->toDateString();

        $mermas = DB::table('mermas as m')->join('productos as p', 'p.IdProducto', '=', 'm.IdProducto')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'm.IdUsuario')
            ->where('m.id_empresa_negocio', $suc)->whereBetween('m.fecha', [$desde, $hasta])
            ->orderByDesc('m.merma_id')
            ->get(['m.*', 'p.pronom', 'p.promocion', 'u.apeusu', 'u.name']);
        $activas = $mermas->where('estado', 'ACTIVA');

        return view('empresas.mermas.index', [
            'mermas' => $mermas,
            'desde' => $desde,
            'hasta' => $hasta,
            'total' => (float) $activas->sum('costo'),
            'porMotivo' => $activas->groupBy('motivo')->map(fn ($g) => (float) $g->sum('costo'))->sortDesc(),
            'masPerdidos' => $activas->groupBy('IdProducto')->map(fn ($g) => (object) ['nombre' => $g->first()->pronom, 'costo' => (float) $g->sum('costo'), 'veces' => $g->count()])
                ->sortByDesc('costo')->take(5)->values(),
            'esAdmin' => Auth::user()->esAdmin(),
        ]);
    }

    /** Insumos, productos y platos que se pueden registrar como merma */
    public function productos(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q'));
        $filas = Producto::where('id_empresa_negocio', $this->sucursal())->where('proest', 'Activo')->whereIn('promocion', [0, 2, 4, 6])
            ->tap(fn ($w) => Buscar::palabras($w, $q, ['pronom'], ['procod', 'codigo_barra']))
            ->orderBy('pronom')->limit(15)->get();

        return response()->json($filas->map(fn ($p) => [
            'id' => $p->IdProducto, 'nombre' => $p->pronom,
            'tipo' => [0 => 'Producto', 2 => 'Plato', 4 => 'Insumo', 6 => 'Combo'][(int) $p->promocion] ?? '',
            'unidades' => Mermas::unidades($p), 'costo' => Mermas::costoUnitario($p), 'unidad' => Recetas::nombreUnidad($p->umecod ?: 'NIU'),
        ]));
    }

    public function guardar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'producto' => 'required|integer',
            'cantidad' => 'required|numeric|gt:0|max:99999',
            'umecod' => 'required|string|max:3',
            'motivo' => 'required|in:'.implode(',', array_keys(Mermas::MOTIVOS)),
            'observacion' => 'nullable|string|max:200',
        ], [], ['cantidad' => 'cantidad', 'motivo' => 'motivo', 'producto' => 'producto']);
        $producto = Producto::where('IdProducto', $d['producto'])->where('id_empresa_negocio', $this->sucursal())
            ->whereIn('promocion', [0, 2, 4, 6])->first();
        if (! $producto) {
            return response()->json(['ok' => false, 'mensaje' => 'Ese producto ya no existe.'], 422);
        }
        try {
            $id = Mermas::registrar(Auth::user(), $producto, $d);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
        $costo = (float) DB::table('mermas')->where('merma_id', $id)->value('costo');
        session()->flash('success', 'Merma registrada: '.$producto->pronom.' (S/ '.number_format($costo, 2).'). Ya salió del almacén.');

        return response()->json(['ok' => true]);
    }

    public function anular(int $id): RedirectResponse
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador anula mermas.');
        try {
            Mermas::anular($id, $this->sucursal());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Merma anulada: lo que había salido regresó al almacén.');
    }
}
