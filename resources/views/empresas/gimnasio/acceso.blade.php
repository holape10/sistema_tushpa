<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ingreso · {{ $negocio->nombre_comercial ?? 'Gimnasio' }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>[x-cloak]{display:none!important}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa')
</head>
<body class="bg-neutral-950 text-white min-h-screen" x-data="acceso()" x-init="iniciar()" @click="enfocar($event)">

    <header class="border-b border-white/10">
        <div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-4 flex flex-wrap items-center gap-4">
            <a href="{{ route('gimnasio.index') }}" title="Volver" class="w-11 h-11 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center"><i class="fas fa-arrow-left"></i></a>
            <div>
                <p class="text-xs uppercase tracking-[.3em] text-orange-400 font-bold">Control de ingreso</p>
                <h1 class="text-xl font-extrabold">{{ $negocio->nombre_comercial ?? '' }}</h1>
            </div>
            <div class="ml-auto text-right">
                <p class="text-4xl font-black tabular-nums" x-text="hora"></p>
                <p class="text-xs text-white/50 capitalize" x-text="fechaLarga"></p>
            </div>
        </div>
    </header>

    <main class="max-w-[1500px] mx-auto px-4 sm:px-6 py-6 grid lg:grid-cols-[1fr_340px] gap-6">
        <section class="space-y-5">
            {{-- Lector: DNI, QR del carnet o huella (los lectores escriben como teclado y terminan con Enter) --}}
            <form @submit.prevent="marcar()" class="flex gap-3">
                <div class="relative flex-1">
                    <i class="fas fa-qrcode absolute left-5 top-1/2 -translate-y-1/2 text-white/30 text-2xl"></i>
                    <input x-ref="lector" x-model="texto" autocomplete="off" autofocus
                           placeholder="Escanea tu carnet, escribe tu DNI o pon tu huella"
                           class="w-full h-16 pl-16 pr-4 rounded-2xl bg-white/5 border-2 border-orange-500/60 text-2xl font-bold tracking-wider placeholder:text-white/25 placeholder:text-lg placeholder:font-normal focus:border-orange-400 focus:ring-4 focus:ring-orange-500/30">
                </div>
                <button class="h-16 px-7 rounded-2xl bg-orange-500 hover:bg-orange-600 text-xl font-black" :disabled="enviando"><i class="fas fa-arrow-right-to-bracket"></i></button>
            </form>

            {{-- Resultado --}}
            <div class="rounded-[2rem] overflow-hidden min-h-[420px] flex items-center justify-center transition-colors duration-300"
                 :class="!r ? 'bg-white/5' : (r.permitido ? 'bg-gradient-to-br from-emerald-500 to-emerald-700' : (r.encontrado ? 'bg-gradient-to-br from-rose-500 to-rose-700' : 'bg-gradient-to-br from-neutral-700 to-neutral-800'))">
                <div x-show="!r" class="text-center text-white/40 p-10">
                    <i class="fas fa-dumbbell text-7xl"></i>
                    <p class="mt-4 text-2xl font-bold">¡Bienvenido!</p>
                    <p class="text-sm">Acerca tu carnet QR, escribe tu DNI o usa el lector de huella.</p>
                </div>
                <template x-if="r">
                    <div class="w-full p-8 grid md:grid-cols-[260px_1fr] gap-8 items-center">
                        <div class="mx-auto">
                            <template x-if="r.foto"><img :src="r.foto" alt="" class="w-60 h-60 rounded-[2rem] object-cover ring-8 ring-white/30 shadow-2xl"></template>
                            <template x-if="!r.foto"><div class="w-60 h-60 rounded-[2rem] bg-black/20 ring-8 ring-white/20 flex items-center justify-center text-8xl font-black" x-text="(r.nombre || '?').charAt(0)"></div></template>
                        </div>
                        <div class="text-center md:text-left">
                            <p class="text-6xl font-black tracking-tight"><i class="fas" :class="r.permitido ? 'fa-circle-check' : 'fa-circle-xmark'"></i>
                                <span x-text="r.permitido ? 'ADELANTE' : (r.encontrado ? 'NO PUEDE PASAR' : 'NO ENCONTRADO')"></span></p>
                            <p class="mt-3 text-3xl font-extrabold uppercase" x-text="r.nombre"></p>
                            <p class="mt-1 text-lg opacity-90" x-show="r.plan" x-text="r.plan + (r.vence ? ' · vence ' + r.vence : '')"></p>
                            <p class="mt-4 text-xl font-semibold leading-snug" x-text="r.mensaje"></p>
                            <p x-show="r.cumple" class="mt-3 text-2xl font-black">🎂 ¡Feliz cumpleaños!</p>
                            <p x-show="r.aviso_congelamiento" class="mt-3 text-sm bg-black/20 rounded-xl px-4 py-2 inline-block" x-text="r.aviso_congelamiento"></p>
                            <div x-show="r.encontrado && !r.permitido" class="mt-6 flex flex-wrap gap-3 justify-center md:justify-start">
                                <button type="button" @click="pedirAprobacion()" class="px-5 py-3 rounded-xl bg-white text-rose-700 font-black"><i class="fas fa-user-shield"></i> Aprobar ingreso (administrador)</button>
                                <a :href="'{{ route('gimnasio.index') }}'" target="_blank" class="px-5 py-3 rounded-xl bg-black/25 font-bold"><i class="fas fa-cash-register"></i> Cobrar plan o rutina del día</a>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <p class="text-xs text-white/30"><i class="fas fa-fingerprint"></i> Lector de huella: registra en la ficha del cliente el número con el que quedó en el lector; al poner el dedo, el lector lo escribe aquí.</p>
        </section>

        {{-- Ingresos de hoy --}}
        <aside class="bg-white/5 rounded-3xl p-4 h-fit">
            <p class="font-bold mb-3 flex items-center justify-between">Ingresos de hoy <span class="text-xs text-white/50" x-text="ingresos.filter(i => i.resultado === 'PERMITIDO').length + ' permitidos'"></span></p>
            <div class="space-y-2 max-h-[70vh] overflow-y-auto">
                <template x-for="(i, n) in ingresos" :key="n">
                    <div class="flex items-center gap-3 p-2 rounded-xl bg-white/5">
                        <template x-if="i.foto"><img :src="i.foto" class="w-10 h-10 rounded-lg object-cover" alt=""></template>
                        <template x-if="!i.foto"><span class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center font-bold" x-text="i.nombre.charAt(0)"></span></template>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold truncate" x-text="i.nombre"></p>
                            <p class="text-[11px] text-white/40" x-text="i.hora + ' · ' + i.metodo"></p>
                        </div>
                        <i class="fas" :class="i.resultado === 'PERMITIDO' ? 'fa-circle-check text-emerald-400' : 'fa-circle-xmark text-rose-400'"></i>
                    </div>
                </template>
                <p x-show="!ingresos.length" class="text-sm text-white/40 text-center py-6">Aún nadie ingresó hoy.</p>
            </div>
        </aside>
    </main>

    {{-- Aprobación del administrador --}}
    <div x-show="aprobando" x-cloak class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
        <form @submit.prevent="aprobar()" class="bg-white text-gray-800 rounded-3xl shadow-2xl w-full max-w-md p-6 space-y-3">
            <h2 class="text-lg font-black"><i class="fas fa-user-shield text-orange-500"></i> Aprobar ingreso</h2>
            <p class="text-sm text-gray-600" x-text="r?.nombre"></p>
            <label class="block text-sm">Motivo
                <input x-model="ap.motivo" x-ref="motivo" required minlength="3" maxlength="120" placeholder="Ej. volvió antes de su viaje" class="block w-full rounded-lg border-gray-300 mt-1"></label>
            @unless ($esAdmin)
                <label class="block text-sm">Usuario del administrador<input x-model="ap.usuario" required autocomplete="off" class="block w-full rounded-lg border-gray-300 mt-1"></label>
                <label class="block text-sm">Clave<input type="password" x-model="ap.password" required autocomplete="new-password" class="block w-full rounded-lg border-gray-300 mt-1"></label>
            @endunless
            <p x-show="ap.error" class="text-sm text-rose-600" x-text="ap.error"></p>
            <div class="flex gap-2 pt-1">
                <button class="flex-1 py-3 rounded-xl bg-orange-500 text-white font-black" :disabled="enviando">Aprobar</button>
                <button type="button" @click="aprobando = false; enfocar()" class="px-5 py-3 rounded-xl bg-gray-100 font-semibold">Cancelar</button>
            </div>
        </form>
    </div>

    <script>
        function acceso() {
            const R = { marcar: @json(route('gimnasio.acceso.marcar')), aprobar: @json(route('gimnasio.acceso.aprobar')), hoy: @json(route('gimnasio.acceso.hoy')) };
            const json = (url, op = {}) => fetch(url, Object.assign({ headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } }, op)).then(async res => {
                    const d = await res.json();
                    return res.status === 422 ? { ok: false, mensaje: Object.values(d.errors || {})[0]?.[0] || d.message } : d;
                });
            return {
                texto: '', r: null, enviando: false, ingresos: @js($ingresos), hora: '', fechaLarga: '', aprobando: false,
                ap: { motivo: '', usuario: '', password: '', error: '' }, _limpiar: null,
                iniciar() {
                    const reloj = () => { const d = new Date(); this.hora = d.toLocaleTimeString('es-PE', { hour12: false });
                        this.fechaLarga = d.toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long' }); };
                    reloj(); setInterval(reloj, 1000);
                    setInterval(() => this.refrescar(), 30000);
                },
                enfocar(e) { if (!this.aprobando && !(e && e.target.closest('input, button, a, select'))) this.$refs.lector.focus(); },
                async refrescar() { try { this.ingresos = await json(R.hoy); } catch (e) {} },
                async marcar() {
                    const t = this.texto.trim();
                    this.texto = '';
                    if (!t || this.enviando) return;
                    this.enviando = true;
                    const d = await json(R.marcar, { method: 'POST', body: JSON.stringify({ texto: t }) }).catch(() => ({ ok: false, mensaje: 'Sin conexión con el servidor.' }));
                    this.enviando = false;
                    this.mostrar(d.encontrado === undefined ? { encontrado: false, permitido: false, nombre: '', mensaje: d.mensaje } : d);
                },
                mostrar(d) {
                    this.r = d;
                    this.sonido(d.permitido);
                    this.refrescar();
                    clearTimeout(this._limpiar);
                    // Permitido se limpia rápido; denegado se queda para que recepción lo vea y apruebe o cobre
                    this._limpiar = setTimeout(() => { if (!this.aprobando) this.r = null; }, d.permitido ? 6000 : 25000);
                    this.$nextTick(() => this.$refs.lector.focus());
                },
                pedirAprobacion() {
                    this.ap = { motivo: '', usuario: '', password: '', error: '' };
                    this.aprobando = true;
                    clearTimeout(this._limpiar);
                    this.$nextTick(() => this.$refs.motivo.focus());
                },
                async aprobar() {
                    this.enviando = true;
                    const d = await json(R.aprobar, { method: 'POST', body: JSON.stringify({ soc_id: this.r.soc_id, ...this.ap }) }).catch(() => ({ ok: false, mensaje: 'Sin conexión.' }));
                    this.enviando = false;
                    if (!d.ok) { this.ap.error = d.mensaje; return; }
                    this.aprobando = false;
                    this.mostrar(d);
                },
                sonido(ok) {
                    try {
                        const c = new (window.AudioContext || window.webkitAudioContext)(), o = c.createOscillator(), g = c.createGain();
                        o.connect(g); g.connect(c.destination);
                        o.frequency.value = ok ? 880 : 220; o.type = ok ? 'sine' : 'square';
                        g.gain.setValueAtTime(0.15, c.currentTime); g.gain.exponentialRampToValueAtTime(0.001, c.currentTime + (ok ? 0.35 : 0.7));
                        o.start(); o.stop(c.currentTime + (ok ? 0.35 : 0.7));
                    } catch (e) {}
                },
            };
        }
    </script>
</body>
</html>
