<?php

namespace App\Http\Controllers;

use App\Support\Fidelizacion;
use App\Support\Kardex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Puntos y premios: la regla de la sucursal (Sí/No, S/ por punto, compra mínima), los premios,
 * los clientes con más puntos, su historial, el canje de premios y los ajustes.
 * Caja consulta y canjea; solo el administrador cambia la regla, los premios y hace ajustes.
 */
class FidelizacionController extends Controller
{
    private function recepcion(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja.');
    }

    private function soloAdmin(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador puede hacer esto.');
    }

    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index()
    {
        $this->recepcion();
        $suc = $this->sucursal();
        $mov = DB::table('fid_movimientos')->where('id_empresa_negocio', $suc);

        return view('empresas.fidelizacion.index', [
            'cfg' => DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->first(['fid_activo', 'fid_soles_por_punto', 'fid_compra_minima']),
            'premios' => DB::table('fid_premios as p')->leftJoin('productos as pr', 'pr.IdProducto', '=', 'p.IdProducto')
                ->leftJoin('producto_stock as st', fn ($j) => $j->on('st.IdProducto', '=', 'p.IdProducto')->where('st.id_almacen', Kardex::almacenPredeterminado($suc)?->id_almacen ?? 0))
                ->where('p.id_empresa_negocio', $suc)->orderByDesc('p.activo')->orderBy('p.puntos')
                ->get(['p.*', 'pr.pronom', 'pr.promocion', 'st.stock'])
                ->map(function ($p) {
                    $p->vencido = $p->vence && $p->vence < now()->toDateString();

                    return $p;
                }),
            'ranking' => DB::table('cliente')->where('rucemp', Auth::user()->IdEmpresa)->where('puntos', '>', 0)
                ->orderByDesc('puntos')->limit(30)->get(['clicod', 'clinum', 'clinom', 'telefono', 'puntos']),
            'resumen' => [
                'clientes' => DB::table('cliente')->where('rucemp', Auth::user()->IdEmpresa)->where('puntos', '>', 0)->count(),
                'ganados_mes' => (int) (clone $mov)->where('tipo', 'VENTA')->where('fecha', '>=', now()->startOfMonth())->sum('puntos'),
                'canjes_mes' => (clone $mov)->where('tipo', 'CANJE')->where('fecha', '>=', now()->startOfMonth())->count(),
            ],
            'esAdmin' => Auth::user()->esAdmin(),
        ]);
    }

    public function guardarConfig(Request $request)
    {
        $this->soloAdmin();
        $d = $request->validate([
            'fid_activo' => 'required|boolean',
            'fid_soles_por_punto' => 'required|numeric|min:0.01|max:100000',
            'fid_compra_minima' => 'nullable|numeric|min:0|max:1000000',
        ], [], ['fid_soles_por_punto' => 'los soles por punto', 'fid_compra_minima' => 'la compra mínima']);
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal())->update([
            'fid_activo' => (int) $d['fid_activo'], 'fid_soles_por_punto' => $d['fid_soles_por_punto'], 'fid_compra_minima' => $d['fid_compra_minima'] ?? 0,
        ]);

        return back()->with('success', $d['fid_activo'] ? 'Fidelización activada: desde ahora cada venta con DNI o RUC suma puntos.' : 'Fidelización desactivada: las ventas ya no suman puntos.');
    }

    public function guardarPremio(Request $request)
    {
        $this->soloAdmin();
        $d = $request->validate([
            'premio_id' => 'nullable|integer', 'nombre' => 'required|string|max:120', 'descripcion' => 'nullable|string|max:255',
            'puntos' => 'required|integer|min:1|max:10000000', 'activo' => 'nullable|boolean',
            'IdProducto' => 'nullable|integer', 'cantidad' => 'nullable|numeric|min:0.001|max:99999', 'vence' => 'nullable|date',
        ], [], ['nombre' => 'el nombre del premio', 'puntos' => 'los puntos que cuesta', 'vence' => 'la fecha de vencimiento']);
        $producto = ! empty($d['IdProducto']) ? DB::table('productos')->where('IdProducto', $d['IdProducto'])->where('id_empresa_negocio', $this->sucursal())->first(['IdProducto', 'pronom']) : null;
        $fila = ['nombre' => mb_strtoupper(trim($d['nombre'] ?: ($producto->pronom ?? ''))), 'descripcion' => $d['descripcion'] ?? null, 'puntos' => $d['puntos'],
            'activo' => (int) ($d['activo'] ?? 1), 'IdProducto' => $producto->IdProducto ?? null, 'cantidad' => $producto ? ($d['cantidad'] ?? 1) : 1, 'vence' => $d['vence'] ?? null];
        if (! empty($d['premio_id'])) {
            DB::table('fid_premios')->where('premio_id', $d['premio_id'])->where('id_empresa_negocio', $this->sucursal())->update($fila);
        } else {
            DB::table('fid_premios')->insert($fila + ['id_empresa_negocio' => $this->sucursal()]);
        }

        return back()->with('success', 'Premio guardado.');
    }

    /** Productos del catálogo para elegir como premio */
    public function productos(Request $request)
    {
        $this->soloAdmin();
        $q = trim((string) $request->get('q'));

        return response()->json(mb_strlen($q) < 2 ? [] : DB::table('productos')->where('id_empresa_negocio', $this->sucursal())->where('proest', 'Activo')
            ->whereIn('promocion', [0, 6])->where(fn ($w) => $w->where('pronom', 'like', "%{$q}%")->orWhere('procod', $q)->orWhere('codigo_barra', $q))
            ->orderBy('pronom')->limit(12)->get(['IdProducto', 'pronom', 'procod']));
    }

    /** Buscar cliente por DNI/RUC o nombre */
    public function buscar(Request $request)
    {
        $this->recepcion();
        $q = trim((string) $request->get('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(DB::table('cliente')->where('rucemp', Auth::user()->IdEmpresa)->where('clinum', '!=', '00000000')
            ->where(fn ($w) => $w->where('clinum', 'like', $q.'%')->orWhere('clinom', 'like', '%'.$q.'%'))
            ->orderByDesc('puntos')->limit(15)->get(['clicod', 'clinum', 'clinom', 'puntos']));
    }

    /** Ficha del cliente: saldo, premios que le alcanzan e historial */
    public function cliente(int $clicod)
    {
        $this->recepcion();
        $c = DB::table('cliente')->where('clicod', $clicod)->where('rucemp', Auth::user()->IdEmpresa)->first(['clicod', 'clinum', 'clinom', 'telefono', 'puntos']);
        abort_unless($c, 404);

        return response()->json([
            'cliente' => $c,
            'historial' => DB::table('fid_movimientos as m')->leftJoin('users as u', 'u.IdUsuario', '=', 'm.IdUsuario')
                ->where('m.clicod', $clicod)->orderByDesc('m.mov_id')->limit(50)
                ->get(['m.fecha', 'm.tipo', 'm.puntos', 'm.saldo', 'm.detalle', 'u.apeusu']),
        ]);
    }

    public function canjear(Request $request, int $clicod)
    {
        $this->recepcion();
        $d = $request->validate(['premio_id' => 'required|integer']);
        abort_unless(DB::table('cliente')->where('clicod', $clicod)->where('rucemp', Auth::user()->IdEmpresa)->exists(), 404);
        try {
            $saldo = Fidelizacion::canjear(Auth::user(), $clicod, (int) $d['premio_id']);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'saldo' => $saldo, 'mensaje' => "Premio canjeado. Le quedan {$saldo} puntos."]);
    }

    public function ajustar(Request $request, int $clicod)
    {
        $this->soloAdmin();
        $d = $request->validate(['puntos' => 'required|integer|not_in:0|min:-1000000|max:1000000', 'motivo' => 'required|string|min:3|max:150'],
            ['puntos.not_in' => 'Escribe cuántos puntos sumar (ej. 50) o restar (ej. -50).'], ['motivo' => 'el motivo']);
        abort_unless(DB::table('cliente')->where('clicod', $clicod)->where('rucemp', Auth::user()->IdEmpresa)->exists(), 404);
        try {
            $saldo = Fidelizacion::ajustar(Auth::user(), $clicod, (int) $d['puntos'], $d['motivo']);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'saldo' => $saldo, 'mensaje' => "Puntos ajustados. Ahora tiene {$saldo}."]);
    }
}
