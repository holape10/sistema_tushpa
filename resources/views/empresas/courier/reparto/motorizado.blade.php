@php
    $pendientes = $envios->where('estado', 'EN_REPARTO');
    $entregados = $envios->where('estado', 'ENTREGADO');
    $datos = $pendientes->values()->map(fn ($e) => ['id' => $e->env_id, 'codigo' => $e->codigo, 'nombre' => $e->des_nombre, 'doc' => $e->des_doc,
        'cobrar' => \App\Support\CourierReparto::cobraAlEntregar($e) ? (float) $e->total : 0]);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#c2410c">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>Mis entregas · {{ $m->nombre }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased" x-data="rutaMotorizado()">
    <header class="bg-gradient-to-br from-orange-600 to-rose-700 text-white rounded-b-[2rem] shadow-lg">
        <div class="max-w-lg mx-auto px-5 pt-6 pb-7">
            <p class="text-sm text-orange-100">{{ $negocio->nombre_comercial ?? 'Courier' }}</p>
            <p class="text-2xl font-black">Hola, {{ $m->nombre }}</p>
            <p class="text-sm text-orange-100">{{ $pendientes->count() }} por entregar · {{ $entregados->count() }} entregado(s) hoy</p>
        </div>
    </header>

    <main class="max-w-lg mx-auto px-4 -mt-4 pb-10 space-y-3">
        @forelse ($pendientes as $e)
            @php
                $tel = preg_replace('/\D/', '', (string) $e->des_telefono);
                $cobrar = \App\Support\CourierReparto::cobraAlEntregar($e);
            @endphp
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-mono font-black tracking-wider">{{ $e->codigo }} <span class="font-sans text-xs text-slate-400">· {{ $e->piezas }} bulto(s)</span></p>
                        <p class="font-bold text-lg leading-tight">{{ $e->des_nombre }}</p>
                    </div>
                    @if ($cobrar)<span class="rounded-xl bg-rose-100 text-rose-700 px-2.5 py-1 text-sm font-black whitespace-nowrap">Cobrar S/ {{ number_format($e->total, 2) }}</span>@endif
                </div>
                <p class="mt-2 text-sm"><i class="fas fa-location-dot text-orange-500"></i> {{ $e->direccion_entrega }}</p>
                @if ($e->observacion)<p class="text-xs text-amber-700 mt-1">{{ $e->observacion }}</p>@endif
                <div class="mt-3 grid grid-cols-3 gap-2 text-sm font-bold">
                    <a href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($e->direccion_entrega) }}" target="_blank" class="h-11 rounded-xl bg-slate-100 flex items-center justify-center gap-1"><i class="fas fa-map-location-dot"></i> Mapa</a>
                    @if ($tel)
                        <a href="tel:{{ $tel }}" class="h-11 rounded-xl bg-slate-100 flex items-center justify-center gap-1"><i class="fas fa-phone"></i> Llamar</a>
                        <a href="https://wa.me/{{ strlen($tel) === 9 ? '51'.$tel : $tel }}?text={{ rawurlencode('Hola, soy '.$m->nombre.' de '.($negocio->nombre_comercial ?? 'la agencia').'. Estoy llevando tu envío '.$e->codigo.'.') }}"
                           target="_blank" class="h-11 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center gap-1"><i class="fab fa-whatsapp"></i> Escribir</a>
                    @endif
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <button type="button" @click="abrir({{ $e->env_id }})" class="h-12 rounded-xl bg-emerald-600 text-white font-black"><i class="fas fa-check"></i> Entregado</button>
                    <button type="button" @click="noEntregado({{ $e->env_id }})" class="h-12 rounded-xl bg-white ring-1 ring-rose-200 text-rose-600 font-bold">No se pudo</button>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl shadow-sm p-8 text-center">
                <p class="text-5xl">🏁</p>
                <p class="mt-2 font-black text-lg">No tienes entregas pendientes</p>
                <p class="text-sm text-slate-500">Cuando la agencia te asigne envíos aparecerán aquí. Desliza hacia abajo para actualizar.</p>
            </div>
        @endforelse

        @if ($entregados->isNotEmpty())
            <div class="bg-white rounded-2xl shadow-sm p-4 text-sm">
                <p class="text-xs font-semibold uppercase text-slate-400 mb-2">Entregados hoy</p>
                @foreach ($entregados as $e)
                    <p class="py-1 flex justify-between"><span><i class="fas fa-circle-check text-emerald-500"></i> {{ $e->codigo }} · {{ $e->entregado_a }}</span>
                        @if ($e->cobrado_rep)<b>{{ $e->medio_rep }} S/ {{ number_format($e->cobrado_rep, 2) }}</b>@endif</p>
                @endforeach
            </div>
        @endif
    </main>

    {{-- Entregar --}}
    <div x-show="actual" x-cloak class="fixed inset-0 z-50 bg-slate-900/60 flex items-end sm:items-center justify-center">
        <form @submit.prevent="entregar()" class="bg-white w-full max-w-lg rounded-t-3xl sm:rounded-3xl p-5 space-y-3 max-h-[95vh] overflow-y-auto">
            <p class="font-black text-lg">Entregar <span class="font-mono" x-text="actual?.codigo"></span></p>
            <label class="block text-xs font-semibold text-slate-500">Quién recibe
                <input x-model="f.recibe_nombre" required class="mt-1 w-full h-11 rounded-xl border-slate-300 uppercase"></label>
            <label class="block text-xs font-semibold text-slate-500">DNI de quien recibe
                <input x-model="f.recibe_doc" required minlength="8" maxlength="15" inputmode="numeric" class="mt-1 w-full h-11 rounded-xl border-slate-300"></label>
            <template x-if="actual?.cobrar > 0">
                <div class="rounded-xl bg-rose-50 p-3 space-y-2">
                    <p class="font-black text-rose-700" x-text="'Cobra S/ ' + actual.cobrar.toFixed(2)"></p>
                    <div class="grid grid-cols-4 gap-1">
                        @foreach ($medios as $medio)
                            <label class="text-center text-xs font-bold rounded-lg ring-2 py-2 cursor-pointer" :class="f.medio === '{{ $medio }}' ? 'ring-rose-500 bg-white' : 'ring-transparent bg-white/60'">
                                <input type="radio" value="{{ $medio }}" x-model="f.medio" class="sr-only">{{ $medio }}</label>
                        @endforeach
                    </div>
                    <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" x-model="f.cobre" class="rounded"> Ya cobré el monto completo</label>
                </div>
            </template>
            <div>
                <p class="text-xs font-semibold text-slate-500 mb-1">Foto de la entrega</p>
                <label class="flex items-center gap-3 rounded-xl ring-1 ring-slate-200 p-3 cursor-pointer">
                    <i class="fas fa-camera text-xl text-orange-600"></i><span class="text-sm" x-text="foto ? '✔ Foto lista (tocar para cambiar)' : 'Tomar foto'"></span>
                    <input type="file" accept="image/*" capture="environment" class="sr-only" @change="foto = $event.target.files[0] || null">
                </label>
            </div>
            <div>
                <div class="flex justify-between items-center mb-1"><p class="text-xs font-semibold text-slate-500">Firma de quien recibe</p>
                    <button type="button" @click="limpiarFirma()" class="text-xs text-slate-500 underline">Borrar</button></div>
                <canvas x-ref="firma" width="600" height="220" class="w-full h-32 rounded-xl ring-1 ring-slate-300 bg-slate-50 touch-none"
                        @pointerdown="trazar($event, true)" @pointermove="trazar($event)" @pointerup="dibujando = false" @pointerleave="dibujando = false"></canvas>
            </div>
            <p x-show="error" x-text="error" class="rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700"></p>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" @click="actual = null" class="h-12 rounded-xl bg-slate-100 font-bold">Cancelar</button>
                <button :disabled="ocupado" class="h-12 rounded-xl bg-emerald-600 text-white font-black disabled:opacity-50" x-text="ocupado ? 'Enviando…' : '✓ Confirmar'"></button>
            </div>
        </form>
    </div>

    <script>
        function rutaMotorizado() {
            const ENVIOS = @json($datos);
            const token = document.querySelector('meta[name=csrf-token]').content;
            const base = @json(url('courier/repartidor/'.$token));
            return {
                actual: null, ocupado: false, error: '', foto: null, firmado: false, dibujando: false,
                f: { recibe_nombre: '', recibe_doc: '', medio: 'EFECTIVO', cobre: false },
                abrir(id) {
                    this.actual = ENVIOS.find(e => e.id === id); this.error = ''; this.foto = null;
                    this.f = { recibe_nombre: this.actual.nombre, recibe_doc: this.actual.doc || '', medio: 'EFECTIVO', cobre: false };
                    this.$nextTick(() => this.limpiarFirma());
                },
                limpiarFirma() { const c = this.$refs.firma; c.getContext('2d').clearRect(0, 0, c.width, c.height); this.firmado = false; },
                trazar(ev, inicio = false) {
                    const c = this.$refs.firma, r = c.getBoundingClientRect(), ctx = c.getContext('2d');
                    const x = (ev.clientX - r.left) * c.width / r.width, y = (ev.clientY - r.top) * c.height / r.height;
                    if (inicio) { this.dibujando = true; ctx.beginPath(); ctx.moveTo(x, y); return; }
                    if (!this.dibujando) return;
                    ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.strokeStyle = '#0f172a'; ctx.lineTo(x, y); ctx.stroke(); this.firmado = true;
                },
                async entregar() {
                    if (!this.foto && !this.firmado) { this.error = 'Toma una foto o pide la firma.'; return; }
                    if (this.actual.cobrar > 0 && !this.f.cobre) { this.error = 'Marca que ya cobraste S/ ' + this.actual.cobrar.toFixed(2) + '.'; return; }
                    this.ocupado = true; this.error = '';
                    const datos = new FormData();
                    datos.append('recibe_nombre', this.f.recibe_nombre); datos.append('recibe_doc', this.f.recibe_doc);
                    if (this.actual.cobrar > 0) { datos.append('cobrado', this.actual.cobrar); datos.append('medio', this.f.medio); }
                    if (this.foto) datos.append('foto', this.foto);
                    if (this.firmado) datos.append('firma', this.$refs.firma.toDataURL('image/png'));
                    try {
                        const d = await fetch(base + '/' + this.actual.id + '/entregar', { method: 'POST', body: datos, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token } }).then(r => r.json());
                        if (d.errors) { this.error = Object.values(d.errors).flat()[0]; return; }
                        if (!d.ok) { this.error = d.mensaje; return; }
                        location.reload();
                    } catch (e) { this.error = 'Sin conexión. Intenta de nuevo.'; } finally { this.ocupado = false; }
                },
                async noEntregado(id) {
                    const motivos = @json($motivos);
                    const n = prompt('¿Por qué no se pudo entregar?\n' + motivos.map((m, i) => (i + 1) + '. ' + m).join('\n') + '\n\nEscribe el número o el motivo:');
                    if (!n) return;
                    const motivo = motivos[Number(n) - 1] || n;
                    const d = await fetch(base + '/' + id + '/no-entregado', { method: 'POST', body: JSON.stringify({ motivo }),
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token } }).then(r => r.json());
                    alert(d.mensaje || 'Error'); if (d.ok) location.reload();
                },
            };
        }
    </script>
</body>
</html>
