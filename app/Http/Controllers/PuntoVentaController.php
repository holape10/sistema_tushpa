<?php
namespace App\Http\Controllers;

use App\Models\{EmpresaNegocio, MedioPago, Turno};
use App\Support\{Lotes, Proformas, VentaDirecta};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Punto de venta de escritorio: venta directa con teclado y lector de barras, líneas libres,
 * observaciones y medios de pago con comisión. Usa la misma emisión que el PV Móvil y el cobro de mesas.
 */
class PuntoVentaController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja pueden vender.');
    }

    public function index(Request $request)
    {
        return $this->pantalla(false, $request);
    }

    /** PV Farmacia: el mismo punto de venta mostrando lotes y vencimientos; vende primero el lote que vence antes */
    public function farmacia(Request $request)
    {
        return $this->pantalla(true, $request);
    }

    private function pantalla(bool $farmacia, Request $request)
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
            ->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag', 'predeterminado', 'comision'])
            ->map(fn($m) => [
                'id' => $m->id_med_pag, 'nombre' => $m->nom_med_pag,
                'comision' => (float) ($m->comision ?? 0), 'predeterminado' => $m->predeterminado == '1',
            ]);
        $documentos = DB::table('tipo_documento_identidad')->orderBy('orden')->get(['tdicod', 'tdides']);

        $diasAlerta = Lotes::diasAlerta($user->id_empresa_negocio);

        // Proforma abierta para editarla o cobrarla desde aquí
        $proforma = null;
        if ($request->filled('proforma')) {
            $proforma = Proformas::paraCaja($user, (int) $request->proforma);
            if (!$proforma) {
                return redirect()->route('proformas.index')->with('error', 'La proforma no existe o ya fue cobrada.');
            }
        }

        return view('empresas.pos.escritorio', compact('turno', 'negocio', 'comprobantes', 'estadopagos', 'mediospagos', 'documentos', 'farmacia', 'diasAlerta', 'proforma'));
    }

    public function registrar(Request $request)
    {
        return $this->registrarComo($request, 'PV');
    }

    public function registrarFarmacia(Request $request)
    {
        return $this->registrarComo($request, 'FARMACIA');
    }

    private function registrarComo(Request $request, string $origen)
    {
        $this->autorizar();
        $request->validate(VentaDirecta::REGLAS, VentaDirecta::MENSAJES, VentaDirecta::NOMBRES);

        try {
            $cabId = VentaDirecta::registrar(Auth::user(), $request->all(), $origen, recargos: true);
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
