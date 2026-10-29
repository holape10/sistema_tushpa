<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#c2410c">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>Reclamo · guía {{ $e->codigo }} · {{ $nombre }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased" x-data="reclamoWeb()">
    @php $campo = 'mt-1 w-full h-11 rounded-xl border-slate-300 text-sm'; @endphp
    <header class="bg-gradient-to-br from-orange-600 to-rose-700 text-white rounded-b-[2rem] shadow-lg">
        <div class="max-w-lg mx-auto px-5 pt-7 pb-8">
            <a href="{{ route('rastreo.ver', $e->codigo) }}" class="text-sm text-orange-100">← Volver al rastreo</a>
            <p class="font-black text-xl mt-1">Reclamo de la guía <span class="font-mono">{{ $e->codigo }}</span></p>
            <p class="text-sm text-orange-100">{{ $nombre }} revisará tu caso y te responderá aquí mismo.</p>
        </div>
    </header>

    <main class="max-w-lg mx-auto px-4 -mt-4 pb-12 space-y-4">
        <template x-if="numero">
            <div class="bg-white rounded-2xl shadow-sm p-6 text-center">
                <p class="text-5xl">📨</p>
                <p class="mt-2 font-black text-lg" x-text="'Reclamo N° ' + numero + ' registrado'"></p>
                <p class="text-sm text-slate-500 mt-1">Para ver la respuesta, vuelve a esta página y consulta con tu DNI o celular.</p>
            </div>
        </template>

        <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
            <label class="block text-xs font-semibold text-slate-500">Tu DNI o celular (el del remitente o el de quien recibe)
                <input x-model="f.verificacion" inputmode="numeric" maxlength="15" class="{{ $campo }}"></label>
            <button type="button" @click="consultar()" :disabled="ocupado" class="w-full h-11 rounded-xl bg-slate-100 font-bold text-sm">Ver mis reclamos de esta guía</button>
            <template x-for="r in lista" :key="r.numero">
                <div class="rounded-xl bg-slate-50 p-3 text-sm">
                    <p class="font-bold" x-text="'N° ' + r.numero + ' · ' + r.tipo + ' · ' + r.estado"></p>
                    <p class="text-xs text-slate-400" x-text="r.fecha"></p>
                    <p x-show="r.respuesta" class="mt-1" x-text="'Respuesta: ' + r.respuesta"></p>
                </div>
            </template>
        </div>

        <form x-show="!numero" @submit.prevent="enviar()" class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
            <p class="font-black"><i class="fas fa-comment-dots text-orange-500"></i> Presentar un reclamo</p>
            <label class="block text-xs font-semibold text-slate-500">¿Qué pasó?
                <select x-model="f.tipo" required class="{{ $campo }}">
                    @foreach ($tipos as $clave => $texto)<option value="{{ $clave }}">{{ $texto }}</option>@endforeach
                </select></label>
            <label class="block text-xs font-semibold text-slate-500">Cuéntanos con detalle
                <textarea x-model="f.detalle" required minlength="10" maxlength="2000" rows="4" class="mt-1 w-full rounded-xl border-slate-300 text-sm"></textarea></label>
            <label class="block text-xs font-semibold text-slate-500">¿Qué solución esperas? (opcional)<input x-model="f.pedido" maxlength="255" class="{{ $campo }}"></label>
            <label class="block text-xs font-semibold text-slate-500">Tu nombre<input x-model="f.nombre" required maxlength="150" class="{{ $campo }}"></label>
            <label class="block text-xs font-semibold text-slate-500">Correo (opcional)<input type="email" x-model="f.correo" maxlength="120" class="{{ $campo }}"></label>
            <p x-show="error" x-text="error" class="rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700"></p>
            <button :disabled="ocupado" class="w-full h-12 rounded-xl bg-orange-600 text-white font-black disabled:opacity-50" x-text="ocupado ? 'Enviando…' : 'Enviar reclamo'"></button>
            <p class="text-xs text-slate-400">Este canal es para reclamos sobre tu envío. También puedes usar el Libro de Reclamaciones de la empresa.</p>
        </form>
    </main>

    <script>
        function reclamoWeb() {
            const token = document.querySelector('meta[name=csrf-token]').content;
            const post = data => fetch(@json(route('rastreo.reclamar', $e->codigo)), { method: 'POST', body: JSON.stringify(data),
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token } }).then(async r => ({ status: r.status, ...(await r.json().catch(() => ({}))) }));
            return {
                ocupado: false, error: '', numero: null, lista: [],
                f: { verificacion: '', tipo: 'DANO', detalle: '', pedido: '', nombre: '', correo: '' },
                problema(d) { return d.errors ? Object.values(d.errors).flat()[0] : (d.mensaje || (d.status === 429 ? 'Muchos intentos: espera unos minutos.' : 'No se pudo.')); },
                async consultar() {
                    this.ocupado = true; this.error = '';
                    try { const d = await post({ verificacion: this.f.verificacion, consultar: true }); if (!d.ok) { alert(this.problema(d)); return; }
                        this.lista = d.reclamos; if (!d.reclamos.length) alert('No hay reclamos de esta guía.'); } finally { this.ocupado = false; }
                },
                async enviar() {
                    this.ocupado = true; this.error = '';
                    try { const d = await post(this.f); if (!d.ok) { this.error = this.problema(d); return; } this.numero = d.numero; window.scrollTo({ top: 0, behavior: 'smooth' }); }
                    catch (e) { this.error = 'Sin conexión.'; } finally { this.ocupado = false; }
                },
            };
        }
    </script>
</body>
</html>
