<?php
namespace App\Http\Controllers;

use App\Models\{EmpresaNegocio, MedioPago, Turno};
use App\Support\{CatalogoVenta, Proformas, VentaDirecta};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Punto de venta móvil: venta directa (sin mesa ni pedido) desde el celular o tablet.
 * Busca por texto, voz o código de barras y emite con la misma lógica que el cobro de mesas.
 */
class PosMovilController extends Controller
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

        $negocio = EmpresaNegocio::find($user->id_empresa_negocio);
        $comprobantes = DB::table('tipo_documento')->where('caja', 1)->get(['tdocod', 'tdodes']);
        $estadopagos = DB::table('credito_dias')->where('id_empresa_negocio', $user->id_empresa_negocio)
            ->get(['cre_dia_id', 'cre_dia_nom', 'cre_dia_tip', 'cre_dia_fac']);
        $mediospagos = MedioPago::where('id_empresa_negocio', $user->id_empresa_negocio)
            ->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag', 'predeterminado']);

        $documentos = DB::table('tipo_documento_identidad')->orderBy('orden')->get(['tdicod', 'tdides']);

        // Proforma abierta para editarla o cobrarla desde aquí
        $proforma = null;
        if ($request->filled('proforma')) {
            $proforma = Proformas::paraCaja($user, (int) $request->proforma);
            if (!$proforma) {
                return redirect()->route('proformas.index')->with('error', 'La proforma no existe o ya fue cobrada.');
            }
        }

        return view('empresas.pos.movil', compact('turno', 'negocio', 'comprobantes', 'estadopagos', 'mediospagos', 'documentos', 'proforma'));
    }

    /**
     * Búsqueda de productos. Con `codigo` busca el código exacto (lector de barras / cámara), también el código de barras
     * del producto o de sus presentaciones; con `q` busca por nombre o código, primero los que empiezan con lo escrito.
     */
    public function productos(Request $request)
    {
        $this->autorizar();
        $codigo = trim((string) $request->get('codigo'));
        $q = trim((string) $request->get('q'));

        if ($codigo === '' && mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(CatalogoVenta::buscar(Auth::user(), $codigo, $q));
    }

    public function registrar(Request $request)
    {
        $this->autorizar();
        $request->validate(VentaDirecta::REGLAS, VentaDirecta::MENSAJES, VentaDirecta::NOMBRES);

        try {
            $cabId = VentaDirecta::registrar(Auth::user(), $request->all(), 'POS');
        } catch (\RuntimeException $e) {
            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'estado' => 'error',
                'mensaje' => config('app.debug') ? $e->getMessage() : 'Error al registrar la venta.',
            ]);
        }

        return response()->json(VentaDirecta::respuesta($cabId));
    }
}
