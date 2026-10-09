<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\EmpresaNegocio;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Piso;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\User;
use App\Support\Cocina;
use App\Support\Impresion\Impresion;
use App\Support\Precios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Carrito de la comanda (en sesión), UNA línea por producto y presentación (clave = IdProducto, o IdProducto-p{id} si se
 * eligió una presentación como TAJADA / ENTERA):
 *  - is_old_item = true  -> ya enviado a cocina; guarda los ped_det_id que agrupa (si había líneas repetidas
 *    se fusionan en una al enviar). Bajar de lo enviado pide autorización; nunca se baja de lo ya cobrado.
 *  - is_old_item = false -> producto nuevo en esta edición.
 */
class ComandasController extends Controller
{
    private const SESION = ['comanda_cart', 'comanda_order_type', 'comanda_mesa_id', 'comanda_mesa_nombre', 'comanda_pedido_id', 'comanda_eliminados', 'comanda_reserva_id', 'comanda_mot_id'];

    public function seleccionServicio()
    {
        $id_empresa_negocio = Auth::user()->id_empresa_negocio;
        $pisos = Piso::where('id_empresa_negocio', $id_empresa_negocio)->get();
        $primerPisoId = $pisos->first()?->pis_id;
        $mesas = collect();

        if ($primerPisoId) {
            $mesas = $this->mesasDelPiso($primerPisoId, $id_empresa_negocio);
        }

        session()->forget(self::SESION);

        // El mozo solo toma pedidos: no ve los botones de cobro
        $puedeCobrar = Auth::user()->esAdminOCaja();

        return view('empresas.comandas.seleccion_servicio', compact('pisos', 'mesas', 'primerPisoId', 'puedeCobrar'));
    }

    private function pedidoAbierto($ped_id): Pedido
    {
        return Pedido::where('ped_id', $ped_id)
            ->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->where('ped_est', 'Aperturado')
            ->firstOrFail();
    }

    /** Precuenta: lo que va consumiendo la mesa (no es comprobante) */
    public function precuenta($ped_id)
    {
        $pedido = $this->pedidoAbierto($ped_id);
        $lineas = PedidoDetalle::where('ped_id', $pedido->ped_id)->where('estadoitem', '!=', 'Eliminado')->get();

        $items = $lineas->groupBy(fn ($d) => $d->IdProducto.'|'.$d->id_presentacion.'|'.$d->ped_det_pre)->map(fn ($g) => (object) [
            'descripcion' => $g->first()->descripcion,
            'precio' => (float) $g->first()->ped_det_pre,
            'cantidad' => (float) $g->sum('ped_det_can'),
            'pagado' => (float) $g->sum('item_facturado'),
        ])->values();

        $total = round($items->sum(fn ($i) => $i->cantidad * $i->precio), 2);
        $pagado = round($items->sum(fn ($i) => $i->pagado * $i->precio), 2);

        return view('empresas.comandas.precuenta', [
            'pedido' => $pedido, 'items' => $items, 'total' => $total, 'pagado' => $pagado,
            'mesa' => $pedido->mes_id ? Mesa::find($pedido->mes_id) : null,
            'piso' => $pedido->pis_id ? Piso::find($pedido->pis_id) : null,
            'mozo' => User::find($pedido->mozo)?->apeusu,
            'negocio' => EmpresaNegocio::find($pedido->id_empresa_negocio),
        ]);
    }

    /** Mesas para cambiar (libres) o unir (ocupadas, menos la actual) */
    public function mesasDisponibles(Request $request)
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        $abiertos = Pedido::where('id_empresa_negocio', $sucursal)->where('ped_est', 'Aperturado')
            ->whereNotNull('mes_id')->pluck('ped_id', 'mes_id');

        $mesas = Mesa::leftJoin('pisos', 'pisos.pis_id', '=', 'mesas.pis_id')
            ->where('mesas.id_empresa_negocio', $sucursal)
            ->orderBy('pisos.pis_nom')->orderBy('mesas.mes_nom')
            ->get(['mesas.mes_id', 'mesas.mes_nom', 'pisos.pis_nom'])
            ->filter(fn ($m) => $request->tipo === 'ocupadas'
                ? isset($abiertos[$m->mes_id]) && (int) $abiertos[$m->mes_id] !== (int) $request->ped_id
                : ! isset($abiertos[$m->mes_id]))
            ->map(fn ($m) => ['mes_id' => $m->mes_id, 'nombre' => $m->mes_nom, 'piso' => $m->pis_nom, 'ped_id' => $abiertos[$m->mes_id] ?? null])
            ->values();

        return response()->json(['success' => true, 'mesas' => $mesas]);
    }

    public function cambiarMesa(Request $request)
    {
        $request->validate(['ped_id' => 'required|integer', 'mes_id' => 'required|integer']);
        $sucursal = Auth::user()->id_empresa_negocio;

        try {
            DB::transaction(function () use ($request, $sucursal) {
                $pedido = Pedido::where('ped_id', $request->ped_id)->where('id_empresa_negocio', $sucursal)
                    ->where('ped_est', 'Aperturado')->lockForUpdate()->first();
                $destino = Mesa::where('mes_id', $request->mes_id)->where('id_empresa_negocio', $sucursal)->lockForUpdate()->first();
                if (! $pedido || ! $pedido->mes_id) {
                    throw new \RuntimeException('El pedido ya no está abierto o no es de salón.');
                }
                if (! $destino) {
                    throw new \RuntimeException('Mesa no válida.');
                }
                if (Pedido::where('mes_id', $destino->mes_id)->where('ped_est', 'Aperturado')->exists()) {
                    throw new \RuntimeException("La {$destino->mes_nom} ya está ocupada. Usa Unir mesa.");
                }

                $origen = $pedido->mes_id;
                $pedido->update(['mes_id' => $destino->mes_id, 'pis_id' => $destino->pis_id, 'fecha_hora_modificacion' => now()]);
                Mesa::where('mes_id', $origen)->update(['mes_est' => 'Libre']);
                $destino->update(['mes_est' => 'Ocupado']);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    /** Une el pedido de otra mesa (origen) dentro del pedido actual (destino) y libera la mesa origen */
    public function unirMesa(Request $request)
    {
        $request->validate(['ped_id' => 'required|integer', 'ped_id_origen' => 'required|integer|different:ped_id']);
        $sucursal = Auth::user()->id_empresa_negocio;

        try {
            DB::transaction(function () use ($request, $sucursal) {
                $pedidos = Pedido::whereIn('ped_id', [$request->ped_id, $request->ped_id_origen])
                    ->where('id_empresa_negocio', $sucursal)->where('ped_est', 'Aperturado')
                    ->orderBy('ped_id')->lockForUpdate()->get()->keyBy('ped_id');
                $destino = $pedidos[$request->ped_id] ?? null;
                $origen = $pedidos[$request->ped_id_origen] ?? null;
                if (! $destino || ! $origen) {
                    throw new \RuntimeException('Alguno de los pedidos ya no está abierto.');
                }
                if (PedidoDetalle::where('ped_id', $origen->ped_id)->where('item_facturado', '>', 0)->exists()) {
                    throw new \RuntimeException('La otra mesa ya tiene cuentas separadas cobradas; termina de cobrarla antes de unirla.');
                }

                PedidoDetalle::where('ped_id', $origen->ped_id)->update(['ped_id' => $destino->ped_id]);

                $total = (float) PedidoDetalle::where('ped_id', $destino->ped_id)->where('estadoitem', '!=', 'Eliminado')
                    ->sum(DB::raw('ped_det_can * ped_det_pre'));
                $destino->update(['ped_tot' => $total, 'fecha_hora_modificacion' => now()]);

                $origen->update(['ped_est' => 'Unido', 'ped_tot' => 0, 'fecha_hora_modificacion' => now(),
                    'ped_obs' => mb_substr("Unido al pedido {$destino->ped_id}", 0, 255)]);
                if ($origen->mes_id) {
                    Mesa::where('mes_id', $origen->mes_id)->update(['mes_est' => 'Libre']);
                }
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    private function mesasDelPiso($piso_id, $id_empresa_negocio)
    {
        $mesas = Mesa::leftJoin('pedidos', function ($join) {
            $join->on('mesas.mes_id', '=', 'pedidos.mes_id')->where('pedidos.ped_est', 'Aperturado');
        })
            ->where('mesas.pis_id', $piso_id)
            ->where('mesas.id_empresa_negocio', $id_empresa_negocio)
            ->select('mesas.*', DB::raw('MAX(pedidos.ped_id) as pedido_id'), DB::raw('MAX(pedidos.ped_tot) as ped_tot'), DB::raw('MAX(pedidos.fecha_hora) as pedido_fecha_hora'))
            ->groupBy('mesas.mes_id')
            ->orderBy('mesas.mes_nom')
            ->get();

        // Cocina terminó y falta llevarlo a la mesa (pantalla de cocina)
        $listos = DB::table('cocina_tickets')->whereIn('ped_id', $mesas->pluck('pedido_id')->filter())
            ->whereNotNull('listo')->whereNull('entregado')->where('tipo', '!=', 'ANULACION')
            ->groupBy('ped_id')->select('ped_id', DB::raw('COUNT(*) as n'))->pluck('n', 'ped_id');

        // Próxima reserva de hoy (en las siguientes 3 horas) de cada mesa
        $reservas = DB::table('reservas')->where('id_empresa_negocio', $id_empresa_negocio)
            ->whereIn('mes_id', $mesas->pluck('mes_id'))->where('fecha_reserva', now()->toDateString())
            ->whereIn('estado', ['Pendiente', 'Confirmada'])
            // Rango de horas del mismo día (cerca de la medianoche no se pasa al día siguiente)
            ->whereBetween('hora_inicio', [
                now()->subMinutes(30)->isSameDay(now()) ? now()->subMinutes(30)->format('H:i:s') : '00:00:00',
                now()->addHours(3)->isSameDay(now()) ? now()->addHours(3)->format('H:i:s') : '23:59:59',
            ])
            ->orderBy('hora_inicio')->get(['mes_id', 'hora_inicio', 'nombre_cliente'])->unique('mes_id')->keyBy('mes_id');

        return $mesas->each(function ($m) use ($listos, $reservas) {
            $m->listos = (int) ($listos[$m->pedido_id] ?? 0);
            $m->reserva = $reservas[$m->mes_id] ?? null;
        });
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
        $id_empresa_negocio = Auth::user()->id_empresa_negocio;
        $orderType = $request->order_type;
        $pedidoId = $request->pedido_id;

        if (! in_array($orderType, ['salon', 'llevar', 'delivery'], true)) {
            return response()->json(['success' => false, 'message' => 'Tipo de pedido no válido.'], 422);
        }

        // La mesa y el pedido deben ser de la sucursal del usuario
        if ($orderType === 'salon') {
            $mesa = Mesa::where('mes_id', $request->mesa_id)->where('id_empresa_negocio', $id_empresa_negocio)->first();
            if (! $mesa) {
                return response()->json(['success' => false, 'message' => 'Mesa no válida.'], 403);
            }
            // Si la pantalla estaba desactualizada y la mesa ya tiene pedido abierto, se continúa ese pedido
            if (! $pedidoId) {
                $pedidoId = Pedido::where('mes_id', $mesa->mes_id)->where('ped_est', 'Aperturado')->value('ped_id');
            }
        }

        if ($pedidoId && ! Pedido::where('ped_id', $pedidoId)->where('id_empresa_negocio', $id_empresa_negocio)->where('ped_est', 'Aperturado')->exists()) {
            return response()->json(['success' => false, 'message' => 'El pedido ya fue cobrado o no existe.'], 409);
        }

        session()->forget(self::SESION);
        session()->put('comanda_order_type', $orderType);
        session()->put('comanda_mesa_id', $orderType === 'salon' ? (int) $request->mesa_id : null);
        session()->put('comanda_mesa_nombre', $request->mesa_nombre);
        if ($pedidoId) {
            session()->put('comanda_pedido_id', (int) $pedidoId);
        }

        return response()->json(['success' => true]);
    }

    public function getPedidoDetails($ped_id)
    {
        $pedido = Pedido::where('ped_id', $ped_id)
            ->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->firstOrFail();
        $detalles = PedidoDetalle::where('ped_id', $ped_id)->where('estadoitem', '!=', 'Eliminado')->get();
        $total = $detalles->sum(fn ($d) => $d->ped_det_can * $d->ped_det_pre);

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
        $cart = session('comanda_cart');

        // Al entrar a editar un pedido se cargan sus ítems actuales (una sola vez por edición)
        if ($pedidoId && $cart === null) {
            $cart = [];
            $detalles = PedidoDetalle::where('ped_id', $pedidoId)
                ->where('estadoitem', '!=', 'Eliminado')
                ->orderBy('ped_det_id')
                ->get();

            // Las líneas repetidas del mismo producto se muestran juntas en una sola
            foreach ($detalles->groupBy(fn ($d) => $d->IdProducto.'|'.$d->id_presentacion) as $lineas) {
                $idProducto = $lineas->first()->IdProducto;
                $presentacion = $lineas->first()->id_presentacion;
                $key = $presentacion ? $idProducto.'-p'.$presentacion : (string) $idProducto;
                $enviado = (float) $lineas->sum('ped_det_can');
                $obs = $lineas->pluck('item_obs')->filter()->unique()->implode(' / ');
                $cart[$key] = [
                    'id' => $key,
                    'IdProducto' => (int) $idProducto,
                    'presentacion' => $presentacion ? (int) $presentacion : null,
                    'ped_det_ids' => $lineas->pluck('ped_det_id')->map(fn ($v) => (int) $v)->all(),
                    'nombre' => $lineas->first()->descripcion,
                    'precio' => (float) $lineas->first()->ped_det_pre,
                    'cantidad' => $enviado,
                    'cantidad_original' => $enviado,  // lo enviado a cocina: bajar de aquí pide autorización
                    'cantidad_minima' => $enviado,    // baja con cada reducción autorizada
                    'facturado' => (float) $lineas->sum('item_facturado'), // ya cobrado en cuentas separadas: no se puede bajar
                    'observaciones' => $obs,
                    'is_old_item' => true,
                ];
            }
            session()->put('comanda_cart', $cart);
        }
        $cart ??= [];

        $mesa_info = $order_type == 'salon'
            ? ['id' => session('comanda_mesa_id'), 'nombre' => session('comanda_mesa_nombre')]
            : ['nombre' => strtoupper($order_type === 'llevar' ? 'PARA LLEVAR' : $order_type)];

        $esAdmin = Auth::user()->esAdmin(); // el admin reduce o elimina sin pedir clave (solo el motivo)

        // Delivery: motorizado elegido (el del pedido si ya existe)
        $motorizados = $order_type === 'delivery' ? MotorizadoController::activos($id_empresa_negocio) : collect();
        $motActual = $order_type === 'delivery' ? ($pedidoId ? Pedido::where('ped_id', $pedidoId)->value('mot_id') : session('comanda_mot_id')) : null;

        return view('empresas.comandas.menu_pedido', compact('categorias', 'cat_default_id', 'cart', 'order_type', 'mesa_info', 'esAdmin', 'motorizados', 'motActual'));
    }

    public function searchProducts(Request $request)
    {
        $id_empresa_negocio = Auth::user()->id_empresa_negocio;
        $almacen_id = Almacen::where('id_empresa_negocio', $id_empresa_negocio)
            ->where('predeterminado', 1)->value('id_almacen');

        $productos = Producto::leftJoin('producto_stock', function ($join) use ($almacen_id) {
            $join->on('productos.IdProducto', '=', 'producto_stock.IdProducto')
                ->where('producto_stock.id_almacen', $almacen_id);
        })
            ->where('productos.id_empresa_negocio', $id_empresa_negocio)
            ->where('productos.proest', 'Activo')
            ->where('productos.promocion', '!=', 4)
            ->when($request->search_text, fn ($q) => $q->where('productos.pronom', 'like', '%'.$request->search_text.'%'))
            ->when($request->category_id && ! $request->search_text, fn ($q) => $q->where('productos.cat_id', $request->category_id))
            ->select('productos.*', 'producto_stock.stock as stock_disponible')
            ->orderBy('productos.pronom')
            ->get();
        // Precio dinámico (happy hour, fin de semana…) vigente en este momento
        $precios = Precios::vigentes($productos);
        $presentaciones = Precios::presentaciones($productos->pluck('IdProducto'));

        return response()->json([
            'vista' => view('empresas.comandas.partials.productos_grid', compact('productos', 'precios', 'presentaciones'))->render(),
        ]);
    }

    public function clearCart(Request $request)
    {
        // Solo se vacía lo nuevo; lo ya enviado a cocina se mantiene
        $cart = session('comanda_cart', []);
        $newCart = array_filter($cart, fn ($item) => ! empty($item['is_old_item']));
        if (count($newCart) === count($cart)) {
            return response()->json(['success' => false, 'message' => 'No hay productos nuevos para vaciar.']);
        }

        // Requiere usuario y contraseña de un Administrador (si el que vacía ya es admin, basta con confirmar)
        if (! Auth::user()->esAdmin()) {
            $request->validate(['auth_user' => 'required|string', 'auth_password' => 'required|string'],
                [], ['auth_user' => 'Usuario', 'auth_password' => 'Contraseña']);
            if ($error = $this->validarAutorizacionAdminCaja($request, true)) {
                return response()->json(['success' => false, 'message' => $error]);
            }
        }

        session()->put('comanda_cart', $newCart);

        return response()->json(['success' => true]);
    }

    public function addToCart(Request $request)
    {
        // Nombre y precio salen de la BD, nunca del navegador
        $producto = Producto::where('IdProducto', $request->id)
            ->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->where('proest', 'Activo')
            ->where('promocion', '!=', 4)
            ->first();

        if (! $producto) {
            return response()->json(['success' => false, 'message' => 'Producto no disponible.'], 404);
        }

        $presentacion = null;
        if ($request->filled('presentacion')) {
            $presentacion = ProductoPresentacion::where('id_presentacion', $request->integer('presentacion'))
                ->where('IdProducto', $producto->IdProducto)->where('estado', 1)->first();
            if (! $presentacion) {
                return response()->json(['success' => false, 'message' => 'Esa presentación ya no está disponible.'], 404);
            }
        }

        $cart = session('comanda_cart', []);
        $id = $presentacion ? $producto->IdProducto.'-p'.$presentacion->id_presentacion : (string) $producto->IdProducto;

        if (isset($cart[$id])) {
            $cart[$id]['cantidad']++;
        } else {
            $cart[$id] = [
                'id' => $id,
                'IdProducto' => (int) $producto->IdProducto,
                'presentacion' => $presentacion?->id_presentacion,
                'nombre' => $presentacion ? mb_substr($producto->pronom.' - '.$presentacion->nombre, 0, 150) : $producto->pronom,
                'precio' => $presentacion
                    ? ((float) $presentacion->precio > 0 ? (float) $presentacion->precio : round(Precios::de($producto) * (float) $presentacion->factor, 2))
                    : Precios::de($producto),
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
        $id = (string) $request->id;

        if (! isset($cart[$id])) {
            return response()->json(['success' => false, 'message' => 'Producto no encontrado.']);
        }

        if ($request->has('cantidad')) {
            $nueva = max(1, (float) $request->cantidad);

            if ($nueva < ($cart[$id]['facturado'] ?? 0)) {
                return response()->json(['success' => false, 'message' => 'Ya se cobraron '.$cart[$id]['facturado'].' de este producto; no se puede bajar de ahí.']);
            }

            // Bajar un ítem ya comandado por debajo de lo autorizado requiere Admin/Caja
            if (! empty($cart[$id]['is_old_item']) && $nueva < ($cart[$id]['cantidad_minima'] ?? $cart[$id]['cantidad_original'])) {
                return response()->json(['success' => false, 'requiere_autorizacion' => true]);
            }
            $cart[$id]['cantidad'] = $nueva;
        }

        // Las observaciones solo cambian si se mandan (antes se borraban al tocar + / -)
        if ($request->has('observaciones') && $request->observaciones !== null) {
            $cart[$id]['observaciones'] = mb_substr(trim($request->observaciones), 0, 200);
        }

        session()->put('comanda_cart', $cart);

        return response()->json(['success' => true]);
    }

    /** Reglas del pedido de autorización: el Administrador no necesita escribir usuario ni contraseña */
    private function reglasAutorizacion(): array
    {
        $req = Auth::user()->esAdmin() ? 'nullable' : 'required';

        return ['auth_user' => "$req|string", 'auth_password' => "$req|string", 'reason' => 'required|string|max:150'];
    }

    private function autorizadoPor(Request $request): string
    {
        return Auth::user()->esAdmin() ? Auth::user()->email : (string) $request->auth_user;
    }

    private function validarAutorizacionAdminCaja(Request $request, bool $soloAdmin = false): ?string
    {
        // El Administrador logueado reduce o elimina directamente
        if (Auth::user()->esAdmin()) {
            return null;
        }

        // Límite de intentos para que no se pueda adivinar la clave del admin desde la comanda
        $llave = 'autoriza-comanda:'.Auth::id();
        if (RateLimiter::tooManyAttempts($llave, 5)) {
            return 'Demasiados intentos. Espera '.RateLimiter::availableIn($llave).' segundos.';
        }

        // Solo usuarios de la misma sucursal pueden autorizar
        $user = User::where('email', $request->auth_user)
            ->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->first();

        if (! $user || ! Hash::check($request->auth_password, $user->password)) {
            RateLimiter::hit($llave, 60);

            return 'Usuario o contraseña incorrectos.';
        }

        RateLimiter::clear($llave);

        if ($soloAdmin) {
            return $user->esAdmin() ? null : 'Solo un Administrador puede autorizar esto.';
        }

        return $user->esAdminOCaja() ? null : 'Ese usuario no tiene permisos de Admin/Caja para autorizar esto.';
    }

    public function reducirItemAutorizado(Request $request)
    {
        $request->validate(['id' => 'required', 'cantidad' => 'required|numeric|min:1'] + $this->reglasAutorizacion(),
            [], ['auth_user' => 'Usuario', 'auth_password' => 'Contraseña', 'reason' => 'Motivo']);

        if ($error = $this->validarAutorizacionAdminCaja($request)) {
            return response()->json(['success' => false, 'message' => $error]);
        }

        $cart = session('comanda_cart', []);
        $id = (string) $request->id;

        if (! isset($cart[$id]) || empty($cart[$id]['is_old_item'])) {
            return response()->json(['success' => false, 'message' => 'Ítem no encontrado en el pedido original.']);
        }
        if ($request->cantidad >= $cart[$id]['cantidad']) {
            return response()->json(['success' => false, 'message' => 'Esa cantidad no reduce el ítem, usa los botones normales.']);
        }
        if ($request->cantidad < ($cart[$id]['facturado'] ?? 0)) {
            return response()->json(['success' => false, 'message' => 'Ya se cobraron '.$cart[$id]['facturado'].' de este producto; no se puede bajar de ahí.']);
        }

        $cart[$id]['cantidad'] = (float) $request->cantidad;
        $cart[$id]['cantidad_minima'] = (float) $request->cantidad;
        $cart[$id]['motivo_reduccion'] = trim($request->reason).' - Autorizado por: '.$this->autorizadoPor($request);
        session()->put('comanda_cart', $cart);

        return response()->json(['success' => true]);
    }

    public function eliminarItemAutorizado(Request $request)
    {
        $request->validate(['id' => 'required'] + $this->reglasAutorizacion(),
            [], ['auth_user' => 'Usuario', 'auth_password' => 'Contraseña', 'reason' => 'Motivo']);

        if ($error = $this->validarAutorizacionAdminCaja($request)) {
            return response()->json(['success' => false, 'message' => $error]);
        }

        $cart = session('comanda_cart', []);
        $id = (string) $request->id;

        if (! isset($cart[$id]) || empty($cart[$id]['is_old_item'])) {
            return response()->json(['success' => false, 'message' => 'Ítem no encontrado en el pedido original.']);
        }

        if (($cart[$id]['facturado'] ?? 0) > 0) {
            return response()->json(['success' => false, 'message' => 'Parte de este producto ya se cobró en una cuenta separada; solo puedes reducirlo hasta '.$cart[$id]['facturado'].'.']);
        }

        $pedDetIds = $cart[$id]['ped_det_ids'] ?? [];
        unset($cart[$id]);
        session()->put('comanda_cart', $cart);

        // Se aplica de verdad recién cuando se envía la comanda
        $eliminados = session('comanda_eliminados', []);
        $eliminados[] = ['ped_det_ids' => $pedDetIds, 'motivo' => trim($request->reason), 'autorizado_por' => $this->autorizadoPor($request)];
        session()->put('comanda_eliminados', $eliminados);

        return response()->json(['success' => true]);
    }

    public function removeCartItem(Request $request)
    {
        $cart = session('comanda_cart', []);
        $id = (string) $request->id;

        if (isset($cart[$id]) && ! empty($cart[$id]['is_old_item'])) {
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
            'total' => round(collect($cart)->sum(fn ($i) => $i['cantidad'] * $i['precio']), 2),
        ]);
    }

    public function enviarComanda(Request $request)
    {
        $cart = session('comanda_cart', []);
        $eliminados = session('comanda_eliminados', []);
        $order_type = session('comanda_order_type');

        if (! $order_type) {
            return response()->json(['success' => false, 'message' => 'La sesión de la comanda expiró. Vuelve a elegir la mesa.']);
        }
        if (empty($cart) && empty($eliminados)) {
            return response()->json(['success' => false, 'message' => 'El carrito está vacío.']);
        }

        $mesa_id = session('comanda_mesa_id');
        $pedidoId = session('comanda_pedido_id');
        $usuario = Auth::user();

        try {
            // Lo que se imprime en cocina/bar: lo nuevo (o el aumento) y, aparte, lo anulado (eliminado o reducido)
            $cocina = [];
            $anulaciones = [];
            $resultado = DB::transaction(function () use ($cart, $eliminados, $order_type, $mesa_id, $pedidoId, $usuario, &$cocina, &$anulaciones) {
                $pis_id = null;
                if ($order_type == 'salon') {
                    // Bloquea la mesa: dos mozos no pueden abrir pedido en la misma mesa a la vez
                    $mesa = Mesa::where('mes_id', $mesa_id)->where('id_empresa_negocio', $usuario->id_empresa_negocio)->lockForUpdate()->first();
                    if (! $mesa) {
                        throw new \RuntimeException('Mesa no válida.');
                    }
                    $pis_id = $mesa->pis_id;
                }

                if ($pedidoId) {
                    $pedido = Pedido::where('ped_id', $pedidoId)
                        ->where('id_empresa_negocio', $usuario->id_empresa_negocio)
                        ->lockForUpdate()->first();
                    if (! $pedido || $pedido->ped_est !== 'Aperturado') {
                        throw new \RuntimeException('Este pedido ya fue cobrado o anulado. No se guardaron los cambios.');
                    }
                } else {
                    if ($order_type == 'salon' && Pedido::where('mes_id', $mesa_id)->where('ped_est', 'Aperturado')->exists()) {
                        throw new \RuntimeException('Otro usuario acaba de abrir un pedido en esta mesa. Vuelve a seleccionarla.');
                    }

                    $pedido = Pedido::create([
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
                        'ped_cli_nom' => match ($order_type) {
                            'salon' => 'CONSUMO EN SALON', 'delivery' => 'DELIVERY', default => 'PARA LLEVAR'
                        },
                        'mot_id' => $order_type === 'delivery' ? session('comanda_mot_id') : null,
                        'ped_tot' => 0,
                    ]);
                }

                // 1) Eliminados con autorización (solo líneas sin nada cobrado)
                foreach ($eliminados as $el) {
                    $ids = $el['ped_det_ids'] ?? (isset($el['ped_det_id']) ? [$el['ped_det_id']] : []);
                    if (! $ids) {
                        continue;
                    }
                    if (PedidoDetalle::where('ped_id', $pedido->ped_id)->whereIn('ped_det_id', $ids)->where('item_facturado', '>', 0)->exists()) {
                        throw new \RuntimeException('Mientras editabas se cobró parte de un producto que querías eliminar. Vuelve a abrir la mesa.');
                    }
                    foreach (PedidoDetalle::where('ped_id', $pedido->ped_id)->whereIn('ped_det_id', $ids)->where('estadoitem', '!=', 'Eliminado')->get() as $f) {
                        $anulaciones[] = ['IdProducto' => $f->IdProducto, 'nombre' => $f->descripcion, 'cantidad' => (float) $f->ped_det_can,
                            'observacion' => 'Motivo: '.$el['motivo']];
                    }
                    PedidoDetalle::where('ped_id', $pedido->ped_id)->whereIn('ped_det_id', $ids)->update([
                        'estadoitem' => 'Eliminado',
                        'item_obs' => mb_substr('[ELIMINADO - Motivo: '.$el['motivo'].' - Autorizado por: '.$el['autorizado_por'].']', 0, 255),
                    ]);
                }

                // 2) Ítems ya comandados: quedan en UNA sola línea por producto y 3) ítems nuevos
                foreach ($cart as $item) {
                    if (! empty($item['is_old_item'])) {
                        $lineas = PedidoDetalle::where('ped_id', $pedido->ped_id)
                            ->whereIn('ped_det_id', $item['ped_det_ids'] ?? [])
                            ->where('estadoitem', '!=', 'Eliminado')
                            ->orderBy('ped_det_id')->lockForUpdate()->get();
                        if ($lineas->isEmpty()) {
                            continue;
                        }

                        // Lo cobrado se lee de la BD en este momento (otra caja pudo cobrar una cuenta separada)
                        $facturado = (float) $lineas->sum('item_facturado');
                        if ($item['cantidad'] < $facturado) {
                            throw new \RuntimeException("Ya se cobraron {$facturado} de {$item['nombre']}; no se puede dejar en {$item['cantidad']}.");
                        }

                        $obs = $item['observaciones'] ?? '';
                        if (! empty($item['motivo_reduccion'])) {
                            $obs = trim($obs.' [Reducido: '.$item['motivo_reduccion'].']');
                        }
                        $cambios = ['ped_det_can' => $item['cantidad'], 'item_obs' => mb_substr($obs, 0, 255), 'item_facturado' => $facturado];
                        // Si piden más unidades, la línea vuelve a cocina (solo la diferencia); si bajan, sale como anulación
                        $original = (float) ($item['cantidad_original'] ?? $item['cantidad']);
                        if ($item['cantidad'] > $original) {
                            $cambios['impreso'] = 'imprimir';
                            $cambios['estadoitem'] = 'Ingresado';
                            $cocina[] = ['IdProducto' => $item['IdProducto'], 'nombre' => $item['nombre'],
                                'cantidad' => $item['cantidad'] - $original, 'observacion' => '(adicional)'];
                        } elseif ($item['cantidad'] < $original) {
                            $anulaciones[] = ['IdProducto' => $item['IdProducto'], 'nombre' => $item['nombre'],
                                'cantidad' => $original - $item['cantidad'], 'observacion' => $item['motivo_reduccion'] ?? ''];
                        }

                        // Se conserva la primera línea con el total y las repetidas se fusionan en ella
                        $principal = $lineas->first();
                        PedidoDetalle::where('ped_det_id', $principal->ped_det_id)->update($cambios);
                        PedidoDetalle::whereIn('ped_det_id', $lineas->skip(1)->pluck('ped_det_id'))->delete();
                    } else {
                        $cocina[] = ['IdProducto' => $item['IdProducto'] ?? $item['id'], 'nombre' => $item['nombre'],
                            'cantidad' => $item['cantidad'], 'observacion' => $item['observaciones'] ?? ''];
                        PedidoDetalle::create([
                            'ped_id' => $pedido->ped_id,
                            'IdProducto' => $item['IdProducto'] ?? $item['id'],
                            'id_presentacion' => $item['presentacion'] ?? null,
                            'IdEmpresa' => $usuario->IdEmpresa,
                            'descripcion' => $item['nombre'],
                            'detalle' => $item['nombre'],
                            'ped_det_can' => $item['cantidad'],
                            'ped_det_pre' => $item['precio'],
                            'item_obs' => $item['observaciones'] ?? null,
                            'estadoitem' => 'Ingresado',
                            'impreso' => 'imprimir',
                            'fecha_hora' => now(),
                        ]);
                    }
                }

                // El total se recalcula desde la BD (fuente de verdad), no desde la sesión
                $total = (float) PedidoDetalle::where('ped_id', $pedido->ped_id)
                    ->where('estadoitem', '!=', 'Eliminado')
                    ->sum(DB::raw('ped_det_can * ped_det_pre'));

                // Si se eliminaron todos los ítems, el pedido se anula y la mesa queda libre
                if ($total <= 0) {
                    $pedido->update(['ped_est' => 'Anulado', 'ped_tot' => 0, 'fecha_hora_modificacion' => now()]);
                    if ($pedido->mes_id) {
                        Mesa::where('mes_id', $pedido->mes_id)->update(['mes_est' => 'Libre']);
                    }

                    return ['pedido_id' => $pedido->ped_id, 'anulado' => true, 'mesa' => (bool) $pedido->mes_id];
                }

                $pedido->update(['ped_tot' => $total, 'fecha_hora_modificacion' => now()]);
                if ($order_type == 'salon') {
                    Mesa::where('mes_id', $mesa_id)->update(['mes_est' => 'Ocupado']);
                }

                return ['pedido_id' => $pedido->ped_id, 'anulado' => false];
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        // Si la comanda viene de una reserva, la reserva queda enlazada a su pedido
        if ($reservaId = session('comanda_reserva_id')) {
            DB::table('reservas')->where('res_id', $reservaId)->whereNull('ped_id')->update(['ped_id' => $resultado['pedido_id']]);
        }

        session()->forget(self::SESION);

        // Tickets de cocina/bar (directo a la impresora de cada categoría). Un fallo aquí no deshace la comanda.
        $tickets = 0;
        try {
            $tickets = Impresion::comanda($resultado['pedido_id'], $cocina)
                + Impresion::comanda($resultado['pedido_id'], $anulaciones, true);
        } catch (\Throwable $e) {
            report($e);
        }

        // Pantalla de cocina (KDS): misma información que se imprime
        try {
            Cocina::registrar($resultado['pedido_id'], $cocina, $anulaciones);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'tickets' => $tickets,
            'message' => $resultado['anulado']
                ? 'Se eliminaron todos los ítems: el pedido quedó ANULADO'.($resultado['mesa'] ? ' y la mesa libre.' : '.')
                : 'Comanda enviada correctamente.',
            'pedido_id' => $resultado['pedido_id'],
        ]);
    }
}
