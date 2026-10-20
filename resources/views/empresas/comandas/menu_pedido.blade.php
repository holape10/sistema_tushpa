@extends('layouts.app')
@section('title', 'Comanda · '.($order_type === 'salon' ? ($mesa_info['nombre'] ?? 'Mesa') : strtoupper($order_type === 'llevar' ? 'Para llevar' : $order_type)))
@section('content')
    <style>
        .no-scroll::-webkit-scrollbar { display: none; } .no-scroll { scrollbar-width: none; }
        /* Modales de presentaciones y opciones (los arma el JavaScript) */
        .pres-fondo { position: fixed; inset: 0; background: rgba(15,23,42,.6); z-index: 60; display: flex; align-items: flex-end; justify-content: center; }
        @media (min-width: 640px) { .pres-fondo { align-items: center; padding: 16px; } }
        .pres-caja { background: #fff; border-radius: 22px 22px 0 0; width: 100%; max-width: 420px; padding: 18px; box-shadow: 0 20px 40px rgba(0,0,0,.3); max-height: 90dvh; overflow-y: auto; }
        @media (min-width: 640px) { .pres-caja { border-radius: 22px; } }
        .pres-caja.grande { max-width: 560px; }
        .pres-caja h3 { margin: 0 0 4px; font-size: 1.05rem; font-weight: 800; color: #1e293b; }
        .pres-caja p { margin: 0 0 12px; font-size: .85rem; color: #64748b; }
        .pres-opcion { display: flex; width: 100%; justify-content: space-between; align-items: center; gap: 10px; border: 2px solid #e2e8f0; background: #fff; border-radius: 14px; padding: 14px; margin-bottom: 8px; font-weight: 700; color: #1e293b; }
        .pres-opcion:active { border-color: #4f46e5; background: #eef2ff; }
        .pres-opcion span:last-child { color: #4f46e5; white-space: nowrap; }
        .op-grupo { margin-bottom: 14px; }
        .op-grupo-titulo { display: flex; justify-content: space-between; align-items: center; font-weight: 800; color: #1e293b; margin-bottom: 6px; }
        .op-contador { font-size: .8em; padding: 2px 8px; border-radius: 999px; background: #fee2e2; color: #b91c1c; }
        .op-contador.ok { background: #dcfce7; color: #166534; }
        .op-lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 6px; }
        .op-item { position: relative; border: 2px solid #e2e8f0; background: #fff; border-radius: 12px; padding: 12px 10px; font-weight: 700; font-size: .9rem; color: #334155; text-align: left; }
        .op-item.sel { border-color: #4f46e5; background: #eef2ff; color: #3730a3; }
        .op-item small { display: block; color: #059669; font-weight: 700; }
        .op-item .op-veces { position: absolute; top: -8px; right: -6px; background: #4f46e5; color: #fff; border-radius: 999px; min-width: 22px; height: 22px; font-size: .8em; display: flex; align-items: center; justify-content: center; }
        .op-limpiar { font-size: .78em; color: #64748b; background: none; border: 0; text-decoration: underline; }
        .op-pie { display: flex; gap: 8px; align-items: center; margin-top: 8px; position: sticky; bottom: -18px; background: #fff; padding: 10px 0 2px; }
        .op-agregar { flex: 1; border: 0; border-radius: 14px; padding: 14px; font-weight: 800; color: #fff; background: #4f46e5; }
        .op-agregar:disabled { background: #a5b4fc; }
        .pres-cancelar { width: 100%; border: 0; background: #f1f5f9; border-radius: 14px; padding: 12px; font-weight: 700; color: #475569; }
    </style>

    @php
        $titulo = $order_type === 'salon' ? 'MESA '.($mesa_info['nombre'] ?? '') : ($order_type === 'llevar' ? 'PARA LLEVAR' : strtoupper($order_type));
        $nombreProm = $esPromotor ? ($promotores->firstWhere('IdUsuario', auth()->id())->nombre ?? auth()->user()->apeusu) : null;
    @endphp

    <div class="-m-4 sm:-m-6 pb-28 lg:pb-6" x-data="{ verPedido: false }" @cart-abrir.window="verPedido = true" @keydown.escape.window="verPedido = false">
        {{-- Cabecera: a dónde va el pedido, promotor y motorizado --}}
        <div class="sticky top-0 z-20 flex flex-wrap items-center gap-2 bg-white/95 px-4 py-2.5 shadow-sm backdrop-blur sm:px-6">
            <a href="{{ route('comandas.seleccion') }}" class="inline-flex h-10 items-center gap-1 rounded-xl bg-gray-100 px-3 text-sm font-bold text-gray-700 active:bg-gray-200">← Mesas</a>
            <h2 class="mr-auto text-base font-black text-gray-800 sm:text-lg">{{ $titulo }}</h2>
            @if ($order_type === 'delivery')
                <div class="flex items-center gap-1.5">
                    <span title="Motorizado">🛵</span>
                    <select id="motorizado" class="h-10 max-w-[160px] rounded-xl border-gray-300 text-base sm:text-sm">
                        <option value="">Motorizado…</option>
                        @foreach ($motorizados as $m)<option value="{{ $m->mot_id }}" @selected((int) $motActual === (int) $m->mot_id)>{{ $m->nombre }}</option>@endforeach
                    </select>
                    <button type="button" id="mot_nuevo" title="Nuevo motorizado" class="h-10 w-10 rounded-xl bg-gray-100 font-black text-gray-700">+</button>
                </div>
            @endif
            @if ($mozos->isNotEmpty())
                {{-- Caja o administrador: el mozo que atendió (sale en la comanda, el comprobante y el reporte por mozo) --}}
                <div class="flex items-center gap-1.5">
                    <span title="Mozo que atendió">🤵</span>
                    <select id="mozo" class="h-10 max-w-[170px] rounded-xl border-gray-300 text-base sm:text-sm" title="Mozo que atendió">
                        <option value="">Mozo…</option>
                        @foreach ($mozos as $mz)<option value="{{ $mz->IdUsuario }}" @selected((int) $mozoActual === (int) $mz->IdUsuario)>{{ $mz->nombre }}</option>@endforeach
                    </select>
                </div>
            @endif
            @if ($esPromotor)
                {{-- El promotor que entró con su usuario: el pedido es suyo --}}
                <span class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-fuchsia-50 px-3 text-sm font-bold text-fuchsia-700">⭐ {{ $nombreProm }}</span>
            @elseif ($promotores->isNotEmpty())
                <div class="flex items-center gap-1.5">
                    <span title="Promotor (opcional)">⭐</span>
                    <select id="promotor" class="h-10 max-w-[170px] rounded-xl border-gray-300 text-base sm:text-sm" title="Promotor (opcional)">
                        <option value="">Promotor…</option>
                        @foreach ($promotores as $pr)<option value="{{ $pr->IdUsuario }}" @selected((int) $promActual === (int) $pr->IdUsuario)>{{ $pr->nombre }}</option>@endforeach
                    </select>
                </div>
            @endif
        </div>

        <div class="lg:grid lg:grid-cols-[1fr_380px] lg:gap-5 lg:px-6 lg:pt-4">
            {{-- Productos --}}
            <section class="min-w-0">
                <div class="space-y-2 px-4 pb-2 pt-3 lg:px-0 lg:pt-0">
                    <label class="relative block">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔎</span>
                        <input type="search" id="buscar_producto" placeholder="Buscar producto…" autocomplete="off" autocorrect="off" spellcheck="false" enterkeyhint="search"
                               class="h-12 w-full rounded-2xl border-gray-300 bg-white pl-10 pr-3 text-base text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </label>
                    <div class="no-scroll -mx-4 flex gap-2 overflow-x-auto px-4 lg:mx-0 lg:flex-wrap lg:px-0" id="categorias">
                        @foreach ($categorias as $cat)
                            <button type="button" data-cat-id="{{ $cat->cat_id }}"
                                    class="btn-categoria h-10 shrink-0 whitespace-nowrap rounded-full px-4 text-sm font-bold ring-1 transition {{ $cat->cat_id == $cat_default_id ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-700 ring-gray-200' }}">
                                {{ $cat->cat_nom }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <div id="productos_grid" class="grid grid-cols-2 gap-2.5 px-4 pt-1 sm:grid-cols-3 xl:grid-cols-4 lg:px-0">
                    {!! $productosIniciales ?: '<p class="col-span-full py-10 text-center text-gray-400">Elige una categoría</p>' !!}
                </div>
            </section>

            {{-- Pedido: hoja que sube desde abajo en el celular; columna fija en pantallas grandes --}}
            <div x-show="verPedido" x-cloak x-transition.opacity @click="verPedido = false" class="fixed inset-0 z-40 bg-black/40 lg:hidden"></div>
            <aside class="fixed inset-x-0 bottom-0 z-50 flex max-h-[88dvh] flex-col rounded-t-3xl bg-gray-50 shadow-2xl transition-transform duration-200
                          lg:sticky lg:top-4 lg:z-0 lg:max-h-[calc(100dvh-140px)] lg:translate-y-0 lg:self-start lg:rounded-2xl lg:shadow-sm lg:ring-1 lg:ring-gray-200"
                   :class="verPedido ? 'translate-y-0' : 'translate-y-full lg:translate-y-0'">
                <div class="flex items-center gap-2 border-b px-4 py-3">
                    <h3 class="flex-1 text-base font-black text-gray-800">🧾 Pedido</h3>
                    <span class="text-lg font-black text-emerald-700" id="cart_total">S/ {{ number_format($total, 2) }}</span>
                    <button type="button" @click="verPedido = false" class="ml-1 h-9 w-9 rounded-full bg-gray-200 text-lg font-bold text-gray-600 lg:hidden" aria-label="Cerrar">&times;</button>
                </div>
                <div id="cart_items" class="flex-1 space-y-2 overflow-y-auto px-3 py-3">
                    @include('empresas.comandas.partials.cart_details', ['cart' => $cart])
                </div>
                <div class="grid grid-cols-[1fr_2fr] gap-2 border-t bg-white px-3 py-3 pb-[max(.75rem,env(safe-area-inset-bottom))] lg:rounded-b-2xl">
                    <button type="button" id="btn_vaciar" class="h-12 rounded-2xl bg-gray-100 text-sm font-bold text-gray-600 active:bg-gray-200">Vaciar nuevos</button>
                    <button type="button" class="btn-enviar h-12 rounded-2xl bg-emerald-600 text-base font-black text-white shadow active:bg-emerald-700 disabled:opacity-60">ENVIAR COMANDA</button>
                </div>
            </aside>
        </div>

        {{-- Barra fija del celular: total y enviar sin bajar --}}
        <div class="fixed inset-x-0 bottom-0 z-30 border-t bg-white/95 px-3 py-2.5 pb-[max(.625rem,env(safe-area-inset-bottom))] shadow-[0_-4px_14px_rgba(0,0,0,.06)] backdrop-blur lg:hidden">
            <div class="flex items-center gap-2">
                <button type="button" @click="verPedido = true" class="flex h-12 flex-1 items-center gap-2 rounded-2xl bg-indigo-50 px-3 text-left active:bg-indigo-100">
                    <span id="cart_unidades" class="flex h-7 min-w-[28px] items-center justify-center rounded-full bg-indigo-600 px-1.5 text-sm font-black text-white">{{ rtrim(rtrim(number_format(collect($cart)->sum('cantidad'), 2), '0'), '.') }}</span>
                    <span class="flex-1 text-sm font-bold text-indigo-800">Ver pedido <span id="cart_estado" class="font-normal text-indigo-500"></span></span>
                    <span class="text-base font-black text-indigo-900" id="cart_total_barra">S/ {{ number_format($total, 2) }}</span>
                </button>
                <button type="button" class="btn-enviar h-12 rounded-2xl bg-emerald-600 px-4 text-sm font-black text-white shadow active:bg-emerald-700 disabled:opacity-60">ENVIAR</button>
            </div>
        </div>

        {{-- Autorización (Admin/Caja) para reducir o eliminar lo ya enviado --}}
        <div id="modal_auth" class="fixed inset-0 z-[70] hidden items-end justify-center bg-black/50 sm:items-center sm:p-4">
            <div class="w-full rounded-t-3xl bg-white p-5 shadow-xl sm:max-w-sm sm:rounded-3xl">
                <h4 class="text-lg font-black text-gray-800">Autorización requerida</h4>
                <p class="mt-1 text-sm text-gray-500" id="modal_auth_texto"></p>
                <div id="auth_credenciales" class="mt-3 space-y-2 {{ $esAdmin ? 'hidden' : '' }}">
                    <label class="block text-xs font-bold text-gray-600" id="auth_label_usuario">Usuario (Admin/Caja)</label>
                    <input type="text" id="auth_user" autocomplete="off" autocapitalize="off" class="h-11 w-full rounded-xl border-gray-300 text-base">
                    <label class="block text-xs font-bold text-gray-600">Contraseña</label>
                    <input type="password" id="auth_password" class="h-11 w-full rounded-xl border-gray-300 text-base">
                </div>
                <div id="auth_motivo" class="mt-3">
                    <label class="block text-xs font-bold text-gray-600">Motivo</label>
                    <textarea id="auth_reason" rows="2" class="w-full rounded-xl border-gray-300 text-base"></textarea>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button id="auth_cancelar" type="button" class="h-12 rounded-2xl bg-gray-100 font-bold text-gray-700">Cancelar</button>
                    <button id="auth_confirmar" type="button" class="h-12 rounded-2xl bg-rose-600 font-black text-white">Confirmar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const CSRF = @json(csrf_token());
    const ES_ADMIN = @json((bool) $esAdmin);
    const URL = {
        productos: @json(route('comandas.search_products')),
        agregar: @json(route('comandas.add_to_cart')),
        actualizar: @json(route('comandas.update_cart_item')),
        quitar: @json(route('comandas.remove_cart_item')),
        carrito: @json(route('comandas.get_cart_details')),
        vaciar: @json(route('comandas.clear_cart')),
        reducir: @json(route('comandas.reducir_autorizado')),
        eliminar: @json(route('comandas.eliminar_autorizado')),
        enviar: @json(route('comandas.enviar')),
        opciones: @json(url('/comandas/opciones')),
        promotor: @json(route('comandas.promotor')),
        mozo: @json(route('comandas.mozo')),
        motAsignar: @json(route('motorizados.asignar')),
        motGuardar: @json(route('motorizados.guardar')),
        mesas: @json(route('comandas.seleccion')),
    };
    const $ = (id) => document.getElementById(id);
    const esc = (t) => String(t ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const soles = (n) => 'S/ ' + Number(n || 0).toFixed(2);
    const aviso = (t, tipo = false) => window.tushpaAviso ? window.tushpaAviso(t, tipo) : alert(t);

    function post(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(data || {}),
        }).then(async (r) => {
            if (r.status === 419) { aviso('Tu sesión expiró. Vuelve a iniciar sesión.'); setTimeout(() => location.reload(), 1500); throw new Error('419'); }
            const res = await r.json().catch(() => ({ success: false, message: 'Error del servidor (' + r.status + ').' }));
            if (r.status === 422 && res.errors) res.message = Object.values(res.errors)[0][0];
            return res;
        });
    }

    // ---------- Carrito ----------
    // Los cambios van en fila: tres toques rápidos en "+" suben 3 (cada uno parte de lo que respondió el anterior)
    let cola = Promise.resolve();
    let pendientes = 0;
    function enCola(tarea) {
        pendientes++;
        pintarEstado();
        cola = cola.then(tarea).catch((e) => { if (e?.message !== '419') aviso('Sin conexión. Revisa el internet.', false); })
            .finally(() => { pendientes--; pintarEstado(); });
        return cola;
    }
    function pintarEstado() { $('cart_estado').textContent = pendientes ? '· guardando…' : ''; }

    function pintarCarrito(d) {
        $('cart_items').innerHTML = d.vista;
        $('cart_total').textContent = soles(d.total);
        $('cart_total_barra').textContent = soles(d.total);
        pintarCantidades();
    }
    // Cuántos de cada producto van en el pedido (el número sobre cada producto)
    function pintarCantidades() {
        const porProducto = {};
        let unidades = 0;
        document.querySelectorAll('#cart_items .cart-item').forEach((i) => {
            const c = parseFloat(i.dataset.cantidad) || 0;
            porProducto[i.dataset.producto] = (porProducto[i.dataset.producto] || 0) + c;
            unidades += c;
        });
        $('cart_unidades').textContent = String(Math.round(unidades * 100) / 100);
        document.querySelectorAll('#productos_grid .product-item-kiosko').forEach((card) => {
            const b = card.querySelector('.en-pedido');
            const n = porProducto[card.dataset.id];
            if (!b) return;
            b.textContent = n ? String(Math.round(n * 100) / 100) : '';
            b.classList.toggle('hidden', !n);
            b.classList.toggle('flex', !!n);
        });
    }
    // Respuesta de un cambio: trae el carrito (una sola petición); si no lo trae, se pide
    function aplicar(res) {
        if (!res.success && res.message) aviso(res.message, false);
        if (res.vista !== undefined) { pintarCarrito(res); return res; }
        return fetch(URL.carrito, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((d) => { pintarCarrito(d); return res; });
    }
    const item = (id) => document.querySelector('#cart_items .cart-item[data-id="' + CSS.escape(String(id)) + '"]');

    function agregar(idProducto, presentacion = null, opciones = null, tarjeta = null) {
        if (tarjeta) { tarjeta.classList.add('ring-2', 'ring-indigo-500'); setTimeout(() => tarjeta.classList.remove('ring-2', 'ring-indigo-500'), 350); }
        return enCola(() => post(URL.agregar, { id: idProducto, presentacion, opciones }).then(aplicar));
    }
    function actualizar(id, calcular) {
        return enCola(() => {
            const it = item(id);
            if (!it) return;
            const datos = calcular(it);
            if (!datos) return;
            return post(URL.actualizar, Object.assign({ id }, datos)).then(aplicar);
        });
    }

    // ---------- Productos (con memoria: volver a una categoría es instantáneo) ----------
    const memoria = new Map();
    let busqueda = null;
    let pedidoActual = '';
    function cargarProductos(catId = null, texto = '') {
        const clave = texto ? 'q:' + texto.toLowerCase() : 'c:' + catId;
        pedidoActual = clave;
        const guardado = memoria.get(clave);
        if (guardado && Date.now() - guardado.t < 60000) { pintarProductos(guardado.html); return; }
        if (!guardado) $('productos_grid').style.opacity = '.55';
        const params = new URLSearchParams();
        if (texto) params.set('search_text', texto); else if (catId) params.set('category_id', catId);
        busqueda?.abort();
        busqueda = new AbortController();
        fetch(URL.productos + '?' + params, { signal: busqueda.signal, headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((d) => {
                memoria.set(clave, { html: d.vista, t: Date.now() });
                if (pedidoActual === clave) pintarProductos(d.vista);
            })
            .catch((e) => { if (e.name !== 'AbortError') $('productos_grid').style.opacity = ''; });
    }
    function pintarProductos(html) {
        $('productos_grid').innerHTML = html;
        $('productos_grid').style.opacity = '';
        pintarCantidades();
    }
    let categoriaActiva = @json($cat_default_id);
    // La primera categoría ya vino con la página: no se pide otra vez
    memoria.set('c:' + categoriaActiva, { html: $('productos_grid').innerHTML, t: Date.now() });
    pintarCantidades();
    // Se abrió con /comandas/menu?tipo=…: la dirección queda limpia (al recargar no se vuelve a abrir el pedido)
    if (location.search) history.replaceState(null, '', location.pathname);

    $('categorias').addEventListener('click', (e) => {
        const b = e.target.closest('.btn-categoria');
        if (!b) return;
        document.querySelectorAll('.btn-categoria').forEach((x) => {
            const activa = x === b;
            x.classList.toggle('bg-indigo-600', activa); x.classList.toggle('text-white', activa); x.classList.toggle('ring-indigo-600', activa);
            x.classList.toggle('bg-white', !activa); x.classList.toggle('text-gray-700', !activa); x.classList.toggle('ring-gray-200', !activa);
        });
        $('buscar_producto').value = '';
        ultimoTexto = '';
        categoriaActiva = b.dataset.catId;
        cargarProductos(categoriaActiva);
    });

    let temporizador, ultimoTexto = '';
    $('buscar_producto').addEventListener('input', function () {
        const val = this.value.trim();
        if (val === ultimoTexto) return;
        ultimoTexto = val;
        clearTimeout(temporizador);
        temporizador = setTimeout(() => cargarProductos(val ? null : categoriaActiva, val), 300);
    });

    // ---------- Presentaciones y opciones ----------
    let modalOp = null;
    function abrirOpciones(id) {
        fetch(URL.opciones + '/' + id, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((d) => {
            modalOp = { d, pres: null, sel: Object.fromEntries(d.grupos.map((g) => [g.grupo_id, []])) };
            $('pres_modal')?.remove();
            document.body.insertAdjacentHTML('beforeend', '<div class="pres-fondo" id="pres_modal"><div class="pres-caja grande" id="op_caja"></div></div>');
            pintarOpciones();
        });
    }
    function tocarOpcion(grupoId, id) {
        const g = modalOp.d.grupos.find((x) => String(x.grupo_id) === String(grupoId));
        const sel = modalOp.sel[g.grupo_id];
        const ya = sel.includes(id);
        if (ya && !g.repetir) sel.splice(sel.indexOf(id), 1);
        else if (sel.length < g.cantidad) sel.push(id);
        else if (g.cantidad === 1) { sel.length = 0; sel.push(id); }
        pintarOpciones();
    }
    function pintarOpciones() {
        const { d, sel } = modalOp;
        const presElegida = (d.presentaciones || []).find((p) => String(p.id) === String(modalOp.pres));
        const base = presElegida ? (presElegida.precio > 0 ? presElegida.precio : d.precio * presElegida.factor) : d.precio;
        let extra = 0, completo = true;
        let html = '<h3>' + esc(d.nombre) + '</h3><p>Elige las opciones del cliente</p>';
        if ((d.presentaciones || []).length) {
            html += '<div class="op-grupo"><div class="op-grupo-titulo">Presentación</div><div class="op-lista">'
                + [{ id: '', nombre: 'UNIDAD', precio: d.precio }].concat(d.presentaciones).map((p) => '<button type="button" class="op-item op-pres ' + (String(p.id || '') === String(modalOp.pres || '') ? 'sel' : '')
                    + '" data-pres="' + (p.id || '') + '">' + esc(p.nombre) + '<small>' + soles(p.precio > 0 ? p.precio : d.precio * (p.factor || 1)) + '</small></button>').join('') + '</div></div>';
        }
        d.grupos.forEach((g) => {
            const s = sel[g.grupo_id];
            const ok = g.obligatorio ? s.length === g.cantidad : true;
            if (!ok) completo = false;
            html += '<div class="op-grupo"><div class="op-grupo-titulo"><span>' + esc(g.nombre) + ' <span style="font-weight:600;color:#64748b;font-size:.85em">· '
                + (g.obligatorio ? 'elige ' + g.cantidad : 'opcional, hasta ' + g.cantidad) + '</span></span><span>'
                + (s.length ? '<button type="button" class="op-limpiar" data-grupo="' + g.grupo_id + '">limpiar</button> ' : '')
                + '<span class="op-contador ' + (ok ? 'ok' : '') + '">' + s.length + '/' + g.cantidad + '</span></span></div><div class="op-lista">';
            g.items.forEach((it) => {
                const veces = s.filter((x) => x === it.id).length;
                extra += veces * it.precio_extra;
                html += '<button type="button" class="op-item ' + (veces ? 'sel' : '') + '" data-grupo="' + g.grupo_id + '" data-id="' + it.id + '">' + esc(it.nombre)
                    + (it.precio_extra > 0 ? '<small>+ ' + soles(it.precio_extra) + '</small>' : '') + (veces > 1 ? '<span class="op-veces">' + veces + '</span>' : '') + '</button>';
            });
            html += '</div></div>';
        });
        html += '<div class="op-pie"><button type="button" class="pres-cancelar" style="width:auto;padding:14px 16px">Cancelar</button>'
            + '<button type="button" class="op-agregar" ' + (completo ? '' : 'disabled') + '>Agregar · ' + soles(base + extra) + '</button></div>';
        $('op_caja').innerHTML = html;
    }
    function elegirPresentacion(card) {
        const opciones = [{ id: '', nombre: 'UNIDAD', precio: card.dataset.precio }].concat(JSON.parse(card.dataset.presentaciones));
        document.body.insertAdjacentHTML('beforeend', '<div class="pres-fondo" id="pres_modal"><div class="pres-caja">'
            + '<h3>' + esc(card.dataset.nombre) + '</h3><p>¿Qué presentación?</p>'
            + opciones.map((o) => '<button type="button" class="pres-opcion" data-id="' + card.dataset.id + '" data-pres="' + o.id + '">'
                + '<span>' + esc(o.nombre) + '</span><span>' + soles(o.precio) + '</span></button>').join('')
            + '<button type="button" class="pres-cancelar">Cancelar</button></div></div>');
    }

    // ---------- Autorización (Admin/Caja) ----------
    let pendienteAuth = null, opcionesAuth = {};
    function abrirModalAuth(texto, cb, opciones = {}) {
        opcionesAuth = Object.assign({ soloAdmin: false, pideMotivo: true }, opciones);
        $('modal_auth_texto').textContent = texto;
        $('auth_label_usuario').textContent = opcionesAuth.soloAdmin ? 'Usuario (Administrador)' : 'Usuario (Admin/Caja)';
        $('auth_motivo').classList.toggle('hidden', !opcionesAuth.pideMotivo);
        ['auth_user', 'auth_password', 'auth_reason'].forEach((i) => $(i).value = '');
        $('modal_auth').classList.remove('hidden'); $('modal_auth').classList.add('flex');
        pendienteAuth = cb;
    }
    function cerrarModalAuth() { $('modal_auth').classList.add('hidden'); $('modal_auth').classList.remove('flex'); pendienteAuth = null; }
    $('auth_cancelar').addEventListener('click', cerrarModalAuth);
    $('auth_confirmar').addEventListener('click', () => {
        const user = $('auth_user').value.trim(), password = $('auth_password').value, reason = $('auth_reason').value.trim();
        if (opcionesAuth.pideMotivo && !reason) { aviso('Escribe el motivo.', 'aviso'); return; }
        if (!ES_ADMIN && (!user || !password)) { aviso('Completa usuario y contraseña.', 'aviso'); return; }
        if (pendienteAuth) pendienteAuth(user, password, reason);
    });
    const conAuth = (url, datos) => (user, pass, reason) => post(url, Object.assign(datos, { auth_user: user, auth_password: pass, reason }))
        .then((res) => { if (!res.success) { aviso(res.message, false); return; } cerrarModalAuth(); aplicar(res); });

    // ---------- Observaciones: se guardan al salir del campo ----------
    document.addEventListener('change', (e) => {
        const obs = e.target.closest('.cart-obs');
        if (obs) { const id = obs.closest('.cart-item').dataset.id; actualizar(id, () => ({ observaciones: obs.value })); }
    });

    // ---------- Un solo listener de toques ----------
    document.addEventListener('click', (e) => {
        const card = e.target.closest('.product-item-kiosko');
        if (card) {
            if (card.dataset.opciones) abrirOpciones(card.dataset.id);
            else if (card.dataset.presentaciones) elegirPresentacion(card);
            else agregar(card.dataset.id, null, null, card);
            return;
        }
        const op = e.target.closest('.op-item');
        if (op && !op.classList.contains('op-pres')) { tocarOpcion(op.dataset.grupo, parseInt(op.dataset.id, 10)); return; }
        const limpiar = e.target.closest('.op-limpiar');
        if (limpiar) { modalOp.sel[limpiar.dataset.grupo] = []; pintarOpciones(); return; }
        const presOp = e.target.closest('.op-pres');
        if (presOp) { modalOp.pres = presOp.dataset.pres || null; pintarOpciones(); return; }
        if (e.target.closest('.op-agregar')) {
            const boton = e.target.closest('.op-agregar');
            boton.disabled = true;
            // Si algo falla (ej. no hay stock) el modal sigue abierto para cambiar la elección
            enCola(() => post(URL.agregar, { id: modalOp.d.id, presentacion: modalOp.pres, opciones: modalOp.sel }).then((res) => {
                boton.disabled = false;
                if (!res.success) { aviso(res.message || 'No se pudo agregar.', false); return; }
                $('pres_modal')?.remove();
                aplicar(res);
            }));
            return;
        }
        const opcion = e.target.closest('.pres-opcion');
        if (opcion) { agregar(opcion.dataset.id, opcion.dataset.pres || null); $('pres_modal')?.remove(); return; }
        if (e.target.closest('.pres-cancelar') || e.target.classList.contains('pres-fondo')) { $('pres_modal')?.remove(); return; }

        const plus = e.target.closest('.btn-plus');
        if (plus) { actualizar(plus.closest('.cart-item').dataset.id, (it) => ({ cantidad: parseFloat(it.dataset.cantidad) + 1 })); return; }

        const minus = e.target.closest('.btn-minus');
        if (minus) {
            const id = minus.closest('.cart-item').dataset.id;
            actualizar(id, (it) => {
                const actual = parseFloat(it.dataset.cantidad), nueva = actual - 1;
                if (nueva < 1) return null;
                if (nueva < parseFloat(it.dataset.facturado || 0)) { aviso('Ya se cobraron ' + it.dataset.facturado + ' de este producto en una cuenta separada; no se puede bajar más.', 'aviso'); return null; }
                if (it.dataset.old === '1' && nueva < parseFloat(it.dataset.minima)) {
                    abrirModalAuth('Vas a reducir un ítem ya enviado a cocina de ' + actual + ' a ' + nueva + '.', conAuth(URL.reducir, { id, cantidad: nueva }));
                    return null;
                }
                return { cantidad: nueva };
            });
            return;
        }
        const del = e.target.closest('.btn-quitar');
        if (del) { const id = del.closest('.cart-item').dataset.id; enCola(() => post(URL.quitar, { id }).then(aplicar)); return; }

        const delAuth = e.target.closest('.btn-quitar-autorizado');
        if (delAuth) {
            const it = delAuth.closest('.cart-item');
            abrirModalAuth('Vas a eliminar por completo "' + it.querySelector('.cart-item-name').firstChild.textContent.trim() + '" del pedido.', conAuth(URL.eliminar, { id: it.dataset.id }));
            return;
        }
        if (e.target.closest('.btn-enviar')) enviar();
    });

    // ---------- Mozo, promotor y motorizado ----------
    $('mozo')?.addEventListener('change', (e) => post(URL.mozo, { mozo_id: e.target.value || null }));
    $('promotor')?.addEventListener('change', (e) => post(URL.promotor, { promotor_id: e.target.value || null }));
    const selMot = $('motorizado');
    if (selMot) {
        selMot.addEventListener('change', () => post(URL.motAsignar, { mot_id: selMot.value || null }));
        $('mot_nuevo').addEventListener('click', async () => {
            const nombre = prompt('Nombre del motorizado:');
            if (!nombre || nombre.trim().length < 2) return;
            const r = await post(URL.motGuardar, { nombre: nombre.trim() });
            if (!r.ok) { aviso(r.message || r.mensaje || 'No se pudo guardar.', false); return; }
            selMot.add(new Option(r.motorizado.nombre, r.motorizado.mot_id, true, true));
            post(URL.motAsignar, { mot_id: r.motorizado.mot_id });
        });
    }

    // ---------- Enviar / vaciar ----------
    let enviando = false;
    function enviar() {
        if (enviando) return;
        enviando = true;
        const botones = document.querySelectorAll('.btn-enviar');
        botones.forEach((b) => { b.disabled = true; b.dataset.texto = b.textContent; b.textContent = 'ENVIANDO…'; });
        const restaurar = () => { enviando = false; botones.forEach((b) => { b.disabled = false; b.textContent = b.dataset.texto; }); };
        // Lo que esté escrito en una observación (sin salir del campo) se guarda antes; y se espera la fila de cambios
        const activo = document.activeElement;
        if (activo?.classList.contains('cart-obs')) { const id = activo.closest('.cart-item').dataset.id; actualizar(id, () => ({ observaciones: activo.value })); }
        cola.then(() => post(URL.enviar, {})).then((res) => {
            if (res.success) { window.location.href = URL.mesas; return; }
            aviso(res.message, false);
            restaurar();
        }).catch(() => { aviso('Error de conexión. Intenta de nuevo.', false); restaurar(); });
    }

    // Vaciar: requiere datos de un Administrador (si el usuario ya es admin, solo confirma)
    $('btn_vaciar').addEventListener('click', () => {
        const hayNuevos = [...document.querySelectorAll('#cart_items .cart-item')].some((i) => i.dataset.old !== '1');
        if (!hayNuevos) { aviso('No hay productos nuevos para vaciar.', 'aviso'); return; }
        const texto = '¿Vaciar los productos nuevos del pedido? Lo ya enviado a cocina se mantiene.';
        if (ES_ADMIN) {
            if (confirm(texto)) enCola(() => post(URL.vaciar, {}).then(aplicar));
            return;
        }
        abrirModalAuth(texto, (user, pass) => post(URL.vaciar, { auth_user: user, auth_password: pass }).then((res) => {
            if (!res.success) { aviso(res.message, false); return; }
            cerrarModalAuth();
            aplicar(res);
        }), { soloAdmin: true, pideMotivo: false });
    });
})();
</script>
@endpush
