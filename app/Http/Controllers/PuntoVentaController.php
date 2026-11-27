<?php

namespace App\Http\Controllers;

use App\Models\EmpresaNegocio;
use App\Models\MedioPago;
use App\Models\Turno;
use App\Support\Acciones;
use App\Support\CatalogoVenta;
use App\Support\Clinica;
use App\Support\EdicionVenta;
use App\Support\Lotes;
use App\Support\PausasCaja;
use App\Support\Proformas;
use App\Support\VentaDirecta;
use Carbon\Carbon;
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

    /** Punto Venta IA: el mismo punto de venta, pero el cajero arma y cobra la venta hablando */
    public function ia(Request $request)
    {
        abort_unless(Auth::user()->puedeCobrar(), 403, 'Solo Administrador o Caja pueden vender.');

        return $this->pantalla(false, $request, true);
    }

    /**
     * Tomar Pedido (sucursales que trabajan con pedidos): la misma pantalla de venta, pero solo guarda el pedido y le da su
     * número del día; no imprime ni cobra. La caja lo trae con ese número.
     */
    public function pedido(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->esAdminOCaja() || $user->esVendedor(), 403, 'Solo Administrador, Caja o Vendedor toman pedidos.');
        if (! (bool) EmpresaNegocio::where('id_empresa_negocio', $user->id_empresa_negocio)->value('trabaja_pedidos')) {
            return redirect()->route('inicio')->with('error', 'Esta sucursal no trabaja con pedidos: actívalo en Mantenimiento › Sucursales.');
        }

        return $this->pantalla(false, $request, false, true);
    }

    /** El pedido de hoy con ese número: la caja lo trae para cobrarlo; el vendedor, para cambiarlo o anularlo */
    public function buscarPedido(Request $request)
    {
        abort_unless(Auth::user()->esAdminOCaja() || Auth::user()->esVendedor(), 403, 'Solo Administrador, Caja o Vendedor ven los pedidos.');
        $numero = (int) $request->validate(['numero' => 'required|integer|min:1|max:99999'], [], ['numero' => 'número de pedido'])['numero'];
        $pedido = Proformas::pedidoDelDia(Auth::user(), $numero);

        return response()->json(is_string($pedido) ? ['ok' => false, 'mensaje' => $pedido] : ['ok' => true, 'pedido' => $pedido]);
    }

    /** Pausar caja: el mensaje grande para los clientes (el turno sigue abierto) */
    public function pausar(Request $request)
    {
        $this->autorizar();
        $d = $request->validate(['motivo' => 'required|in:'.implode(',', array_keys(PausasCaja::MOTIVOS)), 'minutos' => 'nullable|integer|min:1|max:240',
            'pc' => 'nullable|string|max:40']);
        if (! PausasCaja::disponible()) {
            return response()->json(['ok' => false, 'mensaje' => 'Falta actualizar el sistema para pausar la caja.']);
        }
        $p = PausasCaja::iniciar(Auth::user(), $d['motivo'], $d['minutos'] ?? null, $d['pc'] ?? null);
        $inicio = Carbon::parse($p->inicio);

        return response()->json(['ok' => true, 'pausa' => ['motivo' => $p->motivo, 'minutos' => $p->minutos, 'pc' => $p->pc, 'inicio' => $inicio->format('H:i'),
            'vuelve' => $p->minutos ? $inicio->copy()->addMinutes($p->minutos)->format('H:i') : null]]);
    }

    /** Volver a la caja: la clave del cajero o la de un administrador */
    public function reanudar(Request $request)
    {
        try {
            $minutos = PausasCaja::terminar(Auth::user(), (string) $request->input('clave', ''));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'minutos' => $minutos]);
    }

    /** El cliente ya no quiere el pedido: queda ANULADO (su número no se vuelve a dar) */
    public function anularPedido(int $id)
    {
        abort_unless(Auth::user()->esAdminOCaja() || Auth::user()->esVendedor(), 403, 'Solo Administrador, Caja o Vendedor anulan pedidos.');
        try {
            $p = Proformas::anularPedido(Auth::user(), $id);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Pedido N° '.$p->pedido_dia.' anulado.']);
    }

    private function pantalla(bool $farmacia, Request $request, bool $ia = false, bool $modoPedido = false)
    {
        $user = Auth::user();
        // El vendedor entra sin caja: solo arma pedidos (proformas) que la caja cobra; en «Tomar Pedido» nadie cobra
        $soloPedido = $modoPedido || (! $user->puedeCobrar() && $user->esVendedor());
        if (! $soloPedido) {
            $this->autorizar();
        }

        $turno = $soloPedido ? null : Turno::abiertoDe($user);
        // Caja en pausa: se muestra el mensaje grande aunque el turno ya no esté (se cerró desde otro lado)
        $pausa = $soloPedido ? null : PausasCaja::abierta($user);
        if ($pausa) {
            $request->session()->put(PausasCaja::SESION, $pausa->id);
        }
        if (! $turno && ! $soloPedido && ! $pausa) {
            return redirect()->route('turnos.index')->with('error', 'Debes aperturar tu turno antes de vender.');
        }

        $negocio = EmpresaNegocio::find($user->id_empresa_negocio);
        $comprobantes = DB::table('tipo_documento')->where('caja', 1)->get(['tdocod', 'tdodes']);
        $estadopagos = DB::table('credito_dias')->where('id_empresa_negocio', $user->id_empresa_negocio)
            ->get(['cre_dia_id', 'cre_dia_nom', 'cre_dia_tip', 'cre_dia_fac']);
        $mediospagos = MedioPago::where('id_empresa_negocio', $user->id_empresa_negocio)
            ->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag', 'predeterminado', 'comision', 'qr'])
            ->map(fn ($m) => [
                'id' => $m->id_med_pag, 'nombre' => $m->nom_med_pag,
                // QR de cobro (Yape, Plin…): se muestra al cliente al cobrar con este medio
                'qr' => $m->qr && is_file(public_path($m->qr)) ? asset($m->qr) : null,
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
        if ($request->filled('clinica') && ! $soloPedido) {
            $clinica = Clinica::paraCaja($user, (int) $request->clinica);
            if (! $clinica) {
                return redirect()->route('clinica.agenda')->with('error', 'Esa atención ya se cobró o no tiene nada por cobrar.');
            }
        }

        // Venta abierta para corregirla (aún no enviada a SUNAT)
        $edicion = null;
        if ($request->filled('editar') && ! $soloPedido) {
            $edicion = $this->ventaEditable((int) $request->editar);
            if (is_string($edicion)) {
                return redirect()->route('ventas.index')->with('error', $edicion);
            }
        }

        // Varios venden en la misma PC: se elige quién vendió
        $vendedores = ! empty($negocio->pv_elegir_vendedor) && ! $soloPedido ? VentaDirecta::vendedores($user) : [];

        // Botones rápidos con los productos que más se mueven (si algo falla, la caja abre igual sin ellos)
        $masVendidos = rescue(fn () => CatalogoVenta::masVendidos($user), [], true);

        return view('empresas.pos.escritorio', compact('turno', 'negocio', 'comprobantes', 'estadopagos', 'mediospagos', 'documentos', 'farmacia', 'diasAlerta', 'proforma', 'edicion', 'clinica', 'soloPedido', 'vendedores', 'ia', 'masVendidos', 'modoPedido', 'pausa'));
    }

    /** Editar una venta del Panel de Ventas: se abre en el Punto Venta con sus productos, cliente y pago */
    public function editar(int $id)
    {
        $this->autorizar();
        abort_unless(Auth::user()->puede('editar_ventas'), 403, Acciones::mensaje('editar_ventas'));
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first(['ped_tip']);

        return redirect()->route(($cab->ped_tip ?? '') === 'FARMACIA' ? 'pv.farmacia' : 'pv.index', ['editar' => $id]);
    }

    public function guardarEdicion(Request $request, int $id)
    {
        $this->autorizar();
        if (! Auth::user()->puede('editar_ventas')) {
            return response()->json(['estado' => 'error', 'mensaje' => Acciones::mensaje('editar_ventas')]);
        }
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
