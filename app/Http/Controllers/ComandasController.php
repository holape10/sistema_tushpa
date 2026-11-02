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
use App\Support\Buscar;
use App\Support\Cocina;
use App\Support\ControlStock;
use App\Support\Delivery;
use App\Support\ImagenProducto;
use App\Support\Impresion\Impresion;
use App\Support\LogoNegocio;
use App\Support\MesasUnidas;
use App\Support\MetasMozo;
use App\Support\NombreCorto;
use App\Support\OpcionesPlato;
use App\Support\PlanoMesas;
use App\Support\Porciones;
use App\Support\Precios;
use App\Support\PromocionesVenta;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Comandas del mozo: elegir mesa o tipo de pedido, la carta para pedir, precuenta, mozo/promotor y enviar a cocina.
 * El carrito está en ComandaCarritoController y pasar/juntar mesas en ComandaMesaController (comparten la sesión comanda_*).
 */
class ComandasController extends Controller
{
    private const SESION = ['comanda_cart', 'comanda_order_type', 'comanda_mesa_id', 'comanda_mesa_nombre', 'comanda_pedido_id', 'comanda_eliminados', 'comanda_reserva_id', 'comanda_mot_id', 'comanda_prom_id', 'comanda_mozo_id'];

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
        $permisos = self::permisos();
        // El mozo ve cómo va con su meta de venta (si el administrador puso metas)
        $miMeta = Auth::user()->esMozo() ? MetasMozo::delMozo((int) $id_empresa_negocio, (int) Auth::id()) : null;
        // Pedidos, llamadas y cuentas que piden los clientes desde el QR de su mesa
        $cartaMesa = (bool) DB::table('empresa_negocios')->where('id_empresa_negocio', $id_empresa_negocio)->value('carta_pedido_mesa');
        $fotoRedes = self::fotoRedes((int) $id_empresa_negocio);

        return view('empresas.comandas.seleccion_servicio', compact('pisos', 'mesas', 'primerPisoId', 'puedeCobrar', 'permisos', 'miMeta', 'cartaMesa', 'fotoRedes')
            + self::plano((int) $id_empresa_negocio, (int) $primerPisoId));
    }

    /**
     * Si la sucursal ve sus mesas en el plano del local, lo que va alrededor de las mesas (barra, paredes, DJ...)
     *
     * @return array{usaPlano: bool, elementos: Collection, alto: int, ancho: int}
     */
    private static function plano(int $sucursal, int $piso): array
    {
        $usaPlano = $piso && PlanoMesas::usaPlano($sucursal);

        return ['usaPlano' => $usaPlano, 'elementos' => $usaPlano ? PlanoMesas::elementos($sucursal, $piso) : collect(),
            'alto' => $usaPlano ? PlanoMesas::alto($piso) : PlanoMesas::ALTO, 'ancho' => $usaPlano ? PlanoMesas::ancho($piso) : PlanoMesas::ANCHO];
    }

    /**
     * Qué tipos de pedido puede abrir el usuario. Administrador y caja: todos. Mozos, promotores y demás: solo Para
     * llevar; y con "comandas solo mesas" en la sucursal, ni eso (solo mesas).
     *
     * @return array{llevar: bool, delivery: bool, pv: bool}
     */
    public static function permisos(): array
    {
        $usuario = Auth::user();
        if ($usuario->esAdminOCaja()) {
            return ['llevar' => true, 'delivery' => true, 'pv' => true];
        }
        $soloMesas = (bool) DB::table('empresa_negocios')->where('id_empresa_negocio', $usuario->id_empresa_negocio)->value('comandas_solo_mesas');

        return ['llevar' => ! $soloMesas, 'delivery' => false, 'pv' => false];
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

        $items = $lineas->groupBy(fn ($d) => $d->IdProducto.'|'.$d->id_presentacion.'|'.md5((string) $d->opciones).'|'.$d->ped_det_pre)->map(fn ($g) => (object) [
            'descripcion' => $g->first()->descripcion.(($t = OpcionesPlato::texto($g->first()->opciones)) !== '' ? ' ('.$t.')' : ''),
            'precio' => (float) $g->first()->ped_det_pre,
            'cantidad' => (float) $g->sum('ped_det_can'),
            'pagado' => (float) $g->sum('item_facturado'),
        ])->values();

        $total = round($items->sum(fn ($i) => $i->cantidad * $i->precio), 2);
        $pagado = round($items->sum(fn ($i) => $i->pagado * $i->precio), 2);

        return view('empresas.comandas.precuenta', [
            'pedido' => $pedido, 'items' => $items, 'total' => $total, 'pagado' => $pagado, 'promos' => PromocionesVenta::delPedido((int) $pedido->ped_id),
            'mesa' => $pedido->mes_id ? Mesa::find($pedido->mes_id) : null,
            'piso' => $pedido->pis_id ? Piso::find($pedido->pis_id) : null,
            'mozo' => User::find($pedido->mozo)?->apeusu,
            'negocio' => EmpresaNegocio::find($pedido->id_empresa_negocio),
        ]);
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
        $nombrePiso = Piso::where('pis_id', $piso_id)->value('pis_nom');

        // Platos que cocina ya despachó y falta llevar a la mesa (cada plato sale cuando está listo)
        $listos = Cocina::listosParaLlevar($mesas->pluck('pedido_id')->filter()->all());

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

        // Mesas juntas a un grupo: se ven ocupadas y al tocarlas se abre la mesa principal del grupo
        $grupos = Pedido::join('mesas', 'mesas.mes_id', '=', 'pedidos.mes_id')
            ->leftJoin('pisos', 'pisos.pis_id', '=', 'mesas.pis_id')
            ->whereIn('pedidos.ped_id', $mesas->whereNull('pedido_id')->pluck('unida_ped_id')->filter())
            ->where('pedidos.ped_est', 'Aperturado')
            ->get(['pedidos.ped_id', 'pedidos.ped_tot', 'pedidos.fecha_hora', 'mesas.mes_id', 'mesas.mes_nom', 'pisos.pis_nom'])->keyBy('ped_id');

        // Mozo de cada mesa ocupada (primer nombre y primer apellido), si la sucursal lo usa
        $mozos = [];
        if (NombreCorto::activo((int) $id_empresa_negocio)) {
            $pedidos = $mesas->pluck('pedido_id')->merge($grupos->keys())->filter()->unique()->values();
            $mozos = $pedidos->isEmpty() ? [] : DB::table('pedidos as p')->join('users as u', 'u.IdUsuario', '=', 'p.mozo')->whereIn('p.ped_id', $pedidos)
                ->select('p.ped_id', DB::raw("COALESCE(NULLIF(TRIM(u.apeusu), ''), u.name) as nombre"))
                ->pluck('nombre', 'ped_id')->map(fn ($n) => NombreCorto::de((string) $n))->all();
        }

        return $mesas->each(function ($m) use ($listos, $reservas, $grupos, $nombrePiso, $mozos) {
            $m->etiqueta = Mesa::etiqueta($nombrePiso, $m->mes_nom);
            $grupo = ! $m->pedido_id && $m->unida_ped_id ? ($grupos[$m->unida_ped_id] ?? null) : null;
            $m->principal = $grupo ? ['mes_id' => $grupo->mes_id, 'nombre' => Mesa::etiqueta($grupo->pis_nom, $grupo->mes_nom)] : null;
            if ($grupo) {
                $m->pedido_id = $grupo->ped_id;
                $m->ped_tot = $grupo->ped_tot;
                $m->pedido_fecha_hora = $grupo->fecha_hora;
            }
            $m->listos = count($listos[$m->pedido_id] ?? []);
            $m->listos_texto = implode(' · ', $listos[$m->pedido_id] ?? []);
            $m->reserva = $reservas[$m->mes_id] ?? null;
            $m->mozo_nombre = $m->pedido_id ? ($mozos[$m->pedido_id] ?? null) : null;
        });
    }

    /**
     * Mesas de una zona (la pantalla las vuelve a pedir cada 10 s). Con ?h= (la huella de lo que ya tiene) y sin
     * cambios, responde solo {igual: true}: en internet lento no se vuelve a bajar todo.
     */
    public function getMesasPorPiso(Request $request, $piso_id)
    {
        $mesas = $this->mesasDelPiso($piso_id, Auth::user()->id_empresa_negocio);
        $vista = view('empresas.comandas.partials.mesas_piso', ['mesas' => $mesas] + self::plano((int) Auth::user()->id_empresa_negocio, (int) $piso_id))->render();
        $huella = substr(md5($vista), 0, 16);

        return response()->json($request->query('h') === $huella ? ['igual' => true] : ['vista' => $vista, 'h' => $huella]);
    }

    public function setServiceData(Request $request)
    {
        [$ok, $mensaje, $estado] = $this->prepararServicio($request->order_type, $request->mesa_id, $request->mesa_nombre, $request->pedido_id);

        return response()->json($ok ? ['success' => true] : ['success' => false, 'message' => $mensaje], $ok ? 200 : $estado);
    }

    /**
     * Deja en la sesión el pedido que se va a tomar (mesa, para llevar o delivery, nuevo o uno abierto).
     *
     * @return array{0: bool, 1: string, 2: int} [listo, mensaje si no, código HTTP]
     */
    private function prepararServicio(?string $orderType, $mesaId, ?string $mesaNombre, $pedidoId): array
    {
        $id_empresa_negocio = Auth::user()->id_empresa_negocio;

        if (! in_array($orderType, ['salon', 'llevar', 'delivery'], true)) {
            return [false, 'Tipo de pedido no válido.', 422];
        }
        // Para llevar / delivery nuevos solo si el usuario puede abrirlos (editar uno ya abierto sí puede)
        if ($orderType !== 'salon' && ! $pedidoId && ! (self::permisos()[$orderType] ?? false)) {
            return [false, 'Tu usuario solo atiende mesas.', 403];
        }

        // La mesa y el pedido deben ser de la sucursal del usuario
        if ($orderType === 'salon') {
            $mesa = Mesa::where('mes_id', $mesaId)->where('id_empresa_negocio', $id_empresa_negocio)->first();
            if (! $mesa) {
                return [false, 'Mesa no válida.', 403];
            }
            // Si la pantalla estaba desactualizada y la mesa ya tiene pedido abierto (propio o de su grupo), se continúa ese pedido
            if (! $pedidoId) {
                $pedidoId = MesasUnidas::pedidoDe($mesa->mes_id);
            }
            // Una mesa del grupo trabaja con la mesa principal del pedido
            $principal = $pedidoId ? Pedido::where('ped_id', $pedidoId)->value('mes_id') : null;
            if ($principal && (int) $principal !== (int) $mesa->mes_id) {
                $mesa = Mesa::with('piso')->find($principal);
                $mesaId = $mesa->mes_id;
                $mesaNombre = Mesa::etiqueta($mesa->piso?->pis_nom, $mesa->mes_nom);
            }
        }

        if ($pedidoId && ! Pedido::where('ped_id', $pedidoId)->where('id_empresa_negocio', $id_empresa_negocio)->where('ped_est', 'Aperturado')->exists()) {
            return [false, 'El pedido ya fue cobrado o no existe.', 409];
        }

        session()->forget(self::SESION);
        session()->put('comanda_order_type', $orderType);
        session()->put('comanda_mesa_id', $orderType === 'salon' ? (int) $mesaId : null);
        session()->put('comanda_mesa_nombre', $mesaNombre);
        if ($pedidoId) {
            session()->put('comanda_pedido_id', (int) $pedidoId);
        }

        return [true, '', 200];
    }

    public function getPedidoDetails($ped_id)
    {
        $pedido = Pedido::where('ped_id', $ped_id)
            ->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->firstOrFail();
        $detalles = PedidoDetalle::where('ped_id', $ped_id)->where('estadoitem', '!=', 'Eliminado')->get();
        $total = $detalles->sum(fn ($d) => $d->ped_det_can * $d->ped_det_pre);

        $unidas = MesasUnidas::delPedido((int) $pedido->ped_id)->map(fn ($m) => ['mes_id' => $m->mes_id, 'nombre' => $m->etiqueta])->values();

        return response()->json(['success' => true, 'pedido' => $pedido, 'detalles' => $detalles, 'total' => $total, 'unidas' => $unidas]);
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

    public function menuPedido(Request $request)
    {
        $id_empresa_negocio = Auth::user()->id_empresa_negocio;
        // Abrir directo desde las mesas, sin un paso previo: una sola ida y vuelta al servidor
        if ($request->filled('tipo')) {
            [$ok, $mensaje] = $this->prepararServicio($request->tipo, $request->mesa, $request->nombre, $request->pedido);
            if (! $ok) {
                return redirect()->route('comandas.seleccion')->with('error', $mensaje);
            }
        }
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
            foreach ($detalles->groupBy(fn ($d) => $d->IdProducto.'|'.$d->id_presentacion.'|'.md5((string) $d->opciones)) as $lineas) {
                $idProducto = $lineas->first()->IdProducto;
                $presentacion = $lineas->first()->id_presentacion;
                $opciones = json_decode((string) $lineas->first()->opciones, true) ?: null;
                $key = ($presentacion ? $idProducto.'-p'.$presentacion : (string) $idProducto).($opciones ? '-o'.($opciones['firma'] ?? substr(md5($lineas->first()->opciones), 0, 8)) : '');
                $enviado = (float) $lineas->sum('ped_det_can');
                $obs = $lineas->pluck('item_obs')->filter()->unique()->implode(' / ');
                $cart[$key] = [
                    'id' => $key,
                    'IdProducto' => (int) $idProducto,
                    'presentacion' => $presentacion ? (int) $presentacion : null,
                    'opciones' => $opciones,
                    'opciones_texto' => OpcionesPlato::texto($opciones['grupos'] ?? []),
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

        // Promotor del pedido (opcional; solo si la sucursal tiene usuarios con rol Promotor)
        $promotores = self::promotores((int) $id_empresa_negocio);
        $promActual = $promotores->isEmpty() ? null : ($pedidoId ? Pedido::where('ped_id', $pedidoId)->value('promotor_id') : session('comanda_prom_id'));
        // Si quien toma el pedido es un promotor, el pedido es suyo
        if (! $promActual && Auth::user()->tieneRol([13])) {
            $promActual = (int) Auth::id();
            session()->put('comanda_prom_id', $promActual);
        }

        $esPromotor = Auth::user()->tieneRol([13]);

        // Caja y administrador eligen el mozo que atendió (el pedido queda a su nombre); los mozos no lo ven
        $mozos = Auth::user()->esAdminOCaja() ? self::mozos((int) $id_empresa_negocio) : collect();
        $mozoActual = $mozos->isEmpty() ? null : ($pedidoId ? Pedido::where('ped_id', $pedidoId)->value('mozo') : session('comanda_mozo_id'));
        $total = round(collect($cart)->sum(fn ($i) => $i['cantidad'] * $i['precio']), 2);
        $productosIniciales = $cat_default_id ? $this->productosHtml($cat_default_id, null) : '';
        $fotoRedes = $order_type === 'salon' ? self::fotoRedes((int) $id_empresa_negocio) : null;

        return view('empresas.comandas.menu_pedido', compact('categorias', 'cat_default_id', 'cart', 'order_type', 'mesa_info', 'esAdmin', 'motorizados', 'motActual',
            'promotores', 'promActual', 'esPromotor', 'mozos', 'mozoActual', 'total', 'productosIniciales', 'fotoRedes'));
    }

    /**
     * Foto para redes (si la sucursal la activó): nombre, logo (el de la empresa, a color; el de la sucursal suele ser
     * en blanco y negro para la ticketera) y el marco que eligió la sucursal
     *
     * @return array{nombre: string, logo: ?string, estilo: string, color: string, texto: ?string}|null
     */
    public static function fotoRedes(int $sucursal): ?array
    {
        $negocio = DB::table('empresa_negocios as n')->join('empresa as e', 'e.IdEmpresa', '=', 'n.IdEmpresa')
            ->where('n.id_empresa_negocio', $sucursal)->where('n.foto_redes', true)
            ->first(['n.nombre_comercial', 'n.logo_suc', 'n.foto_marco_estilo', 'n.foto_marco_color', 'n.foto_texto', 'e.LogEmpresa', 'e.NomEmpresa']);
        if (! $negocio) {
            return null;
        }
        $logo = LogoNegocio::de($negocio->LogEmpresa, $negocio->logo_suc);

        return ['nombre' => (string) ($negocio->nombre_comercial ?: $negocio->NomEmpresa), 'logo' => $logo ? asset($logo) : null,
            'estilo' => (string) $negocio->foto_marco_estilo, 'color' => (string) $negocio->foto_marco_color, 'texto' => $negocio->foto_texto];
    }

    /** Usuarios activos con rol Mozo de la sucursal */
    public static function mozos(int $sucursal): Collection
    {
        return DB::table('users as u')->join('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')->where('r.role_id', 8)
            ->where('u.id_empresa_negocio', $sucursal)->where('u.estusu', 1)->orderBy('u.apeusu')
            ->get(['u.IdUsuario', DB::raw("COALESCE(NULLIF(u.apeusu, ''), u.name) as nombre")]);
    }

    /** Caja o administrador eligen el mozo que atendió: queda en el pedido (o en la comanda que aún no se envía) */
    public function asignarMozo(Request $request)
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403);
        $d = $request->validate(['mozo_id' => 'nullable|integer']);
        $suc = (int) Auth::user()->id_empresa_negocio;
        $mozo = ! empty($d['mozo_id']) && self::mozos($suc)->contains('IdUsuario', (int) $d['mozo_id']) ? (int) $d['mozo_id'] : null;
        session()->put('comanda_mozo_id', $mozo);
        if ($pedId = (int) session('comanda_pedido_id')) {
            DB::table('pedidos')->where('ped_id', $pedId)->where('id_empresa_negocio', $suc)->where('ped_est', 'Aperturado')
                ->update(['mozo' => $mozo ?: Auth::id()]);
        }

        return response()->json(['ok' => true]);
    }

    /** Usuarios activos con rol Promotor de la sucursal */
    public static function promotores(int $sucursal): Collection
    {
        return DB::table('users as u')->join('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')->where('r.role_id', 13)
            ->where('u.id_empresa_negocio', $sucursal)->where('u.estusu', 1)->orderBy('u.apeusu')
            ->get(['u.IdUsuario', DB::raw("COALESCE(NULLIF(u.apeusu, ''), u.name) as nombre")]);
    }

    /** El mozo elige el promotor: queda en el pedido (o en la comanda que aún no se envía) */
    public function asignarPromotor(Request $request)
    {
        $d = $request->validate(['promotor_id' => 'nullable|integer']);
        $suc = (int) Auth::user()->id_empresa_negocio;
        $promotor = ! empty($d['promotor_id']) && self::promotores($suc)->contains('IdUsuario', (int) $d['promotor_id']) ? (int) $d['promotor_id'] : null;
        if (Auth::user()->tieneRol([13])) {
            $promotor = (int) Auth::id();
        }
        session()->put('comanda_prom_id', $promotor);
        if ($pedId = (int) session('comanda_pedido_id')) {
            DB::table('pedidos')->where('ped_id', $pedId)->where('id_empresa_negocio', $suc)->where('ped_est', 'Aperturado')->update(['promotor_id' => $promotor]);
        }

        return response()->json(['ok' => true]);
    }

    public function searchProducts(Request $request)
    {
        return response()->json(['vista' => $this->productosHtml($request->category_id, $request->search_text, $request->vista === 'pv')]);
    }

    /** Grilla de productos de una categoría o de una búsqueda (la de la comanda o la del PV Restaurante) */
    private function productosHtml($categoria, ?string $texto, bool $pv = false): string
    {
        $request = new Request(['category_id' => $categoria, 'search_text' => $texto]);
        $id_empresa_negocio = Auth::user()->id_empresa_negocio;
        $almacen_id = Almacen::where('id_empresa_negocio', $id_empresa_negocio)
            ->where('predeterminado', 1)->value('id_almacen');

        $productos = Producto::leftJoin('producto_stock', function ($join) use ($almacen_id) {
            $join->on('productos.IdProducto', '=', 'producto_stock.IdProducto')
                ->where('producto_stock.id_almacen', $almacen_id);
        })
            ->where('productos.id_empresa_negocio', $id_empresa_negocio)
            ->where('productos.proest', 'Activo')
            ->whereNotIn('productos.promocion', Producto::NO_VENDIBLES)
            ->tap(fn ($q) => Buscar::palabras($q, $request->search_text, ['productos.pronom'], ['productos.procod']))
            ->when($request->category_id && ! $request->search_text, fn ($q) => $q->where('productos.cat_id', $request->category_id))
            ->select('productos.*', 'producto_stock.stock as stock_disponible')
            ->orderBy('productos.pronom')
            ->get();
        // Precio dinámico (happy hour, fin de semana…) vigente en este momento
        $precios = Precios::vigentes($productos);
        $presentaciones = Precios::presentaciones($productos->pluck('IdProducto'));
        $conOpciones = OpcionesPlato::conOpciones($productos->pluck('IdProducto'));
        $controlados = array_keys(Porciones::controlados($productos->pluck('IdProducto')));
        $saldo = Porciones::saldo((int) $id_empresa_negocio, $controlados);
        $reservado = Porciones::reservado((int) $id_empresa_negocio, $controlados);
        $quedan = collect($controlados)->mapWithKeys(fn ($id) => [$id => ($saldo[$id] ?? 0) - ($reservado[$id] ?? 0)])->all();
        // Fotos en los botones (si la sucursal lo activó): miniaturas livianas
        $fotos = ImagenProducto::enVentas((int) $id_empresa_negocio)
            ? $productos->filter(fn ($p) => $p->imagenproducto)->mapWithKeys(fn ($p) => [$p->IdProducto => ImagenProducto::mini($p->imagenproducto)])->filter()->all()
            : [];

        return view($pv ? 'empresas.comandas.partials.productos_grid_pv' : 'empresas.comandas.partials.productos_grid', compact('productos', 'precios', 'presentaciones', 'conOpciones', 'quedan', 'fotos'))->render();
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
                    if ($order_type == 'salon' && MesasUnidas::pedidoDe((int) $mesa_id)) {
                        throw new \RuntimeException('Esta mesa ya está ocupada (o está junta con otra mesa). Vuelve a seleccionarla.');
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
                        'mozo' => ($usuario->esAdminOCaja() ? session('comanda_mozo_id') : null) ?: $usuario->IdUsuario,
                        'IdUsuario' => $usuario->IdUsuario,
                        'ped_cli_nom' => match ($order_type) {
                            'salon' => 'CONSUMO EN SALON', 'delivery' => 'DELIVERY', default => 'PARA LLEVAR'
                        },
                        'mot_id' => $order_type === 'delivery' ? session('comanda_mot_id') : null,
                        'promotor_id' => session('comanda_prom_id') ?: ($usuario->tieneRol([13]) ? $usuario->IdUsuario : null),
                        'ped_tot' => 0,
                    ]);
                    if ($order_type === 'delivery') {
                        Delivery::iniciar($pedido->ped_id);
                    }
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

                // Solo con stock: lo nuevo (o el aumento) se reserva con bloqueo; si otro mozo manda lo mismo, espera
                $productos = Producto::whereIn('IdProducto', collect($cart)->pluck('IdProducto')->filter()->unique())->get()->keyBy('IdProducto');
                $factores = ProductoPresentacion::whereIn('id_presentacion', collect($cart)->pluck('presentacion')->filter()->unique())->pluck('factor', 'id_presentacion');
                $porciones = [];
                foreach ($cart as $item) {
                    $nuevo = (float) $item['cantidad'] - (! empty($item['is_old_item']) ? (float) ($item['cantidad_original'] ?? $item['cantidad']) : 0);
                    if ($nuevo > 0 && ! empty($item['IdProducto'])) {
                        $porciones[$item['IdProducto']] = ($porciones[$item['IdProducto']] ?? 0) + $nuevo * (float) ($factores[$item['presentacion'] ?? 0] ?? 1);
                        foreach ($item['opciones']['kardex'] ?? [] as $idOp => $porPlato) {
                            $porciones[$idOp] = ($porciones[$idOp] ?? 0) + $nuevo * (float) $porPlato;
                        }
                    }
                }
                if ($falta = Porciones::faltante((int) $usuario->id_empresa_negocio, $porciones, true)) {
                    throw new \RuntimeException($falta);
                }
                ControlStock::asegurar((int) $usuario->id_empresa_negocio, collect($cart)->map(function ($item) use ($productos, $factores) {
                    $nuevo = (float) $item['cantidad'] - (! empty($item['is_old_item']) ? (float) ($item['cantidad_original'] ?? $item['cantidad']) : 0);
                    $producto = $productos[$item['IdProducto'] ?? 0] ?? null;

                    return $producto && $nuevo > 0 ? ['producto' => $producto, 'cantidad' => $nuevo,
                        'factor' => (float) ($factores[$item['presentacion'] ?? 0] ?? 1), 'opciones' => $item['opciones']['kardex'] ?? []] : null;
                })->filter()->values()->all());

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
                                'cantidad' => $item['cantidad'] - $original, 'observacion' => trim(($item['opciones_texto'] ?? '').' (adicional)')];
                        } elseif ($item['cantidad'] < $original) {
                            $anulaciones[] = ['IdProducto' => $item['IdProducto'], 'nombre' => $item['nombre'],
                                'cantidad' => $original - $item['cantidad'], 'observacion' => $item['motivo_reduccion'] ?? ''];
                        }

                        // Se conserva la primera línea con el total y las repetidas se fusionan en ella
                        $principal = $lineas->first();
                        PedidoDetalle::where('ped_det_id', $principal->ped_det_id)->update($cambios);
                        PedidoDetalle::whereIn('ped_det_id', $lineas->skip(1)->pluck('ped_det_id'))->delete();
                    } else {
                        $cocina[] = ['IdProducto' => $item['IdProducto'] ?? $item['id'], 'nombre' => $item['nombre'], 'cantidad' => $item['cantidad'],
                            'observacion' => collect([$item['opciones_texto'] ?? '', $item['observaciones'] ?? ''])->filter()->implode(' · ')];
                        PedidoDetalle::create([
                            'ped_id' => $pedido->ped_id,
                            'IdProducto' => $item['IdProducto'] ?? $item['id'],
                            'id_presentacion' => $item['presentacion'] ?? null,
                            'opciones' => ! empty($item['opciones']) ? json_encode($item['opciones'], JSON_UNESCAPED_UNICODE) : null,
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
                    MesasUnidas::liberar($pedido->ped_id);

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
        // Si la sucursal imprime las comandas al cobrar, aquí solo se guardan
        $tickets = 0;
        $alCobrar = Impresion::comandaAlCobrar((int) $usuario->id_empresa_negocio);
        try {
            if ($alCobrar) {
                Impresion::guardarComandaPendiente($resultado['pedido_id'], $cocina, $anulaciones);
            } else {
                // Varias barras: si el mozo (o la PC de caja) tiene barra, su comanda sale ahí
                $barra = Impresion::barraDeEnvio((int) $usuario->id_empresa_negocio, $usuario);
                $tickets = Impresion::comanda($resultado['pedido_id'], $cocina, false, '', $barra)
                    + Impresion::comanda($resultado['pedido_id'], $anulaciones, true, '', $barra);
            }
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
                : ($alCobrar ? 'Pedido guardado: la comanda se imprimirá al cobrar.' : 'Comanda enviada correctamente.'),
            'pedido_id' => $resultado['pedido_id'],
        ]);
    }
}
