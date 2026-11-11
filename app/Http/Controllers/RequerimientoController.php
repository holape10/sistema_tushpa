<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Producto;
use App\Support\Bitacora;
use App\Support\Compras;
use App\Support\Requerimientos;
use App\Support\TelegramCitas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Requerimientos del día: cocina o almacén pide lo que falta; el administrador (o quien registra compras) lo compra,
 * marca lo comprado y lo registra como compra. Cada sucursal ve solo los suyos.
 */
class RequerimientoController extends Controller
{
    public function index(Request $request): View
    {
        $this->autorizar();
        $sucursal = (int) Auth::user()->id_empresa_negocio;
        $estado = array_key_exists((string) $request->query('estado'), Requerimientos::ESTADOS) ? (string) $request->query('estado') : null;

        return view('empresas.requerimientos.index', [
            'requerimientos' => DB::table('requerimientos as r')->leftJoin('users as u', 'u.IdUsuario', '=', 'r.IdUsuario')
                ->leftJoin('almacenes as a', 'a.id_almacen', '=', 'r.id_almacen')
                ->leftJoin('empresa_negocios as sp', 'sp.id_empresa_negocio', '=', 'r.id_empresa_negocio')
                ->leftJoin('empresa_negocios as so', 'so.id_empresa_negocio', '=', 'r.sucursal_origen')
                ->where(fn ($q) => $q->where('r.id_empresa_negocio', $sucursal)->orWhere('r.sucursal_origen', $sucursal))
                ->when($estado, fn ($q) => $q->where('r.estado', $estado))
                ->select('r.*', 'u.apeusu as pidio', 'a.descripcion as almacen', 'sp.nombre_comercial as sede_pide', 'so.nombre_comercial as sede_envia',
                    DB::raw('(SELECT COUNT(*) FROM requerimientos_detalle d WHERE d.requerimiento_id = r.id) as lineas'))
                ->orderByRaw("r.estado = 'PENDIENTE' DESC")->orderByDesc('r.id')->paginate(25)->withQueryString(),
            'estado' => $estado,
            'conteo' => DB::table('requerimientos')->where(fn ($q) => $q->where('id_empresa_negocio', $sucursal)->orWhere('sucursal_origen', $sucursal))
                ->groupBy('estado')->selectRaw('estado, COUNT(*) as n')->pluck('n', 'estado'),
            'sucursal' => $sucursal,
            'atiende' => Requerimientos::atiende(Auth::user()),
            // Pedidos por Telegram: los administradores de la empresa (solo un administrador lo configura)
            'telegram' => Auth::user()->esAdmin() && TelegramCitas::configurado() ? $this->administradores() : null,
        ]);
    }

    /** @return Collection<int, object> administradores activos de la empresa con su Telegram */
    private function administradores()
    {
        return DB::table('users as u')->join('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')
            ->where('r.role_id', 2)->where('u.IdEmpresa', Auth::user()->IdEmpresa)->where('u.estusu', 1)
            ->orderBy('u.IdUsuario')->distinct()->get(['u.IdUsuario', 'u.apeusu', 'u.name', 'u.telegram_chat_id', 'u.telegram_pedidos']);
    }

    /** Un administrador de esta empresa (para configurar su Telegram) */
    private function administrador(int $usuario): object
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador configura los avisos por Telegram.');
        $u = $this->administradores()->firstWhere('IdUsuario', $usuario);
        abort_unless($u, 404);

        return $u;
    }

    public function telegramEnlace(int $usuario): JsonResponse
    {
        $this->administrador($usuario);
        $enlace = TelegramCitas::enlace($usuario);

        return response()->json($enlace ? ['ok' => true, 'enlace' => $enlace]
            : ['ok' => false, 'mensaje' => 'Telegram no está configurado en el servidor (falta el token del bot).']);
    }

    /** ¿Ya tocó Iniciar en el bot? Devuelve los administradores que quedaron vinculados */
    public function telegramVerificar(): JsonResponse
    {
        abort_unless(Auth::user()->esAdmin(), 403);
        TelegramCitas::recibir();
        TelegramCitas::sincronizar();

        return response()->json(['ok' => true, 'vinculados' => $this->administradores()->whereNotNull('telegram_chat_id')
            ->pluck('IdUsuario')->map(fn ($id) => (int) $id)->values()]);
    }

    /** Recibir (o dejar de recibir) los pedidos en su Telegram */
    public function telegramRecibir(Request $request, int $usuario): JsonResponse
    {
        $this->administrador($usuario);
        DB::table('users')->where('IdUsuario', $usuario)->update(['telegram_pedidos' => $request->boolean('recibir')]);

        return response()->json(['ok' => true]);
    }

    public function telegramProbar(int $usuario): JsonResponse
    {
        $u = $this->administrador($usuario);
        if (! $u->telegram_chat_id) {
            return response()->json(['ok' => false, 'mensaje' => 'Ese administrador aún no vincula su Telegram.']);
        }
        [$ok, $detalle] = TelegramCitas::enviar((string) $u->telegram_chat_id, '🛒 Prueba: así te llegarán los pedidos de almacén de '
            .DB::table('empresa_negocios')->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->value('nombre_comercial').'.');

        return response()->json(['ok' => $ok, 'mensaje' => $ok ? '✔ Mensaje de prueba enviado a su Telegram.' : $detalle]);
    }

    public function telegramQuitar(int $usuario): JsonResponse
    {
        $this->administrador($usuario);
        TelegramCitas::desvincular($usuario);
        DB::table('users')->where('IdUsuario', $usuario)->update(['telegram_pedidos' => false]);

        return response()->json(['ok' => true]);
    }

    /** Lo que hay, lo que falta y lo que se acabaría: de ahí se arma el pedido */
    public function create(Request $request): View
    {
        $this->autorizar();
        $sucursal = (int) Auth::user()->id_empresa_negocio;
        $almacenes = Almacen::where('id_empresa_negocio', $sucursal)->orderByDesc('predeterminado')->orderBy('descripcion')->get(['id_almacen', 'descripcion']);
        abort_if($almacenes->isEmpty(), 404, 'La sucursal no tiene almacén.');
        $almacen = $almacenes->firstWhere('id_almacen', (int) $request->query('almacen')) ?? $almacenes->first();

        // Desde "Producción del día": lo que falta para los platos planificados ya viene puesto
        $desdeProduccion = collect((array) session('produccion_faltan', []))->mapWithKeys(fn ($v, $k) => [(int) $k => (float) $v]);

        return view('empresas.requerimientos.create', [
            'almacenes' => $almacenes, 'almacen' => $almacen, 'sedes' => Requerimientos::sedes(Auth::user()),
            'productos' => Requerimientos::sugerencias($sucursal, (int) $almacen->id_almacen)
                ->map(function ($p) use ($desdeProduccion) {
                    if ($desdeProduccion->isNotEmpty()) {
                        $p->sugerido = (float) ($desdeProduccion[$p->id] ?? 0);
                    }

                    return $p;
                }),
            'desdeProduccion' => $desdeProduccion->isNotEmpty(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate([
            'id_almacen' => 'required|integer', 'fecha_para' => 'required|date|after_or_equal:today',
            'observaciones' => 'nullable|string|max:500', 'urgente' => 'nullable|boolean', 'sucursal_origen' => 'nullable|integer',
            'items' => 'required|array|max:500', 'items.*.id' => 'required|integer',
            'items.*.cantidad' => 'nullable|numeric|min:0|max:9999999', 'items.*.nota' => 'nullable|string|max:150',
        ], ['items.required' => 'Pon la cantidad de al menos un producto.', 'fecha_para.after_or_equal' => 'El pedido es para hoy o un día que viene.'],
            ['fecha_para' => 'fecha']);
        try {
            $id = Requerimientos::guardar(Auth::user(), $d);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        $r = DB::table('requerimientos')->where('id', $id)->first();
        Bitacora::registrar('compra.requerimiento', 'Pidió el requerimiento '.Requerimientos::codigo($r).' para el '.date('d/m/Y', strtotime($r->fecha_para)),
            null, ['referencia' => ['requerimiento', $id]]);

        session()->forget('produccion_faltan');

        return redirect()->route('requerimientos.show', $id)->with('success', Requerimientos::codigo($r)
            .($r->sucursal_origen ? ' enviado a la sede '.DB::table('empresa_negocios')->where('id_empresa_negocio', $r->sucursal_origen)->value('nombre_comercial').': ellos lo despachan.'
                : ' enviado: el administrador ya lo ve en su inicio y le llegó el aviso al celular.'));
    }

    /** Cambiar un pedido que aún no se compra: quien lo pidió o quien lo atiende */
    public function editar(int $id): View|RedirectResponse
    {
        $this->autorizar();
        $r = $this->requerimiento($id);
        if ($motivo = $this->noEditable($r)) {
            return redirect()->route('requerimientos.show', $id)->with('error', $motivo);
        }
        $pedido = DB::table('requerimientos_detalle')->where('requerimiento_id', $id)->get()->keyBy('IdProducto');
        $sucursal = (int) Auth::user()->id_empresa_negocio;
        $almacen = Almacen::where('id_almacen', $r->id_almacen)->first(['id_almacen', 'descripcion']);

        return view('empresas.requerimientos.create', [
            'almacenes' => collect([$almacen]), 'almacen' => $almacen, 'sedes' => Requerimientos::sedes(Auth::user()),
            'productos' => Requerimientos::sugerencias($sucursal, (int) $r->id_almacen)->map(function ($p) use ($pedido) {
                $p->sugerido = (float) ($pedido[$p->id]->cantidad ?? 0);
                $p->nota_pedido = (string) ($pedido[$p->id]->nota ?? '');

                return $p;
            }),
            'desdeProduccion' => false, 'editar' => $r, 'codigo' => Requerimientos::codigo($r),
        ]);
    }

    public function actualizar(Request $request, int $id): RedirectResponse
    {
        $this->autorizar();
        $r = $this->requerimiento($id);
        if ($motivo = $this->noEditable($r)) {
            return redirect()->route('requerimientos.show', $id)->with('error', $motivo);
        }
        $d = $request->validate([
            'fecha_para' => 'required|date', 'observaciones' => 'nullable|string|max:500', 'urgente' => 'nullable|boolean',
            'items' => 'required|array|max:500', 'items.*.id' => 'required|integer',
            'items.*.cantidad' => 'nullable|numeric|min:0|max:9999999', 'items.*.nota' => 'nullable|string|max:150',
        ], ['items.required' => 'Pon la cantidad de al menos un producto.'], ['fecha_para' => 'fecha']);
        try {
            Requerimientos::guardar(Auth::user(), $d, $id);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        Bitacora::registrar('compra.requerimiento', 'Cambió el requerimiento '.Requerimientos::codigo($r), null, ['referencia' => ['requerimiento', $id]]);

        return redirect()->route('requerimientos.show', $id)->with('success', Requerimientos::codigo($r).' cambiado: el administrador recibió el pedido actualizado.');
    }

    /** Por qué no se puede cambiar el pedido (null = sí se puede) */
    private function noEditable(object $r): ?string
    {
        $user = Auth::user();
        if ($r->estado !== 'PENDIENTE' || (int) $r->id_empresa_negocio !== (int) $user->id_empresa_negocio) {
            return 'Solo se cambia un pedido pendiente de tu sucursal.';
        }
        if ((int) $r->IdUsuario !== (int) $user->IdUsuario && ! Requerimientos::atiende($user)) {
            return 'Solo quien lo pidió o el administrador lo cambia.';
        }

        return Requerimientos::editable((int) $r->id) ? null : 'Ya se marcó lo comprado o lo enviado: el pedido ya no se puede cambiar.';
    }

    public function show(int $id): View
    {
        $this->autorizar();
        $r = $this->requerimiento($id);
        $user = Auth::user();
        $despacha = $r->estado === 'PENDIENTE' && (int) $r->sucursal_origen === (int) $user->id_empresa_negocio;
        $almacenes = $despacha ? Almacen::where('id_empresa_negocio', $user->id_empresa_negocio)->orderByDesc('predeterminado')->get(['id_almacen', 'descripcion']) : collect();
        $datos = $this->datos($r);

        return view('empresas.requerimientos.show', $datos + [
            'editable' => $this->noEditable($r) === null,
            'atiende' => Requerimientos::atiende($user) && (int) $r->id_empresa_negocio === (int) $user->id_empresa_negocio && ! $r->sucursal_origen,
            'despacha' => $despacha, 'almacenes' => $almacenes,
            'recibe' => $r->estado === 'EN CAMINO' && (int) $r->id_empresa_negocio === (int) $user->id_empresa_negocio,
            'pares' => $despacha ? Requerimientos::emparejar((int) $r->sucursal_origen, $datos['lineas']) : [],
            'stockOrigen' => $despacha && $almacenes->isNotEmpty() ? DB::table('producto_stock')->where('id_almacen', $almacenes->first()->id_almacen)->pluck('stock', 'IdProducto') : collect(),
        ]);
    }

    public function imprimir(Request $request, int $id): View
    {
        $this->autorizar();
        $r = $this->requerimiento($id);
        // Lo comprado en el mercado (ticket o A4): lo desmarcado (0) no se compró; lo que quedó vacío se compró como se pidió
        $formato = in_array($request->query('compra'), ['ticket', 'a4'], true) ? $request->query('compra') : null;

        return view($formato ? 'empresas.requerimientos.imprimir_compra' : 'empresas.requerimientos.imprimir', $this->datos($r) + [
            'formato' => $formato,
            'empresa' => DB::table('empresa')->where('IdEmpresa', Auth::user()->IdEmpresa)->first(['NomEmpresa']),
            'sucursal' => DB::table('empresa_negocios')->where('id_empresa_negocio', $r->id_empresa_negocio)->first(['nombre_comercial', 'direccion']),
        ]);
    }

    /** En el mercado: lo que se compró de cada producto y a qué precio */
    public function marcar(Request $request, int $id): RedirectResponse
    {
        $this->autorizar();
        abort_unless(Requerimientos::atiende(Auth::user()), 403, 'Solo el administrador o quien registra compras marca lo comprado.');
        $d = $request->validate(['lineas' => 'required|array', 'lineas.*.comprado' => 'nullable|numeric|min:0|max:9999999',
            'lineas.*.precio' => 'nullable|numeric|min:0|max:9999999', 'registrar' => 'nullable|boolean', 'comprobante' => 'nullable|in:01,03,12']);
        try {
            Requerimientos::marcar(Auth::user(), $id, $d['lineas']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return ! empty($d['registrar'])
            ? redirect()->route('compras.create', ['requerimiento' => $id, 'comprobante' => $d['comprobante'] ?? '12'])
            : back()->with('success', 'Guardado lo comprado. Cuando termines, regístralo como compra.');
    }

    /** Envío directo a otra sede (sin pedido): el almacén central repone a los restaurantes */
    public function enviar(Request $request): View|RedirectResponse
    {
        $this->autorizar();
        $user = Auth::user();
        $sedes = Requerimientos::sedes($user);
        abort_if($sedes->isEmpty(), 404, 'Tu empresa tiene una sola sede.');
        $almacenes = Almacen::where('id_empresa_negocio', $user->id_empresa_negocio)->orderByDesc('predeterminado')->orderBy('descripcion')->get(['id_almacen', 'descripcion']);
        abort_if($almacenes->isEmpty(), 404, 'Tu sede no tiene almacén.');

        if ($request->isMethod('post')) {
            $d = $request->validate(['destino' => 'required|integer', 'id_almacen_origen' => 'required|integer', 'observaciones' => 'nullable|string|max:400',
                'items' => 'required|array|max:500', 'items.*.id' => 'required|integer', 'items.*.cantidad' => 'nullable|numeric|min:0|max:9999999'],
                ['items.required' => 'Pon la cantidad que envías de al menos un producto.']);
            try {
                $id = Requerimientos::enviarASede($user, (int) $d['destino'], (int) $d['id_almacen_origen'], $d['items'], $d['observaciones'] ?? null);
            } catch (\RuntimeException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }
            $r = DB::table('requerimientos')->find($id);
            $nuevos = DB::table('requerimientos_detalle')->where('requerimiento_id', $id)->whereNotNull('nota')->count();
            Bitacora::registrar('almacen.envio_sede', 'Envió '.Requerimientos::codigo($r).' a la sede '.$sedes->firstWhere('id_empresa_negocio', $r->id_empresa_negocio)?->nombre_comercial
                .($nuevos ? " ({$nuevos} producto(s) nuevos creados en su catálogo)" : ''), null, ['referencia' => ['requerimiento', $id]]);

            return redirect()->route('requerimientos.show', $id)->with('success', '🚚 Enviado: ya salió de tu almacén. Entra al de la otra sede cuando lo reciban.'
                .($nuevos ? " A esa sede se le crearon {$nuevos} producto(s) que no tenía." : ''));
        }

        $almacen = $almacenes->firstWhere('id_almacen', (int) $request->query('almacen')) ?? $almacenes->first();

        return view('empresas.requerimientos.enviar', [
            'sedes' => $sedes, 'almacenes' => $almacenes, 'almacen' => $almacen,
            'productos' => DB::table('productos as p')->leftJoin('producto_stock as s', fn ($j) => $j->on('s.IdProducto', '=', 'p.IdProducto')->where('s.id_almacen', $almacen->id_almacen))
                ->leftJoin('categorias as c', 'c.cat_id', '=', 'p.cat_id')
                ->where('p.id_empresa_negocio', $user->id_empresa_negocio)->where('p.proest', 'Activo')->whereIn('p.promocion', Compras::TIPOS_CON_STOCK)
                ->orderBy('c.cat_nom')->orderBy('p.pronom')->get(['p.IdProducto', 'p.pronom', 'p.umecod', 'c.cat_nom', DB::raw('COALESCE(s.stock, 0) as stock')]),
        ]);
    }

    /** La sede central envía lo pedido por otra sede */
    public function despachar(Request $request, int $id): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate(['id_almacen_origen' => 'required|integer', 'enviados' => 'required|array', 'enviados.*' => 'nullable|numeric|min:0|max:9999999']);
        try {
            $r = Requerimientos::despachar(Auth::user(), $id, (int) $d['id_almacen_origen'], $d['enviados']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        Bitacora::registrar('almacen.requerimiento_despacho', 'Despachó el requerimiento '.Requerimientos::codigo($r).' a otra sede', null, ['referencia' => ['requerimiento', $id]]);

        return back()->with('success', '🚚 '.Requerimientos::codigo($r).' despachado: ya salió de tu almacén. Entra al de la otra sede cuando lo reciban.');
    }

    /** La sede que pidió confirma lo que llegó */
    public function recibir(Request $request, int $id): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate(['recibidos' => 'nullable|array', 'recibidos.*' => 'nullable|numeric|min:0|max:9999999']);
        try {
            $r = Requerimientos::recibir(Auth::user(), $id, array_filter($d['recibidos'] ?? [], fn ($v) => $v !== null && $v !== ''));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $faltantes = Requerimientos::detalle($id)->filter(fn ($l) => (float) $l->enviado > (float) $l->recibido + 0.0001)
            ->map(fn ($l) => $l->pronom.': llegó '.(float) $l->recibido.' de '.(float) $l->enviado)->values();
        Bitacora::registrar('almacen.requerimiento_recibido', 'Recibió el requerimiento '.Requerimientos::codigo($r).' de otra sede'
            .($faltantes->isNotEmpty() ? ' con '.$faltantes->count().' faltante(s)' : ''), null,
            ['referencia' => ['requerimiento', $id], 'alerta' => $faltantes->isNotEmpty(), 'datos' => ['faltantes' => $faltantes->all()]]);

        return back()->with('success', '✔ Recibido: ya está en tu stock.'.($faltantes->isNotEmpty() ? ' Faltó: '.$faltantes->implode('; ').'.' : ''));
    }

    /** Producción del día: qué platos se van a preparar y qué insumos faltan para eso */
    public function produccion(Request $request): View|RedirectResponse
    {
        $this->autorizar();
        $sucursal = (int) Auth::user()->id_empresa_negocio;
        $almacenes = Almacen::where('id_empresa_negocio', $sucursal)->orderByDesc('predeterminado')->orderBy('descripcion')->get(['id_almacen', 'descripcion']);
        abort_if($almacenes->isEmpty(), 404, 'La sucursal no tiene almacén.');
        $almacen = $almacenes->firstWhere('id_almacen', (int) $request->input('almacen')) ?? $almacenes->first();
        $platos = collect((array) $request->input('platos', []))->map(fn ($n) => is_numeric($n) ? (float) $n : 0)->filter(fn ($n) => $n > 0);
        $resultado = $request->isMethod('post') ? Requerimientos::produccion($sucursal, (int) $almacen->id_almacen, $platos->all()) : null;

        if ($request->isMethod('post') && $request->boolean('pedir')) {
            $faltan = $resultado->filter(fn ($i) => $i->falta > 0)->mapWithKeys(fn ($i) => [$i->id => $i->falta]);
            if ($faltan->isEmpty()) {
                return back()->with('success', '✔ Alcanza con lo que hay: no hace falta pedir nada.');
            }
            session(['produccion_faltan' => $faltan->all()]);

            return redirect()->route('requerimientos.create', ['almacen' => $almacen->id_almacen]);
        }

        return view('empresas.requerimientos.produccion', [
            'almacenes' => $almacenes, 'almacen' => $almacen, 'platos' => DB::table('productos as p')
                ->where('p.id_empresa_negocio', $sucursal)->where('p.proest', 'Activo')->whereIn('p.promocion', [2, Producto::OPCION])
                ->whereExists(fn ($q) => $q->from('producto_receta as r')->whereColumn('r.IdProducto', 'p.IdProducto'))
                ->orderBy('p.pronom')->get(['p.IdProducto', 'p.pronom']),
            'cantidades' => $platos, 'resultado' => $resultado,
        ]);
    }

    public function anular(Request $request, int $id): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate(['motivo' => 'required|string|max:255'], [], ['motivo' => 'motivo']);
        try {
            $r = Requerimientos::anular(Auth::user(), $id, $d['motivo']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        Bitacora::registrar('compra.requerimiento_anular', 'Anuló el requerimiento '.Requerimientos::codigo($r).': '.$d['motivo'], null,
            ['referencia' => ['requerimiento', $id]]);

        return redirect()->route('requerimientos.index')->with('success', Requerimientos::codigo($r).' anulado.');
    }

    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->tieneModulo('/requerimientos') || Requerimientos::atiende(Auth::user()),
            403, 'No tienes permiso para los requerimientos.');
    }

    private function requerimiento(int $id): object
    {
        try {
            return Requerimientos::deSucursal(Auth::user(), $id);
        } catch (\RuntimeException) {
            abort(404);
        }
    }

    /** @return array<string, mixed> */
    private function datos(object $r): array
    {
        $usuarios = DB::table('users')->whereIn('IdUsuario', array_filter([$r->IdUsuario, $r->atendido_por]))->pluck('apeusu', 'IdUsuario');

        return [
            'r' => $r, 'codigo' => Requerimientos::codigo($r), 'lineas' => Requerimientos::detalle($r->id),
            'pidio' => $usuarios[$r->IdUsuario] ?? '', 'atendio' => $r->atendido_por ? ($usuarios[$r->atendido_por] ?? '') : null,
            'almacen' => DB::table('almacenes')->where('id_almacen', $r->id_almacen)->value('descripcion'),
            'sedePide' => DB::table('empresa_negocios')->where('id_empresa_negocio', $r->id_empresa_negocio)->value('nombre_comercial'),
            'sedeEnvia' => $r->sucursal_origen ? DB::table('empresa_negocios')->where('id_empresa_negocio', $r->sucursal_origen)->value('nombre_comercial') : null,
            'compra' => $r->com_cab_id ? DB::table('compras_cabecera')->where('com_cab_id', $r->com_cab_id)->first(['com_cab_id', 'com_doc_ser', 'com_doc_num', 'total_com']) : null,
        ];
    }
}
