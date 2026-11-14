<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\User;
use App\Support\Cocina;
use App\Support\ControlStock;
use App\Support\OpcionesPlato;
use App\Support\Porciones;
use App\Support\Precios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Carrito de la comanda (en sesión), UNA línea por producto y presentación (clave = IdProducto, o IdProducto-p{id} si se
 * eligió una presentación como TAJADA / ENTERA):
 *  - is_old_item = true  -> ya enviado a cocina; guarda los ped_det_id que agrupa (si había líneas repetidas
 *    se fusionan en una al enviar). Bajar de lo enviado pide autorización; nunca se baja de lo ya cobrado.
 *  - is_old_item = false -> producto nuevo en esta edición.
 */
class ComandaCarritoController extends Controller
{
    /** Porciones aún no enviadas de una entrada en el carrito */
    private function opcionEnCarrito(array $cart, int $idOpcion): float
    {
        return (float) collect($cart)->sum(fn ($i) => (float) ($i['opciones']['kardex'][$idOpcion] ?? 0)
            * max(0, (float) $i['cantidad'] - (! empty($i['is_old_item']) ? (float) ($i['cantidad_original'] ?? $i['cantidad']) : 0)));
    }

    /** Cantidad aún no enviada a cocina de un producto en el carrito (lo enviado ya está reservado) */
    private function nuevoEnCarrito(array $cart, int $idProducto): float
    {
        return (float) collect($cart)->where('IdProducto', $idProducto)
            ->sum(fn ($i) => max(0, (float) $i['cantidad'] - (! empty($i['is_old_item']) ? (float) ($i['cantidad_original'] ?? $i['cantidad']) : 0)));
    }

    /** Opciones y presentaciones de un plato, para el modal de la comanda */
    public function opcionesProducto(int $id)
    {
        $producto = Producto::where('IdProducto', $id)->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->firstOrFail();

        return response()->json([
            'id' => $producto->IdProducto, 'nombre' => $producto->pronom, 'precio' => Precios::de($producto),
            'presentaciones' => Precios::presentaciones([$producto->IdProducto])[$producto->IdProducto] ?? [],
            'grupos' => OpcionesPlato::de($producto->IdProducto),
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

        return response()->json(['success' => true] + $this->carrito());
    }

    public function addToCart(Request $request)
    {
        // Nombre y precio salen de la BD, nunca del navegador
        $producto = Producto::where('IdProducto', $request->id)
            ->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->where('proest', 'Activo')
            ->whereNotIn('promocion', Producto::NO_VENDIBLES)
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

        // Plato con opciones (entrada del menú, arma tu trío): se valida lo elegido
        $opciones = null;
        if (OpcionesPlato::conOpciones([$producto->IdProducto])) {
            try {
                $opciones = OpcionesPlato::elegir($producto->IdProducto, (array) $request->input('opciones', []));
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }

        $cart = session('comanda_cart', []);
        $id = ($presentacion ? $producto->IdProducto.'-p'.$presentacion->id_presentacion : (string) $producto->IdProducto)
            .($opciones && $opciones['firma'] !== '' ? '-o'.$opciones['firma'] : '');

        // Sucursal que vende solo con stock: avisa antes de mandarlo a cocina
        $yaEnCarrito = $this->nuevoEnCarrito($cart, $producto->IdProducto);
        if ($falta = ControlStock::faltante($producto, $yaEnCarrito + 1, (int) Auth::user()->id_empresa_negocio,
            (float) ($presentacion?->factor ?? 1), $opciones['kardex'] ?? [])) {
            return response()->json(['success' => false, 'message' => $falta], 422);
        }

        // Platos con porciones del día (juanes, sopa del día...): avisa si ya no hay
        $porciones = [$producto->IdProducto => ($yaEnCarrito + 1) * (float) ($presentacion?->factor ?? 1)];
        foreach ($opciones['kardex'] ?? [] as $idOp => $porPlato) {
            $porciones[$idOp] = ($porciones[$idOp] ?? 0) + $this->opcionEnCarrito($cart, (int) $idOp) + $porPlato;
        }
        if ($falta = Porciones::faltante((int) Auth::user()->id_empresa_negocio, $porciones)) {
            return response()->json(['success' => false, 'message' => $falta], 422);
        }

        if (isset($cart[$id])) {
            $cart[$id]['cantidad']++;
        } else {
            $cart[$id] = [
                'id' => $id,
                'IdProducto' => (int) $producto->IdProducto,
                'presentacion' => $presentacion?->id_presentacion,
                'opciones' => $opciones && $opciones['grupos'] ? ['grupos' => $opciones['grupos'], 'kardex' => $opciones['kardex'], 'firma' => $opciones['firma']] : null,
                'opciones_texto' => $opciones['texto'] ?? '',
                'nombre' => $presentacion ? mb_substr($producto->pronom.' - '.$presentacion->nombre, 0, 150) : $producto->pronom,
                // Precio del plato (o de su presentación) + lo que cuestan de más las opciones elegidas
                'precio' => ($presentacion
                    ? ((float) $presentacion->precio > 0 ? (float) $presentacion->precio : round(Precios::de($producto) * (float) $presentacion->factor, 2))
                    : Precios::de($producto)) + ($opciones['extra'] ?? 0),
                'cantidad' => 1,
                'observaciones' => '',
                'is_old_item' => false,
            ];
        }

        session()->put('comanda_cart', $cart);

        return response()->json(['success' => true] + $this->carrito());
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
            if ($nueva > $cart[$id]['cantidad'] && ($producto = Producto::find($cart[$id]['IdProducto'] ?? $id))) {
                $total = $this->nuevoEnCarrito($cart, $producto->IdProducto) + ($nueva - $cart[$id]['cantidad']);
                $factor = ! empty($cart[$id]['presentacion']) ? (float) ProductoPresentacion::where('id_presentacion', $cart[$id]['presentacion'])->value('factor') : 1;
                if ($falta = ControlStock::faltante($producto, $total, (int) Auth::user()->id_empresa_negocio, $factor ?: 1, $cart[$id]['opciones']['kardex'] ?? [])) {
                    return response()->json(['success' => false, 'message' => $falta]);
                }
            }
            $cart[$id]['cantidad'] = $nueva;
        }

        // Las observaciones solo cambian si se mandan (antes se borraban al tocar + / -)
        if ($request->has('observaciones') && $request->observaciones !== null) {
            $cart[$id]['observaciones'] = mb_substr(trim($request->observaciones), 0, 200);
        }

        session()->put('comanda_cart', $cart);

        return response()->json(['success' => true] + $this->carrito());
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

        return response()->json(['success' => true] + $this->carrito());
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

        return response()->json(['success' => true] + $this->carrito());
    }

    /**
     * Todo el pedido de una vez (pruebas, el cliente se fue...): con la misma autorización que un producto, quedan
     * marcados para eliminar todos los productos enviados y se quitan los nuevos. Al enviar la comanda el pedido se anula,
     * la mesa queda libre y la cocina recibe la anulación. Si algo ya se cobró en una cuenta separada, no se puede.
     */
    public function eliminarTodoAutorizado(Request $request)
    {
        $request->validate($this->reglasAutorizacion(), [], ['auth_user' => 'Usuario', 'auth_password' => 'Contraseña', 'reason' => 'Motivo']);

        if ($error = $this->validarAutorizacionAdminCaja($request)) {
            return response()->json(['success' => false, 'message' => $error]);
        }

        $cart = session('comanda_cart', []);
        $enviados = array_filter($cart, fn ($item) => ! empty($item['is_old_item']));
        if (! $enviados) {
            return response()->json(['success' => false, 'message' => 'Este pedido aún no se envió a cocina: usa "Vaciar nuevos".']);
        }
        if (collect($enviados)->contains(fn ($item) => ($item['facturado'] ?? 0) > 0)) {
            return response()->json(['success' => false, 'message' => 'Parte del pedido ya se cobró en una cuenta separada: no se puede eliminar entero.']);
        }

        $eliminados = session('comanda_eliminados', []);
        $eliminados[] = ['ped_det_ids' => collect($enviados)->flatMap(fn ($item) => $item['ped_det_ids'] ?? [])->values()->all(),
            'motivo' => trim($request->reason), 'autorizado_por' => $this->autorizadoPor($request)];
        session()->put('comanda_eliminados', $eliminados);
        session()->put('comanda_cart', []);

        return response()->json(['success' => true] + $this->carrito());
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

        return response()->json(['success' => true] + $this->carrito());
    }

    public function getCartDetails()
    {
        return response()->json($this->carrito());
    }

    /**
     * El carrito ya dibujado, su total y cuántas unidades lleva. Va en la misma respuesta de agregar, sumar, quitar o
     * vaciar: la pantalla se actualiza con una sola petición (en celular con señal lenta se nota).
     *
     * @return array{vista: string, total: float, unidades: float}
     */
    private function carrito(): array
    {
        $cart = session('comanda_cart', []);

        return [
            'vista' => view('empresas.comandas.partials.cart_details', compact('cart'))->render(),
            'total' => round(collect($cart)->sum(fn ($i) => $i['cantidad'] * $i['precio']), 2),
            'unidades' => (float) collect($cart)->sum('cantidad'),
        ];
    }
}
