<?php
namespace App\Http\Controllers;

use App\Models\{Pedido, PedidoDetalle, Piso, Mesa, Producto, Categoria};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class ComandasController extends Controller
{
    public function seleccionServicio()
    {
        $id_empresa_negocio = Auth::user()->id_empresa_negocio;
        $pisos = Piso::where('id_empresa_negocio', $id_empresa_negocio)->get();
        $primerPisoId = $pisos->first()?->pis_id;
        $mesas = collect();

        if ($primerPisoId) {
            $mesas = $this->mesasDelPiso($primerPisoId, $id_empresa_negocio);
        }

        session()->forget(['comanda_cart', 'comanda_order_type', 'comanda_mesa_id', 'comanda_mesa_nombre', 'comanda_pedido_id']);

        return view('empresas.comandas.seleccion_servicio', compact('pisos', 'mesas', 'primerPisoId'));
    }

    private function mesasDelPiso($piso_id, $id_empresa_negocio)
    {
        return Mesa::leftJoin('pedidos', function ($join) {
                $join->on('mesas.mes_id', '=', 'pedidos.mes_id')->where('pedidos.ped_est', 'Aperturado');
            })
            ->where('mesas.pis_id', $piso_id)
            ->where('mesas.id_empresa_negocio', $id_empresa_negocio)
            ->select('mesas.*', DB::raw('MAX(pedidos.ped_id) as pedido_id'), DB::raw('MAX(pedidos.ped_tot) as ped_tot'), DB::raw('MAX(pedidos.fecha_hora) as pedido_fecha_hora'))
            ->groupBy('mesas.mes_id')
            ->orderBy('mesas.mes_nom')
            ->get();
    }

    public function getMesasPorPiso($piso_id)
    {
        $mesas = $this->mesasDelPiso($piso_id, Auth::user()->id_empresa_negocio);
        return response()->json([
            'vista' => view('empresas.comandas.partials.mesas_grid', compact('mesas'))->render(),
        ]);
    }

    public function setServiceData(Request $request)
    {
        session()->put('comanda_order_type', $request->order_type);
        session()->put('comanda_mesa_id', $request->mesa_id);
        session()->put('comanda_mesa_nombre', $request->mesa_nombre);

        if ($request->pedido_id) {
            session()->put('comanda_pedido_id', $request->pedido_id);
            session()->forget('comanda_cart');
        } else {
            session()->forget(['comanda_pedido_id', 'comanda_cart']);
        }

        return response()->json(['success' => true]);
    }

    public function getPedidoDetails($ped_id)
    {
        $pedido = Pedido::findOrFail($ped_id);
        $detalles = PedidoDetalle::where('ped_id', $ped_id)->where('estadoitem', '!=', 'Eliminado')->get();
        $total = $detalles->sum(fn($d) => $d->ped_det_can * $d->ped_det_pre);

        return response()->json(['success' => true, 'pedido' => $pedido, 'detalles' => $detalles, 'total' => $total]);
    }

    public function activosLlevarDelivery()
    {
        $id_empresa_negocio = Auth::user()->id_empresa_negocio;

        $pedidos = Pedido::where('id_empresa_negocio', $id_empresa_negocio)
            ->whereIn('ped_tip', ['Llevar', 'Delivery'])
            ->where('ped_est', 'Aperturado')
            ->orderBy('fecha_hora')
            ->get();

        return response()->json(['success' => true, 'pedidos' => $pedidos]);
    }

    public function menuPedido()
    {
        $id_empresa_negocio = Auth::user()->id_empresa_negocio;
        $order_type = session('comanda_order_type');

        if (empty($order_type)) {
            return redirect()->route('comandas.seleccion')->with('error', 'Selecciona una mesa o "Para llevar" primero.');
        }

        $categorias = Categoria::where('id_empresa_negocio', $id_empresa_negocio)->where('visible', 1)->get();
        $cat_default_id = $categorias->firstWhere('predeterminado', 1)?->cat_id ?? $categorias->first()?->cat_id;

        $pedidoId = session('comanda_pedido_id');
        $cart = session('comanda_cart', []);

        // Si hay un pedido existente en la mesa y el carrito está vacío, lo cargamos
        if ($pedidoId && empty($cart)) {
            $detalles = PedidoDetalle::where('ped_id', $pedidoId)
                ->where('estadoitem', '!=', 'Eliminado')
                ->get();

            $cart = [];
            foreach ($detalles as $d) {
                $cart[(string) $d->IdProducto] = [
                    'id' => (string) $d->IdProducto,
                    'nombre' => $d->descripcion,
                    'precio' => (float) $d->ped_det_pre,
                    'cantidad' => (int) $d->ped_det_can,
                    'observaciones' => $d->item_obs,
                    'is_old_item' => true,
                ];
            }
            session()->put('comanda_cart', $cart);
        }

        $mesa_info = $order_type == 'salon'
            ? ['id' => session('comanda_mesa_id'), 'nombre' => session('comanda_mesa_nombre')]
            : ['nombre' => 'PARA LLEVAR'];

        return view('empresas.comandas.menu_pedido', compact('categorias', 'cat_default_id', 'cart', 'order_type', 'mesa_info'));
    }

    public function searchProducts(Request $request)
    {
        $id_empresa_negocio = Auth::user()->id_empresa_negocio;
        $almacen_id = \App\Models\Almacen::where('id_empresa_negocio', $id_empresa_negocio)
            ->where('predeterminado', 1)->value('id_almacen');

        $productos = Producto::leftJoin('producto_stock', function ($join) use ($almacen_id) {
                $join->on('productos.IdProducto', '=', 'producto_stock.IdProducto')
                     ->where('producto_stock.id_almacen', $almacen_id);
            })
            ->where('productos.id_empresa_negocio', $id_empresa_negocio)
            ->where('productos.proest', 'Activo')
            ->where('productos.promocion', '!=', 4)
            ->when($request->search_text, fn($q) => $q->where('productos.pronom', 'like', '%' . $request->search_text . '%'))
            ->when($request->category_id && !$request->search_text, fn($q) => $q->where('productos.cat_id', $request->category_id))
            ->select('productos.*', 'producto_stock.stock as stock_disponible')
            ->orderBy('productos.pronom')
            ->get();

        return response()->json([
            'vista' => view('empresas.comandas.partials.productos_grid', compact('productos'))->render(),
        ]);
    }

    public function clearCart()
    {
        $cart = session('comanda_cart', []);
        $newCart = array_filter($cart, fn($item) => !empty($item['is_old_item']));
        session()->put('comanda_cart', $newCart);
        return response()->json(['success' => true]);
    }

    public function addToCart(Request $request)
    {
        $cart = session('comanda_cart', []);
        $id = $request->id;

        if (isset($cart[$id]) && empty($cart[$id]['is_old_item'])) {
            $cart[$id]['cantidad']++;
        } else {
            $cart[$id] = [
                'id' => $id,
                'nombre' => $request->producto,
                'precio' => (float) $request->precio,
                'cantidad' => 1,
                'observaciones' => '',
                'is_old_item' => false,
            ];
        }

        session()->put('comanda_cart', $cart);
        return response()->json(['success' => true]);
    }

    public function updateCartItem(Request $request)
    {
        $cart = session('comanda_cart', []);
        $id = $request->id;

        if (!isset($cart[$id])) {
            return response()->json(['success' => false, 'message' => 'Producto no encontrado.']);
        }

        $cart[$id]['cantidad'] = max(1, (int) $request->cantidad);
        $cart[$id]['observaciones'] = $request->observaciones;
        session()->put('comanda_cart', $cart);

        return response()->json(['success' => true]);
    }

    public function removeCartItem(Request $request)
    {
        $cart = session('comanda_cart', []);
        $id = $request->id;

        if (isset($cart[$id]) && !empty($cart[$id]['is_old_item'])) {
            return response()->json(['success' => false, 'message' => 'No se puede eliminar un ítem ya comandado desde aquí.']);
        }

        unset($cart[$id]);
        session()->put('comanda_cart', $cart);
        return response()->json(['success' => true]);
    }

    public function getCartDetails()
    {
        $cart = session('comanda_cart', []);
        return response()->json([
            'vista' => view('empresas.comandas.partials.cart_details', compact('cart'))->render(),
        ]);
    }

    public function enviarComanda(Request $request)
    {
        $cart = session('comanda_cart', []);
        if (empty($cart)) {
            return response()->json(['success' => false, 'message' => 'El carrito está vacío.']);
        }

        $order_type = session('comanda_order_type', 'salon');
        $mesa_id = session('comanda_mesa_id');
        $pedidoId = session('comanda_pedido_id');
        $usuario = Auth::user();

        $total = collect($cart)->sum(fn($i) => $i['cantidad'] * $i['precio']);

        DB::transaction(function () use (&$pedidoId, $cart, $order_type, $mesa_id, $usuario, $total) {
            $pis_id = null;
            if ($order_type == 'salon' && $mesa_id) {
                $pis_id = Mesa::where('mes_id', $mesa_id)->value('pis_id');
            }

            if ($pedidoId) {
                Pedido::where('ped_id', $pedidoId)->update([
                    'ped_tot' => $total,
                    'fecha_hora_modificacion' => now(),
                ]);
            } else {
                $pedidoId = Pedido::insertGetId([
                    'ped_tip' => $order_type == 'salon' ? 'Salon' : ucfirst($order_type),
                    'ped_fec' => now()->toDateString(),
                    'fecha_hora' => now(),
                    'pis_id' => $pis_id,
                    'mes_id' => $order_type == 'salon' ? $mesa_id : null,
                    'IdEmpresa' => $usuario->IdEmpresa,
                    'id_empresa_negocio' => $usuario->id_empresa_negocio,
                    'ped_est' => 'Aperturado',
                    'mozo' => $usuario->IdUsuario,
                    'IdUsuario' => $usuario->IdUsuario,
                    'ped_cli_nom' => $order_type == 'salon' ? 'CONSUMO EN SALON' : 'PARA LLEVAR',
                    'ped_tot' => $total,
                ]);
            }

            foreach ($cart as $item) {
                if (!empty($item['is_old_item'])) {
                    PedidoDetalle::where('ped_id', $pedidoId)->where('IdProducto', $item['id'])->update([
                        'ped_det_can' => $item['cantidad'],
                        'item_obs' => $item['observaciones'],
                    ]);
                } else {
                    PedidoDetalle::create([
                        'ped_id' => $pedidoId,
                        'IdProducto' => $item['id'],
                        'IdEmpresa' => $usuario->IdEmpresa,
                        'descripcion' => $item['nombre'],
                        'detalle' => $item['nombre'],
                        'ped_det_can' => $item['cantidad'],
                        'ped_det_pre' => $item['precio'],
                        'item_obs' => $item['observaciones'],
                        'estadoitem' => 'Ingresado',
                        'impreso' => 'imprimir',
                        'fecha_hora' => now(),
                    ]);
                }
            }

            if ($order_type == 'salon' && $mesa_id) {
                Mesa::where('mes_id', $mesa_id)->update(['mes_est' => 'Ocupado']);
            }
        });

        session()->put('comanda_pedido_id', $pedidoId);
        session()->forget(['comanda_cart', 'comanda_order_type', 'comanda_mesa_id', 'comanda_mesa_nombre']);

        return response()->json(['success' => true, 'message' => 'Comanda enviada correctamente.', 'pedido_id' => $pedidoId]);
    }
}