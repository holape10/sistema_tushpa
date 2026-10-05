<?php
namespace App\Http\Controllers;

use App\Models\{Almacen, EmpresaNegocio, MedioPago, Turno};
use App\Support\{Precios, Proformas, VentaDirecta};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * PV Grifo: venta de combustible por importe (S/ 20) o por galones, más los productos de la tienda.
 * Botones con imagen, placa obligatoria para factura y la misma emisión validada que los otros puntos de venta.
 * Los combustibles son los productos marcados "Es combustible" en su ficha.
 */
class PvGrifoController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja pueden vender.');
    }

    public function index(Request $request)
    {
        $this->autorizar();
        $user = Auth::user();

        $turno = Turno::abiertoDe($user);
        if (!$turno) {
            return redirect()->route('turnos.index')->with('error', 'Debes aperturar tu turno antes de vender.');
        }

        $suc = $user->id_empresa_negocio;
        $almacen = Almacen::where('id_empresa_negocio', $suc)->where('predeterminado', 1)->value('id_almacen');

        $filas = DB::table('productos as p')
            ->leftJoin('producto_stock as s', fn($j) => $j->on('s.IdProducto', '=', 'p.IdProducto')->where('s.id_almacen', $almacen))
            ->where('p.id_empresa_negocio', $suc)->where('p.proest', 'Activo')->where('p.promocion', '!=', 4)
            ->orderByDesc('p.es_combustible')->orderBy('p.pronom')
            ->get(['p.IdProducto', 'p.pronom', 'p.propun', 'p.procod', 'p.codigo_barra', 'p.umecod', 'p.cat_id', 'p.promocion',
                   'p.imagenproducto', 'p.es_combustible', 's.stock']);
        $precios = Precios::vigentes($filas);
        $presentaciones = Precios::presentaciones($filas->pluck('IdProducto'));
        $unidades = DB::table('unidad_medida')->pluck('umenom', 'umecod');

        $productos = $filas->map(fn($p) => [
            'id' => $p->IdProducto, 'nombre' => $p->pronom, 'precio' => $precios[$p->IdProducto], 'codigo' => $p->procod,
            'barra' => $p->codigo_barra, 'cat' => $p->cat_id, 'unidad' => $unidades[$p->umecod] ?? $p->umecod,
            'abrev' => $p->umecod === 'GLL' ? 'gal' : ($p->umecod === 'LTR' ? 'L' : strtolower($unidades[$p->umecod] ?? $p->umecod)),
            'combustible' => (bool) $p->es_combustible,
            'stock' => (int) $p->promocion === 0 ? (float) ($p->stock ?? 0) : null,
            'img' => $p->imagenproducto ? asset($p->imagenproducto) : null,
            'pres' => $presentaciones[$p->IdProducto] ?? [],
        ]);

        // Proforma abierta para editarla o cobrarla desde aquí
        $proforma = null;
        if ($request->filled('proforma')) {
            $proforma = Proformas::paraCaja($user, (int) $request->proforma);
            if (!$proforma) {
                return redirect()->route('proformas.index')->with('error', 'La proforma no existe o ya fue cobrada.');
            }
        }

        return view('empresas.pos.grifo', [
            'turno' => $turno,
            'negocio' => EmpresaNegocio::find($suc),
            'categorias' => DB::table('categorias')->where('id_empresa_negocio', $suc)->where('visible', 1)->orderBy('cat_nom')->get(['cat_id', 'cat_nom', 'color']),
            'productos' => $productos,
            'comprobantes' => DB::table('tipo_documento')->where('caja', 1)->get(['tdocod', 'tdodes']),
            'estadopagos' => DB::table('credito_dias')->where('id_empresa_negocio', $suc)->get(['cre_dia_id', 'cre_dia_nom', 'cre_dia_tip', 'cre_dia_fac']),
            'medios' => MedioPago::where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag', 'comision'])
                ->map(fn($m) => ['id' => $m->id_med_pag, 'nombre' => $m->nom_med_pag, 'comision' => (float) ($m->comision ?? 0)]),
            'proforma' => $proforma,
        ]);
    }

    /** Placas que el cliente ya usó (para elegirlas con un toque) */
    public function placas(Request $request)
    {
        $this->autorizar();
        $doc = trim((string) $request->get('doc'));
        if ($doc === '' || $doc === '00000000') {
            return response()->json([]);
        }
        return response()->json(DB::table('cpe_cabecera')
            ->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->where('ccandi', $doc)->whereNotNull('placa')
            ->groupBy('placa')->orderByRaw('MAX(IdCpe_cabecera) DESC')->limit(6)->pluck('placa'));
    }

    public function registrar(Request $request)
    {
        $this->autorizar();
        $request->validate(VentaDirecta::REGLAS, VentaDirecta::MENSAJES, VentaDirecta::NOMBRES + ['placa' => 'Placa']);
        if ($request->tdocod === '01' && trim((string) $request->placa) === '') {
            return response()->json(['estado' => 'error', 'mensaje' => 'La placa del vehículo es obligatoria para emitir factura.']);
        }

        try {
            $cabId = VentaDirecta::registrar(Auth::user(), $request->all(), 'GRIFO', recargos: true);
        } catch (\RuntimeException $e) {
            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['estado' => 'error', 'mensaje' => config('app.debug') ? $e->getMessage() : 'Error al registrar la venta.']);
        }

        return response()->json(VentaDirecta::respuesta($cabId));
    }
}
