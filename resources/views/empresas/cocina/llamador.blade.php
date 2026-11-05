<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="tushpa-canal" content="{{ \App\Events\CambioEnSucursal::canal((string) auth()->user()->IdEmpresa, (int) auth()->user()->id_empresa_negocio) }}" data-clave="{{ config('broadcasting.default') === 'reverb' ? config('broadcasting.connections.reverb.key') : '' }}">
    <title>Llamador de pedidos</title>
    <link rel="icon" href="{{ asset(config('marca.icono')) }}" type="image/png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        body { background: #0b1120; }
        @keyframes llamar { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.08); } }
        .recien { animation: llamar 0.9s ease-in-out 6; }
    </style>
    @include('partials.pwa')
</head>
{{--
    Televisor a la vista de los clientes. Se llena solo con lo que cocina marca en su pantalla: al despachar un pedido
    para llevar pasa a "¡Listo!", suena y se anuncia en voz alta. Sale cuando el mozo lo entrega o a los 15 minutos.
--}}
<body class="text-white h-screen overflow-hidden select-none" x-data="llamador()" x-init="iniciar()">
    <div class="h-full flex flex-col">
        <header class="flex items-center gap-4 px-8 py-4 bg-slate-900/80">
            <p class="text-3xl font-black tracking-wide mr-auto">{{ $negocio }}</p>
            <p class="text-3xl font-bold text-slate-300 tabular-nums" x-text="reloj"></p>
            <button type="button" @click="pantallaCompleta()" class="px-4 py-2 rounded-xl bg-white/10 text-sm font-bold" x-show="!completa">⛶ Pantalla completa</button>
        </header>

        <main class="flex-1 grid grid-cols-5 gap-6 p-6 min-h-0">
            {{-- Preparando --}}
            <section class="col-span-2 rounded-3xl bg-slate-800/70 p-6 flex flex-col min-h-0">
                <h2 class="text-3xl font-black text-amber-300 mb-5">⏳ Preparando</h2>
                <div class="flex flex-wrap content-start gap-3 overflow-hidden">
                    <template x-for="p in preparando" :key="p.clave">
                        <div class="rounded-2xl bg-slate-700 px-5 py-3 text-center min-w-28">
                            <p class="text-5xl font-black tabular-nums" x-text="p.numero"></p>
                            <p class="text-base font-semibold text-slate-300 truncate max-w-40" x-text="p.nombre" x-show="p.nombre"></p>
                        </div>
                    </template>
                    <p x-show="!preparando.length" class="text-2xl text-slate-500">—</p>
                </div>
            </section>

            {{-- ¡Listo! --}}
            <section class="col-span-3 rounded-3xl bg-emerald-600 p-6 flex flex-col min-h-0 shadow-2xl">
                <h2 class="text-4xl font-black mb-5">✅ ¡Listo! Acércate a recoger</h2>
                <div class="flex flex-wrap content-start gap-4 overflow-hidden">
                    <template x-for="p in listos" :key="p.clave">
                        <div class="rounded-3xl bg-white text-emerald-700 px-8 py-5 text-center min-w-44 shadow-lg" :class="{ 'recien': recientes.includes(p.clave) }">
                            <p class="text-7xl font-black tabular-nums" x-text="p.numero"></p>
                            <p class="text-xl font-bold text-emerald-900 truncate max-w-56" x-text="p.nombre" x-show="p.nombre"></p>
                        </div>
                    </template>
                    <p x-show="!listos.length" class="text-2xl text-emerald-100">Aquí aparece tu número cuando esté listo.</p>
                </div>
            </section>
        </main>
    </div>

    {{-- El navegador solo deja sonar después de un toque: se pide una vez al abrir --}}
    <div x-show="!sonido" x-cloak @click="activarSonido()" class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center cursor-pointer">
        <div class="text-center">
            <p class="text-6xl mb-4">🔊</p>
            <p class="text-3xl font-black">Toca la pantalla para activar el sonido</p>
            <p class="text-lg text-slate-300 mt-2">Así se anuncia cada pedido listo en voz alta.</p>
        </div>
    </div>

    <script>
        function llamador() {
            return {
                preparando: [], listos: [], recientes: [], conocidos: null, sonido: false, completa: false, reloj: '', audio: null,
                iniciar() {
                    this.cargar();
                    const vivo = window.tushpaEnVivo ? window.tushpaEnVivo(['cocina'], () => this.cargar()) : { conectado: () => false };
                    let vueltas = 0;
                    // Con tiempo real se actualiza al instante; si no, pregunta cada 5 segundos
                    setInterval(() => { if (!vivo.conectado() || ++vueltas % 12 === 0) this.cargar(); }, 5000);
                    setInterval(() => { this.reloj = new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' }); }, 1000);
                    document.addEventListener('fullscreenchange', () => { this.completa = !!document.fullscreenElement; });
                },
                async cargar() {
                    const d = await fetch(@js(route('llamador.datos')), { headers: { Accept: 'application/json' } }).then(r => r.ok ? r.json() : null).catch(() => null);
                    if (!d) return;
                    this.preparando = d.preparando;
                    this.listos = d.listos;
                    const claves = d.listos.map(p => p.clave);
                    // La primera carga no anuncia lo que ya estaba listo
                    if (this.conocidos !== null) {
                        const nuevos = d.listos.filter(p => !this.conocidos.includes(p.clave));
                        if (nuevos.length) {
                            this.recientes = nuevos.map(p => p.clave);
                            this.anunciar(nuevos);
                        }
                    }
                    this.conocidos = claves;
                },
                activarSonido() {
                    this.audio = new (window.AudioContext || window.webkitAudioContext)();
                    this.sonido = true;
                    this.pantallaCompleta();
                },
                pantallaCompleta() { document.documentElement.requestFullscreen?.().catch(() => {}); },
                timbre() {
                    if (!this.audio) return;
                    [0, 0.25].forEach((t, i) => {
                        const o = this.audio.createOscillator(), g = this.audio.createGain();
                        o.frequency.value = i ? 660 : 880;
                        g.gain.setValueAtTime(0.35, this.audio.currentTime + t);
                        g.gain.exponentialRampToValueAtTime(0.001, this.audio.currentTime + t + 0.6);
                        o.connect(g).connect(this.audio.destination);
                        o.start(this.audio.currentTime + t); o.stop(this.audio.currentTime + t + 0.6);
                    });
                },
                anunciar(nuevos) {
                    if (!this.sonido) return;
                    this.timbre();
                    if (!window.speechSynthesis) return;
                    nuevos.forEach(p => {
                        const voz = new SpeechSynthesisUtterance('Pedido número ' + p.numero + (p.nombre ? ', ' + p.nombre : '') + ', está listo');
                        voz.lang = 'es-PE';
                        voz.rate = 0.95;
                        setTimeout(() => speechSynthesis.speak(voz), 900);
                    });
                },
            };
        }
    </script>
</body>
</html>
