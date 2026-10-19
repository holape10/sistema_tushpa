@extends('layouts.app')
@section('title', 'Cobranza de hoy')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php $in = 'block w-full rounded-xl border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500'; @endphp

    <div x-data="cobranza()" class="max-w-3xl mx-auto space-y-3 pb-24">
        {{-- Resumen del día --}}
        <div class="rounded-3xl p-5 text-white bg-gradient-to-br from-emerald-600 to-teal-800 shadow-lg">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-widest text-emerald-100/80"><i class="fas fa-motorcycle"></i> Cobranza · {{ now()->locale('es')->isoFormat('dddd D [de] MMMM') }}</p>
                    <p class="mt-1 text-sm text-emerald-100">Cobrado hoy</p>
                    <p class="text-4xl font-black" x-text="soles(r.cobrado)"></p>
                </div>
                @if ($gestiona)
                    <a href="{{ route('prestamos.index') }}" class="px-3 py-2 rounded-xl bg-white/15 text-sm font-bold"><i class="fas fa-chart-line"></i> Panel</a>
                @endif
            </div>
            <div class="mt-3 h-3 rounded-full bg-white/20"><div class="h-3 rounded-full bg-gradient-to-r from-amber-300 to-lime-300 transition-all" :style="'width:' + avance + '%'"></div></div>
            <div class="mt-2 grid grid-cols-3 gap-2 text-center text-xs">
                <div class="rounded-xl bg-white/10 py-1.5"><b class="block text-base" x-text="soles(r.pendiente)"></b>falta cobrar</div>
                <div class="rounded-xl bg-white/10 py-1.5"><b class="block text-base" x-text="r.pagaron + ' / ' + r.tocan"></b>pagaron</div>
                <div class="rounded-xl bg-white/10 py-1.5"><b class="block text-base" x-text="soles(r.efectivo)"></b>en efectivo</div>
            </div>
        </div>

        <div class="sticky top-16 z-10 bg-gray-100/95 backdrop-blur py-2 space-y-2">
            <div class="flex gap-2">
                <input x-model="q" placeholder="🔎 Buscar cliente o dirección" class="{{ $in }} flex-1">
                @if ($gestiona && $cobradores->isNotEmpty())
                    <select onchange="location.href = '{{ route('prestamos.cobranza') }}' + (this.value ? '?cobrador=' + this.value : '')" class="rounded-xl border-gray-300 text-sm max-w-[40%]">
                        <option value="">Todos los cobradores</option>
                        @foreach ($cobradores as $u)<option value="{{ $u->IdUsuario }}" @selected($cobrador === (int) $u->IdUsuario)>{{ $u->nombre }}</option>@endforeach
                    </select>
                @endif
            </div>
            <div class="flex gap-1.5 overflow-x-auto text-sm font-bold">
                <template x-for="t in [['cobrar', 'Por cobrar'], ['pagaron', 'Ya pagaron'], ['todos', 'Todos']]" :key="t[0]">
                    <button type="button" @click="tab = t[0]" class="px-4 py-2 rounded-xl whitespace-nowrap" :class="tab === t[0] ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600'"
                            x-text="t[1] + ' (' + contar(t[0]) + ')'"></button>
                </template>
                <template x-for="z in zonas" :key="z">
                    <button type="button" @click="zona = zona === z ? '' : z" class="px-3 py-2 rounded-xl whitespace-nowrap" :class="zona === z ? 'bg-gray-800 text-white' : 'bg-white text-gray-500'" x-text="'📍 ' + z"></button>
                </template>
            </div>
        </div>

        <template x-for="f in visibles" :key="f.pre_id">
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden" :class="f.pagado_hoy > 0 ? 'opacity-80' : ''">
                <div class="p-4 flex gap-3">
                    <div class="w-12 h-12 shrink-0 rounded-2xl flex items-center justify-center text-white text-xl font-black"
                         :class="f.pagado_hoy > 0 ? 'bg-emerald-500' : ({ MOROSO: 'bg-rose-500', ATRASADO: 'bg-amber-400', AL_DIA: 'bg-sky-500' })[f.estado] || 'bg-gray-400'"
                         x-text="f.pagado_hoy > 0 ? '✓' : f.cliente.slice(0, 1)"></div>
                    <div class="flex-1 min-w-0">
                        <a :href="'{{ url('prestamos') }}/' + f.pre_id" class="font-extrabold text-gray-800 leading-tight block" x-text="f.cliente"></a>
                        <p class="text-xs text-gray-500 truncate" x-text="[f.negocio, f.direccion].filter(Boolean).join(' · ') || 'Sin dirección'"></p>
                        <p x-show="f.referencia" class="text-[11px] text-gray-400 truncate" x-text="'Ref: ' + f.referencia"></p>
                        <div class="mt-1 flex flex-wrap gap-1 text-[11px] font-bold">
                            <span x-show="f.vencidas" class="px-1.5 rounded bg-rose-100 text-rose-700" x-text="f.vencidas + ' atrasada(s)'"></span>
                            <span class="px-1.5 rounded bg-gray-100 text-gray-600" x-text="f.pagadas + '/' + f.cuotas + ' cuotas'"></span>
                            <span class="px-1.5 rounded bg-gray-100 text-gray-600" x-text="'saldo ' + soles(f.saldo)"></span>
                            <span x-show="f.gestion" class="px-1.5 rounded bg-amber-100 text-amber-800" x-text="f.gestion"></span>
                            @if ($gestiona)<span x-show="f.cobrador" class="px-1.5 rounded bg-indigo-50 text-indigo-700" x-text="f.cobrador"></span>@endif
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-[10px] uppercase text-gray-400" x-text="f.pagado_hoy > 0 ? 'Pagó hoy' : 'Cobrar'"></p>
                        <p class="text-2xl font-black" :class="f.pagado_hoy > 0 ? 'text-emerald-600' : (f.cobrar > 0 ? 'text-rose-600' : 'text-gray-300')" x-text="soles(f.pagado_hoy > 0 ? f.pagado_hoy : f.cobrar || f.cuota)"></p>
                    </div>
                </div>
                <div class="grid grid-cols-5 border-t text-xs font-bold text-gray-600">
                    <button type="button" @click="abrirCobro(f)" x-show="f.estado !== 'PAGADO'" class="col-span-2 py-3 bg-emerald-600 text-white"><i class="fas fa-coins"></i> Cobrar</button>
                    <span x-show="f.estado === 'PAGADO'" class="col-span-2 py-3 bg-sky-50 text-sky-700 text-center">✔ Préstamo pagado</span>
                    <button type="button" @click="gestion = { f, resultado: 'NO_PAGO', promesa: '', nota: '' }" x-show="f.estado !== 'PAGADO'" class="py-3 border-l"><i class="fas fa-user-clock text-amber-500"></i><span class="block">No pagó</span></button>
                    <span x-show="f.estado === 'PAGADO'" class="border-l"></span>
                    <a :href="f.telefono ? wa(f) : null" target="_blank" class="py-3 border-l text-center" :class="!f.telefono && 'opacity-30 pointer-events-none'"><i class="fab fa-whatsapp text-green-600"></i><span class="block">WhatsApp</span></a>
                    <a :href="mapa(f)" target="_blank" class="py-3 border-l text-center" :class="!mapa(f) && 'opacity-30 pointer-events-none'"><i class="fas fa-location-dot text-rose-500"></i><span class="block">Ir</span></a>
                </div>
            </div>
        </template>
        <div x-show="!visibles.length" class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">
            <p class="text-4xl">🎉</p><p x-text="tab === 'cobrar' ? '¡Terminaste! No queda nadie por cobrar.' : 'No hay clientes aquí.'"></p>
        </div>

        {{-- Cobrar --}}
        <div x-show="cobro" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center sm:p-4">
            <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-xl p-5 space-y-3" @click.outside="cobro = null">
                <template x-if="cobro">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between"><div><p class="font-black text-lg" x-text="cobro.f.cliente"></p>
                            <p class="text-xs text-gray-500" x-text="'Cuota ' + soles(cobro.f.cuota) + ' · saldo ' + soles(cobro.f.saldo)"></p></div>
                            <button type="button" @click="cobro = null" class="text-gray-400 text-xl">✕</button></div>
                        <input type="number" step="0.10" min="0" x-model.number="cobro.monto" class="block w-full rounded-2xl border-2 border-emerald-500 text-4xl font-black text-center py-4 text-emerald-700">
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="m in sugeridos(cobro.f)" :key="m">
                                <button type="button" @click="cobro.monto = m" class="px-3 py-2 rounded-xl bg-emerald-50 text-emerald-800 text-sm font-bold" x-text="(m === cobro.f.saldo ? 'Todo ' : '') + soles(m)"></button>
                            </template>
                        </div>
                        @if ((float) $cfg->mora_diaria > 0)
                            <label class="block text-xs font-semibold text-gray-500">Mora S/<input type="number" step="0.10" min="0" x-model.number="cobro.mora" class="{{ $in }} mt-1 text-right"></label>
                        @endif
                        <div class="grid grid-cols-4 gap-1.5">
                            @foreach (\App\Support\Prestamos::MEDIOS as $m)
                                <button type="button" @click="cobro.medio = '{{ $m }}'" class="py-2.5 rounded-xl text-xs font-bold border-2" :class="cobro.medio === '{{ $m }}' ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-gray-200 text-gray-600'">{{ $m }}</button>
                            @endforeach
                        </div>
                        <input x-show="cobro.medio !== 'EFECTIVO'" x-model="cobro.operacion" placeholder="N° de operación *" maxlength="30" class="{{ $in }} uppercase">
                        <button type="button" @click="pagar()" :disabled="ocupado || !(cobro.monto > 0 || cobro.mora > 0)" class="w-full py-4 rounded-2xl bg-emerald-600 text-white text-xl font-black disabled:opacity-40"
                                x-text="ocupado ? 'Guardando…' : 'Cobrar ' + soles((Number(cobro.monto) || 0) + (Number(cobro.mora) || 0))"></button>
                    </div>
                </template>
            </div>
        </div>
        {{-- Hecho --}}
        <div x-show="hecho" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-sm rounded-3xl shadow-xl p-6 text-center space-y-3">
                <p class="text-5xl">✅</p>
                <p class="text-lg font-black" x-text="hecho?.mensaje"></p>
                <a x-show="hecho?.whatsapp" :href="hecho?.whatsapp" target="_blank" class="block py-3 rounded-xl bg-green-600 text-white font-bold"><i class="fab fa-whatsapp"></i> Enviarle su comprobante</a>
                <button type="button" @click="hecho = null" class="w-full py-3 rounded-xl bg-emerald-600 text-white font-bold">Siguiente cliente</button>
            </div>
        </div>
        {{-- No pagó --}}
        <div x-show="gestion" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center sm:p-4">
            <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-xl p-5 space-y-3" @click.outside="gestion = null">
                <template x-if="gestion">
                    <div class="space-y-3">
                        <p class="font-black text-lg" x-text="gestion.f.cliente"></p>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (\App\Support\Prestamos::RESULTADOS_GESTION as $k => $v)
                                <button type="button" @click="gestion.resultado = '{{ $k }}'" class="py-3 rounded-xl text-sm font-bold border-2" :class="gestion.resultado === '{{ $k }}' ? 'border-amber-500 bg-amber-50' : 'border-gray-200'">{{ $v }}</button>
                            @endforeach
                        </div>
                        <label x-show="gestion.resultado === 'PROMESA'" class="block text-xs font-semibold text-gray-500">¿Cuándo paga?<input type="date" x-model="gestion.promesa" min="{{ now()->toDateString() }}" class="{{ $in }} mt-1"></label>
                        <input x-model="gestion.nota" placeholder="Nota (opcional)" maxlength="200" class="{{ $in }}">
                        <button type="button" @click="guardarGestion()" :disabled="ocupado" class="w-full py-3 rounded-2xl bg-amber-500 text-white font-black">Anotar</button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
        function cobranza() {
            const CSRF = '{{ csrf_token() }}';
            const DATOS = '{{ route('prestamos.cobranza.datos') }}' + location.search;
            const NEGOCIO = @js($negocio);
            const post = (url, data) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data) })
                .then(async r => { const j = await r.json().catch(() => ({})); return r.status === 422 ? { ok: false, mensaje: Object.values(j.errors || {}).flat()[0] } : j; })
                .catch(() => ({ ok: false, mensaje: 'Sin conexión: intenta otra vez.' }));
            return {
                filas: @js($filas), r: @js($resumen), q: '', tab: 'cobrar', zona: '', cobro: null, gestion: null, hecho: null, ocupado: false,
                soles: n => 'S/ ' + (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                get avance() { const t = this.r.cobrado + this.r.pendiente; return t > 0 ? Math.min(100, Math.round(this.r.cobrado / t * 100)) : 0; },
                get zonas() { return [...new Set(this.filas.map(f => f.zona).filter(Boolean))].sort(); },
                enTab(f, t) { return t === 'todos' || (t === 'pagaron' ? f.pagado_hoy > 0 : (f.pagado_hoy <= 0 && f.toca_hoy)); },
                contar(t) { return this.filas.filter(f => this.enTab(f, t) && (!this.zona || f.zona === this.zona)).length; },
                get visibles() {
                    const q = this.q.trim().toLowerCase();
                    return this.filas.filter(f => this.enTab(f, this.tab) && (!this.zona || f.zona === this.zona)
                        && (!q || [f.cliente, f.dni, f.direccion, f.negocio, f.referencia].join(' ').toLowerCase().includes(q)));
                },
                sugeridos(f) { return [...new Set([f.cuota, f.cobrar, Math.round(f.cuota * 2 * 100) / 100, f.saldo].filter(m => m > 0 && m <= f.saldo))]; },
                wa(f) {
                    let n = String(f.telefono || '').replace(/\D/g, ''); if (n.length === 9) n = '51' + n;
                    return 'https://wa.me/' + n + '?text=' + encodeURIComponent('Hola ' + f.cliente + ', le saludamos de ' + NEGOCIO + '. Hoy le toca pagar ' + this.soles(f.cobrar || f.cuota) + '. ¡Gracias!');
                },
                mapa(f) { return f.lat ? 'https://www.google.com/maps/search/?api=1&query=' + f.lat + ',' + f.lng : (f.direccion ? 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(f.direccion) : null); },
                abrirCobro(f) { this.cobro = { f, monto: f.cobrar > 0 ? f.cobrar : Math.min(f.cuota, f.saldo), mora: f.mora || 0, medio: 'EFECTIVO', operacion: '' }; },
                async recargar() { const d = await fetch(DATOS, { headers: { Accept: 'application/json' } }).then(x => x.json()).catch(() => null); if (d) { this.filas = d.filas; this.r = d.resumen; } },
                async pagar() {
                    this.ocupado = true;
                    const pos = await new Promise(ok => navigator.geolocation ? navigator.geolocation.getCurrentPosition(p => ok(p.coords), () => ok(null), { timeout: 2500 }) : ok(null));
                    const c = this.cobro;
                    const r = await post('{{ url('prestamos') }}/' + c.f.pre_id + '/pagar', { monto: c.monto || 0, mora: c.mora || 0, medio: c.medio, operacion: c.operacion, lat: pos?.latitude ?? null, lng: pos?.longitude ?? null });
                    this.ocupado = false;
                    if (!r.ok) return tushpaAviso(r.mensaje || 'No se pudo cobrar.', 'error');
                    this.cobro = null; this.hecho = r; this.recargar();
                },
                async guardarGestion() {
                    this.ocupado = true;
                    const g = this.gestion;
                    const r = await post('{{ url('prestamos') }}/' + g.f.pre_id + '/gestion', { resultado: g.resultado, promesa: g.promesa || null, nota: g.nota });
                    this.ocupado = false;
                    tushpaAviso(r.mensaje || 'Error', r.ok ? 'ok' : 'error');
                    if (r.ok) { this.gestion = null; this.recargar(); }
                },
            };
        }
    </script>
@endsection
