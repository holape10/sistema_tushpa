<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Models\Producto;
use App\Support\Carta;
use App\Support\CartaMesa;
use App\Support\Delivery;
use App\Support\PedidoCarta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Carta digital con QR: la página pública que ven los clientes y su configuración e impresión de QR.
 */
class CartaController extends Controller
{
    /** Carta pública: /carta/{sucursal}?mesa={id} */
    public function ver(Request $request, ?int $sucursal = null): View
    {
        $negocio = Carta::sucursal($sucursal);
        if (! $negocio) {
            abort(response()->view('carta.no_disponible', [], 404));
        }
        $fila = $request->integer('mesa') ? $this->mesa($negocio, $request->integer('mesa')) : null;
        $mesa = $fila?->mes_nom;

        // Pedido delivery desde la carta (no en el QR de una mesa: ahí el cliente ya está en el local)
        $delivery = $negocio->carta_delivery && ! $mesa;
        // Desde el QR de su mesa el cliente pide, llama al mozo o pide la cuenta (si la sucursal lo permite)
        $pedidoMesa = CartaMesa::permite($negocio, $fila, $request->query('k'));

        return view('carta.ver', $this->datos($negocio) + [
            'categorias' => Carta::categorias($negocio->id_empresa_negocio),
            'mesa' => $mesa,
            'pedidoMesa' => $pedidoMesa ? ['id' => (int) $fila->mes_id, 'k' => (string) $request->query('k')] : null,
            'delivery' => $delivery,
            'mediosPago' => array_diff_key(Delivery::MEDIOS_PAGO, ['PAGADO' => 1]),
            'conTarifas' => $delivery && Delivery::tarifas($negocio->id_empresa_negocio),
            'local' => $negocio->local_lat !== null ? ['lat' => (float) $negocio->local_lat, 'lng' => (float) $negocio->local_lng] : null,
        ]);
    }

    /** Costo de envío a la ubicación del cliente (antes de enviar el pedido) */
    public function envio(Request $request, int $sucursal): JsonResponse
    {
        $negocio = Carta::sucursal($sucursal);
        abort_unless($negocio && $negocio->carta_delivery, 404);
        $d = $request->validate(['lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180']);

        return response()->json(Delivery::costoEnvio($sucursal, (float) $d['lat'], (float) $d['lng']));
    }

    /** El cliente envía su pedido delivery desde la carta */
    public function pedir(Request $request, int $sucursal): JsonResponse
    {
        $negocio = Carta::sucursal($sucursal);
        abort_unless($negocio, 404);
        $d = $request->validate([
            'items' => 'required|array|min:1|max:60',
            'items.*.id' => 'required|integer',
            'items.*.cantidad' => 'required|integer|min:1|max:99',
            'items.*.presentacion' => 'nullable|integer',
            'items.*.nota' => 'nullable|string|max:80',
            'cliente' => 'required|string|min:2|max:100',
            'telefono' => ['required', 'regex:/^\+?[\d\s]{9,15}$/'],
            'direccion' => 'required|string|min:5|max:200',
            'referencia' => 'nullable|string|max:200',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180|required_with:lat',
            'pago' => 'required|in:'.implode(',', array_diff(array_keys(Delivery::MEDIOS_PAGO), ['PAGADO'])),
            'paga_con' => 'nullable|numeric|min:0|max:9999',
            'nota' => 'nullable|string|max:150',
        ], ['telefono.regex' => 'Escribe tu número de WhatsApp (9 dígitos).'], [
            'cliente' => 'nombre', 'telefono' => 'WhatsApp', 'direccion' => 'dirección', 'pago' => 'medio de pago', 'paga_con' => 'monto con el que pagas',
        ]);

        try {
            $pedido = PedidoCarta::registrar($negocio, $d['items'], $d);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($pedido);
    }

    /** Desde el QR de su mesa: pedir platos, llamar al mozo o pedir la cuenta */
    public function solicitarMesa(Request $request, int $sucursal, int $mesa): JsonResponse
    {
        $negocio = Carta::sucursal($sucursal);
        abort_unless($negocio, 404);
        $fila = $this->mesa($negocio, $mesa);
        if (! CartaMesa::permite($negocio, $fila, $request->input('k'))) {
            return response()->json(['message' => 'Por ahora no se puede pedir desde esta mesa. Llama al mozo, por favor.'], 422);
        }
        $d = $request->validate([
            'tipo' => 'required|in:'.implode(',', CartaMesa::TIPOS),
            'items' => 'required_if:tipo,PEDIDO|array|max:40',
            'items.*.id' => 'required|integer',
            'items.*.cantidad' => 'required|integer|min:1|max:30',
            'items.*.presentacion' => 'nullable|integer',
            'items.*.nota' => 'nullable|string|max:80',
            'nombre' => 'nullable|string|max:60',
            'nota' => 'nullable|string|max:150',
        ], ['items.required_if' => 'Agrega al menos un producto.']);

        try {
            $r = CartaMesa::solicitar($negocio, $fila, $d['tipo'], $d['items'] ?? [], $d['nombre'] ?? null, $d['nota'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($r);
    }

    /** El cliente ve si el mozo ya aceptó su pedido */
    public function estadoSolicitud(int $sucursal, string $token): JsonResponse
    {
        $estado = CartaMesa::estado($sucursal, $token);
        abort_unless($estado, 404);

        return response()->json($estado);
    }

    public function configuracion(): View
    {
        $negocio = $this->negocioDelAdmin();

        return view('empresas.carta.configuracion', $this->datos($negocio) + [
            'url' => Carta::url($negocio->id_empresa_negocio),
            'qr' => Carta::qr(Carta::url($negocio->id_empresa_negocio), 220),
            'mesas' => $this->mesas($negocio->id_empresa_negocio),
            'categorias' => Carta::categorias($negocio->id_empresa_negocio),
            'sinCategoria' => DB::table('productos')->where('id_empresa_negocio', $negocio->id_empresa_negocio)
                ->where('proest', 'Activo')->whereNotIn('promocion', Producto::NO_VENDIBLES)->whereNull('cat_id')->count(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $negocio = $this->negocioDelAdmin();
        $datos = $request->validate([
            'carta_mensaje' => 'nullable|string|max:300',
            'carta_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'delivery_minimo' => 'nullable|numeric|min:0|max:99999',
        ], [], ['carta_mensaje' => 'mensaje', 'carta_color' => 'color', 'delivery_minimo' => 'pedido mínimo']);
        $negocio->update($datos + ['carta_activa' => $request->boolean('carta_activa'), 'carta_delivery' => $request->boolean('carta_delivery'),
            'carta_pedido_mesa' => $request->boolean('carta_pedido_mesa')]);

        return back()->with('success', $request->boolean('carta_activa') ? 'Carta digital publicada.' : 'Carta digital desactivada.');
    }

    /** Hojas para imprimir: el afiche con el QR general o una tarjeta con QR por cada mesa */
    public function imprimir(Request $request): View
    {
        $negocio = $this->negocioDelAdmin();
        $suc = $negocio->id_empresa_negocio;
        $elegidas = array_map('intval', (array) $request->input('mesas', []));
        $tarjetas = $request->input('tipo') === 'mesas'
            ? $this->mesas($suc)->when($elegidas, fn ($m) => $m->whereIn('mes_id', $elegidas))
                ->map(fn ($m) => ['titulo' => $m->mes_nom, 'sub' => $m->pis_nom, 'qr' => Carta::qr(Carta::url($suc, $m->mes_id), 300)])->values()
            : collect([['titulo' => null, 'sub' => null, 'qr' => Carta::qr(Carta::url($suc), 420)]]);

        return view('empresas.carta.imprimir', $this->datos($negocio) + [
            'tarjetas' => $tarjetas,
            'tipo' => $request->input('tipo') === 'mesas' ? 'mesas' : 'general',
        ]);
    }

    private function negocioDelAdmin(): EmpresaNegocio
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador configura la carta digital.');

        return EmpresaNegocio::findOrFail(Auth::user()->id_empresa_negocio);
    }

    private function mesa(EmpresaNegocio $negocio, int $mesId): ?object
    {
        return DB::table('mesas')->where('mes_id', $mesId)->where('id_empresa_negocio', $negocio->id_empresa_negocio)->first(['mes_id', 'mes_nom']);
    }

    private function mesas(int $sucursal): Collection
    {
        return DB::table('mesas as m')->leftJoin('pisos as p', 'p.pis_id', '=', 'm.pis_id')
            ->where('m.id_empresa_negocio', $sucursal)->orderBy('m.pis_id')->orderBy('m.mes_id')
            ->get(['m.mes_id', 'm.mes_nom', 'p.pis_nom']);
    }

    /**
     * @return array{negocio: EmpresaNegocio, empresa: ?Empresa, nombre: string, logo: ?string, color: string}
     */
    private function datos(EmpresaNegocio $negocio): array
    {
        $empresa = Empresa::find($negocio->IdEmpresa);

        return [
            'negocio' => $negocio,
            'empresa' => $empresa,
            'nombre' => $negocio->nombre_comercial ?: ($empresa->NomEmpresa ?? 'Nuestra carta'),
            'logo' => collect([$negocio->logo_suc, $empresa->LogEmpresa ?? null])->first(fn ($l) => $l && is_file(public_path($l))),
            'color' => Carta::color($negocio),
        ];
    }
}
