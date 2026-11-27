<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Support\Asistente\CuadernoFoto;
use App\Support\Asistente\PedidoWhatsapp;
use App\Support\Impresion\Impresion;
use App\Support\Proformas;
use Dompdf\Dompdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Proformas: se crean desde los puntos de venta (menos el cobro de mesas). Aquí se listan, imprimen y eliminan;
 * para editarlas o cobrarlas se abren en la caja, que al registrar emite el comprobante y la marca FACTURADA.
 */
class ProformaController extends Controller
{
    private function autorizar(): void
    {
        // El vendedor arma los pedidos; la caja los cobra
        abort_unless(Auth::user()->esAdminOCaja() || Auth::user()->esVendedor(), 403, 'Solo Administrador, Caja o Vendedor pueden manejar proformas.');
    }

    private function proforma($id): object
    {
        $p = DB::table('proformas')->where('id_proforma', $id)->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->first();
        abort_unless($p, 404);

        return $p;
    }

    /** Caja donde se abre una proforma para editarla o cobrarla (el PV táctil no edita: se abre en el Punto Venta) */
    public static function urlCaja(object $p): string
    {
        return match ($p->origen) {
            'POS' => route('pos.movil', ['proforma' => $p->id_proforma]),
            'FARMACIA' => route('pv.farmacia', ['proforma' => $p->id_proforma]),
            'GRIFO' => route('pv.grifo', ['proforma' => $p->id_proforma]),
            default => route('pv.index', ['proforma' => $p->id_proforma]),
        };
    }

    public function index(Request $request)
    {
        $this->autorizar();
        $user = Auth::user();
        $q = trim((string) $request->get('q'));
        $estado = $request->get('estado', 'PENDIENTE');
        $desde = $request->get('desde', now()->subDays(30)->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());

        $proformas = DB::table('proformas as p')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'p.IdUsuario')
            ->leftJoin('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'p.IdCpe_cabecera')
            ->where('p.id_empresa_negocio', $user->id_empresa_negocio)
            ->when(in_array($estado, ['PENDIENTE', 'FACTURADA'], true), fn ($w) => $w->where('p.estado', $estado))
            ->whereBetween('p.fecha', [$desde, $hasta])
            ->when($q !== '', function ($w) use ($q) {
                $num = ltrim(preg_replace('/^.*-/', '', $q), '0');
                $w->where(fn ($x) => $x->where('p.clinom', 'like', "%{$q}%")->orWhere('p.clinum', $q)
                    ->when(ctype_digit($num), fn ($y) => $y->orWhere('p.numero', (int) $num)));
            })
            ->orderByDesc('p.id_proforma')
            ->select('p.*', 'u.apeusu as usuario', 'c.serdoc', 'c.numdoc')
            ->paginate(20)->withQueryString();

        $pendientes = DB::table('proformas')->where('id_empresa_negocio', $user->id_empresa_negocio)->where('estado', 'PENDIENTE');
        $resumen = ['cantidad' => (clone $pendientes)->count(), 'total' => (float) (clone $pendientes)->sum('total')];

        return view('empresas.proformas.index', compact('proformas', 'q', 'estado', 'desde', 'hasta', 'resumen'));
    }

    /** Pedido de WhatsApp: se pega el mensaje (o la foto de la lista) del cliente y la IA arma la proforma */
    public function whatsapp()
    {
        $this->autorizar();

        return view('empresas.proformas.whatsapp', [
            'productos' => CuadernoFoto::productos((int) Auth::user()->id_empresa_negocio)->sortBy('pronom')
                ->map(fn ($p) => ['id' => (int) $p->IdProducto, 'nombre' => $p->pronom, 'precio' => (float) $p->propun])->values(),
            'negocio' => (string) DB::table('empresa_negocios')->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->value('nombre_comercial'),
        ]);
    }

    public function whatsappLeer(Request $request): JsonResponse
    {
        $this->autorizar();
        $request->validate([
            'texto' => 'nullable|required_without:foto|string|max:3000',
            'foto' => 'nullable|file|max:12288|extensions:'.PedidoWhatsapp::EXTENSIONES,
        ], ['texto.required_without' => 'Pega el mensaje del cliente o sube la foto de su lista.', 'foto.extensions' => 'Sube una foto (JPG o PNG).']);
        set_time_limit(120);
        try {
            return response()->json(['ok' => true] + PedidoWhatsapp::leer(Auth::user(), (string) $request->texto, $request->file('foto')));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Desde las cajas (JSON): crea la proforma o guarda los cambios si trae id */
    public function guardar(Request $request)
    {
        $this->autorizar();
        $request->validate(Proformas::REGLAS, ['items.required' => 'El detalle está vacío.'],
            ['clinum' => 'DNI / RUC', 'clinom' => 'Nombre o razón social']);

        try {
            $id = Proformas::guardar(Auth::user(), $request->all());
        } catch (\RuntimeException $e) {
            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['estado' => 'error', 'mensaje' => config('app.debug') ? $e->getMessage() : 'No se pudo guardar la proforma.']);
        }

        $p = $this->proforma($id);
        // Sale sola en la impresora de caja, como los comprobantes (el vendedor que solo toma pedidos no imprime)
        $impresora = $request->boolean('imprimir') ? Impresion::proforma($id, (int) Auth::user()->id_empresa_negocio) : null;

        return response()->json([
            'estado' => 'success', 'id' => $id, 'numero' => Proformas::numero($p), 'total' => (float) $p->total, 'pedido' => ! empty($p->pedido_dia) ? (int) $p->pedido_dia : null,
            'editada' => ! empty($request->id), 'imprimir' => route('proformas.imprimir', $id), 'impresora' => $impresora,
        ]);
    }

    public function imprimir($id)
    {
        $this->autorizar();
        $p = $this->proforma($id);
        $detalle = DB::table('proforma_detalle')->where('id_proforma', $id)->orderBy('id_proforma_detalle')->get();
        $negocio = EmpresaNegocio::find($p->id_empresa_negocio);
        $empresa = Empresa::find($p->IdEmpresa);
        $usuario = DB::table('users')->where('IdUsuario', $p->IdUsuario)->value('apeusu');
        $formato = strtoupper((string) request('formato', $negocio->formato_impresion ?: 'TICKET')) === 'A4' ? 'A4' : 'TICKET';

        return view('empresas.proformas.imprimir', compact('p', 'detalle', 'negocio', 'empresa', 'usuario', 'formato'));
    }

    /** La proforma en PDF A4 para descargar (como «Descargar PDF» del Panel de Ventas) */
    public function pdf($id)
    {
        $this->autorizar();
        $p = $this->proforma($id);
        $html = view('empresas.proformas.imprimir', ['p' => $p, 'detalle' => DB::table('proforma_detalle')->where('id_proforma', $id)->orderBy('id_proforma_detalle')->get(),
            'negocio' => EmpresaNegocio::find($p->id_empresa_negocio), 'empresa' => Empresa::find($p->IdEmpresa),
            'usuario' => DB::table('users')->where('IdUsuario', $p->IdUsuario)->value('apeusu'), 'formato' => 'A4', 'paraPdf' => true])->render();
        $pdf = new Dompdf;
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return response((string) $pdf->output(), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="proforma_'.Proformas::numero($p).'.pdf"']);
    }

    /** Imprimir directo en la impresora de caja (como el botón de la ticketera del Panel de Ventas) */
    public function directo($id)
    {
        $this->autorizar();
        $this->proforma($id);
        $sucursal = (int) Auth::user()->id_empresa_negocio;
        $impresora = Impresion::proforma((int) $id, $sucursal);

        return response()->json(['directa' => (bool) $impresora, 'mensaje' => ! $impresora
            ? 'No hay una impresora de caja configurada (menú Impresoras): usa «Ticket» o «PDF».'
            : (Impresion::agenteConectado($sucursal) ? 'Enviada a la impresora '.$impresora.'.'
                : 'En cola: el agente de impresión no está conectado, se imprimirá apenas se conecte.')]);
    }

    public function destroy($id)
    {
        $this->autorizar();
        $p = $this->proforma($id);
        if ($p->estado !== 'PENDIENTE') {
            return back()->with('error', 'Una proforma ya cobrada no se puede eliminar.');
        }
        DB::transaction(function () use ($p) {
            DB::table('proforma_detalle')->where('id_proforma', $p->id_proforma)->delete();
            DB::table('proformas')->where('id_proforma', $p->id_proforma)->delete();
        });

        return back()->with('success', 'Proforma '.Proformas::numero($p).' eliminada.');
    }
}
