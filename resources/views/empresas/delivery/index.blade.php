@extends('layouts.app')
@section('title', 'Delivery en vivo')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <style>
        .dl-icono { background: none; border: none; }
        .dl-icono span { display: flex; align-items: center; gap: 3px; white-space: nowrap; font-size: 11px; font-weight: 700; background: #fff; border-radius: 9999px; padding: 2px 7px 2px 3px; box-shadow: 0 1px 4px rgba(0,0,0,.35); }
        .dl-icono b { font-size: 18px; line-height: 1; }
        .dl-icono.apagado span { opacity: .55; }
    </style>
    @php $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

    <div x-data="panelDelivery()" x-init="iniciar()" class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[220px]">
                <h1 class="text-2xl font-extrabold text-gray-800">🛵 Delivery en vivo</h1>
                <p class="text-sm text-gray-500">Pedidos delivery de hoy, dónde va cada motorizado y el enlace de seguimiento para tu cliente.</p>
            </div>
            <button type="button" @click="fijandoLocal = !fijandoLocal"
                    :class="fijandoLocal ? 'bg-amber-500 text-white' : 'bg-white text-gray-700 border'"
                    class="px-3 py-2 rounded-lg text-sm font-semibold shadow-sm"
                    x-text="fijandoLocal ? 'Toca en el mapa dónde está tu local…' : '🏪 ' + (local ? 'Cambiar ubicación del local' : 'Fijar ubicación de mi local')"></button>
            <button type="button" @click="abrirTarifas()" class="px-3 py-2 rounded-lg bg-white border text-sm font-semibold text-gray-700 shadow-sm">💲 Costos de envío</button>
            <a href="{{ route('delivery.liquidacion') }}" class="px-3 py-2 rounded-lg bg-white border text-sm font-semibold text-gray-700 shadow-sm">💵 Liquidación</a>
            <a href="{{ route('delivery.reporte') }}" class="px-3 py-2 rounded-lg bg-white border text-sm font-semibold text-gray-700 shadow-sm">⏱️ Reporte</a>
            <a href="{{ route('motorizados.index') }}" class="px-3 py-2 rounded-lg bg-white border text-sm font-semibold text-gray-700 shadow-sm">📲 Motorizados</a>
        </div>

        <div x-show="!local" class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
            Primero fija la ubicación de tu local: toca <b>🏪 Fijar ubicación de mi local</b> y luego el punto en el mapa.
        </div>

        {{-- Motorizados: verde si mandó su ubicación hace menos de 2 minutos --}}
        <div class="flex flex-wrap gap-2">
            <template x-for="m in motorizados" :key="m.id">
                <button type="button" @click="verMoto(m)" class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white border text-sm shadow-sm">
                    <span class="w-2.5 h-2.5 rounded-full" :class="m.hace !== null && m.hace < 120 ? 'bg-emerald-500' : 'bg-gray-300'"></span>
                    <span class="font-semibold text-gray-700" x-text="m.nombre"></span>
                    <span class="text-xs text-gray-400" x-text="textoHace(m)"></span>
                </button>
            </template>
            <p x-show="!motorizados.length" class="text-sm text-gray-400">No hay motorizados activos. <a href="{{ route('motorizados.index') }}" class="text-indigo-600 underline">Agrégalos aquí</a>.</p>
        </div>

        <div class="grid lg:grid-cols-[400px_1fr] gap-4 items-start">
            <div class="space-y-3 lg:max-h-[calc(100vh-230px)] lg:overflow-y-auto lg:pr-1 order-2 lg:order-1">
                <template x-for="p in pedidos" :key="p.id">
                    <div class="bg-white rounded-2xl shadow-sm border-2 p-4 cursor-pointer transition"
                         :class="seleccionado === p.id ? 'border-indigo-500' : 'border-transparent'" @click="seleccionar(p)">
                        <div class="flex items-start gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-gray-800">
                                    <span class="text-gray-400">#<span x-text="p.id"></span></span>
                                    <span x-text="p.cliente || 'Sin nombre'"></span>
                                    <span x-show="p.web" class="ml-1 text-[10px] font-black text-emerald-700 bg-emerald-100 rounded px-1 align-middle">🌐 CARTA QR</span>
                                </p>
                                <p class="text-xs text-gray-500 truncate" x-text="(p.direccion || 'Sin dirección') + (p.referencia ? ' · ' + p.referencia : '')"></p>
                                <p class="text-xs mt-0.5">
                                    <span class="text-gray-500">🛵</span>
                                    <span :class="p.motorizado ? 'text-gray-700 font-semibold' : 'text-rose-600'" x-text="p.motorizado || 'Sin motorizado'"></span>
                                    <span class="text-gray-400"> · S/ </span><span class="font-semibold" x-text="p.total.toFixed(2)"></span>
                                    <span x-show="!p.cobrado" class="ml-1 text-[10px] font-bold text-amber-700 bg-amber-100 rounded px-1">POR COBRAR</span>
                                </p>
                                <p x-show="p.pago_texto || p.envio" class="text-xs text-gray-600 mt-0.5">
                                    <span x-show="p.pago_texto" x-text="'💵 ' + p.pago_texto"></span>
                                    <span x-show="p.envio" x-text="' · Envío S/ ' + (p.envio || 0).toFixed(2)"></span>
                                </p>
                                <p x-show="p.nota" class="text-xs text-indigo-700 mt-0.5" x-text="'📝 ' + p.nota"></p>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="inline-block text-[11px] font-bold rounded-full px-2 py-0.5" :class="colores[p.estado]" x-text="nombres[p.estado]"></span>
                                <p class="text-xs mt-1" :class="p.estado !== 'ENTREGADO' && p.minutos >= 45 ? 'text-rose-600 font-bold' : 'text-gray-400'" x-text="p.minutos + ' min'"></p>
                            </div>
                        </div>

                        {{-- Pasos con su hora --}}
                        <div class="grid grid-cols-5 gap-1 mt-3 text-center">
                            <template x-for="(paso, i) in pasos" :key="paso.clave">
                                <div>
                                    <div class="h-1.5 rounded-full" :class="p.horas[paso.hora] ? 'bg-indigo-500' : 'bg-gray-200'"></div>
                                    <p class="text-[10px] text-gray-500 mt-1 leading-tight" x-text="paso.corto"></p>
                                    <p class="text-[10px] font-semibold text-gray-700" x-text="p.horas[paso.hora] || '–'"></p>
                                </div>
                            </template>
                        </div>

                        <div class="flex flex-wrap gap-1.5 mt-3 text-xs" @click.stop>
                            <button type="button" @click="abrirDatos(p)" class="px-2.5 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 font-semibold">📝 Datos de entrega</button>
                            <button type="button" x-show="p.estado !== 'ENTREGADO'" @click="verSugeridos(p)" class="px-2.5 py-1.5 rounded-lg font-semibold"
                                    :class="p.motorizado ? 'bg-gray-100 text-gray-700' : 'bg-orange-500 text-white'" x-text="p.motorizado ? '🛵 Cambiar motorizado' : '🛵 Asignar el más cercano'"></button>
                            <a x-show="p.cobrar" :href="p.cobrar" class="px-2.5 py-1.5 rounded-lg bg-amber-100 text-amber-800 font-semibold">💰 Cobrar</a>
                            <button type="button" x-show="p.estado === 'PREPARANDO'" @click="estado(p, 'LISTO')" class="px-2.5 py-1.5 rounded-lg bg-sky-50 text-sky-700 font-semibold">✅ Listo para salir</button>
                            <button type="button" x-show="p.estado !== 'ENTREGADO'" @click="if (confirm('¿Marcar el pedido #' + p.id + ' como entregado?')) estado(p, 'ENTREGADO')" class="px-2.5 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 font-semibold">📦 Entregado</button>
                            <a x-show="p.whatsapp" :href="p.whatsapp" target="_blank" rel="noopener" class="px-2.5 py-1.5 rounded-lg bg-green-600 text-white font-semibold">WhatsApp al cliente</a>
                            <button type="button" @click="copiar(p.seguimiento)" class="px-2.5 py-1.5 rounded-lg bg-gray-100 text-gray-700 font-semibold">🔗 Copiar seguimiento</button>
                        </div>
                    </div>
                </template>
                <div x-show="cargado && !pedidos.length" class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">
                    No hay pedidos delivery activos. Créalos desde <a href="{{ url('comandas') }}" class="text-indigo-600 underline">Comandas › Delivery</a>.
                </div>
            </div>

            <div class="order-1 lg:order-2 bg-white rounded-2xl shadow-sm overflow-hidden relative">
                <div id="mapa-delivery" class="h-[55vh] lg:h-[calc(100vh-230px)] min-h-[320px]"></div>
                <p x-show="recorridoTexto" x-text="recorridoTexto" class="absolute bottom-2 left-2 z-[500] bg-white/95 rounded-lg px-3 py-1.5 text-xs text-gray-700 shadow"></p>
            </div>
        </div>

        {{-- Datos de entrega --}}
        <div x-show="datos" x-cloak class="fixed inset-0 z-[1000] bg-black/40 flex items-end sm:items-center justify-center p-0 sm:p-4" @keydown.escape.window="datos = null">
            <div class="bg-white w-full sm:max-w-2xl rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[95vh] overflow-y-auto" @click.outside="datos = null">
                <template x-if="datos">
                    <form @submit.prevent="guardarDatos()" class="p-5 space-y-3 text-sm">
                        <div class="flex items-center">
                            <h2 class="flex-1 text-lg font-bold text-gray-800">Datos de entrega · Pedido #<span x-text="datos.id"></span></h2>
                            <button type="button" @click="datos = null" class="text-2xl text-gray-400 leading-none">&times;</button>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <label>Cliente<input x-model="datos.cliente" maxlength="120" class="{{ $in }} mt-1 uppercase"></label>
                            <label>Celular<input x-model="datos.telefono" maxlength="20" inputmode="tel" class="{{ $in }} mt-1" placeholder="987654321"></label>
                            <label class="sm:col-span-2">Dirección<input x-model="datos.direccion" maxlength="255" class="{{ $in }} mt-1"></label>
                            <label>Referencia<input x-model="datos.referencia" maxlength="255" class="{{ $in }} mt-1" placeholder="Frente al parque, puerta verde"></label>
                            <label>Motorizado
                                <select x-model="datos.mot_id" class="{{ $in }} mt-1">
                                    <option value="">— Sin asignar —</option>
                                    <template x-for="m in motorizados" :key="m.id"><option :value="m.id" x-text="m.nombre" :selected="String(m.id) === String(datos.mot_id)"></option></template>
                                </select>
                            </label>
                        </div>
                        <div class="grid sm:grid-cols-3 gap-3">
                            <label>¿Cómo paga?
                                <select x-model="datos.pago" class="{{ $in }} mt-1">
                                    <option value="">— Sin indicar —</option>
                                    @foreach ($mediosPago as $clave => $nombreMedio)<option value="{{ $clave }}">{{ $nombreMedio }}</option>@endforeach
                                </select>
                            </label>
                            <label x-show="datos.pago === 'EFECTIVO'">Paga con (vuelto)<input type="number" x-model="datos.paga_con" min="0" step="0.10" class="{{ $in }} mt-1" placeholder="100.00"></label>
                            <label>Costo de envío
                                <input type="number" x-model="datos.envio" min="0" step="0.10" class="{{ $in }} mt-1" placeholder="0.00">
                                <span class="text-[11px] text-gray-500" x-text="datos.km !== null ? 'A ' + datos.km.toFixed(1) + ' km del local' + (datos.fuera ? ' · fuera de zona' : '') : 'Se calcula al marcar la ubicación'"></span>
                            </label>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3 space-y-2">
                            <p class="font-semibold text-gray-700">📍 Ubicación de entrega</p>
                            <div class="flex gap-2">
                                <input x-model="enlace" class="{{ $in }}" placeholder="Pega aquí la ubicación que te mandó por WhatsApp o el enlace de Google Maps">
                                <button type="button" @click="buscarEnlace()" :disabled="!enlace || buscando" class="px-3 rounded-lg bg-indigo-600 text-white font-semibold disabled:opacity-50" x-text="buscando ? '…' : 'Ubicar'"></button>
                            </div>
                            <p class="text-xs text-gray-500">O toca el punto en el mapa (puedes arrastrar el pin).</p>
                            <div id="mapa-datos" class="h-64 rounded-lg overflow-hidden"></div>
                            <p class="text-xs" :class="datos.lat ? 'text-emerald-700' : 'text-gray-400'"
                               x-text="datos.lat ? 'Ubicación marcada: ' + Number(datos.lat).toFixed(5) + ', ' + Number(datos.lng).toFixed(5) : 'Aún sin ubicación'"></p>
                        </div>
                        <p x-show="error" x-text="error" class="text-rose-600"></p>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="datos = null" class="px-4 py-2 rounded-lg bg-gray-100">Cancelar</button>
                            <button :disabled="guardando" class="px-5 py-2 rounded-lg bg-indigo-600 text-white font-bold disabled:opacity-50" x-text="guardando ? 'Guardando…' : 'Guardar'"></button>
                        </div>
                    </form>
                </template>
            </div>
        </div>

        {{-- Motorizado más cercano --}}
        <div x-show="sugeridos" x-cloak class="fixed inset-0 z-[1000] bg-black/40 flex items-center justify-center p-4" @keydown.escape.window="sugeridos = null">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-xl p-5 space-y-3 text-sm" @click.outside="sugeridos = null">
                <template x-if="sugeridos">
                    <div class="space-y-3">
                        <h2 class="text-lg font-bold text-gray-800">🛵 Motorizado para el pedido #<span x-text="sugeridos.pedido"></span></h2>
                        <p class="text-gray-500">Primero los libres y más cerca del local, según su última ubicación.</p>
                        <div class="divide-y border rounded-xl">
                            <template x-for="(m, i) in sugeridos.lista" :key="m.id">
                                <div class="flex items-center gap-3 px-3 py-2.5">
                                    <span class="w-2.5 h-2.5 rounded-full" :class="m.km !== null ? 'bg-emerald-500' : 'bg-gray-300'"></span>
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-800"><span x-text="m.nombre"></span>
                                            <span x-show="i === 0 && m.km !== null" class="text-[10px] font-black text-emerald-700 bg-emerald-100 rounded px-1">MÁS CONVENIENTE</span></p>
                                        <p class="text-xs text-gray-500" x-text="(m.km !== null ? m.km.toFixed(1) + ' km del local' : 'Sin ubicación reciente') + ' · ' + (m.libre ? 'Libre' : m.en_mano + ' pedido(s) en mano')"></p>
                                    </div>
                                    <button type="button" @click="asignar(sugeridos.pedido, m.id)" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-semibold">Asignar</button>
                                </div>
                            </template>
                            <p x-show="!sugeridos.lista.length" class="p-4 text-center text-gray-400">No hay motorizados activos.</p>
                        </div>
                        <button type="button" @click="sugeridos = null" class="w-full py-2 rounded-lg bg-gray-100">Cerrar</button>
                    </div>
                </template>
            </div>
        </div>

        {{-- Costos de envío por distancia --}}
        <div x-show="tarifas" x-cloak class="fixed inset-0 z-[1000] bg-black/40 flex items-center justify-center p-4" @keydown.escape.window="tarifas = null">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-xl p-5 space-y-3 text-sm" @click.outside="tarifas = null">
                <template x-if="tarifas">
                    <div class="space-y-3">
                        <h2 class="text-lg font-bold text-gray-800">💲 Costos de envío</h2>
                        <p class="text-gray-500">Según la distancia en línea recta del local a la entrega. Más lejos que la última fila es <b>fuera de zona</b>: la carta QR no acepta el pedido.</p>
                        <template x-for="(t, i) in tarifas" :key="i">
                            <div class="flex items-center gap-2">
                                <span class="text-gray-500">Hasta</span>
                                <input type="number" x-model="t.hasta_km" min="0.1" step="0.1" class="{{ $in }} !w-24"><span class="text-gray-500">km</span>
                                <span class="text-gray-500 ml-2">S/</span><input type="number" x-model="t.costo" min="0" step="0.10" class="{{ $in }} !w-24">
                                <button type="button" @click="tarifas.splice(i, 1)" class="ml-auto text-rose-600 text-lg">&times;</button>
                            </div>
                        </template>
                        <button type="button" @click="tarifas.push({ hasta_km: '', costo: '' })" class="text-indigo-600 font-semibold">+ Agregar distancia</button>
                        <p x-show="!local" class="text-amber-700">Primero fija la ubicación de tu local para poder medir las distancias.</p>
                        <p x-show="error" x-text="error" class="text-rose-600"></p>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="tarifas = null" class="px-4 py-2 rounded-lg bg-gray-100">Cancelar</button>
                            <button type="button" @click="guardarTarifas()" class="px-5 py-2 rounded-lg bg-indigo-600 text-white font-bold">Guardar</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div x-show="aviso" x-text="aviso" x-transition class="fixed bottom-5 left-1/2 -translate-x-1/2 z-[1100] bg-gray-900 text-white text-sm rounded-full px-5 py-2 shadow-lg"></div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script>
        function panelDelivery() {
            const CSRF = @json(csrf_token());
            const URL_ESTADO = @json(route('delivery.estado'));
            const URL_BASE = @json(url('delivery'));
            const LOCAL_INICIAL = @json($local && $local->local_lat !== null ? ['lat' => (float) $local->local_lat, 'lng' => (float) $local->local_lng] : null);
            const NOMBRE_LOCAL = @json($local->nombre_comercial ?? 'Mi local');
            const ABRIR = @json($abrir);
            let TARIFAS = @json($tarifas);
            let conocidos = null;

            let mapa, mapaDatos, pinDatos, capaMotos, capaDestinos, marcaLocal, linea;

            function icono(emoji, texto, apagado) {
                const t = String(texto ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
                return L.divIcon({ className: 'dl-icono' + (apagado ? ' apagado' : ''), html: '<span><b>' + emoji + '</b>' + t + '</span>', iconSize: null, iconAnchor: [14, 14] });
            }

            function post(url, data) {
                return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data || {}) })
                    .then(async r => { const d = await r.json().catch(() => ({})); if (!r.ok) throw new Error(d.error || d.message || 'No se pudo guardar.'); return d; });
            }

            return {
                pedidos: [], motorizados: [], local: LOCAL_INICIAL, cargado: false, seleccionado: null,
                fijandoLocal: false, datos: null, sugeridos: null, tarifas: null, enlace: '', buscando: false, guardando: false, error: '', aviso: '', recorridoTexto: '',
                nombres: { PREPARANDO: 'Preparando', LISTO: 'Listo', EN_LOCAL: 'En el local', EN_CAMINO: 'En camino', ENTREGADO: 'Entregado' },
                colores: { PREPARANDO: 'bg-gray-100 text-gray-700', LISTO: 'bg-sky-100 text-sky-700', EN_LOCAL: 'bg-amber-100 text-amber-800', EN_CAMINO: 'bg-indigo-100 text-indigo-700', ENTREGADO: 'bg-emerald-100 text-emerald-700' },
                pasos: [
                    { clave: 'PREPARANDO', hora: 'pedido', corto: 'Pedido' }, { clave: 'LISTO', hora: 'listo', corto: 'Listo' },
                    { clave: 'EN_LOCAL', hora: 'local', corto: 'En local' }, { clave: 'EN_CAMINO', hora: 'salida', corto: 'Salió' },
                    { clave: 'ENTREGADO', hora: 'fin', corto: 'Entregó' },
                ],

                iniciar() {
                    mapa = L.map('mapa-delivery').setView(this.local ? [this.local.lat, this.local.lng] : [-9.19, -75.02], this.local ? 15 : 5);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(mapa);
                    capaMotos = L.layerGroup().addTo(mapa);
                    capaDestinos = L.layerGroup().addTo(mapa);
                    this.pintarLocal();
                    mapa.on('click', e => { if (this.fijandoLocal) this.guardarLocal(e.latlng); });

                    this.cargar(true);
                    setInterval(() => { if (!document.hidden) this.cargar(); }, 10000);
                    document.addEventListener('visibilitychange', () => { if (!document.hidden) this.cargar(); });
                },

                cargar(primera) {
                    return fetch(URL_ESTADO, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(d => {
                        // Pedido nuevo desde la carta QR: suena y avisa
                        if (conocidos) {
                            const nuevos = d.pedidos.filter(p => !conocidos.has(p.id) && p.web);
                            if (nuevos.length) { this.sonar(); this.mostrarAviso('🔔 Nuevo pedido delivery por la carta QR: #' + nuevos.map(p => p.id).join(', #')); }
                        }
                        conocidos = new Set(d.pedidos.map(p => p.id));
                        this.pedidos = d.pedidos;
                        this.motorizados = d.motorizados;
                        this.cargado = true;
                        this.pintar();
                        if (primera && ABRIR) {
                            const p = this.pedidos.find(x => x.id === ABRIR);
                            if (p) { this.seleccionar(p); if (!p.lat) this.abrirDatos(p); }
                        } else if (this.seleccionado) {
                            const p = this.pedidos.find(x => x.id === this.seleccionado);
                            if (p && ['EN_CAMINO', 'ENTREGADO'].includes(p.estado)) this.verRecorrido(p, false);
                        }
                    }).catch(() => {});
                },

                pintarLocal() {
                    if (marcaLocal) marcaLocal.remove();
                    if (this.local) marcaLocal = L.marker([this.local.lat, this.local.lng], { icon: icono('🏪', NOMBRE_LOCAL) }).addTo(mapa);
                },

                pintar() {
                    capaMotos.clearLayers();
                    capaDestinos.clearLayers();
                    this.motorizados.filter(m => m.lat !== null).forEach(m => {
                        L.marker([m.lat, m.lng], { icon: icono('🛵', m.nombre, m.hace === null || m.hace >= 120), zIndexOffset: 1000 })
                            .bindTooltip('Hace ' + this.textoHace(m)).addTo(capaMotos);
                    });
                    this.pedidos.filter(p => p.lat !== null && p.estado !== 'ENTREGADO').forEach(p => {
                        L.marker([p.lat, p.lng], { icon: icono('🏠', '#' + p.id + ' ' + (p.cliente || '')) })
                            .on('click', () => this.seleccionar(p)).addTo(capaDestinos);
                    });
                },

                textoHace(m) {
                    if (m.hace === null) return 'sin señal';
                    if (m.hace < 60) return m.hace + ' s';
                    if (m.hace < 3600) return Math.floor(m.hace / 60) + ' min';
                    return 'hace horas';
                },

                verMoto(m) {
                    if (m.lat !== null) mapa.setView([m.lat, m.lng], 16);
                    else this.mostrarAviso(m.nombre + ' aún no manda su ubicación. Envíale su enlace desde Motorizados.');
                },

                seleccionar(p) {
                    this.seleccionado = p.id;
                    if (['EN_CAMINO', 'ENTREGADO'].includes(p.estado)) {
                        this.verRecorrido(p, true);
                        return;
                    }
                    this.quitarLinea();
                    if (p.lat !== null) mapa.setView([p.lat, p.lng], 16);
                },

                quitarLinea() {
                    if (linea) { linea.remove(); linea = null; }
                    this.recorridoTexto = '';
                },

                verRecorrido(p, centrar) {
                    fetch(URL_BASE + '/' + p.id + '/recorrido', { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(d => {
                        this.quitarLinea();
                        const puntos = d.puntos || [];
                        const marcas = puntos.slice();
                        if (p.lat !== null) marcas.push([p.lat, p.lng]);
                        if (puntos.length) {
                            linea = L.polyline(puntos, { color: '#4f46e5', weight: 5, opacity: .8 }).addTo(mapa);
                            let km = 0;
                            for (let i = 1; i < puntos.length; i++) km += mapa.distance(puntos[i - 1], puntos[i]) / 1000;
                            this.recorridoTexto = 'Pedido #' + p.id + ': recorrido de ' + km.toFixed(1) + ' km';
                        } else {
                            this.recorridoTexto = 'Pedido #' + p.id + ': aún no hay recorrido registrado';
                        }
                        if (centrar && marcas.length) mapa.fitBounds(L.latLngBounds(marcas), { padding: [40, 40], maxZoom: 16 });
                    }).catch(() => {});
                },

                guardarLocal(latlng) {
                    post(URL_BASE + '/local', { lat: latlng.lat, lng: latlng.lng }).then(d => {
                        this.local = { lat: latlng.lat, lng: latlng.lng };
                        this.fijandoLocal = false;
                        this.pintarLocal();
                        this.mostrarAviso(d.mensaje);
                    }).catch(e => this.mostrarAviso(e.message));
                },

                estado(p, estado) {
                    post(URL_BASE + '/' + p.id + '/estado', { estado }).then(() => this.cargar()).catch(e => this.mostrarAviso(e.message));
                },

                abrirDatos(p) {
                    this.error = '';
                    this.enlace = '';
                    this.datos = { id: p.id, cliente: p.cliente, telefono: p.telefono || '', direccion: p.direccion || '', referencia: p.referencia || '',
                        mot_id: p.mot_id ? String(p.mot_id) : '', lat: p.lat, lng: p.lng, pago: p.pago || '', paga_con: p.paga_con ?? '',
                        envio: p.envio ?? '', km: null, fuera: false, cobrado: p.cobrado };
                    // El mapa se crea cuando la ventana ya se ve (con tamaño 0 Leaflet no carga el mapa)
                    setTimeout(() => {
                        if (mapaDatos) { mapaDatos.remove(); mapaDatos = null; pinDatos = null; }
                        const centro = p.lat !== null ? [p.lat, p.lng] : (this.local ? [this.local.lat, this.local.lng] : [-9.19, -75.02]);
                        mapaDatos = L.map('mapa-datos').setView(centro, p.lat !== null || this.local ? 16 : 5);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(mapaDatos);
                        if (this.local) L.marker([this.local.lat, this.local.lng], { icon: icono('🏪', NOMBRE_LOCAL) }).addTo(mapaDatos);
                        if (p.lat !== null) this.ponerPin(p.lat, p.lng, false);
                        mapaDatos.on('click', e => this.ponerPin(e.latlng.lat, e.latlng.lng, false));
                    }, 80);
                },

                ponerPin(lat, lng, centrar) {
                    this.datos.lat = lat;
                    this.datos.lng = lng;
                    if (!pinDatos) {
                        pinDatos = L.marker([lat, lng], { draggable: true, icon: icono('🏠', 'Entrega') }).addTo(mapaDatos);
                        pinDatos.on('dragend', () => { const c = pinDatos.getLatLng(); this.datos.lat = c.lat; this.datos.lng = c.lng; });
                    } else {
                        pinDatos.setLatLng([lat, lng]);
                    }
                    if (centrar) mapaDatos.setView([lat, lng], 17);
                    // Costo de envío según la distancia (si hay tarifas y aún no se cobró)
                    post(URL_BASE + '/envio', { lat, lng }).then(c => {
                        if (!this.datos) return;
                        this.datos.km = c.km;
                        this.datos.fuera = c.fuera;
                        if (c.costo !== null && !this.datos.cobrado) this.datos.envio = c.costo;
                    }).catch(() => {});
                },

                buscarEnlace() {
                    this.buscando = true;
                    this.error = '';
                    post(URL_BASE + '/ubicacion', { texto: this.enlace }).then(c => this.ponerPin(c.lat, c.lng, true))
                        .catch(e => this.error = e.message).finally(() => this.buscando = false);
                },

                guardarDatos() {
                    this.guardando = true;
                    this.error = '';
                    const d = Object.assign({}, this.datos);
                    ['envio', 'paga_con'].forEach(k => { if (d[k] === '' || d[k] === null) delete d[k]; });
                    if (!d.pago) delete d.pago;
                    post(URL_BASE + '/' + d.id + '/datos', d).then(r => {
                        this.datos = null;
                        this.mostrarAviso(r.mensaje);
                        this.cargar();
                    }).catch(e => this.error = e.message).finally(() => this.guardando = false);
                },

                verSugeridos(p) {
                    fetch(URL_BASE + '/' + p.id + '/sugerir', { headers: { 'Accept': 'application/json' } }).then(r => r.json())
                        .then(d => this.sugeridos = { pedido: p.id, lista: d.motorizados }).catch(() => this.mostrarAviso('No se pudo cargar los motorizados.'));
                },

                asignar(pedido, motId) {
                    post(URL_BASE + '/' + pedido + '/asignar', { mot_id: motId }).then(d => {
                        this.sugeridos = null;
                        this.mostrarAviso(d.mensaje);
                        this.cargar();
                    }).catch(e => this.mostrarAviso(e.message));
                },

                abrirTarifas() {
                    this.error = '';
                    this.tarifas = TARIFAS.length ? TARIFAS.map(t => ({ ...t })) : [{ hasta_km: 2, costo: 3 }, { hasta_km: 4, costo: 5 }];
                },

                guardarTarifas() {
                    this.error = '';
                    const lista = this.tarifas.filter(t => t.hasta_km !== '' && t.costo !== '');
                    post(URL_BASE + '/tarifas', { tarifas: lista }).then(d => {
                        TARIFAS = d.tarifas;
                        this.tarifas = null;
                        this.mostrarAviso(d.mensaje);
                    }).catch(e => this.error = e.message);
                },

                // Pitido corto, sin archivo de sonido
                sonar() {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        [0, .25, .5].forEach(t => {
                            const o = ctx.createOscillator(), g = ctx.createGain();
                            o.frequency.value = 880;
                            o.connect(g);
                            g.connect(ctx.destination);
                            g.gain.setValueAtTime(.25, ctx.currentTime + t);
                            g.gain.exponentialRampToValueAtTime(.001, ctx.currentTime + t + .2);
                            o.start(ctx.currentTime + t);
                            o.stop(ctx.currentTime + t + .2);
                        });
                    } catch (e) {}
                },

                copiar(texto) {
                    (navigator.clipboard ? navigator.clipboard.writeText(texto) : Promise.reject())
                        .then(() => this.mostrarAviso('Enlace copiado'))
                        .catch(() => window.prompt('Copia el enlace:', texto));
                },

                mostrarAviso(t) {
                    this.aviso = t;
                    clearTimeout(this._aviso);
                    this._aviso = setTimeout(() => this.aviso = '', 3000);
                },
            };
        }
    </script>
@endpush
