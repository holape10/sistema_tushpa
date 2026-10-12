<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#3730a3">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>Repartidor · {{ $motorizado->nombre }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased" x-data="repartidor()" x-init="iniciar()">

    <header class="relative isolate overflow-hidden bg-gradient-to-br from-indigo-800 via-indigo-700 to-blue-700 text-white rounded-b-[2rem] shadow-lg">
        <x-kene-adorno patron="escalera" />
        <div class="max-w-md mx-auto px-5 pt-6 pb-7">
            <p class="text-sm text-indigo-200">{{ $negocio->nombre_comercial ?? 'Delivery' }}</p>
            <div class="flex items-center gap-2">
                <p class="flex-1 text-2xl font-black">🛵 {{ $motorizado->nombre }}</p>
                @auth
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-xs text-indigo-100 underline">Salir</button></form>
                @endauth
            </div>
            <button type="button" @click="activarGps()" class="mt-3 w-full flex items-center gap-2 rounded-xl px-3 py-2 text-left text-sm font-semibold"
                    :class="gps === 'activo' ? 'bg-emerald-500/90' : (gps === 'error' ? 'bg-rose-500/90' : 'bg-white/15')">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="gps === 'activo' ? 'bg-white animate-pulse' : 'bg-white/60'"></span>
                <span x-text="textoGps"></span>
            </button>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 -mt-4 pb-10 space-y-3">
        <div class="bg-amber-50 border border-amber-200 rounded-2xl px-4 py-3 text-xs text-amber-800">
            Mantén esta página abierta y la pantalla encendida mientras repartes: si bloqueas el celular o cambias de app, el local deja de ver dónde estás.
        </div>

        <template x-for="p in pedidos" :key="p.id">
            <div class="bg-white rounded-2xl shadow-sm p-4" :class="p.estado === 'ENTREGADO' ? 'opacity-60' : ''">
                <div class="flex items-start gap-2">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-slate-400">Pedido #<span x-text="p.id"></span></p>
                        <p class="text-lg font-bold text-slate-900" x-text="p.cliente"></p>
                        <p class="text-sm text-slate-600" x-text="p.direccion || 'Sin dirección'"></p>
                        <p x-show="p.referencia" class="text-xs text-slate-500" x-text="'Ref.: ' + p.referencia"></p>
                    </div>
                    <span class="text-[11px] font-bold rounded-full px-2 py-0.5 shrink-0" :class="colores[p.estado]" x-text="nombres[p.estado]"></span>
                </div>

                <div class="mt-3 rounded-xl px-3 py-2 text-center" :class="p.cobrado ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'">
                    <template x-if="p.cobrado"><p class="text-sm font-semibold">Pagado · S/ <span x-text="p.total.toFixed(2)"></span></p></template>
                    <template x-if="!p.cobrado"><p class="font-black">💵 Cobrar al entregar: S/ <span x-text="p.total.toFixed(2)"></span></p></template>
                    <p x-show="!p.cobrado && p.pago" class="text-xs font-semibold" x-text="p.pago"></p>
                </div>
                <p x-show="p.nota" class="mt-2 text-xs text-indigo-700" x-text="'📝 ' + p.nota"></p>

                <div class="grid grid-cols-3 gap-2 mt-3 text-sm font-semibold text-center" x-show="p.estado !== 'ENTREGADO'">
                    <a :href="p.telefono ? 'tel:' + p.telefono : null" :class="p.telefono ? '' : 'opacity-40 pointer-events-none'" class="py-2.5 rounded-xl bg-slate-100">📞 Llamar</a>
                    <a :href="whatsapp(p)" target="_blank" rel="noopener" :class="p.telefono ? '' : 'opacity-40 pointer-events-none'" class="py-2.5 rounded-xl bg-green-50 text-green-700">💬 WhatsApp</a>
                    <a :href="navegar(p)" target="_blank" rel="noopener" :class="p.lat !== null || p.direccion ? '' : 'opacity-40 pointer-events-none'" class="py-2.5 rounded-xl bg-blue-50 text-blue-700">🧭 Navegar</a>
                </div>

                <template x-if="siguiente(p)">
                    <button type="button" :disabled="enviando === p.id" @click="cambiar(p, siguiente(p).estado)"
                            class="mt-3 w-full py-4 rounded-2xl text-white text-lg font-black shadow-lg active:scale-[.98] disabled:opacity-50" :class="siguiente(p).color">
                        <span x-text="enviando === p.id ? 'Enviando…' : siguiente(p).texto"></span>
                    </button>
                </template>
            </div>
        </template>

        <div x-show="cargado && !pedidos.length" class="bg-white rounded-2xl shadow-sm p-8 text-center text-slate-500">
            <p class="text-4xl">🛵</p>
            <p class="font-semibold mt-2">No tienes pedidos asignados</p>
            <p class="text-sm mt-1">Cuando el local te asigne uno, aparecerá aquí solo.</p>
        </div>
        <p x-show="mensaje" x-text="mensaje" class="text-center text-sm text-rose-600"></p>
    </main>

    <script>
        function repartidor() {
            const URL_PEDIDOS = @json(route('repartidor.pedidos', $token));
            const URL_ESTADO = @json(route('repartidor.estado', $token));
            const URL_POSICION = @json(route('repartidor.posicion', $token));
            const CSRF = document.querySelector('meta[name=csrf-token]').content;

            function post(url, data) {
                return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data) })
                    .then(r => { if (r.status === 419) { location.reload(); } if (!r.ok) throw new Error(); return r.json(); });
            }

            return {
                pedidos: [], cargado: false, enviando: null, mensaje: '', gps: 'apagado', vigilancia: null, ultimoEnvio: 0, bloqueo: null,
                nombres: { PREPARANDO: 'Preparando', LISTO: 'Listo', EN_LOCAL: 'En el local', EN_CAMINO: 'En camino', ENTREGADO: 'Entregado' },
                colores: { PREPARANDO: 'bg-slate-100 text-slate-700', LISTO: 'bg-sky-100 text-sky-700', EN_LOCAL: 'bg-amber-100 text-amber-800', EN_CAMINO: 'bg-indigo-100 text-indigo-700', ENTREGADO: 'bg-emerald-100 text-emerald-700' },

                get textoGps() {
                    return { activo: '📡 Ubicación activa: el local te ve en el mapa', error: '⚠️ Sin ubicación. Toca aquí y permite la ubicación', apagado: '📍 Toca aquí para activar tu ubicación' }[this.gps];
                },

                iniciar() {
                    this.cargar();
                    setInterval(() => { if (!document.hidden) this.cargar(); }, 20000);
                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden) { this.cargar(); this.mantenerPantalla(); }
                    });
                    // Si ya dio permiso antes, la ubicación arranca sola
                    if (navigator.permissions) {
                        navigator.permissions.query({ name: 'geolocation' }).then(p => { if (p.state === 'granted') this.activarGps(); }).catch(() => {});
                    }
                },

                cargar() {
                    fetch(URL_PEDIDOS, { headers: { 'Accept': 'application/json' } }).then(r => r.json())
                        .then(d => {
                            // Pedido nuevo asignado: vibra y suena
                            if (this.cargado && d.pedidos.some(p => !this.pedidos.some(x => x.id === p.id))) this.avisarNuevo();
                            this.pedidos = d.pedidos;
                            this.cargado = true;
                            this.mensaje = '';
                        })
                        .catch(() => this.mensaje = 'Sin conexión. Reintentando…');
                },

                avisarNuevo() {
                    if (navigator.vibrate) navigator.vibrate([300, 150, 300]);
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const o = ctx.createOscillator(), g = ctx.createGain();
                        o.frequency.value = 880;
                        o.connect(g);
                        g.connect(ctx.destination);
                        g.gain.setValueAtTime(.3, ctx.currentTime);
                        g.gain.exponentialRampToValueAtTime(.001, ctx.currentTime + .6);
                        o.start();
                        o.stop(ctx.currentTime + .6);
                    } catch (e) {}
                },

                siguiente(p) {
                    if (['PREPARANDO', 'LISTO'].includes(p.estado)) return { estado: 'EN_LOCAL', texto: '🏪 Ya estoy en el local', color: 'bg-amber-500 shadow-amber-500/30' };
                    if (p.estado === 'EN_LOCAL') return { estado: 'EN_CAMINO', texto: '🛵 Salgo en camino', color: 'bg-indigo-600 shadow-indigo-500/30' };
                    if (p.estado === 'EN_CAMINO') return { estado: 'ENTREGADO', texto: '✅ Ya entregué', color: 'bg-emerald-600 shadow-emerald-500/30' };
                    return null;
                },

                cambiar(p, estado) {
                    if (estado === 'ENTREGADO' && !p.cobrado && !confirm('¿Entregaste el pedido y cobraste S/ ' + p.total.toFixed(2) + '?')) return;
                    if (estado === 'EN_CAMINO' && this.gps !== 'activo') this.activarGps();
                    this.enviando = p.id;
                    post(URL_ESTADO, { ped_id: p.id, estado }).then(() => { p.estado = estado; this.cargar(); })
                        .catch(() => this.mensaje = 'No se pudo enviar. Revisa tu internet e intenta de nuevo.')
                        .finally(() => this.enviando = null);
                },

                activarGps() {
                    if (!navigator.geolocation) { this.gps = 'error'; return; }
                    this.mantenerPantalla();
                    if (this.vigilancia !== null) return;
                    this.vigilancia = navigator.geolocation.watchPosition(
                        pos => { this.gps = 'activo'; this.enviarPosicion(pos.coords); },
                        () => { this.gps = 'error'; navigator.geolocation.clearWatch(this.vigilancia); this.vigilancia = null; },
                        { enableHighAccuracy: true, maximumAge: 5000, timeout: 30000 }
                    );
                },

                enviarPosicion(c) {
                    if (Date.now() - this.ultimoEnvio < 10000 || !this.pedidos.some(p => p.estado !== 'ENTREGADO')) return;
                    this.ultimoEnvio = Date.now();
                    post(URL_POSICION, { lat: c.latitude, lng: c.longitude, precision: Math.round(c.accuracy || 0) }).catch(() => {});
                },

                // Pide que la pantalla no se apague mientras la página está abierta (Chrome y Safari recientes)
                mantenerPantalla() {
                    if (!('wakeLock' in navigator) || (this.bloqueo && !this.bloqueo.released)) return;
                    navigator.wakeLock.request('screen').then(b => this.bloqueo = b).catch(() => {});
                },

                whatsapp(p) {
                    const t = String(p.telefono || '').replace(/\D/g, '');
                    return 'https://wa.me/' + (t.length === 9 ? '51' + t : t) + '?text=' + encodeURIComponent('Hola, soy ' + @json($motorizado->nombre) + ', el motorizado de ' + @json($negocio->nombre_comercial ?? '') + '. Estoy llevando tu pedido.');
                },

                navegar(p) {
                    const destino = p.lat !== null ? p.lat + ',' + p.lng : (p.direccion || '');
                    return 'https://www.google.com/maps/dir/?api=1&travelmode=driving&destination=' + encodeURIComponent(destino);
                },
            };
        }
    </script>
</body>
</html>
