<?php

namespace App\Http\Controllers;

use App\Models\EmpresaNegocio;
use App\Models\MedioPago;
use App\Models\Turno;
use App\Support\Clinica;
use App\Support\EdicionVenta;
use App\Support\Lotes;
use App\Support\Proformas;
use App\Support\VentaDirecta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Punto de venta de escritorio: venta directa con teclado y lector de barras, líneas libres,
 * observaciones y medios de pago con comisión. Usa la misma emisión que el PV Móvil y el cobro de mesas.
 */
class PuntoVentaController extends Controller
{
    private function autorizar(): void
    {
        // Clínicas: el doctor también, si la sucursal le permite cobrar sus atenciones
        abort_unless(Auth::user()->puedeCobrar(), 403, 'Solo Administrador o Caja pueden vender.');
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
        if (! $turno) {
            return redirect()->route('turnos.index')->with('error', 'Debes aperturar tu turno antes de vender.');
        }

        $negocio = EmpresaNegocio::find($user->id_empresa_negocio);
        $comprobantes = DB::table('tipo_documento')->where('caja', 1)->get(['tdocod', 'tdodes']);
        $estadopagos = DB::table('credito_dias')->where('id_empresa_negocio', $user->id_empresa_negocio)
            ->get(['cre_dia_id', 'cre_dia_nom', 'cre_dia_tip', 'cre_dia_fac']);
        $mediospagos = MedioPago::where('id_empresa_negocio', $user->id_empresa_negocio)
            ->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag', 'predeterminado', 'comision'])
            ->map(fn ($m) => [
                'id' => $m->id_med_pag, 'nombre' => $m->nom_med_pag,
                'comision' => (float) ($m->comision ?? 0), 'predeterminado' => $m->predeterminado == '1',
            ]);
        $documentos = DB::table('tipo_documento_identidad')->orderBy('orden')->get(['tdicod', 'tdides']);

        $diasAlerta = Lotes::diasAlerta($user->id_empresa_negocio);

        // Proforma abierta para editarla o cobrarla desde aquí
        $proforma = null;
        if ($request->filled('proforma')) {
            $proforma = Proformas::paraCaja($user, (int) $request->proforma);
            if (! $proforma) {
                return redirect()->route('proformas.index')->with('error', 'La proforma no existe o ya fue cobrada.');
            }
        }

        // Atención de la clínica por cobrar: entra con sus líneas y el paciente como cliente
        $clinica = null;
        if ($request->filled('clinica')) {
            $clinica = Clinica::paraCaja($user, (int) $request->clinica);
            if (! $clinica) {
                return redirect()->route('clinica.agenda')->with('error', 'Esa atención ya se cobró o no tiene nada por cobrar.');
            }
        }

        // Venta abierta para corregirla (aún no enviada a SUNAT)
        $edicion = null;
        if ($request->filled('editar')) {
            $edicion = $this->ventaEditable((int) $request->editar);
            if (is_string($edicion)) {
                return redirect()->route('ventas.index')->with('error', $edicion);
            }
        }

        return view('empresas.pos.escritorio', compact('turno', 'negocio', 'comprobantes', 'estadopagos', 'mediospagos', 'documentos', 'farmacia', 'diasAlerta', 'proforma', 'edicion', 'clinica'));
    }

    /** Editar una venta del Panel de Ventas: se abre en el Punto Venta con sus productos, cliente y pago */
    public function editar(int $id)
    {
        $this->autorizar();
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first(['ped_tip']);

        return redirect()->route(($cab->ped_tip ?? '') === 'FARMACIA' ? 'pv.farmacia' : 'pv.index', ['editar' => $id]);
    }

    public function guardarEdicion(Request $request, int $id)
    {
        $this->autorizar();
        $request->validate(VentaDirecta::REGLAS, VentaDirecta::MENSAJES, VentaDirecta::NOMBRES);
        if (is_string($error = $this->ventaEditable($id))) {
            return response()->json(['estado' => 'error', 'mensaje' => $error]);
        }

        try {
            EdicionVenta::editar(Auth::user(), $id, $request->all());
        } catch (\RuntimeException $e) {
            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['estado' => 'error', 'mensaje' => config('app.debug') ? $e->getMessage() : 'Error al guardar la venta.']);
        }

        return response()->json(VentaDirecta::respuesta($id) + ['panel' => route('ventas.index')]);
    }

    /**
     * La venta lista para editar, o el motivo por el que no se puede (el cajero solo corrige sus propias ventas).
     *
     * @return array<string, mixed>|string
     */
    private function ventaEditable(int $id): array|string
    {
        $user = Auth::user();
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->where('id_empresa_negocio', $user->id_empresa_negocio)->first();
        if (! $cab) {
            return 'La venta no existe en esta sucursal.';
        }
        if (! $user->esAdmin() && (int) $cab->IdUsuario !== (int) $user->IdUsuario) {
            return 'Solo el administrador puede editar ventas de otro cajero.';
        }
        if ($motivo = EdicionVenta::bloqueo($cab)) {
            return 'No se puede editar '.$cab->serdoc.'-'.$cab->numdoc.': '.$motivo;
        }

        return EdicionVenta::paraEditar($user, $id);
    }

    public function registrar(Request $request)
    {
        return $this->registrarComo($request, 'PV');
    }

    public function registrarFarmacia(Request $request)
    {
        return $this->registrarComo($request, 'FARMACIA');
    }

    /**
     * Internet lento: cada venta trae una clave (la pantalla la crea y la repite si reintenta). Si la venta ya se registró
     * pero la respuesta no llegó y la caja vuelve a tocar REGISTRAR, se devuelve la misma venta en vez de crear otra.
     */
    private function registrarComo(Request $request, string $origen)
    {
        $this->autorizar();
        $request->validate(VentaDirecta::REGLAS, VentaDirecta::MENSAJES, VentaDirecta::NOMBRES);

        $clave = (string) $request->input('clave_venta');
        $llave = preg_match('/^[A-Za-z0-9-]{8,64}$/', $clave) ? 'pv:'.DB::connection()->getDatabaseName().':'.Auth::id().':'.$clave : null;
        if ($llave && ! Cache::add($llave, 'procesando', now()->addMinutes(15))) {
            $previa = Cache::get($llave);

            return response()->json(is_array($previa) ? $previa + ['repetida' => true]
                : ['estado' => 'error', 'mensaje' => 'Esta venta todavía se está registrando. Espera unos segundos y revisa la última venta.']);
        }

        try {
            $cabId = VentaDirecta::registrar(Auth::user(), $request->all(), $origen, recargos: true);
        } catch (\RuntimeException $e) {
            $llave && Cache::forget($llave);

            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $llave && Cache::forget($llave);
            report($e);

            return response()->json([
                'estado' => 'error',
                'mensaje' => config('app.debug') ? $e->getMessage() : 'Error al registrar la venta.',
            ]);
        }

        $respuesta = VentaDirecta::respuesta($cabId);
        if ($llave) {
            Cache::put($llave, $respuesta, now()->addMinutes(15));
        }

        return response()->json($respuesta);
    }
}
