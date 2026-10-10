<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comanda - Sistema Tushpa</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8f9fa; }
        .header-kiosko {
            background-color: #3498db; color: white; padding: 10px; text-align: center;
            font-size: 1.1em; font-weight: bold; margin-bottom: 10px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .header-kiosko .back-button { background: none; border: none; color: white; font-size: 1.2em; cursor: pointer; padding: 6px; }
        .main-content { display: flex; flex-wrap: wrap; }
        .menu-section { flex: 2; padding: 10px; border-right: 1px solid #eee; min-width: 300px; }
        .cart-section { flex: 1; padding: 10px; background-color: #e9ecef; border-radius: 8px; min-width: 280px; }

        .category-buttons-container-kiosko { display: flex; flex-wrap: wrap; gap: 8px; padding: 2px; margin-bottom: 10px; }
        .btn-category-kiosko {
            height: 40px; padding: 0 14px; font-size: 1.0em; font-weight: bold; color: white;
            border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none;
            display: flex; align-items: center; justify-content: center; white-space: nowrap;
        }
        .btn-category-kiosko.active { border: 3px solid #f0ad4e; }

        .product-search-box-kiosko { padding: 10px; background-color: #6c757d; color: white; font-weight: bold; text-align: center; border-radius: 5px; margin-bottom: 15px; }
        .product-search-box-kiosko input { border-radius: 5px; padding: 10px; font-size: 1.1em; border: none; width: 100%; }

        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px; padding: 5px; max-height: calc(100vh - 300px); overflow-y: auto; }
        .product-item-kiosko {
            border: 1px solid #ddd; border-radius: 8px; padding: 8px; background-color: #fff;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); cursor: pointer;
            transition: transform 0.1s ease; height: 110px;
        }
        .product-item-kiosko:hover { transform: translateY(-3px); box-shadow: 0 5px 10px rgba(0,0,0,0.15); }
        .product-item-kiosko .product-name-kiosko { font-size: 0.9em; font-weight: bold; color: #333; line-height: 1.2; }
        .product-item-kiosko .product-price-kiosko { font-size: 1.05em; font-weight: bold; color: #007bff; margin-top: 4px; }
        .product-item-kiosko .product-stock-kiosko { font-size: 0.75em; color: #28a745; }
        .product-item-kiosko .product-pres-kiosko { font-size: 0.72em; font-weight: bold; color: #4f46e5; background: #eef2ff; border-radius: 6px; padding: 2px 6px; margin-top: 4px; }
        .pres-fondo { position: fixed; inset: 0; background: rgba(15,23,42,.6); z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 16px; }
        .pres-caja { background: #fff; border-radius: 18px; width: 100%; max-width: 380px; padding: 18px; box-shadow: 0 20px 40px rgba(0,0,0,.3); }
        .pres-caja h3 { margin: 0 0 4px; font-size: 1.05em; font-weight: 800; color: #1e293b; }
        .pres-caja p { margin: 0 0 12px; font-size: .85em; color: #64748b; }
        .pres-opcion { display: flex; width: 100%; justify-content: space-between; align-items: center; gap: 10px; border: 2px solid #e2e8f0; background: #fff; border-radius: 12px; padding: 12px 14px; margin-bottom: 8px; font-weight: 700; font-size: 1em; color: #1e293b; cursor: pointer; }
        .pres-opcion:hover { border-color: #4f46e5; background: #eef2ff; }
        .pres-opcion span:last-child { color: #4f46e5; white-space: nowrap; }
        .cart-opciones { display: block; font-size: .78em; font-weight: 600; color: #92400e; background: #fef3c7; border-radius: 6px; padding: 2px 6px; margin-top: 3px; }
        .op-grupo { margin-bottom: 14px; }
        .op-grupo-titulo { display: flex; justify-content: space-between; align-items: center; font-weight: 800; color: #1e293b; margin-bottom: 6px; }
        .op-contador { font-size: .8em; padding: 2px 8px; border-radius: 999px; background: #fee2e2; color: #b91c1c; }
        .op-contador.ok { background: #dcfce7; color: #166534; }
        .op-lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 6px; }
        .op-item { position: relative; border: 2px solid #e2e8f0; background: #fff; border-radius: 12px; padding: 10px; font-weight: 700; font-size: .88em; color: #334155; cursor: pointer; text-align: left; }
        .op-item.sel { border-color: #4f46e5; background: #eef2ff; color: #3730a3; }
        .op-item small { display: block; color: #059669; font-weight: 700; }
        .op-item .op-veces { position: absolute; top: -8px; right: -6px; background: #4f46e5; color: #fff; border-radius: 999px; min-width: 22px; height: 22px; font-size: .8em; display: flex; align-items: center; justify-content: center; }
        .op-limpiar { font-size: .78em; color: #64748b; background: none; border: 0; cursor: pointer; text-decoration: underline; }
        .op-pie { display: flex; gap: 8px; align-items: center; margin-top: 8px; position: sticky; bottom: -18px; background: #fff; padding: 10px 0 2px; }
        .op-agregar { flex: 1; border: 0; border-radius: 12px; padding: 12px; font-weight: 800; color: #fff; background: #4f46e5; cursor: pointer; }
        .op-agregar:disabled { background: #a5b4fc; cursor: not-allowed; }
        .pres-caja.grande { max-width: 560px; max-height: 88vh; overflow-y: auto; }
        .pres-cancelar { width: 100%; border: 0; background: #f1f5f9; border-radius: 12px; padding: 10px; font-weight: 700; color: #475569; cursor: pointer; }

        .cart-header { font-size: 1.4em; font-weight: bold; color: #333; margin-bottom: 15px; text-align: center; }
        .cart-items-container { max-height: calc(100vh - 400px); overflow-y: auto; border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-bottom: 10px; }
        .cart-item { display: flex; flex-wrap: wrap; align-items: center; margin-bottom: 10px; background: #fff; padding: 10px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .cart-item-name { flex: 1; font-size: 1.0em; font-weight: bold; color: #333; min-width: 130px; }
        .cart-item-qty-control { display: flex; align-items: center; }
        .cart-item-qty-control input { width: 45px; text-align: center; font-size: 1em; margin: 0 5px; border: 1px solid #ccc; border-radius: 4px; padding: 4px 0; }
        .btn-qty { padding: 3px 9px; font-size: 1.1em; border-radius: 4px; background-color: #007bff; color: white; border: none; }
        .cart-item-total-price { font-weight: bold; margin-left: 10px; color: #007bff; }
        .cart-item-remove { background-color: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 5px; margin-left: auto; font-size: 0.85em; }
        .cart-item-old { background-color: #f2dede; color: #843534; font-size: 0.8em; padding: 4px 8px; border-radius: 5px; margin-left: auto; }

        .cart-item-enviado { border-left: 4px solid #f0ad4e; }
        .badge-enviado { display: inline-block; background: #f0ad4e; color: #fff; font-size: 0.65em; padding: 1px 6px; border-radius: 8px; vertical-align: middle; }
        .badge-nuevo { display: inline-block; background: #28a745; color: #fff; font-size: 0.65em; padding: 1px 6px; border-radius: 8px; vertical-align: middle; }
        .badge-cobrado { display: inline-block; background: #6c757d; color: #fff; font-size: 0.65em; padding: 1px 6px; border-radius: 8px; vertical-align: middle; }
        .cart-obs { flex-basis: 100%; margin-top: 6px; padding: 5px 8px; border: 1px solid #ddd; border-radius: 5px; font-size: 0.85em; }
        .cart-obs-texto { flex-basis: 100%; margin-top: 4px; color: #888; font-style: italic; }
        .cart-total { font-size: 1.6em; font-weight: bold; text-align: right; margin-top: 10px; color: #28a745; }
        .cart-actions { text-align: center; margin-top: 15px; }
        .cart-actions .btn { font-size: 1.2em; padding: 15px; margin: 5px 0; border-radius: 8px; width: 100%; }
        .btn-send-order { background-color: #28a745; color: white; }
        .btn-clear-cart { background-color: #dc3545; color: white; }

        @media (max-width: 991px) {
            .menu-section, .cart-section { flex-basis: 100%; border-right: none; margin-bottom: 20px; }
            .menu-section { order: 2; }
            .cart-section { order: 1; }
        }
    </style>
    @include('partials.pwa')
</head>
<body>
    <div class="header-kiosko">
        <button class="back-button" onclick="window.location.href='{{ route('comandas.seleccion') }}'">
            <i class="fas fa-arrow-left"></i>
        </button>
        <span>
            TU PEDIDO -
            @if ($order_type == 'salon')
                MESA: {{ $mesa_info['nombre'] ?? '' }}
            @else
                {{ strtoupper($order_type) }}
            @endif
        </span>
        @if ($order_type === 'delivery')
            {{-- Delivery: quién lo lleva (se puede crear uno nuevo aquí mismo) --}}
            <span style="display:flex; align-items:center; gap:6px; font-size:14px;">
                <i class="fas fa-motorcycle"></i>
                <select id="motorizado" style="color:#111; border-radius:6px; padding:4px 6px; font-size:14px; max-width:180px;">
                    <option value="">Motorizado…</option>
                    @foreach ($motorizados as $m)<option value="{{ $m->mot_id }}" @selected((int) $motActual === (int) $m->mot_id)>{{ $m->nombre }}</option>@endforeach
                </select>
                <button type="button" id="mot_nuevo" title="Nuevo motorizado" style="border:0; border-radius:6px; padding:4px 9px; background:#fff; color:#111; font-weight:bold;">+</button>
            </span>
        @else
            <span></span>
        @endif
    </div>

    <div class="container-fluid main-content">
        <div class="menu-section">
            <div class="category-buttons-container-kiosko">
                @foreach ($categorias as $cat)
                    <button type="button" class="btn-category-kiosko {{ $cat->cat_id == $cat_default_id ? 'active' : '' }}"
                        data-cat-id="{{ $cat->cat_id }}" style="background-color: {{ $cat->color ?? '#3f4aee' }};">
                        {{ $cat->cat_nom }}
                    </button>
                @endforeach
            </div>

            <div class="product-search-box-kiosko">
                PRODUCTOS
                <input type="text" id="buscar_producto" placeholder="BUSCAR PRODUCTO">
            </div>

            <div id="productos_grid" class="products-grid"></div>
        </div>

        <div class="cart-section">
            <div class="cart-header">Tu Carrito</div>
            <div id="cart_items" class="cart-items-container">
                @include('empresas.comandas.partials.cart_details', ['cart' => $cart])
            </div>
            <div class="cart-total" id="cart_total">
                Total: S/ {{ number_format(collect($cart)->sum(fn($i) => $i['cantidad'] * $i['precio']), 2) }}
            </div>
            <div class="cart-actions">
                <button id="btn_enviar" class="btn btn-send-order"><strong>ENVIAR COMANDA</strong></button>
                <button id="btn_vaciar" class="btn btn-clear-cart"><strong>VACIAR PEDIDO</strong></button>
            </div>
        </div>
    </div>

    <!-- El modal va ANTES del <script> para que exista en el DOM cuando el script lo busca -->
    <div id="modal_auth" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:10px; padding:20px; width:320px; max-width:90%;">
            <h4 id="modal_auth_titulo" style="margin-top:0;">Autorización requerida</h4>
            <p style="font-size:0.85em; color:#666;" id="modal_auth_texto"></p>
            <div id="auth_credenciales" @if ($esAdmin) style="display:none;" @endif>
            <label style="font-size:0.8em; font-weight:bold;" id="auth_label_usuario">Usuario (Admin/Caja)</label>
            <input type="text" id="auth_user" style="width:100%; padding:8px; margin-bottom:8px; border:1px solid #ccc; border-radius:5px;">
            <label style="font-size:0.8em; font-weight:bold;">Contraseña</label>
            <input type="password" id="auth_password" style="width:100%; padding:8px; margin-bottom:8px; border:1px solid #ccc; border-radius:5px;">
            </div>
            <div id="auth_motivo">
            <label style="font-size:0.8em; font-weight:bold;">Motivo</label>
            <textarea id="auth_reason" rows="2" style="width:100%; padding:8px; margin-bottom:12px; border:1px solid #ccc; border-radius:5px;"></textarea>
            </div>
            <div style="display:flex; gap:8px;">
                <button id="auth_cancelar" type="button" style="flex:1; padding:10px; border:none; border-radius:6px; background:#ccc;">Cancelar</button>
                <button id="auth_confirmar" type="button" style="flex:1; padding:10px; border:none; border-radius:6px; background:#dc3545; color:#fff; font-weight:bold;">Confirmar</button>
            </div>
        </div>
    </div>

    <script>
        const CSRF = '{{ csrf_token() }}';
        const ES_ADMIN = {{ $esAdmin ? 'true' : 'false' }};
        function post(url, data) {
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(data || {})
            }).then(async r => {
                if (r.status === 419) { alert('Tu sesión expiró. Vuelve a iniciar sesión.'); location.reload(); throw new Error('419'); }
                const res = await r.json().catch(() => ({ success: false, message: 'Error del servidor (' + r.status + ').' }));
                if (r.status === 422 && res.errors) res.message = Object.values(res.errors)[0][0];
                return res;
            });
        }

        // --- Autorización (Admin/Caja) ---
        let pendienteAuth = null, opcionesAuth = {};

        // opciones: { soloAdmin: solo un Administrador autoriza, pideMotivo: false para no pedir motivo }
        function abrirModalAuth(texto, cb, opciones = {}) {
            opcionesAuth = Object.assign({ soloAdmin: false, pideMotivo: true }, opciones);
            document.getElementById('modal_auth_texto').textContent = texto;
            document.getElementById('auth_label_usuario').textContent = opcionesAuth.soloAdmin ? 'Usuario (Administrador)' : 'Usuario (Admin/Caja)';
            document.getElementById('auth_motivo').style.display = opcionesAuth.pideMotivo ? '' : 'none';
            document.getElementById('auth_user').value = '';
            document.getElementById('auth_password').value = '';
            document.getElementById('auth_reason').value = '';
            document.getElementById('modal_auth').style.display = 'flex';
            if (!ES_ADMIN) document.getElementById('auth_user').focus();
            pendienteAuth = cb;
        }
        function cerrarModalAuth() { document.getElementById('modal_auth').style.display = 'none'; pendienteAuth = null; }

        document.getElementById('auth_cancelar').addEventListener('click', cerrarModalAuth);

        document.getElementById('auth_confirmar').addEventListener('click', function () {
            const user = document.getElementById('auth_user').value.trim();
            const password = document.getElementById('auth_password').value;
            const reason = document.getElementById('auth_reason').value.trim();
            if (opcionesAuth.pideMotivo && !reason) { alert('Escribe el motivo.'); return; }
            if (!ES_ADMIN && (!user || !password)) { alert('Completa usuario y contraseña.'); return; }
            if (pendienteAuth) pendienteAuth(user, password, reason);
        });

        // --- Productos ---
        function cargarProductos(catId = null, texto = '') {
            const params = new URLSearchParams();
            if (texto) params.set('search_text', texto); else if (catId) params.set('category_id', catId);
            fetch(`{{ route('comandas.search_products') }}?${params}`)
                .then(r => r.json())
                .then(data => document.getElementById('productos_grid').innerHTML = data.vista);
        }
        cargarProductos({{ $cat_default_id ?? 'null' }});

        document.querySelectorAll('.btn-category-kiosko').forEach(b => b.addEventListener('click', function () {
            document.querySelectorAll('.btn-category-kiosko').forEach(x => x.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('buscar_producto').value = '';
            cargarProductos(this.dataset.catId);
        }));

        let timer;
        document.getElementById('buscar_producto').addEventListener('keyup', function () {
            clearTimeout(timer);
            const val = this.value;
            timer = setTimeout(() => cargarProductos(null, val), 400);
        });

        // --- Carrito ---
        function recargarCarrito() {
            return fetch("{{ route('comandas.get_cart_details') }}")
                .then(r => r.json())
                .then(data => {
                    document.getElementById('cart_items').innerHTML = data.vista;
                    document.getElementById('cart_total').textContent = 'Total: S/ ' + Number(data.total).toFixed(2);
                });
        }

        function actualizarItem(id, datos) {
            return post("{{ route('comandas.update_cart_item') }}", Object.assign({ id }, datos)).then(res => {
                if (!res.success && res.message) alert(res.message);
                return recargarCarrito();
            });
        }

        function agregarProducto(idProducto, presentacion = null, opciones = null) {
            return post("{{ route('comandas.add_to_cart') }}", { id: idProducto, presentacion: presentacion, opciones: opciones }).then(res => {
                if (!res.success) alert(res.message || 'No se pudo agregar.');
                return recargarCarrito();
            });
        }

        // Plato con opciones (entrada del menú, arma tu trío): el mozo elige en un modal
        let modalOp = null;
        function abrirOpciones(id) {
            fetch("{{ url('/comandas/opciones') }}/" + id, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(d => {
                modalOp = { d, pres: null, sel: Object.fromEntries(d.grupos.map(g => [g.grupo_id, []])) };
                document.getElementById('pres_modal')?.remove();
                document.body.insertAdjacentHTML('beforeend', '<div class="pres-fondo" id="pres_modal"><div class="pres-caja grande" id="op_caja"></div></div>');
                pintarOpciones();
            });
        }
        function tocarOpcion(grupoId, id) {
            const g = modalOp.d.grupos.find(x => String(x.grupo_id) === String(grupoId));
            const sel = modalOp.sel[g.grupo_id];
            const ya = sel.includes(id);
            if (ya && !g.repetir) sel.splice(sel.indexOf(id), 1);
            else if (sel.length < g.cantidad) sel.push(id);
            else if (g.cantidad === 1) { sel.length = 0; sel.push(id); }
            pintarOpciones();
        }
        function pintarOpciones() {
            const { d, sel } = modalOp;
            const esc = (t) => String(t).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
            const soles = (n) => 'S/ ' + Number(n).toFixed(2);
            const presElegida = (d.presentaciones || []).find(p => String(p.id) === String(modalOp.pres));
            const base = presElegida ? (presElegida.precio > 0 ? presElegida.precio : d.precio * presElegida.factor) : d.precio;
            let extra = 0, completo = true;
            let html = '<h3>' + esc(d.nombre) + '</h3><p>Elige las opciones del cliente</p>';
            if ((d.presentaciones || []).length) {
                html += '<div class="op-grupo"><div class="op-grupo-titulo">Presentación</div><div class="op-lista">'
                    + [{ id: '', nombre: 'UNIDAD', precio: d.precio }].concat(d.presentaciones).map(p => '<button type="button" class="op-item op-pres ' + (String(p.id || '') === String(modalOp.pres || '') ? 'sel' : '')
                        + '" data-pres="' + (p.id || '') + '">' + esc(p.nombre) + '<small>' + soles(p.precio > 0 ? p.precio : d.precio * (p.factor || 1)) + '</small></button>').join('') + '</div></div>';
            }
            d.grupos.forEach(g => {
                const s = sel[g.grupo_id];
                const ok = g.obligatorio ? s.length === g.cantidad : true;
                if (!ok) completo = false;
                html += '<div class="op-grupo"><div class="op-grupo-titulo"><span>' + esc(g.nombre) + ' <span style="font-weight:600;color:#64748b;font-size:.85em">· '
                    + (g.obligatorio ? 'elige ' + g.cantidad : 'opcional, hasta ' + g.cantidad) + '</span></span><span>'
                    + (s.length ? '<button type="button" class="op-limpiar" data-grupo="' + g.grupo_id + '">limpiar</button> ' : '')
                    + '<span class="op-contador ' + (ok ? 'ok' : '') + '">' + s.length + '/' + g.cantidad + '</span></span></div><div class="op-lista">';
                g.items.forEach(it => {
                    const veces = s.filter(x => x === it.id).length;
                    extra += veces * it.precio_extra;
                    html += '<button type="button" class="op-item ' + (veces ? 'sel' : '') + '" data-grupo="' + g.grupo_id + '" data-id="' + it.id + '">' + esc(it.nombre)
                        + (it.precio_extra > 0 ? '<small>+ ' + soles(it.precio_extra) + '</small>' : '') + (veces > 1 ? '<span class="op-veces">' + veces + '</span>' : '') + '</button>';
                });
                html += '</div></div>';
            });
            html += '<div class="op-pie"><button type="button" class="pres-cancelar" style="width:auto;padding:12px 16px">Cancelar</button>'
                + '<button type="button" class="op-agregar" ' + (completo ? '' : 'disabled') + '>Agregar · ' + soles(base + extra) + '</button></div>';
            document.getElementById('op_caja').innerHTML = html;
        }

        // Producto con presentaciones (TAJADA / ENTERA, VASO / JARRA): el mozo elige cuál
        function elegirPresentacion(card) {
            const esc = (t) => String(t).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
            const soles = (n) => 'S/ ' + Number(n).toFixed(2);
            const opciones = [{ id: '', nombre: 'UNIDAD', precio: card.dataset.precio }].concat(JSON.parse(card.dataset.presentaciones));
            const html = '<div class="pres-fondo" id="pres_modal"><div class="pres-caja">'
                + '<h3>' + esc(card.dataset.nombre) + '</h3><p>¿Qué presentación?</p>'
                + opciones.map((o) => '<button type="button" class="pres-opcion" data-id="' + card.dataset.id + '" data-pres="' + o.id + '">'
                    + '<span>' + esc(o.nombre) + '</span><span>' + soles(o.precio) + '</span></button>').join('')
                + '<button type="button" class="pres-cancelar">Cancelar</button></div></div>';
            document.body.insertAdjacentHTML('beforeend', html);
        }

        // Observaciones de los ítems nuevos: se guardan al salir del campo
        document.addEventListener('change', function (e) {
            const obs = e.target.closest('.cart-obs');
            if (obs) actualizarItem(obs.closest('.cart-item').dataset.id, { observaciones: obs.value });
        });

        // --- Un solo listener de clicks para toda la página ---
        document.addEventListener('click', function (e) {
            const card = e.target.closest('.product-item-kiosko');
            if (card) {
                if (card.dataset.opciones) abrirOpciones(card.dataset.id);
                else if (card.dataset.presentaciones) elegirPresentacion(card);
                else agregarProducto(card.dataset.id);
                return;
            }
            const op = e.target.closest('.op-item');
            if (op) { tocarOpcion(op.dataset.grupo, parseInt(op.dataset.id, 10)); return; }
            const limpiar = e.target.closest('.op-limpiar');
            if (limpiar) { modalOp.sel[limpiar.dataset.grupo] = []; pintarOpciones(); return; }
            const presOp = e.target.closest('.op-pres');
            if (presOp) { modalOp.pres = presOp.dataset.pres || null; pintarOpciones(); return; }
            if (e.target.closest('.op-agregar')) {
                // Si algo falla (ej. no hay stock) el modal sigue abierto para cambiar la elección
                post("{{ route('comandas.add_to_cart') }}", { id: modalOp.d.id, presentacion: modalOp.pres, opciones: modalOp.sel }).then(res => {
                    if (!res.success) { alert(res.message || 'No se pudo agregar.'); return; }
                    document.getElementById('pres_modal')?.remove();
                    recargarCarrito();
                });
                return;
            }
            const opcion = e.target.closest('.pres-opcion');
            if (opcion) {
                agregarProducto(opcion.dataset.id, opcion.dataset.pres || null);
                document.getElementById('pres_modal')?.remove();
                return;
            }
            if (e.target.closest('.pres-cancelar') || e.target.classList.contains('pres-fondo')) {
                document.getElementById('pres_modal')?.remove();
                return;
            }

            const plus = e.target.closest('.btn-plus');
            if (plus) {
                const item = plus.closest('.cart-item');
                // Mismo producto = misma línea (también para lo ya enviado)
                actualizarItem(item.dataset.id, { cantidad: parseFloat(item.dataset.cantidad) + 1 });
                return;
            }

            const minus = e.target.closest('.btn-minus');
            if (minus) {
                const item = minus.closest('.cart-item');
                const actual = parseFloat(item.dataset.cantidad);
                const minima = parseFloat(item.dataset.minima);
                const nueva = actual - 1;
                if (nueva < 1) return;
                if (nueva < parseFloat(item.dataset.facturado || 0)) {
                    alert('Ya se cobraron ' + item.dataset.facturado + ' de este producto en una cuenta separada; no se puede bajar más.');
                    return;
                }

                if (item.dataset.old === '1' && nueva < minima) {
                    abrirModalAuth('Vas a reducir un ítem ya enviado a cocina de ' + actual + ' a ' + nueva + '.', function (user, pass, reason) {
                        post("{{ route('comandas.reducir_autorizado') }}", { id: item.dataset.id, cantidad: nueva, auth_user: user, auth_password: pass, reason: reason })
                            .then(res => {
                                if (!res.success) { alert(res.message); return; }
                                cerrarModalAuth();
                                recargarCarrito();
                            });
                    });
                } else {
                    actualizarItem(item.dataset.id, { cantidad: nueva });
                }
                return;
            }

            const del = e.target.closest('.btn-quitar');
            if (del) {
                const item = del.closest('.cart-item');
                post("{{ route('comandas.remove_cart_item') }}", { id: item.dataset.id }).then(res => {
                    if (!res.success) alert(res.message);
                    recargarCarrito();
                });
                return;
            }

            const delAuth = e.target.closest('.btn-quitar-autorizado');
            if (delAuth) {
                const item = delAuth.closest('.cart-item');
                abrirModalAuth('Vas a eliminar por completo "' + item.querySelector('.cart-item-name').textContent.trim() + '" del pedido.', function (user, pass, reason) {
                    post("{{ route('comandas.eliminar_autorizado') }}", { id: item.dataset.id, auth_user: user, auth_password: pass, reason: reason })
                        .then(res => {
                            if (!res.success) { alert(res.message); return; }
                            cerrarModalAuth();
                            recargarCarrito();
                        });
                });
            }
        });

        // --- Enviar / vaciar pedido ---
        // Delivery: el motorizado se guarda al elegirlo (si el pedido aún no existe, se aplica al enviar la comanda)
        const selMot = document.getElementById('motorizado');
        if (selMot) {
            selMot.addEventListener('change', () => post("{{ route('motorizados.asignar') }}", { mot_id: selMot.value || null }));
            document.getElementById('mot_nuevo').addEventListener('click', async () => {
                const nombre = prompt('Nombre del motorizado:');
                if (!nombre || nombre.trim().length < 2) return;
                const r = await post("{{ route('motorizados.guardar') }}", { nombre: nombre.trim() });
                if (!r.ok) return alert(r.message || r.mensaje || 'No se pudo guardar.');
                selMot.add(new Option(r.motorizado.nombre, r.motorizado.mot_id, true, true));
                post("{{ route('motorizados.asignar') }}", { mot_id: r.motorizado.mot_id });
            });
        }

        document.getElementById('btn_enviar').addEventListener('click', function () {
            const btn = this;
            // Si hay una observación escrita sin salir del campo, se guarda antes de enviar
            const activo = document.activeElement;
            const previo = activo && activo.classList.contains('cart-obs')
                ? actualizarItem(activo.closest('.cart-item').dataset.id, { observaciones: activo.value })
                : Promise.resolve();

            btn.disabled = true;
            btn.textContent = 'ENVIANDO...';
            const restaurar = () => { btn.disabled = false; btn.innerHTML = '<strong>ENVIAR COMANDA</strong>'; };

            previo.then(() => post("{{ route('comandas.enviar') }}", {})).then(res => {
                if (res.success) {
                    // Sin aviso: vuelve directo a las mesas
                    window.location.href = "{{ route('comandas.seleccion') }}";
                } else {
                    alert(res.message);
                    restaurar();
                }
            }).catch(() => { alert('Error de conexión. Intenta de nuevo.'); restaurar(); });
        });

        // Vaciar: requiere datos de un Administrador (si el usuario ya es admin, solo confirma)
        document.getElementById('btn_vaciar').addEventListener('click', function () {
            const hayNuevos = [...document.querySelectorAll('.cart-item')].some(i => i.dataset.old !== '1');
            if (!hayNuevos) { alert('No hay productos nuevos para vaciar.'); return; }
            const texto = '¿Vaciar los productos nuevos del pedido? Lo ya enviado a cocina se mantiene.';

            if (ES_ADMIN) {
                if (confirm(texto)) post("{{ route('comandas.clear_cart') }}", {}).then(res => { if (!res.success) alert(res.message); recargarCarrito(); });
                return;
            }
            abrirModalAuth(texto, function (user, pass) {
                post("{{ route('comandas.clear_cart') }}", { auth_user: user, auth_password: pass }).then(res => {
                    if (!res.success) { alert(res.message); return; }
                    cerrarModalAuth();
                    recargarCarrito();
                });
            }, { soloAdmin: true, pideMotivo: false });
        });
    </script>
@include('partials.avisos')
</body>
</html>