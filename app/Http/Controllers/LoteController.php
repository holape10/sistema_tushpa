<?php
namespace App\Http\Controllers;

use App\Models\{Almacen, Producto};
use App\Support\{Excel, Lotes};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/** Lotes con stock y sus vencimientos (farmacia): vencidos, pronto a vencer y vigentes */
class LoteController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja ven los lotes.');
    }

    /** Lotes con stock de la sucursal, con filtros de estado, almacén y texto */
    private function consulta(Request $request, int $dias)
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        $hoy = now()->toDateString();
        $limite = now()->addDays($dias)->toDateString();
        $estado = $request->get('estado', 'alerta');
        $q = trim((string) $request->get('q'));

        return DB::table('producto_lote as l')
            ->join('productos as p', 'p.IdProducto', '=', 'l.IdProducto')
            ->join('almacenes as a', 'a.id_almacen', '=', 'l.id_almacen')
            ->where('a.id_empresa_negocio', $sucursal)
            ->where('l.stock', '>', 0)
            ->when($request->get('almacen'), fn($w, $a) => $w->where('l.id_almacen', $a))
            ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('p.pronom', 'like', "%{$q}%")
                ->orWhere('p.procod', 'like', "{$q}%")->orWhere('l.lote', 'like', "%{$q}%")))
            ->when($estado === 'vencidos', fn($w) => $w->where('l.vencimiento', '<', $hoy))
            ->when($estado === 'alerta', fn($w) => $w->where('l.vencimiento', '<=', $limite))
            ->when($estado === 'proximos', fn($w) => $w->whereBetween('l.vencimiento', [$hoy, $limite]))
            ->when($estado === 'vigentes', fn($w) => $w->where(fn($x) => $x->where('l.vencimiento', '>', $limite)->orWhereNull('l.vencimiento')))
            ->orderByRaw('l.vencimiento IS NULL, l.vencimiento')->orderBy('p.pronom')
            ->select('l.*', 'p.pronom', 'p.procod', 'p.umecod', 'p.costo', 'a.descripcion as almacen');
    }

    public function index(Request $request)
    {
        $this->autorizar();
        $sucursal = Auth::user()->id_empresa_negocio;
        $dias = Lotes::diasAlerta($sucursal);

        $lotes = $this->consulta($request, $dias)->paginate(30)->withQueryString();
        $resumen = self::resumen($sucursal, $dias);
        $almacenes = Almacen::where('id_empresa_negocio', $sucursal)->orderByDesc('predeterminado')->get();
        $sinControl = Producto::where('id_empresa_negocio', $sucursal)->whereIn('promocion', [0, 4])->where('control_lote', 0)->count();

        return view('empresas.lotes.index', [
            'lotes' => $lotes, 'resumen' => $resumen, 'almacenes' => $almacenes, 'dias' => $dias,
            'estado' => $request->get('estado', 'alerta'), 'q' => $request->get('q', ''), 'sinControl' => $sinControl,
        ]);
    }

    /** Totales para las tarjetas (también los usa el dashboard) */
    public static function resumen(int $sucursal, int $dias): array
    {
        $hoy = now()->toDateString();
        $r = DB::table('producto_lote as l')
            ->join('almacenes as a', 'a.id_almacen', '=', 'l.id_almacen')
            ->join('productos as p', 'p.IdProducto', '=', 'l.IdProducto')
            ->where('a.id_empresa_negocio', $sucursal)->where('l.stock', '>', 0)
            ->selectRaw('SUM(l.vencimiento < ?) as vencidos, SUM(CASE WHEN l.vencimiento < ? THEN l.stock * p.costo ELSE 0 END) as valor_vencidos,
                SUM(l.vencimiento BETWEEN ? AND ?) as en30, SUM(l.vencimiento BETWEEN ? AND ?) as alerta,
                SUM(CASE WHEN l.vencimiento BETWEEN ? AND ? THEN l.stock * p.costo ELSE 0 END) as valor_alerta, COUNT(*) as total',
                [$hoy, $hoy, $hoy, now()->addDays(30)->toDateString(), $hoy, now()->addDays($dias)->toDateString(),
                 $hoy, now()->addDays($dias)->toDateString()])
            ->first();

        return [
            'vencidos' => (int) $r->vencidos, 'valor_vencidos' => (float) $r->valor_vencidos,
            'en30' => (int) $r->en30, 'alerta' => (int) $r->alerta, 'valor_alerta' => (float) $r->valor_alerta,
            'total' => (int) $r->total,
        ];
    }

    public function exportar(Request $request)
    {
        $this->autorizar();
        $dias = Lotes::diasAlerta(Auth::user()->id_empresa_negocio);
        $hoy = now()->startOfDay();

        $filas = $this->consulta($request, $dias)->get()->map(function ($l) use ($hoy) {
            $restan = $l->vencimiento ? (int) $hoy->diffInDays($l->vencimiento, false) : null;
            return [(string) $l->procod, $l->pronom, $l->almacen, $l->lote,
                $l->vencimiento ? \Carbon\Carbon::parse($l->vencimiento)->format('d/m/Y') : '', $restan ?? '',
                $restan === null ? 'SIN FECHA' : ($restan < 0 ? 'VENCIDO' : 'VIGENTE'),
                (float) $l->stock, (float) $l->costo, round($l->stock * $l->costo, 2)];
        })->all();

        $ruta = (new Excel())->hoja('Lotes', ['Código', 'Producto', 'Almacén', 'Lote', 'Vencimiento', 'Días', 'Estado', 'Stock', 'Costo', 'Valor'], $filas)->guardar();
        return response()->download($ruta, 'lotes_vencimientos_' . now()->format('Ymd') . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /** Días de aviso y activar el control de lote en todos los productos */
    public function configuracion(Request $request)
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador cambia la configuración.');
        $sucursal = Auth::user()->id_empresa_negocio;

        if ($request->input('accion') === 'todos') {
            $n = Producto::where('id_empresa_negocio', $sucursal)->whereIn('promocion', [0, 4])->where('control_lote', 0)->update(['control_lote' => 1]);
            return back()->with('success', "Se activó el control de lote y vencimiento en {$n} productos.");
        }

        $request->validate(['dias' => 'required|integer|min:1|max:730'], [], ['dias' => 'días de aviso']);
        DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->update(['dias_alerta_vencimiento' => (int) $request->dias]);
        return back()->with('success', "Se avisará de los lotes que vencen en {$request->dias} días o menos.");
    }
}
