<?php
namespace App\Http\Controllers;

use App\Models\{Almacen, EmpresaNegocio, MedioPago, Turno};
use App\Support\{Precios, VentaDirecta};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * PV táctil (el "PV Mall" del sistema antiguo): pantalla completa para pantallas táctiles.
 * Carga categorías y productos de una sola vez (filtra al instante en el navegador) y, en vez de comandar,
 * emite el comprobante y lo imprime. Usa la misma venta directa validada que el Punto Venta.
 */
class PvTactilController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja pueden vender.');
    }

    public function index()
    {
        $this->autorizar();
        $user = Auth::user();

        $turno = Turno::abiertoDe($user);
        if (!$turno) {
            return redirect()->route('turnos.index')->with('error', 'Debes aperturar tu turno antes de vender.');
        }

        $suc = $user->id_empresa_negocio;
        $almacen = Almacen::where('id_empresa_negocio', $suc)->where('predeterminado', 1)->value('id_almacen');

        $categorias = DB::table('categorias')->where('id_empresa_negocio', $suc)->where('visible', 1)
            ->orderBy('cat_nom')->get(['cat_id', 'cat_nom', 'color']);

        $filas = DB::table('productos as p')
            ->leftJoin('producto_stock as s', fn($j) => $j->on('s.IdProducto', '=', 'p.IdProducto')->where('s.id_almacen', $almacen))
            ->where('p.id_empresa_negocio', $suc)->where('p.proest', 'Activo')->where('p.promocion', '!=', 4)
            ->orderBy('p.pronom')
            ->get(['p.IdProducto', 'p.pronom', 'p.propun', 'p.procod', 'p.codigo_barra', 'p.cat_id', 'p.promocion', 'p.imagenproducto', 's.stock']);
        // Precio vigente (precio dinámico) y presentaciones (SACO x 50, CAJA x 12…)
        $precios = Precios::vigentes($filas);
        $presentaciones = Precios::presentaciones($filas->pluck('IdProducto'));
        $productos = $filas->map(fn($p) => [
            'id' => $p->IdProducto, 'nombre' => $p->pronom, 'precio' => $precios[$p->IdProducto], 'codigo' => $p->procod,
            'barra' => $p->codigo_barra, 'cat' => $p->cat_id, 'stock' => (int) $p->promocion === 0 ? (float) ($p->stock ?? 0) : null,
            'img' => $p->imagenproducto ? asset($p->imagenproducto) : null,
            'pres' => $presentaciones[$p->IdProducto] ?? [],
        ]);

        return view('empresas.pos.tactil', [
            'turno' => $turno,
            'negocio' => EmpresaNegocio::find($suc),
            'categorias' => $categorias,
            'productos' => $productos,
            'comprobantes' => DB::table('tipo_documento')->where('caja', 1)->get(['tdocod', 'tdodes']),
            'contado' => DB::table('credito_dias')->where('id_empresa_negocio', $suc)->where('cre_dia_tip', 'CONTADO')->value('cre_dia_id'),
            'medios' => MedioPago::where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag', 'comision'])
                ->map(fn($m) => ['id' => $m->id_med_pag, 'nombre' => $m->nom_med_pag, 'comision' => (float) ($m->comision ?? 0)]),
        ]);
    }

    /** Precios vigentes de todo el catálogo: el PV táctil los consulta cada pocos minutos para aplicar los precios dinámicos */
    public function precios()
    {
        $this->autorizar();
        $filas = DB::table('productos')->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->where('proest', 'Activo')->where('promocion', '!=', 4)->get(['IdProducto', 'propun']);
        return response()->json(Precios::vigentes($filas));
    }

    public function registrar(Request $request)
    {
        $this->autorizar();
        $request->validate(VentaDirecta::REGLAS, VentaDirecta::MENSAJES, VentaDirecta::NOMBRES);

        try {
            $cabId = VentaDirecta::registrar(Auth::user(), $request->all(), 'TACTIL', recargos: true);
        } catch (\RuntimeException $e) {
            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['estado' => 'error', 'mensaje' => config('app.debug') ? $e->getMessage() : 'Error al registrar la venta.']);
        }

        return response()->json(VentaDirecta::respuesta($cabId));
    }
}
