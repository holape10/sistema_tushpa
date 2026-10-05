<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Asistencia · {{ $negocio->nombre_comercial ?? 'Tushpa' }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>[x-cloak]{display:none!important}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 min-h-screen" x-data="kiosko(@js($tarjetas), @js($motivos))" x-init="iniciar()" @keydown.window="tecla($event)">

    {{-- Encabezado: reloj, lector y resumen --}}
    <header class="bg-gradient-to-br from-indigo-900 via-indigo-800 to-violet-800 text-white">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-5 grid lg:grid-cols-3 gap-5 items-center">
            <div class="flex items-center gap-4">
                <a href="{{ route('dashboard') }}" title="Volver al sistema" class="w-11 h-11 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center"><i class="fas fa-arrow-left"></i></a>
                <div>
                    <p class="text-xs uppercase tracking-widest text-indigo-200">Control de asistencia</p>
                    <h1 class="text-xl font-extrabold">{{ $negocio->nombre_comercial ?? '' }}</h1>
                </div>
            </div>
            <div class="text-center">
                <p class="text-5xl sm:text-6xl font-black tabular-nums tracking-tight" x-text="reloj"></p>
                <p class="text-indigo-200 capitalize" x-text="fecha"></p>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-indigo-200 mb-1"><i class="fas fa-barcode"></i> Escanea o escribe tu DNI</label>
                <form @submit.prevent="marcarDni()" class="flex gap-2">
                    <input x-ref="dni" x-model="dni" inputmode="numeric" autocomplete="off" maxlength="15" placeholder="[ ESCANEA TU DNI AQUÍ ]"
                           class="flex-1 min-w-0 h-14 rounded-2xl border-0 bg-white text-slate-800 text-2xl font-black tracking-[0.2em] text-center placeholder:text-slate-300 placeholder:text-base placeholder:tracking-widest focus:ring-4 focus:ring-amber-300">
                    <button class="h-14 px-5 rounded-2xl bg-amber-400 hover:bg-amber-300 text-amber-950 font-black"><i class="fas fa-check"></i></button>
                </form>
            </div>
        </div>
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 pb-4 flex flex-wrap gap-2">
            <template x-for="r in resumen" :key="r.estado">
                <button type="button" @click="filtro = filtro === r.estado ? '' : r.estado"
                        :class="filtro === r.estado ? 'bg-white text-indigo-900' : 'bg-white/10 hover:bg-white/20'"
                        class="px-3 py-1.5 rounded-full text-sm font-semibold flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full" :class="r.punto"></span><span x-text="r.nombre"></span>
                    <span class="font-black" x-text="r.n"></span>
                </button>
            </template>
            <div class="ml-auto flex gap-2">
                <input x-model="buscar" type="search" placeholder="Buscar nombre…" class="h-9 w-44 sm:w-60 rounded-full border-0 bg-white/10 text-white placeholder:text-indigo-200 text-sm focus:ring-2 focus:ring-white/50">
                <button type="button" @click="pantallaCompleta()" title="Pantalla completa" class="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20"><i class="fas fa-expand"></i></button>
            </div>
        </div>
    </header>

    {{-- Tarjetas --}}
    <main class="max-w-[1600px] mx-auto px-4 sm:px-6 py-5">
        <p class="text-center text-sm text-slate-500 mb-4"><i class="fas fa-mobile-screen-button"></i> ¿No tienes tu DNI? Toca tu nombre y escanea el código QR con tu celular.</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-3 sm:gap-4">
            <template x-for="t in visibles" :key="t.id">
                <button type="button" @click="abrirQr(t)" :class="estilo(t.estado).tarjeta"
                        class="relative text-left rounded-2xl border-2 p-4 transition hover:-translate-y-0.5 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-indigo-300">
                    <span class="absolute top-3 right-3 text-[10px] font-black uppercase px-2 py-0.5 rounded-full" :class="estilo(t.estado).etiqueta" x-text="estilo(t.estado).nombre"></span>
                    <span class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl font-black text-white shadow" :class="estilo(t.estado).avatar" x-text="t.inicial"></span>
                    <span class="block mt-3 font-extrabold text-slate-800 leading-tight uppercase" x-text="t.nombre"></span>
                    <span class="block text-xs font-semibold text-slate-500 uppercase truncate" x-text="t.apellidos"></span>
                    <span class="mt-2 flex flex-wrap items-center gap-1 text-[11px]">
                        <template x-if="t.turno">
                            <span class="px-1.5 py-0.5 rounded font-bold text-white" :style="`background:${t.turno.color}`" x-text="t.turno.codigo + (t.turno.horario ? ' ' + t.turno.horario : '')"></span>
                        </template>
                        <template x-for="m in t.marcas"><span class="px-1.5 py-0.5 rounded bg-white/80 text-slate-600 font-semibold" x-text="m.hora"></span></template>
                    </span>
                    <span x-show="t.tardanza > 0" class="block mt-1 text-[11px] font-bold text-rose-600" x-text="'Tardanza ' + t.tardanza + ' min'"></span>
                </button>
            </template>
        </div>
        <p x-show="!visibles.length" class="text-center text-slate-400 py-16">
            No hay trabajadores para mostrar. Activa la <strong>asistencia</strong> en <em>Usuarios</em> para que aparezcan aquí.
        </p>
    </main>

    {{-- Modal QR --}}
    <div x-show="modal === 'qr'" x-cloak class="fixed inset-0 z-40 bg-slate-900/70 flex items-center justify-center p-4" @click.self="cerrar()">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm p-6 text-center">
            <p class="text-xs font-bold uppercase text-indigo-600" x-text="qr.accion"></p>
            <h2 class="text-xl font-black text-slate-800 uppercase" x-text="qr.empleado"></h2>
            <div class="my-4 mx-auto w-64 h-64 flex items-center justify-center rounded-2xl bg-slate-50" x-html="qr.svg || '<i class=\'fas fa-spinner fa-spin text-3xl text-slate-300\'></i>'"></div>
            <p class="text-sm text-slate-500">Escanéalo con la cámara de <strong>tu</strong> celular (conectado al Wi-Fi del local).</p>
            <div class="mt-3 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-2 bg-indigo-500 transition-all duration-1000" :style="`width:${qr.restante / qr.total * 100}%`"></div></div>
            <p class="text-xs text-slate-400 mt-1" x-text="'Vence en ' + qr.restante + ' s'"></p>
            <button type="button" @click="cerrar()" class="mt-4 w-full py-3 rounded-xl bg-slate-100 font-bold text-slate-600 hover:bg-slate-200">Cerrar</button>
        </div>
    </div>

    {{-- Modal autorización del administrador --}}
    <div x-show="modal === 'auth'" x-cloak class="fixed inset-0 z-40 bg-slate-900/70 flex items-center justify-center p-4" @click.self="cerrar()">
        <form @submit.prevent="autorizar()" class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="bg-amber-400 px-6 py-4">
                <p class="text-xs font-black uppercase text-amber-900"><i class="fas fa-user-shield"></i> Autorización del administrador</p>
                <h2 class="text-lg font-black text-amber-950 uppercase" x-text="auth.nombre"></h2>
                <p class="text-sm text-amber-900" x-text="auth.mensaje"></p>
            </div>
            <div class="p-6 space-y-3 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <label class="col-span-2">Usuario del administrador
                        <input x-model="auth.usuario" required autocomplete="off" class="block w-full mt-1 rounded-xl border-slate-300"></label>
                    <label class="col-span-2">Clave
                        <input x-model="auth.password" type="password" required autocomplete="new-password" class="block w-full mt-1 rounded-xl border-slate-300"></label>
                    <label>Motivo
                        <select x-model="auth.motivo" required class="block w-full mt-1 rounded-xl border-slate-300">
                            <option value="">Elegir…</option>
                            <template x-for="m in motivos"><option :value="m" x-text="m"></option></template>
                        </select></label>
                    <label>Hora a registrar
                        <input type="time" x-model="auth.hora" class="block w-full mt-1 rounded-xl border-slate-300"></label>
                </div>
                <input x-model="auth.detalle" maxlength="100" placeholder="Detalle (opcional)" class="block w-full rounded-xl border-slate-300">
                <p class="text-xs text-slate-400">Si cambias la hora, queda anotado en el reporte como hora corregida.</p>
                <p class="text-rose-600 font-semibold" x-text="auth.error"></p>
                <div class="flex gap-2 pt-1">
                    <button type="button" @click="cerrar()" class="flex-1 py-3 rounded-xl bg-slate-100 font-bold text-slate-600">Cancelar</button>
                    <button :disabled="enviando" class="flex-1 py-3 rounded-xl bg-indigo-600 text-white font-bold disabled:opacity-50" x-text="enviando ? 'Validando…' : 'Autorizar'"></button>
                </div>
            </div>
        </form>
    </div>

    {{-- Aviso grande del resultado --}}
    <div x-show="aviso.visible" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4 pointer-events-none">
        <div class="pointer-events-auto max-w-xl w-full rounded-3xl shadow-2xl p-8 text-center text-white"
             :class="aviso.ok ? 'bg-emerald-600' : 'bg-rose-600'" @click="aviso.visible = false">
            <i class="fas text-6xl" :class="aviso.ok ? 'fa-circle-check' : 'fa-circle-exclamation'"></i>
            <p class="mt-4 text-2xl font-black leading-snug" x-text="aviso.texto"></p>
        </div>
    </div>

    <script>
        function kiosko(tarjetas, motivos) {
            const CSRF = document.querySelector('meta[name=csrf-token]').content;
            const R = {
                estado: @json(route('asistencia.estado')), lector: @json(route('asistencia.lector')),
                qr: @json(url('asistencia/qr')), autorizar: @json(route('asistencia.autorizar')),
            };
            const ESTILOS = {
                trabajando: { nombre: 'Trabajando', tarjeta: 'bg-emerald-50 border-emerald-300', avatar: 'bg-emerald-500', etiqueta: 'bg-emerald-500 text-white', punto: 'bg-emerald-400' },
                refrigerio: { nombre: 'Refrigerio', tarjeta: 'bg-amber-50 border-amber-300', avatar: 'bg-amber-500', etiqueta: 'bg-amber-400 text-amber-950', punto: 'bg-amber-400' },
                salio: { nombre: 'Salió', tarjeta: 'bg-sky-50 border-sky-200', avatar: 'bg-sky-500', etiqueta: 'bg-sky-500 text-white', punto: 'bg-sky-400' },
                pendiente: { nombre: 'Por llegar', tarjeta: 'bg-white border-dashed border-indigo-200', avatar: 'bg-indigo-400', etiqueta: 'bg-indigo-100 text-indigo-700', punto: 'bg-indigo-300' },
                sin_horario: { nombre: 'Sin horario', tarjeta: 'bg-white border-dashed border-slate-200', avatar: 'bg-slate-400', etiqueta: 'bg-slate-100 text-slate-600', punto: 'bg-slate-300' },
                falta: { nombre: 'Falta', tarjeta: 'bg-rose-50 border-rose-300', avatar: 'bg-rose-500', etiqueta: 'bg-rose-500 text-white', punto: 'bg-rose-400' },
                descanso: { nombre: 'Descanso', tarjeta: 'bg-slate-100 border-slate-200 opacity-70', avatar: 'bg-slate-400', etiqueta: 'bg-slate-500 text-white', punto: 'bg-slate-400' },
                leyenda: { nombre: 'Licencia', tarjeta: 'bg-teal-50 border-teal-200 opacity-80', avatar: 'bg-teal-500', etiqueta: 'bg-teal-500 text-white', punto: 'bg-teal-400' },
            };
            const ORDEN = ['trabajando', 'refrigerio', 'pendiente', 'sin_horario', 'falta', 'salio', 'descanso', 'leyenda'];
            const json = (url, opt = {}) => fetch(url, { ...opt, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF } })
                .then(async r => { if (r.status === 419 || r.status === 401) { location.reload(); return {}; } const d = await r.json(); if (r.status === 422) d.message = Object.values(d.errors)[0][0]; return d; });

            return {
                tarjetas, motivos, reloj: '', fecha: '', dni: '', buscar: '', filtro: '', modal: null, enviando: false,
                qr: { svg: '', empleado: '', accion: '', restante: 0, total: 90, id: null, marcas: 0 },
                auth: {}, aviso: { visible: false, ok: true, texto: '' }, _timerQr: null, _timerAviso: null,

                iniciar() {
                    this.tic(); setInterval(() => this.tic(), 1000);
                    setInterval(() => this.refrescar(), 5000);
                    this.$nextTick(() => this.$refs.dni.focus());
                },
                tic() {
                    const d = new Date();
                    this.reloj = d.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
                    this.fecha = d.toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                },
                estilo(e) { return ESTILOS[e] || ESTILOS.pendiente; },
                get resumen() {
                    return ORDEN.map(e => ({ estado: e, nombre: ESTILOS[e].nombre, punto: ESTILOS[e].punto, n: this.tarjetas.filter(t => t.estado === e).length }))
                        .filter(r => r.n > 0);
                },
                get visibles() {
                    const q = this.buscar.trim().toLowerCase();
                    return this.tarjetas.filter(t => (!this.filtro || t.estado === this.filtro) && (!q || (t.nombre + ' ' + t.apellidos).toLowerCase().includes(q)))
                        .sort((a, b) => ORDEN.indexOf(a.estado) - ORDEN.indexOf(b.estado) || a.nombre.localeCompare(b.nombre));
                },

                async refrescar() {
                    try {
                        const d = await json(R.estado);
                        if (!d.tarjetas) return;
                        // Si el trabajador del QR abierto marcó desde su celular, se cierra el QR y se avisa
                        if (this.modal === 'qr' && this.qr.id) {
                            const t = d.tarjetas.find(x => x.id === this.qr.id);
                            if (t && t.marcas.length > this.qr.marcas) {
                                this.cerrar();
                                this.mostrar(true, `✔ ${t.nombre}: ${t.marcas[t.marcas.length - 1].nombre} registrada a las ${t.marcas[t.marcas.length - 1].hora}`);
                            }
                        }
                        this.tarjetas = d.tarjetas;
                    } catch (e) { /* sin conexión: se reintenta en 5 s */ }
                },

                async marcarDni() {
                    const dni = this.dni.trim();
                    this.dni = '';
                    if (dni.length < 6 || this.enviando) return;
                    this.enviando = true;
                    const d = await json(R.lector, { method: 'POST', body: JSON.stringify({ dni }) }).catch(() => ({ message: 'Sin conexión con el servidor.' }));
                    this.enviando = false;
                    if (d.require_auth) return this.pedirAutorizacion(d);
                    this.mostrar(!!d.success, d.message || 'No se pudo registrar.');
                    if (d.success) this.refrescar();
                },

                async abrirQr(t) {
                    if (t.estado === 'salio') return this.mostrar(false, `${t.nombre}: ya completaste tus marcaciones de hoy.`);
                    this.qr = { svg: '', empleado: (t.nombre + ' ' + t.apellidos).trim(), accion: t.siguiente, restante: 90, total: 90, id: t.id, marcas: t.marcas.length };
                    this.modal = 'qr';
                    const d = await json(R.qr + '/' + t.id).catch(() => ({ message: 'Sin conexión con el servidor.' }));
                    if (d.require_auth) { this.modal = null; return this.pedirAutorizacion(d); }
                    if (!d.success) { this.modal = null; return this.mostrar(false, d.message); }
                    this.qr.svg = d.svg; this.qr.accion = d.accion; this.qr.total = this.qr.restante = d.segundos;
                    clearInterval(this._timerQr);
                    this._timerQr = setInterval(() => { if (--this.qr.restante <= 0) this.cerrar(); }, 1000);
                },

                pedirAutorizacion(d) {
                    const ahora = new Date();
                    this.auth = { emp_id: d.empleado.id, nombre: d.empleado.nombre + ' · ' + d.accion, mensaje: d.message, usuario: '', password: '',
                        motivo: '', detalle: '', hora: ahora.toTimeString().slice(0, 5), error: '' };
                    this.modal = 'auth';
                    this.sonido(false);
                },
                async autorizar() {
                    this.enviando = true; this.auth.error = '';
                    const motivo = this.auth.motivo + (this.auth.detalle.trim() ? ' - ' + this.auth.detalle.trim() : '');
                    const d = await json(R.autorizar, { method: 'POST', body: JSON.stringify({
                        emp_id: this.auth.emp_id, usuario: this.auth.usuario, password: this.auth.password, motivo, hora: this.auth.hora,
                    }) }).catch(() => ({ message: 'Sin conexión con el servidor.' }));
                    this.enviando = false;
                    if (!d.success) { this.auth.error = d.message; return; }
                    this.cerrar();
                    this.mostrar(true, d.message);
                    this.refrescar();
                },

                cerrar() {
                    this.modal = null; clearInterval(this._timerQr);
                    this.$nextTick(() => this.$refs.dni.focus());
                },
                mostrar(ok, texto) {
                    this.aviso = { visible: true, ok, texto };
                    this.sonido(ok);
                    clearTimeout(this._timerAviso);
                    this._timerAviso = setTimeout(() => { this.aviso.visible = false; }, ok ? 3500 : 5000);
                    this.$nextTick(() => this.$refs.dni.focus());
                },
                // El lector de barras escribe aunque el cursor esté fuera del campo
                tecla(e) {
                    if (this.modal || e.target.closest('input, select, textarea')) return;
                    if (/^[0-9]$/.test(e.key)) { this.$refs.dni.focus(); }
                },
                pantallaCompleta() {
                    if (!document.fullscreenElement) document.documentElement.requestFullscreen?.(); else document.exitFullscreen?.();
                },
                sonido(ok) {
                    try {
                        this._audio = this._audio || new (window.AudioContext || window.webkitAudioContext)();
                        const o = this._audio.createOscillator(), g = this._audio.createGain();
                        o.frequency.value = ok ? 880 : 220; g.gain.value = 0.08;
                        o.connect(g).connect(this._audio.destination); o.start(); o.stop(this._audio.currentTime + (ok ? 0.15 : 0.4));
                    } catch (e) {}
                },
            };
        }
    </script>
</body>
</html>
