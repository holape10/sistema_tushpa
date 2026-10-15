@extends('layouts.app')
@section('title', 'Comandas')
@section('content')
    <style>
        .no-scroll::-webkit-scrollbar { display: none; } .no-scroll { scrollbar-width: none; }
        /* Elementos que arma el JavaScript (pasar / juntar mesas, reservas) */
        .mover-zonas { display: flex; gap: 6px; overflow-x: auto; margin-bottom: 10px; }
        .mover-zonas button { flex-shrink: 0; border: 2px solid #cbd5e1; background: #fff; border-radius: 999px; padding: 6px 14px; font-weight: 700; color: #475569; }
        .mover-zonas button.activa { background: #1e293b; border-color: #1e293b; color: #fff; }
        .mover-grilla { display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: 10px; }
        .mover-seccion + .mover-seccion { margin-top: 14px; padding-top: 12px; border-top: 1px dashed #cbd5e1; }
        .mover-seccion-titulo { font-weight: 800; color: #1e293b; }
        .mover-seccion-ayuda { font-size: .85em; color: #64748b; margin-bottom: 8px; }
        .mover-ya { background: #ede9fe; color: #5b21b6; border-radius: 10px; padding: 8px 10px; font-size: .9em; margin-bottom: 10px; }
        .mover-mesa { border: none; border-radius: 14px; padding: 14px 6px; color: #fff; font-weight: 800; font-size: 1rem; line-height: 1.2; box-shadow: 0 2px 5px rgba(0,0,0,.12); }
        .mover-mesa:active { transform: scale(.96); }
        .mover-mesa.libre { background: #047857; } .mover-mesa.ocupada { background: #e11d48; }
        .mover-mesa.libre.elegida { background: #065f46; box-shadow: 0 0 0 4px #facc15; }
        .mover-mesa small { display: block; font-weight: 600; font-size: .75em; opacity: .95; margin-top: 3px; }
        .mover-resumen { display: flex; align-items: center; justify-content: center; gap: 10px; margin: 6px 0 14px; flex-wrap: wrap; }
        .mover-ficha { min-width: 104px; border-radius: 14px; padding: 12px 10px; text-align: center; color: #fff; font-weight: 800; line-height: 1.25; }
        .mover-ficha small { display: block; font-weight: 600; font-size: .75em; }
        .mover-ficha.ocupada { background: #e11d48; } .mover-ficha.libre { background: #047857; } .mover-ficha.total { background: #1e293b; }
        .mover-ficha.queda { box-shadow: 0 0 0 4px #facc15; }
        .mover-signo { font-size: 1.6em; font-weight: 800; color: #64748b; }
        .mover-quedar { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin: 8px 0 10px; }
        .mover-quedar button { border: 2px solid #cbd5e1; background: #fff; border-radius: 12px; padding: 10px 6px; font-weight: 700; color: #334155; }
        .mover-quedar button.activa { border-color: #0ea5e9; background: #e0f2fe; color: #0369a1; }
        .mesa-unida-chip { display: inline-flex; align-items: center; gap: 6px; background: #7c3aed; color: #fff; border-radius: 999px; padding: 4px 4px 4px 12px; margin: 0 6px 6px 0; font-weight: 700; }
        .mesa-unida-chip button { border: none; border-radius: 999px; background: rgba(255,255,255,.25); color: #fff; font-size: .85em; padding: 3px 10px; }
        .mesa-unida-chip button.seguro { background: #facc15; color: #1e293b; }
        .reserva-item { border: 1px solid #e5e7eb; border-radius: 14px; padding: 12px; margin-bottom: 8px; }
        .reserva-item.proxima { border-color: #7c3aed; background: #faf5ff; }
        .elegir-mesa { border-radius: 12px; padding: 14px 6px; color: #fff; font-weight: 800; }
    </style>

    @php
        $hayPedidosSueltos = $permisos['llevar'] || $permisos['delivery'] || $puedeCobrar;
        $btnModal = 'h-12 w-full rounded-2xl text-sm font-black text-white shadow-sm active:scale-[.98]';
    @endphp

    <div class="space-y-4">
        {{-- Cabecera --}}
        <div class="flex flex-wrap items-center gap-2">
            <div class="mr-auto">
                <h2 class="text-xl font-black text-gray-800 sm:text-2xl">🍽️ Elige la mesa o el tipo de pedido</h2>
                <p class="text-sm text-gray-500">{{ auth()->user()->apeusu }}</p>
            </div>
            <button type="button" id="btn_reservas" class="inline-flex h-10 items-center gap-2 rounded-full bg-white px-4 text-sm font-bold text-violet-700 shadow-sm ring-1 ring-gray-200">
                📅 Reservas <span id="n_reservas" class="rounded-full bg-violet-600 px-2 text-xs text-white">0</span>
            </button>
            @if ($puedeCobrar)
                <a href="{{ route('turnos.index') }}" class="inline-flex h-10 items-center gap-2 rounded-full bg-white px-4 text-sm font-bold text-gray-700 shadow-sm ring-1 ring-gray-200">💰 Caja</a>
            @endif
        </div>

        @if (session('error'))
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-center text-sm text-amber-800">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="aviso-temporal rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm text-emerald-800">🖨 {{ session('success') }}</div>
        @endif

        {{-- Pisos / zonas --}}
        @if ($pisos->count() > 1)
            <div class="no-scroll -mx-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                @foreach ($pisos as $piso)
                    <button type="button" data-piso-id="{{ $piso->pis_id }}"
                            class="piso-btn h-11 shrink-0 whitespace-nowrap rounded-full px-5 text-sm font-black ring-1 transition {{ $piso->pis_id == $primerPisoId ? 'activo bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-700 ring-gray-200' }}">
                        {{ $piso->pis_nom }}
                    </button>
                @endforeach
            </div>
        @elseif ($pisos->count() === 1)
            <button type="button" data-piso-id="{{ $pisos->first()->pis_id }}" class="piso-btn activo hidden"></button>
        @endif

        {{-- Mesas --}}
        <div id="mesas_container" class="grid grid-cols-[repeat(auto-fill,minmax(104px,1fr))] gap-2.5 sm:grid-cols-[repeat(auto-fill,minmax(130px,1fr))] sm:gap-3">
            @include('empresas.comandas.partials.mesas_grid', ['mesas' => $mesas])
        </div>

        {{-- Otros tipos de pedido (según el usuario) --}}
        @if ($permisos['llevar'] || $permisos['delivery'] || ($permisos['pv'] && $puedeCobrar))
            <div class="grid gap-3 pt-2 sm:grid-cols-3">
                @if ($permisos['llevar'])
                    <button type="button" id="btn_llevar" class="h-16 rounded-2xl bg-indigo-600 text-lg font-black text-white shadow active:scale-[.98]">🥡 PARA LLEVAR</button>
                @endif
                @if ($permisos['delivery'])
                    <button type="button" id="btn_delivery" class="h-16 rounded-2xl bg-emerald-600 text-lg font-black text-white shadow active:scale-[.98]">🛵 DELIVERY</button>
                @endif
                @if ($permisos['pv'] && $puedeCobrar)
                    <a href="{{ route('cobros.directa') }}" class="flex h-16 items-center justify-center rounded-2xl bg-orange-500 text-lg font-black text-white shadow active:scale-[.98]">🧾 PUNTO DE VENTA</a>
                @endif
            </div>
        @endif

        {{-- Para llevar y delivery activos --}}
        @if ($hayPedidosSueltos)
            <section class="rounded-2xl bg-white p-4 shadow-sm">
                <h3 class="mb-3 font-black text-gray-800">Para llevar y delivery en curso</h3>
                <div id="takeaway_container" class="space-y-2"><p class="py-4 text-center text-sm text-gray-400">Cargando…</p></div>
            </section>
        @endif
    </div>

    {{-- Abriendo el pedido (aviso inmediato al tocar la mesa) --}}
    <div id="abriendo" class="fixed inset-0 z-[60] hidden items-center justify-center bg-white/60 backdrop-blur-[2px]">
        <div class="flex items-center gap-3 rounded-2xl bg-white px-5 py-4 shadow-xl ring-1 ring-gray-200">
            <span class="h-6 w-6 animate-spin rounded-full border-4 border-indigo-200 border-t-indigo-600"></span>
            <span id="abriendo_texto" class="font-bold text-gray-700">Abriendo…</span>
        </div>
    </div>

    {{-- ===================== Modales ===================== --}}
    {{-- Opciones de una mesa ocupada --}}
    <div id="modal_mesa" class="modal-t fixed inset-0 z-50 hidden items-end justify-center bg-black/50 sm:items-center sm:p-4">
        <div class="max-h-[92dvh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 shadow-xl sm:max-w-md sm:rounded-3xl">
            <div class="flex items-start gap-2">
                <h4 class="flex-1 text-lg font-black text-gray-800" id="modal_mesa_titulo">Mesa</h4>
                <button type="button" data-cerrar class="h-9 w-9 rounded-full bg-gray-100 text-lg font-bold text-gray-500">&times;</button>
            </div>
            <div id="modal_mesa_listo" class="mt-3 hidden rounded-xl border-2 border-amber-400 bg-amber-50 p-3 text-center">
                <p class="font-bold text-amber-900">🔔 Cocina tiene listo un pedido de esta mesa</p>
                <button type="button" id="btn_modal_entregado" class="mt-2 h-10 rounded-xl bg-amber-500 px-4 text-sm font-black text-white">✔ Ya lo llevé a la mesa</button>
            </div>
            <div id="modal_mesa_detalle" class="mt-3 max-h-56 overflow-y-auto text-sm">Cargando…</div>
            <div id="modal_mesa_unidas" class="mt-3 hidden rounded-xl border border-violet-200 bg-violet-50 p-3 text-sm text-violet-800"></div>
            <div class="mt-4 grid grid-cols-2 gap-2">
                <button type="button" id="btn_modal_editar" class="{{ $btnModal }} bg-emerald-600">✏️ EDITAR</button>
                <button type="button" id="btn_modal_precuenta" class="{{ $btnModal }} bg-sky-600">🧾 PRECUENTA</button>
                @if ($puedeCobrar)
                    <button type="button" id="btn_modal_separadas" class="{{ $btnModal }} bg-violet-600">👥 SEPARADAS</button>
                    <button type="button" id="btn_modal_cobrar" class="{{ $btnModal }} bg-rose-600">💵 COBRAR</button>
                @endif
                <button type="button" id="btn_modal_cambiar" class="{{ $btnModal }} bg-amber-500">⇄ Pasar a otra mesa</button>
                <button type="button" id="btn_modal_unir" class="{{ $btnModal }} bg-cyan-600">🔗 Juntar mesas</button>
            </div>
        </div>
    </div>

    {{-- Reservas de hoy --}}
    <div id="modal_reservas" class="modal-t fixed inset-0 z-50 hidden items-end justify-center bg-black/50 sm:items-center sm:p-4">
        <div class="flex max-h-[92dvh] w-full flex-col rounded-t-3xl bg-white shadow-xl sm:max-w-lg sm:rounded-3xl">
            <div class="flex items-center gap-2 rounded-t-3xl bg-violet-600 px-5 py-4 text-white">
                <h4 class="flex-1 text-lg font-black">📅 Reservas de hoy</h4>
                <button type="button" data-cerrar class="h-9 w-9 rounded-full bg-white/20 text-lg font-bold">&times;</button>
            </div>
            <div id="lista_reservas" class="flex-1 overflow-y-auto p-4"><p class="text-gray-400">Cargando…</p></div>
            <div class="flex gap-2 border-t p-3">
                <a href="{{ route('reservas.index') }}" class="flex h-11 flex-1 items-center justify-center rounded-xl bg-gray-100 text-sm font-bold text-gray-700">+ Nueva / ver todas</a>
                <button type="button" data-cerrar class="h-11 rounded-xl bg-gray-100 px-4 text-sm font-bold text-gray-700">Cerrar</button>
            </div>
        </div>
    </div>

    {{-- Pasar el pedido a otra mesa / juntar mesas: 1) elegir la mesa 2) ver el resumen y confirmar --}}
    <div id="modal_mover" class="modal-t fixed inset-0 z-50 hidden items-end justify-center bg-black/50 sm:items-center sm:p-4">
        <div class="flex max-h-[94dvh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-xl sm:max-w-lg sm:rounded-3xl">
            <div id="mover_cabecera" class="relative px-5 py-4 text-white">
                <button type="button" data-cerrar class="absolute right-4 top-3 h-9 w-9 rounded-full bg-white/20 text-lg font-bold">&times;</button>
                <h4 id="mover_titulo" class="pr-10 text-lg font-black"></h4>
                <p id="mover_sub" class="mt-1 text-sm opacity-90"></p>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                <div class="mb-3 flex gap-2 text-xs font-black text-gray-400">
                    <span id="mover_paso1" class="flex-1 border-b-4 pb-1 text-center">1. Elige la mesa</span>
                    <span id="mover_paso2" class="flex-1 border-b-4 pb-1 text-center">2. Confirma</span>
                </div>
                <div id="mover_elegir">
                    <div class="mover-zonas" id="mover_zonas"></div>
                    <div id="mover_lista"></div>
                    <button type="button" id="mover_siguiente" class="mt-3 hidden h-12 w-full rounded-2xl bg-indigo-600 font-black text-white"></button>
                </div>
                <div id="mover_confirmar" class="hidden">
                    <div class="mover-resumen" id="mover_resumen"></div>
                    <div id="mover_quedar_caja" class="hidden">
                        <p class="text-center font-bold text-gray-700">¿A nombre de qué mesa queda la cuenta?</p>
                        <div class="mover-quedar" id="mover_quedar"></div>
                    </div>
                    <div class="rounded-xl bg-slate-100 p-3 text-center text-sm text-slate-700" id="mover_texto"></div>
                    <div class="mt-4 grid grid-cols-[1fr_2fr] gap-2">
                        <button type="button" id="mover_volver" class="h-12 rounded-2xl bg-gray-100 font-bold text-gray-700">← Otra mesa</button>
                        <button type="button" id="mover_ok" class="h-12 rounded-2xl bg-emerald-600 font-black text-white disabled:opacity-60"></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Elegir una mesa (reservas) --}}
    <div id="modal_elegir_mesa" class="modal-t fixed inset-0 z-50 hidden items-end justify-center bg-black/50 sm:items-center sm:p-4">
        <div class="max-h-[92dvh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 shadow-xl sm:max-w-sm sm:rounded-3xl">
            <div class="flex items-start gap-2">
                <h4 class="flex-1 text-lg font-black text-gray-800" id="elegir_titulo">Elegir mesa</h4>
                <button type="button" data-cerrar class="h-9 w-9 rounded-full bg-gray-100 text-lg font-bold text-gray-500">&times;</button>
            </div>
            <p id="elegir_ayuda" class="mt-1 text-sm text-gray-500"></p>
            <div id="elegir_zonas" class="mover-zonas mt-3"></div>
            <div id="elegir_lista" class="grid max-h-[50dvh] grid-cols-2 gap-2 overflow-y-auto"></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const URL_COBRAR = @json(url('cobrarmesa')) + '/';
    const URL_MESAS = @json(url('comandas/mesas')) + '/';
    const URL_PEDIDO = @json(url('comandas/pedido')) + '/';
    const URL_PRECUENTA = @json(url('comandas/precuenta')) + '/';
    const URL_DELIVERY = @json(route('delivery.index')) + '?pedido=';
    const PUEDE_COBRAR = @json((bool) $puedeCobrar);
    const CSRF = @json(csrf_token());

    const esc = (t) => { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; };
    const soles = (n) => 'S/ ' + Number(n || 0).toFixed(2);
    function toast(texto) { (window.tushpaAviso || alert)(texto, /no se|error|falta|debe|no hay|inv[aá]lid|ya est|solo atiende/i.test(texto) ? 'aviso' : 'ok'); }

    // Modales: se abren y cierran con clases (sin librerías). Tocar el fondo o la × los cierra.
    function abrirModal(id) { const m = document.getElementById(id); m.classList.remove('hidden'); m.classList.add('flex'); }
    function cerrarModal(id) { const m = document.getElementById(id); m.classList.add('hidden'); m.classList.remove('flex'); }
    function cambiarModal(desde, hacia) { cerrarModal(desde.replace('#', '')); abrirModal(hacia.replace('#', '')); }
    document.addEventListener('click', (e) => {
        const modal = e.target.closest('.modal-t');
        if (modal && (e.target === modal || e.target.closest('[data-cerrar]'))) cerrarModal(modal.id);
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') document.querySelectorAll('.modal-t.flex').forEach((m) => cerrarModal(m.id)); });

    // Recargas automáticas: se pausan con la pestaña oculta o la tablet bloqueada y se ponen al día al volver
    function cadaSiVisible(fn, ms) {
        setInterval(() => { if (!document.hidden) fn(); }, ms);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) fn(); });
    }
    function postJson(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(data),
        }).then((r) => r.json().catch(() => ({ success: false, message: 'Error del servidor.' })));
    }
    setTimeout(() => document.querySelectorAll('.aviso-temporal').forEach((a) => a.remove()), 4000);

    // Precuenta: directo a la impresora; si el agente no está conectado, se abre para imprimir desde el navegador
    function imprimirPrecuenta(pedidoId) {
        postJson(@json(url('impresion/precuenta')) + '/' + pedidoId, {}).then((res) => {
            if (res.directa) { toast('🖨 ' + res.mensaje); return; }
            window.open(URL_PRECUENTA + pedidoId, '_blank');
        }).catch(() => window.open(URL_PRECUENTA + pedidoId, '_blank'));
    }

    /** Selector de mesas por zona (reservas). tipo: 'libres' | 'ocupadas'. alElegir(mesa, zona) devuelve {success, message}. */
    function selectorMesas({ tipo, pedidoId = '', titulo, ayuda, desde, confirmar, alElegir }) {
        const lista = document.getElementById('elegir_lista');
        const zonas = document.getElementById('elegir_zonas');
        document.getElementById('elegir_titulo').textContent = titulo;
        document.getElementById('elegir_ayuda').textContent = ayuda;
        lista.innerHTML = '<p class="text-gray-400">Cargando…</p>';
        zonas.innerHTML = '';
        cambiarModal(desde, '#modal_elegir_mesa');
        fetch(@json(route('comandas.mesas_disponibles')) + `?tipo=${tipo}&ped_id=${pedidoId}`).then((r) => r.json()).then((data) => {
            if (!data.mesas.length) { lista.innerHTML = `<p class="col-span-2 text-gray-400">${tipo === 'libres' ? 'No hay mesas libres.' : 'No hay otras mesas ocupadas.'}</p>`; return; }
            const nombresZona = [...new Set(data.mesas.map((m) => m.piso || 'SIN ZONA'))];
            let zonaActiva = nombresZona[0];
            const pintar = () => {
                zonas.innerHTML = '';
                nombresZona.forEach((z) => {
                    const tab = document.createElement('button');
                    tab.type = 'button';
                    tab.className = z === zonaActiva ? 'activa' : '';
                    tab.textContent = z + ' (' + data.mesas.filter((m) => (m.piso || 'SIN ZONA') === z).length + ')';
                    tab.onclick = () => { zonaActiva = z; pintar(); };
                    zonas.appendChild(tab);
                });
                lista.innerHTML = '';
                data.mesas.filter((m) => (m.piso || 'SIN ZONA') === zonaActiva).forEach((m) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'elegir-mesa ' + (tipo === 'libres' ? 'bg-emerald-700' : 'bg-rose-600');
                    b.textContent = m.nombre;
                    b.onclick = () => {
                        if (!confirm(confirmar(m, zonaActiva))) return;
                        b.disabled = true;
                        alElegir(m, zonaActiva).then((res) => {
                            b.disabled = false;
                            if (!res.success) { toast(res.message || 'No se pudo completar.'); return; }
                            cerrarModal('modal_elegir_mesa');
                        });
                    };
                    lista.appendChild(b);
                });
            };
            pintar();
        });
    }

    function refrescarTodo() {
        const activo = document.querySelector('.piso-btn.activo');
        if (activo) refrescarMesas(activo.dataset.pisoId);
        cargarActivos();
    }

    // Pasar el pedido a una mesa libre ('libres') o juntar mesas ('juntar')
    function elegirMesa(tipo, pedidoId, mesaNombre) {
        const pasar = tipo === 'libres';
        const el = (id) => document.getElementById(id);
        const actual = { nombre: mesaNombre, ped_id: pedidoId, total: 0 };
        const elegidas = new Map();
        el('mover_cabecera').className = 'relative px-5 py-4 text-white ' + (pasar ? 'bg-gradient-to-br from-amber-500 to-amber-600' : 'bg-gradient-to-br from-cyan-600 to-sky-700');
        el('mover_titulo').textContent = pasar ? '⇄ Pasar a otra mesa' : '🔗 Juntar mesas';
        el('mover_sub').textContent = pasar ? `Los clientes de ${mesaNombre} se cambian a una mesa libre y se llevan todo su pedido.`
            : `¿Un grupo grande o un cumpleaños? Suma mesas a ${mesaNombre}: todo va en una sola cuenta.`;
        el('mover_lista').innerHTML = '<p class="text-gray-400">Cargando mesas…</p>';
        el('mover_zonas').innerHTML = '';
        el('mover_siguiente').classList.add('hidden');
        paso(1);
        cambiarModal('#modal_mesa', '#modal_mover');

        function paso(n) {
            el('mover_elegir').classList.toggle('hidden', n !== 1);
            el('mover_confirmar').classList.toggle('hidden', n !== 2);
            el('mover_paso1').className = 'flex-1 border-b-4 pb-1 text-center ' + (n === 1 ? 'border-slate-800 text-slate-800' : 'border-slate-200');
            el('mover_paso2').className = 'flex-1 border-b-4 pb-1 text-center ' + (n === 2 ? 'border-slate-800 text-slate-800' : 'border-slate-200');
        }
        function boton(m, clase, detalle, alTocar) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'mover-mesa ' + clase;
            b.innerHTML = `${esc(m.nombre)}<small>${detalle}</small>`;
            b.onclick = alTocar;
            return b;
        }
        function seccion(titulo, ayuda) {
            const d = document.createElement('div');
            d.className = 'mover-seccion';
            d.innerHTML = titulo ? `<div class="mover-seccion-titulo">${titulo}</div><div class="mover-seccion-ayuda">${ayuda}</div>` : '';
            const lista = document.createElement('div');
            lista.className = 'mover-grilla';
            d.appendChild(lista);
            el('mover_lista').appendChild(d);
            return lista;
        }
        function pintarSiguiente() {
            const n = elegidas.size;
            el('mover_siguiente').classList.toggle('hidden', !n);
            el('mover_siguiente').textContent = `Siguiente: juntar ${n} mesa${n > 1 ? 's' : ''} a ${actual.nombre} →`;
        }
        el('mover_siguiente').onclick = () => confirmarLibres();

        fetch(@json(route('comandas.mesas_disponibles')) + `?tipo=${tipo}&ped_id=${pedidoId}`).then((r) => r.json()).then((data) => {
            actual.total = data.total_actual;
            actual.unidas = data.unidas || [];
            if (!data.mesas.length) {
                el('mover_lista').innerHTML = `<p class="py-6 text-center text-gray-400">${pasar ? 'No hay mesas libres en este momento.' : 'No hay otras mesas para juntar.'}</p>`;
                return;
            }
            const zonaDe = (m) => m.piso || 'SIN ZONA';
            const zonas = [...new Set(data.mesas.map(zonaDe))];
            let zona = zonas[0];
            const pintar = () => {
                el('mover_zonas').innerHTML = '';
                if (zonas.length > 1) zonas.forEach((z) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = z === zona ? 'activa' : '';
                    b.textContent = `${z} (${data.mesas.filter((m) => zonaDe(m) === z).length})`;
                    b.onclick = () => { zona = z; pintar(); };
                    el('mover_zonas').appendChild(b);
                });
                el('mover_lista').innerHTML = '';
                const enZona = data.mesas.filter((m) => zonaDe(m) === zona);
                if (pasar) {
                    const lista = seccion('', '');
                    enZona.forEach((m) => lista.appendChild(boton(m, 'libre', 'Libre', () => confirmarPasar(m))));
                    return;
                }
                if (actual.unidas.length) {
                    el('mover_lista').insertAdjacentHTML('beforeend', `<div class="mover-ya">🔗 Ya juntas con ${esc(actual.nombre)}: <b>${actual.unidas.map(esc).join(', ')}</b></div>`);
                }
                const libres = enZona.filter((m) => m.estado === 'libre');
                const ocupadas = enZona.filter((m) => m.estado === 'ocupada');
                if (libres.length) {
                    const lista = seccion('🟢 Mesas libres para el grupo', 'Toca todas las mesas que va a ocupar el grupo (puedes marcar varias).');
                    libres.forEach((m) => {
                        const marcada = elegidas.has(m.mes_id);
                        lista.appendChild(boton(m, 'libre' + (marcada ? ' elegida' : ''), marcada ? '✔ Elegida' : 'Libre', () => {
                            marcada ? elegidas.delete(m.mes_id) : elegidas.set(m.mes_id, m);
                            pintar();
                            pintarSiguiente();
                        }));
                    });
                }
                if (ocupadas.length) {
                    const lista = seccion('🔴 Mesas ocupadas: juntar su cuenta', 'Si otra mesa ya está consumiendo y pagará junto con esta.');
                    ocupadas.forEach((m) => lista.appendChild(boton(m, 'ocupada', soles(m.total) + (m.desde ? ' · desde ' + m.desde : ''), () => confirmarCuenta(m))));
                }
                if (!libres.length && !ocupadas.length) el('mover_lista').insertAdjacentHTML('beforeend', '<p class="text-center text-gray-400">No hay mesas en esta zona.</p>');
            };
            pintar();
        });

        const ficha = (clase, nombre, detalle) => `<div class="mover-ficha ${clase}">${esc(nombre)}<small>${detalle}</small></div>`;

        function confirmarPasar(m) {
            paso(2);
            el('mover_volver').onclick = () => paso(1);
            el('mover_quedar_caja').classList.add('hidden');
            el('mover_resumen').innerHTML = ficha('ocupada', actual.nombre, soles(actual.total)) + '<div class="mover-signo">➜</div>' + ficha('libre queda', m.etiqueta, 'nueva mesa');
            el('mover_texto').innerHTML = `Todo el pedido (<b>${soles(actual.total)}</b>) pasa a <b>${esc(m.etiqueta)}</b>.<br><b>${esc(actual.nombre)}</b> quedará libre.`;
            el('mover_ok').textContent = `✔ Sí, pasar a ${m.etiqueta}`;
            el('mover_ok').onclick = () => ejecutar(postJson(@json(route('comandas.cambiar_mesa')), { ped_id: actual.ped_id, mes_id: m.mes_id }), `✔ Listo: el pedido ahora está en ${m.etiqueta}`);
        }
        function confirmarLibres() {
            const mesas = [...elegidas.values()];
            paso(2);
            el('mover_volver').onclick = () => paso(1);
            el('mover_quedar_caja').classList.add('hidden');
            el('mover_resumen').innerHTML = ficha('ocupada queda', actual.nombre, soles(actual.total)) + mesas.map((m) => '<div class="mover-signo">+</div>' + ficha('libre', m.etiqueta, 'se junta')).join('');
            const total = mesas.length + 1 + actual.unidas.length;
            el('mover_texto').innerHTML = `El grupo ocupará <b>${total} mesas</b> con <b>una sola cuenta</b> en <b>${esc(actual.nombre)}</b>.<br>Al cobrar, todas quedan libres solas. Si una se desocupa antes, la liberas desde la mesa.`;
            el('mover_ok').textContent = `✔ Sí, juntar ${mesas.length} mesa${mesas.length > 1 ? 's' : ''}`;
            el('mover_ok').onclick = () => ejecutar(postJson(@json(route('comandas.juntar_libres')), { ped_id: actual.ped_id, mesas: mesas.map((m) => m.mes_id) }),
                `✔ Listo: ${mesas.map((m) => m.etiqueta).join(', ')} juntas con ${actual.nombre}`);
        }
        function confirmarCuenta(m) {
            paso(2);
            el('mover_volver').onclick = () => paso(1);
            const otra = { nombre: m.etiqueta, ped_id: m.ped_id, total: m.total };
            let queda = actual;
            const pintarJuntar = () => {
                const sale = queda === actual ? otra : actual;
                el('mover_resumen').innerHTML = ficha('ocupada' + (queda === actual ? ' queda' : ''), actual.nombre, soles(actual.total))
                    + '<div class="mover-signo">+</div>' + ficha('ocupada' + (queda === otra ? ' queda' : ''), otra.nombre, soles(otra.total))
                    + '<div class="mover-signo">=</div>' + ficha('total', soles(Number(actual.total) + Number(otra.total)), 'una sola cuenta');
                el('mover_quedar').innerHTML = '';
                [actual, otra].forEach((op) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = op === queda ? 'activa' : '';
                    b.textContent = (op === queda ? '✔ ' : '') + op.nombre;
                    b.onclick = () => { queda = op; pintarJuntar(); };
                    el('mover_quedar').appendChild(b);
                });
                el('mover_texto').innerHTML = `Lo pedido en las dos mesas queda en <b>una sola cuenta</b> a nombre de <b>${esc(queda.nombre)}</b>.<br><b>${esc(sale.nombre)}</b> sigue ocupada como parte del grupo; si se desocupa, la liberas desde la mesa.`;
                el('mover_ok').textContent = `✔ Sí, juntar cuentas en ${queda.nombre}`;
                el('mover_ok').onclick = () => ejecutar(postJson(@json(route('comandas.unir_mesa')), { ped_id: queda.ped_id, ped_id_origen: sale.ped_id }),
                    `✔ Listo: cuentas de ${actual.nombre} y ${otra.nombre} juntas en ${queda.nombre}`);
            };
            el('mover_quedar_caja').classList.remove('hidden');
            pintarJuntar();
        }
        function ejecutar(promesa, mensaje) {
            const ok = el('mover_ok');
            ok.disabled = true;
            promesa.then((res) => {
                ok.disabled = false;
                if (!res.success) { toast(res.message || 'No se pudo completar.'); return; }
                cerrarModal('modal_mover');
                toast(mensaje);
                refrescarTodo();
            }).catch(() => { ok.disabled = false; toast('Sin conexión. Intenta de nuevo.'); });
        }
    }

    // Mesas juntas: se liberan con dos toques (por si se desocupan antes de cobrar)
    function pintarUnidas(pedidoId, unidas) {
        const cont = document.getElementById('modal_mesa_unidas');
        if (!unidas.length) { cont.classList.add('hidden'); cont.innerHTML = ''; return; }
        cont.classList.remove('hidden');
        cont.innerHTML = '<div class="mb-1.5 font-bold">🔗 Mesas juntas en esta cuenta</div>';
        unidas.forEach((m) => {
            const chip = document.createElement('span');
            chip.className = 'mesa-unida-chip';
            chip.innerHTML = `${esc(m.nombre)} <button type="button">Liberar</button>`;
            const b = chip.querySelector('button');
            b.onclick = () => {
                if (!b.classList.contains('seguro')) { b.classList.add('seguro'); b.textContent = '¿Seguro? Toca otra vez'; return; }
                b.disabled = true;
                postJson(@json(route('comandas.separar_mesa')), { ped_id: pedidoId, mes_id: m.mes_id }).then((res) => {
                    if (!res.success) { toast(res.message); b.disabled = false; return; }
                    toast(`✔ ${m.nombre} quedó libre`);
                    pintarUnidas(pedidoId, unidas.filter((x) => x.mes_id !== m.mes_id));
                    refrescarTodo();
                });
            };
            cont.appendChild(chip);
        });
    }

    // ---------------- Reservas de hoy
    let reservasHoy = [];
    function cargarReservas() {
        return fetch(@json(route('reservas.dia')), { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((d) => {
            reservasHoy = d.reservas;
            document.getElementById('n_reservas').textContent = reservasHoy.length;
            pintarReservas();
        }).catch(() => {});
    }
    function pintarReservas() {
        const cont = document.getElementById('lista_reservas');
        if (!reservasHoy.length) { cont.innerHTML = '<p class="py-6 text-center text-gray-400">No hay reservas pendientes para hoy.</p>'; return; }
        const ahora = new Date();
        cont.innerHTML = reservasHoy.map((r) => {
            const [h, m] = r.hora_inicio.split(':');
            const llegada = new Date(); llegada.setHours(+h, +m, 0, 0);
            const minutos = Math.round((llegada - ahora) / 60000);
            const cuando = minutos > 0 ? `en ${minutos >= 60 ? Math.floor(minutos / 60) + ' h ' : ''}${minutos % 60} min` : (minutos > -30 ? 'ya debería estar' : 'atrasada');
            const mesa = r.mes_nom
                ? `${esc(r.pis_nom || '')} / <b>${esc(r.mes_nom)}</b> <span class="rounded px-1.5 text-xs font-bold text-white ${r.mesa_libre ? 'bg-emerald-600' : 'bg-rose-600'}">${r.mesa_libre ? 'libre' : 'ocupada'}</span>`
                : '<span class="text-gray-400">sin mesa elegida</span>';
            return `<div class="reserva-item ${minutos <= 60 ? 'proxima' : ''}"><div class="flex items-start justify-between gap-2"><div>
                <div class="text-base"><b>${r.hora_inicio.slice(0, 5)}</b> · <b>${esc(r.nombre_cliente)}</b> · ${r.cantidad_personas} pers.</div>
                <div class="text-sm text-gray-500">${cuando}${r.telefono ? ' · 📞 ' + esc(r.telefono) : ''} · ${esc(r.estado)}</div>
                <div class="mt-1 text-sm">Mesa: ${mesa}</div>
                ${r.platos.length ? `<div class="mt-1 text-sm">🍽 ${r.platos.map(esc).join(', ')}</div>` : ''}
                ${r.observacion ? `<div class="text-sm text-amber-700">» ${esc(r.observacion)}</div>` : ''}
                </div><button type="button" class="h-10 shrink-0 rounded-xl bg-emerald-600 px-3 text-sm font-black text-white" onclick="atenderReserva(${r.res_id})">LLEGÓ ✔</button></div></div>`;
        }).join('');
    }
    function atenderReserva(resId) {
        const r = reservasHoy.find((x) => x.res_id === resId);
        const atender = (mesId) => postJson(@json(url('reservas')) + '/' + resId + '/atender', { mes_id: mesId }).then((res) => {
            if (res.success) window.location.href = res.redirect;
            return res;
        });
        if (r.mes_id && r.mesa_libre && confirm(`¿Asignar la ${r.mes_nom} reservada a ${r.nombre_cliente}?`)) {
            atender(r.mes_id).then((res) => { if (!res.success) toast(res.message); });
            return;
        }
        selectorMesas({
            tipo: 'libres', desde: '#modal_reservas',
            titulo: `Mesa para ${r.nombre_cliente} (${r.cantidad_personas} pers.)`,
            ayuda: r.mes_id && !r.mesa_libre ? `La ${r.mes_nom} reservada está ocupada: elige otra mesa.` : 'Elige la mesa para la reserva.',
            confirmar: (m, z) => `¿Atender a ${r.nombre_cliente} en ${m.nombre} (${z})?`,
            alElegir: (m) => atender(m.mes_id),
        });
    }
    document.getElementById('btn_reservas').addEventListener('click', () => { cargarReservas(); abrirModal('modal_reservas'); });
    cargarReservas();
    cadaSiVisible(cargarReservas, 60000);

    // Abre el pedido en una sola petición: la pantalla del pedido llega con todo listo (y con los productos)
    let abriendo = false;
    function irAServicio(orderType, mesaId, mesaNombre, pedidoId) {
        if (abriendo) return;
        abriendo = true;
        document.getElementById('abriendo').classList.replace('hidden', 'flex');
        document.getElementById('abriendo_texto').textContent = 'Abriendo ' + (mesaNombre || 'pedido') + '…';
        const params = new URLSearchParams({ tipo: orderType });
        if (mesaId) params.set('mesa', mesaId);
        if (mesaNombre) params.set('nombre', mesaNombre);
        if (pedidoId) params.set('pedido', pedidoId);
        window.location.href = @json(route('comandas.menu')) + '?' + params;
    }
    // Al volver con el botón "atrás" del celular la página puede venir de la memoria: se quita el "Abriendo…"
    window.addEventListener('pageshow', () => { abriendo = false; document.getElementById('abriendo').classList.replace('flex', 'hidden'); });
    document.getElementById('btn_llevar')?.addEventListener('click', () => irAServicio('llevar', null, 'PARA LLEVAR', null));
    document.getElementById('btn_delivery')?.addEventListener('click', () => irAServicio('delivery', null, 'DELIVERY', null));

    document.querySelectorAll('.piso-btn').forEach((btn) => btn.addEventListener('click', function () {
        document.querySelectorAll('.piso-btn').forEach((b) => {
            const activo = b === this;
            b.classList.toggle('activo', activo);
            b.classList.toggle('bg-indigo-600', activo); b.classList.toggle('text-white', activo); b.classList.toggle('ring-indigo-600', activo);
            b.classList.toggle('bg-white', !activo); b.classList.toggle('text-gray-700', !activo); b.classList.toggle('ring-gray-200', !activo);
        });
        refrescarMesas(this.dataset.pisoId);
    }));

    // Cada zona se guarda en memoria: cambiar de zona es instantáneo y se pone al día por detrás.
    // Solo se redibuja si algo cambió: así no se pierde el toque del mozo justo cuando llega la recarga
    const zonas = new Map();
    let pisoVisible = document.querySelector('.piso-btn.activo')?.dataset.pisoId;
    let ultimaVistaMesas = pisoVisible + '|' + document.getElementById('mesas_container').innerHTML;
    if (pisoVisible) zonas.set(String(pisoVisible), document.getElementById('mesas_container').innerHTML);
    function mostrarMesas(pisoId, vista) {
        const clave = pisoId + '|' + vista;
        if (clave === ultimaVistaMesas) return;
        ultimaVistaMesas = clave;
        document.getElementById('mesas_container').innerHTML = vista;
        actualizarTimers();
    }
    function refrescarMesas(pisoId) {
        pisoId = String(pisoId);
        pisoVisible = pisoId;
        if (zonas.has(pisoId)) mostrarMesas(pisoId, zonas.get(pisoId));
        else document.getElementById('mesas_container').style.opacity = '.5';
        return fetch(URL_MESAS + pisoId).then((r) => r.json()).then((data) => {
            zonas.set(pisoId, data.vista);
            if (pisoVisible === pisoId) { document.getElementById('mesas_container').style.opacity = ''; mostrarMesas(pisoId, data.vista); }
        }).catch(() => { document.getElementById('mesas_container').style.opacity = ''; });
    }
    // Las demás zonas se traen de a una, sin apuro, para que al tocarlas aparezcan al instante
    (function precargar() {
        const pendientes = [...document.querySelectorAll('.piso-btn')].map((b) => String(b.dataset.pisoId)).filter((id) => !zonas.has(id));
        pendientes.reduce((p, id) => p.then(() => zonas.has(id) ? null : fetch(URL_MESAS + id).then((r) => r.json()).then((d) => zonas.set(id, d.vista)).catch(() => {})), Promise.resolve());
    })();
    cadaSiVisible(() => { if (pisoVisible) refrescarMesas(pisoVisible); }, 10000);

    // Tiempo en vivo de cada mesa ocupada (corre en el navegador; solo con la pantalla visible)
    function actualizarTimers() {
        const ahora = Date.now();
        document.querySelectorAll('.mesa-timer').forEach((el) => {
            const inicio = new Date(el.dataset.inicio).getTime();
            if (!inicio) return;
            const diff = Math.max(0, Math.floor((ahora - inicio) / 1000));
            el.textContent = [Math.floor(diff / 3600), Math.floor((diff % 3600) / 60), diff % 60].map((n) => String(n).padStart(2, '0')).join(':');
        });
    }
    actualizarTimers();
    setInterval(() => { if (!document.hidden) actualizarTimers(); }, 1000);

    // Toque en una mesa
    document.addEventListener('click', (e) => {
        const mesa = e.target.closest('.btn-mesa-comanda');
        if (!mesa) return;
        const mesaId = mesa.dataset.id, mesaNombre = mesa.dataset.nombre, pedidoId = mesa.dataset.pedidoId;
        if (mesa.dataset.estado === 'Libre') { irAServicio('salon', mesaId, mesaNombre, null); return; }
        if (!pedidoId) { toast('Mesa ocupada sin pedido asociado.'); return; }

        document.getElementById('modal_mesa_titulo').textContent = mesaNombre;
        document.getElementById('modal_mesa_detalle').innerHTML = '<p class="text-gray-400">Cargando…</p>';
        pintarUnidas(pedidoId, []);
        abrirModal('modal_mesa');

        document.getElementById('btn_modal_editar').onclick = () => { cerrarModal('modal_mesa'); irAServicio('salon', mesaId, mesaNombre, pedidoId); };
        if (PUEDE_COBRAR) {
            document.getElementById('btn_modal_cobrar').onclick = () => window.location.href = URL_COBRAR + pedidoId;
            document.getElementById('btn_modal_separadas').onclick = () => window.location.href = URL_COBRAR + pedidoId + '/separadas';
        }
        document.getElementById('btn_modal_precuenta').onclick = () => imprimirPrecuenta(pedidoId);
        document.getElementById('modal_mesa_listo').classList.toggle('hidden', !(Number(mesa.dataset.listos) > 0));
        document.getElementById('btn_modal_entregado').onclick = () => postJson(@json(url('cocina/entregado')) + '/' + pedidoId, {}).then(() => {
            document.getElementById('modal_mesa_listo').classList.add('hidden');
            toast('✔ Entregado a la mesa');
            refrescarTodo();
        });
        document.getElementById('btn_modal_cambiar').onclick = () => elegirMesa('libres', pedidoId, mesaNombre);
        document.getElementById('btn_modal_unir').onclick = () => elegirMesa('juntar', pedidoId, mesaNombre);

        fetch(URL_PEDIDO + pedidoId).then((r) => r.json()).then((data) => {
            if (!data.success) { document.getElementById('modal_mesa_detalle').textContent = 'Error al cargar.'; return; }
            const html = data.detalles.map((d) => `<div class="flex justify-between gap-2 border-b border-gray-100 py-1.5"><span>${parseFloat(d.ped_det_can)}× ${esc(d.descripcion)}</span><b>${soles(d.ped_det_can * d.ped_det_pre)}</b></div>`).join('');
            document.getElementById('modal_mesa_detalle').innerHTML = (html || '<p class="text-gray-400">Sin ítems</p>')
                + `<div class="mt-2 text-right text-base font-black text-indigo-700">Total: ${soles(data.total)}</div>`;
            document.getElementById('modal_mesa_titulo').textContent = `${mesaNombre} · ${soles(data.total)}`;
            pintarUnidas(pedidoId, data.unidas || []);
        });
    });

    // Para llevar y delivery en curso
    function cargarActivos() {
        const cont = document.getElementById('takeaway_container');
        if (!cont) return;
        fetch(@json(route('comandas.activos_llevar_delivery'))).then((r) => r.json()).then((data) => {
            if (!data.pedidos.length) { cont.innerHTML = '<p class="py-4 text-center text-sm text-gray-400">No hay pedidos activos.</p>'; return; }
            cont.innerHTML = data.pedidos.map((p) => {
                const hora = new Date(p.fecha_hora.replace(' ', 'T')).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
                const tipo = p.ped_tip.toLowerCase();
                return `<div class="flex flex-wrap items-center gap-2 rounded-xl px-3 py-2.5 ring-1 ring-gray-100">
                    <span class="rounded-md px-2 py-0.5 text-xs font-black text-white ${tipo === 'delivery' ? 'bg-emerald-600' : 'bg-indigo-600'}">${esc(p.ped_tip.toUpperCase())}</span>
                    <span class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-800">#${p.ped_id} · ${esc(p.ped_cli_nom ?? '-')} <span class="font-normal text-gray-400">· ${hora}</span></span>
                    <span class="text-sm font-black">${soles(p.ped_tot)}</span>
                    <div class="flex w-full gap-1.5 sm:w-auto">
                        <button class="h-9 flex-1 rounded-lg bg-indigo-50 px-3 text-xs font-bold text-indigo-700" onclick="irAServicio('${tipo}', null, '${esc(p.ped_tip.toUpperCase())}', ${p.ped_id})">Editar</button>
                        <button class="h-9 flex-1 rounded-lg bg-sky-50 px-3 text-xs font-bold text-sky-700" onclick="imprimirPrecuenta(${p.ped_id})">Precuenta</button>
                        ${PUEDE_COBRAR ? `<button class="h-9 flex-1 rounded-lg bg-rose-600 px-3 text-xs font-bold text-white" onclick="window.location.href=URL_COBRAR + ${p.ped_id}">Cobrar</button>` : ''}
                        ${PUEDE_COBRAR && p.ped_tip === 'Delivery' ? `<button class="h-9 flex-1 rounded-lg bg-violet-50 px-3 text-xs font-bold text-violet-700" onclick="window.location.href=URL_DELIVERY + ${p.ped_id}">Seguimiento</button>` : ''}
                    </div></div>`;
            }).join('');
        });
    }
    cargarActivos();
    cadaSiVisible(cargarActivos, 15000);
</script>
@include('partials.aviso_servicio')
@endpush
