<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pantalla de Cocina</title>
    <link rel="icon" href="{{ asset('imagenes/512.png') }}" type="image/png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        body { background: #0f172a; }
        .tarjeta { break-inside: avoid; }
        @keyframes latido { 0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, .7); } 50% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); } }
        .urgente { animation: latido 1.4s infinite; }
        @keyframes entrada { from { transform: scale(.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .nueva { animation: entrada .4s ease-out; outline: 4px solid #facc15; }
    </style>
    @include('partials.pwa')
</head>
<body class="text-slate-100 min-h-screen" x-data="kds()" x-init="iniciar()">

    {{-- Barra superior --}}
    <header class="sticky top-0 z-20 bg-slate-900/95 backdrop-blur border-b border-slate-700 px-4 py-2 flex flex-wrap items-center gap-3">
        <div class="font-extrabold text-xl tracking-wide">🍳 COCINA <span class="text-slate-400 text-sm font-semibold">{{ $negocio->nombre_comercial }}</span></div>

        {{-- Estaciones --}}
        <nav class="flex flex-wrap gap-1">
            <a href="{{ route('cocina.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-bold {{ !$estacion ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">TODAS</a>
            @foreach ($estaciones as $e)
                <a href="{{ route('cocina.index', ['estacion' => $e->Id]) }}" class="px-3 py-1.5 rounded-lg text-sm font-bold {{ $estacion == $e->Id ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">{{ $e->descripcion }}</a>
            @endforeach
        </nav>

        <div class="ml-auto flex flex-wrap items-center gap-2 text-sm">
            <span class="px-3 py-1.5 rounded-lg bg-slate-800"><span class="text-slate-400">En cola</span> <b class="text-lg" x-text="tarjetas.length"></b></span>
            <span class="px-3 py-1.5 rounded-lg bg-slate-800" x-show="promedio !== null"><span class="text-slate-400">Promedio hoy</span> <b class="text-lg" x-text="promedio + ' min'"></b></span>
            <span class="px-3 py-1.5 rounded-lg bg-slate-800"><span class="text-slate-400">Despachados</span> <b class="text-lg" x-text="despachados"></b></span>
            <span class="px-3 py-1.5 rounded-lg bg-slate-800 font-mono text-lg" x-text="reloj"></span>
            <button @click="activarSonido()" class="px-3 py-1.5 rounded-lg font-bold" :class="sonido ? 'bg-emerald-600' : 'bg-red-600 animate-pulse'" x-text="sonido ? '🔔 Sonido' : '🔕 Activar sonido'"></button>
            <button @click="panel = panel === 'recientes' ? null : 'recientes'" class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 font-bold">↩ Recuperar</button>
            @if ($esAdmin)
                <button @click="panel = panel === 'config' ? null : 'config'" class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600" title="Tiempos">⚙</button>
            @endif
            <button @click="pantallaCompleta()" class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600" title="Pantalla completa">⛶</button>
            <a href="{{ route('dashboard') }}" class="px-3 py-1.5 rounded-lg bg-slate-800 text-slate-400 hover:text-white" title="Salir">✕</a>
        </div>
    </header>

    {{-- Aviso de conexión --}}
    <div x-show="sinConexion" x-cloak class="bg-red-600 text-white text-center font-bold py-1">Sin conexión con el servidor. Reintentando…</div>

    {{-- Panel: recuperar --}}
    <div x-show="panel === 'recientes'" x-cloak class="bg-slate-800 border-b border-slate-700 px-4 py-3">
        <p class="text-sm text-slate-400 mb-2">Despachados en los últimos 30 minutos (toca para devolverlo a la pantalla):</p>
        <div class="flex flex-wrap gap-2">
            <template x-for="r in recientes" :key="r.id">
                <button @click="recuperar(r.id)" class="px-3 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-left">
                    <b x-text="r.destino"></b> <span class="text-xs text-slate-400" x-text="'· ' + r.items + ' plato(s) · ' + hora(r.listo)"></span>
                </button>
            </template>
            <span x-show="!recientes.length" class="text-slate-500 text-sm">Nada despachado recientemente.</span>
        </div>
    </div>

    {{-- Panel: configuración --}}
    @if ($esAdmin)
        <div x-show="panel === 'config'" x-cloak class="bg-slate-800 border-b border-slate-700 px-4 py-3 flex flex-wrap items-end gap-3 text-sm">
            <label>Amarillo a los (min)<input type="number" min="1" x-model.number="cfg.amarillo" class="block w-28 rounded-lg bg-slate-900 border-slate-600 text-white"></label>
            <label>Rojo a los (min)<input type="number" min="2" x-model.number="cfg.rojo" class="block w-28 rounded-lg bg-slate-900 border-slate-600 text-white"></label>
            <button @click="guardarConfig()" class="px-4 py-2 rounded-lg bg-amber-500 text-slate-900 font-bold">Guardar</button>
            <span class="text-slate-400">Verde hasta <b x-text="cfg.amarillo"></b> min, amarillo hasta <b x-text="cfg.rojo"></b> min, luego rojo.</span>
        </div>
    @endif

    {{-- Tarjetas --}}
    <main class="p-3">
        <div x-show="!tarjetas.length" x-cloak class="text-center text-slate-500 mt-32">
            <div class="text-7xl mb-4">✅</div>
            <p class="text-2xl font-bold">Todo al día</p>
            <p>Los pedidos aparecerán aquí apenas el mozo envíe la comanda.</p>
        </div>

        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));">
            <template x-for="t in tarjetas" :key="t.id">
                <div class="tarjeta rounded-xl overflow-hidden bg-slate-800 flex flex-col shadow-lg"
                     :class="{ 'urgente': nivel(t) === 'rojo' && t.tipo !== 'ANULACION', 'nueva': nuevas.includes(t.id) }">
                    <div class="px-3 py-2 flex items-start justify-between gap-2"
                         :class="t.tipo === 'ANULACION' ? 'bg-red-700' : { verde: 'bg-emerald-600', amarillo: 'bg-amber-500 text-slate-900', rojo: 'bg-red-600' }[nivel(t)]">
                        <div class="min-w-0">
                            <p class="font-extrabold text-xl leading-tight truncate" x-text="t.destino"></p>
                            <p class="text-xs opacity-90">
                                <span x-text="t.ped_id ? '#' + t.ped_id : 'Venta directa'"></span> · <span x-text="t.mozo || ''"></span>
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="font-mono font-extrabold text-2xl leading-none" x-text="transcurrido(t)"></p>
                            <span x-show="t.tipo !== 'NUEVO'" class="inline-block mt-1 px-2 py-0.5 rounded text-[11px] font-black"
                                  :class="t.tipo === 'ANULACION' ? 'bg-white text-red-700' : 'bg-violet-700 text-white'" x-text="t.tipo"></span>
                        </div>
                    </div>

                    <ul class="flex-1 divide-y divide-slate-700">
                        <template x-for="l in t.lineas" :key="l.id">
                            <li @click="!l.anulado && alternar(l)" class="px-3 py-2 select-none"
                                :class="l.anulado ? 'bg-red-950/60' : (l.listo ? 'opacity-40 cursor-pointer' : 'cursor-pointer hover:bg-slate-700/60')">
                                <div class="flex gap-2 items-baseline">
                                    <span class="font-black text-2xl w-10 shrink-0 text-right" :class="l.anulado ? 'text-red-400 line-through' : 'text-amber-300'" x-text="cant(l.cantidad)"></span>
                                    <span class="text-lg font-bold leading-tight" :class="{ 'line-through': l.listo || l.anulado, 'text-red-300': l.anulado }" x-text="l.descripcion"></span>
                                    <span x-show="l.listo && !l.anulado" class="ml-auto text-emerald-400 text-xl">✔</span>
                                </div>
                                <p x-show="l.observacion" class="ml-12 mt-0.5 text-sm font-semibold"
                                   :class="l.anulado ? 'text-red-300' : 'text-yellow-300'" x-text="'» ' + l.observacion"></p>
                            </li>
                        </template>
                    </ul>

                    <button @click="listo(t)" class="m-2 py-3 rounded-lg font-black text-lg tracking-wide"
                            :class="t.tipo === 'ANULACION' ? 'bg-red-600 hover:bg-red-500' : 'bg-emerald-600 hover:bg-emerald-500'"
                            x-text="t.tipo === 'ANULACION' ? 'ENTENDIDO' : 'LISTO ✔'"></button>
                </div>
            </template>
        </div>
    </main>

    <script>
        function kds() {
            const ESTACION = @json($estacion);
            const URL = { datos: @json(route('cocina.datos')), item: @json(url('cocina/item')), ticket: @json(url('cocina/ticket')), config: @json(route('cocina.config')) };
            const CSRF = document.querySelector('meta[name=csrf-token]').content;
            const qs = ESTACION ? '?estacion=' + ESTACION : '';
            const post = (url, data = {}) => fetch(url + qs, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data) });

            return {
                tarjetas: [], recientes: [], promedio: null, despachados: 0, conocidas: null, nuevas: [],
                desfase: 0, reloj: '', tic: 0, sinConexion: false, panel: null, audio: null,
                sonido: localStorage.getItem('kds_sonido') === '1',
                cfg: { amarillo: {{ (int) $negocio->kds_amarillo }}, rojo: {{ (int) $negocio->kds_rojo }} },

                iniciar() {
                    this.cargar();
                    setInterval(() => this.cargar(), 4000);
                    setInterval(() => { this.tic++; this.reloj = new Date(Date.now() + this.desfase).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' }); }, 1000);
                },

                async cargar() {
                    try {
                        const r = await fetch(URL.datos + qs, { headers: { Accept: 'application/json' } });
                        if (r.status === 401 || r.status === 419) { location.reload(); return; }
                        const d = await r.json();
                        this.sinConexion = false;
                        this.desfase = new Date(d.ahora).getTime() - Date.now(); // el tiempo sale del reloj del servidor

                        const ids = d.tarjetas.map(t => t.id);
                        if (this.conocidas !== null) {
                            const llegaron = ids.filter(id => !this.conocidas.includes(id));
                            if (llegaron.length) {
                                this.nuevas = llegaron;
                                setTimeout(() => this.nuevas = [], 6000);
                                this.pitar(d.tarjetas.some(t => llegaron.includes(t.id) && t.tipo === 'ANULACION'));
                            }
                        }
                        this.conocidas = ids;
                        this.tarjetas = d.tarjetas;
                        this.recientes = d.recientes;
                        this.promedio = d.promedio;
                        this.despachados = d.despachadosHoy;
                    } catch (e) {
                        this.sinConexion = true;
                    }
                },

                segundos(t) { this.tic; return Math.max(0, Math.floor((Date.now() + this.desfase - new Date(t.creado).getTime()) / 1000)); },
                transcurrido(t) { const s = this.segundos(t); return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); },
                nivel(t) { const m = this.segundos(t) / 60; return m >= this.cfg.rojo ? 'rojo' : (m >= this.cfg.amarillo ? 'amarillo' : 'verde'); },
                cant(n) { return Number(n) % 1 === 0 ? Number(n) : Number(n).toFixed(1); },
                hora(f) { return new Date(f.replace(' ', 'T')).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' }); },

                async alternar(l) { l.listo = !l.listo; await post(URL.item + '/' + l.id + '/alternar'); this.cargar(); },
                async listo(t) { this.tarjetas = this.tarjetas.filter(x => x.id !== t.id); await post(URL.ticket + '/' + t.id + '/listo'); this.cargar(); },
                async recuperar(id) { await post(URL.ticket + '/' + id + '/recuperar'); this.panel = null; this.cargar(); },
                async guardarConfig() {
                    const r = await post(URL.config, { kds_amarillo: this.cfg.amarillo, kds_rojo: this.cfg.rojo });
                    if (r.ok) { this.panel = null; } else { const d = await r.json(); alert(d.message || 'Revisa los minutos.'); }
                },

                // Los navegadores solo permiten sonido después de un toque del usuario
                activarSonido() {
                    this.sonido = true;
                    localStorage.setItem('kds_sonido', '1');
                    this.audio = this.audio || new (window.AudioContext || window.webkitAudioContext)();
                    this.pitar(false);
                },
                pitar(alerta) {
                    if (!this.sonido) return;
                    try {
                        this.audio = this.audio || new (window.AudioContext || window.webkitAudioContext)();
                        const tonos = alerta ? [440, 330, 440, 330] : [880, 1175];
                        tonos.forEach((f, i) => {
                            const o = this.audio.createOscillator(), g = this.audio.createGain();
                            o.frequency.value = f; o.connect(g); g.connect(this.audio.destination);
                            const t0 = this.audio.currentTime + i * 0.18;
                            g.gain.setValueAtTime(0.25, t0); g.gain.exponentialRampToValueAtTime(0.001, t0 + 0.16);
                            o.start(t0); o.stop(t0 + 0.17);
                        });
                    } catch (e) {}
                },
                pantallaCompleta() {
                    if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen?.();
                },
            };
        }
    </script>
@include('partials.avisos')
</body>
</html>
