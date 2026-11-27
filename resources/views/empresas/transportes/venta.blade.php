@extends('layouts.app')
@section('title', 'Venta de pasajes')
@section('content')
    @php
        $categorias = \App\Support\Transportes::CATEGORIAS;
    @endphp
    <div class="space-y-4">
        @include('empresas.transportes.partials.nav')
        @include('empresas.partials.alert')

        <div class="grid lg:grid-cols-12 gap-4 items-start">
            {{-- Salidas próximas --}}
            <section class="lg:col-span-4 bg-white rounded-2xl shadow-sm overflow-hidden">
                <h2 class="px-4 py-3 border-b font-black text-gray-800"><i class="fas fa-calendar-days text-indigo-600"></i> Salidas</h2>
                <div class="max-h-[70vh] overflow-y-auto divide-y divide-gray-100">
                    @forelse ($proximas as $s)
                        @php $lleno = (int) $s->vendidos >= (int) $s->asientos; @endphp
                        <a href="{{ route('transportes.index', ['salida' => $s->id]) }}"
                           class="block px-4 py-3 {{ $elegida && $elegida->id === $s->id ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-400' : 'hover:bg-gray-50' }}">
                            <div class="flex items-center gap-2">
                                <span class="text-lg font-black text-gray-800">{{ \Carbon\Carbon::parse($s->salida)->format('H:i') }}</span>
                                <span class="text-xs font-semibold text-gray-500">{{ \Carbon\Carbon::parse($s->salida)->isToday() ? 'Hoy' : \Carbon\Carbon::parse($s->salida)->translatedFormat('D d/m') }}</span>
                                <span class="ml-auto px-2 py-0.5 rounded-full text-xs font-bold {{ $lleno ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $s->vendidos }}/{{ $s->asientos }}</span>
                            </div>
                            <p class="font-bold text-gray-700">{{ $s->origen }} → {{ $s->destino }}</p>
                            <p class="text-xs text-gray-500"><i class="fas {{ \App\Support\Transportes::TIPOS[$s->tipo][1] ?? 'fa-bus' }}"></i> {{ $s->unidad }}{{ $s->placa ? ' · '.$s->placa : '' }} · desde S/ {{ number_format($s->precio, 2) }}</p>
                        </a>
                    @empty
                        <p class="p-6 text-center text-sm text-gray-400">No hay salidas programadas en los próximos días.
                            @if (Auth::user()->esAdmin())<a href="{{ route('transportes.salidas') }}" class="block mt-2 font-bold text-indigo-600">Programar una salida →</a>@endif</p>
                    @endforelse
                </div>
            </section>

            {{-- Plano y venta --}}
            <section class="lg:col-span-8">
                @if (! $elegida)
                    <div class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">Elige una salida para ver sus asientos.</div>
                @else
                    <div x-data="ventaPasajes(@js($datos['plano']), @js($datos['ocupados']), @js($datos['precios']))" class="space-y-4">
                        <div class="bg-white rounded-2xl shadow-sm p-4">
                            <div class="flex flex-wrap items-center gap-3 mb-3">
                                <div class="flex-1 min-w-[12rem]">
                                    <p class="text-lg font-black text-gray-800">{{ $elegida->origen }} → {{ $elegida->destino }}</p>
                                    <p class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($elegida->salida)->format('d/m/Y H:i') }} · {{ $elegida->unidad }}{{ $elegida->conductor ? ' · '.$elegida->conductor : '' }}</p>
                                </div>
                                <a href="{{ route('transportes.manifiesto', $elegida->id) }}" target="_blank" class="h-9 px-3 inline-flex items-center rounded-xl bg-slate-100 text-sm font-bold text-slate-700"><i class="fas fa-clipboard-list mr-1"></i> Manifiesto</a>
                            </div>
                            <div class="flex flex-wrap gap-3 text-xs font-semibold text-gray-600 mb-3">
                                <span><span class="inline-block w-3 h-3 rounded bg-emerald-100 ring-1 ring-emerald-400 align-middle"></span> Libre</span>
                                <span><span class="inline-block w-3 h-3 rounded bg-indigo-600 align-middle"></span> Elegido</span>
                                <span><span class="inline-block w-3 h-3 rounded bg-rose-200 align-middle"></span> Vendido</span>
                                <template x-for="(precio, cat) in precios" :key="cat">
                                    <span class="px-2 rounded bg-slate-100" x-text="(categorias[cat] || cat) + ': S/ ' + precio.toFixed(2)"></span>
                                </template>
                            </div>
                            {{-- Pisos o cubiertas, lado a lado --}}
                            <div class="flex flex-wrap gap-6 justify-center">
                                <template x-for="(piso, pi) in plano.pisos" :key="pi">
                                    <div>
                                        <p class="text-center text-xs font-black text-gray-500 uppercase mb-2" x-text="piso.nombre"></p>
                                        <div class="inline-grid gap-1.5 rounded-3xl border-2 border-slate-200 bg-slate-50 p-3" :style="`grid-template-columns: repeat(${piso.columnas}, 2.75rem)`">
                                            <template x-for="(celda, k) in piso.celdas.flat()" :key="pi + '-' + k">
                                                <button type="button" @click="tocar(celda)" :disabled="!vendible(celda)" :title="titulo(celda)"
                                                        class="h-11 rounded-xl flex items-center justify-center text-xs transition"
                                                        :class="claseDe(celda)">
                                                    @include('empresas.transportes.partials.asiento')
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Pasajeros y cobro --}}
                        <div x-show="seleccion.length" x-cloak class="bg-white rounded-2xl shadow-sm p-4 space-y-3">
                            <h3 class="font-black text-gray-800">Pasajeros</h3>
                            <template x-for="(p, i) in seleccion" :key="p.asiento">
                                <div class="grid sm:grid-cols-12 gap-2 items-center rounded-xl bg-slate-50 p-2">
                                    <span class="sm:col-span-2 text-sm font-black text-indigo-700" x-text="'Asiento ' + p.asiento + ' · ' + (p.pos === 'V' ? 'ventana' : 'pasillo')"></span>
                                    <input x-model="p.doc" @keydown.enter.prevent="buscar(p)" @blur="buscar(p)" placeholder="DNI / CE / Pasaporte" maxlength="15" class="sm:col-span-3 h-10 rounded-lg border-gray-300 text-sm">
                                    <input x-model="p.nombre" placeholder="Nombres y apellidos" maxlength="150" class="sm:col-span-4 h-10 rounded-lg border-gray-300 text-sm uppercase">
                                    <input x-model="p.telefono" placeholder="Celular (opcional)" maxlength="20" class="sm:col-span-2 h-10 rounded-lg border-gray-300 text-sm">
                                    <span class="sm:col-span-1 text-right text-sm font-bold" x-text="'S/ ' + p.precio.toFixed(2)"></span>
                                </div>
                            </template>

                            <div class="grid sm:grid-cols-4 gap-2 pt-2 border-t">
                                <label class="text-xs font-semibold text-gray-500">Comprobante
                                    <select x-model="tdocod" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                                        <option value="03">Boleta</option><option value="01">Factura</option><option value="13">Nota de venta</option>
                                    </select></label>
                                <label class="text-xs font-semibold text-gray-500" x-show="tdocod === '01'">RUC
                                    <input x-model="clinum" @blur="buscarRuc()" maxlength="11" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                                <label class="text-xs font-semibold text-gray-500 sm:col-span-2" x-show="tdocod === '01'">Razón social
                                    <input x-model="clinom" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
                                <label class="text-xs font-semibold text-gray-500">Medio de pago
                                    <select x-model="medio" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                                        @foreach ($medios as $m)<option value="{{ $m->id_med_pag }}">{{ $m->nom_med_pag }}</option>@endforeach
                                    </select></label>
                                <label class="text-xs font-semibold text-gray-500">Paga con
                                    <input type="number" step="any" min="0" x-model.number="paga" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                                <div class="sm:col-span-2 flex items-end justify-end gap-4">
                                    <p class="text-sm text-gray-500">Vuelto <b class="text-emerald-600" x-text="paga > total ? (paga - total).toFixed(2) : '0.00'"></b></p>
                                    <p class="text-2xl font-black text-gray-800" x-text="'S/ ' + total.toFixed(2)"></p>
                                </div>
                            </div>
                            <p x-show="error" x-text="error" class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700"></p>
                            <div class="flex flex-wrap justify-end gap-2">
                                <button type="button" @click="seleccion = []" class="h-12 px-4 rounded-xl bg-slate-100 font-bold text-slate-700">Cancelar</button>
                                <button type="button" @click="vender(false)" :disabled="enviando" class="h-12 px-5 rounded-xl bg-sky-600 font-bold text-white disabled:opacity-50">REGISTRAR</button>
                                <button type="button" @click="vender(true)" :disabled="enviando" class="h-12 px-6 rounded-xl bg-emerald-600 font-bold text-white disabled:opacity-50"
                                        x-text="enviando ? 'Vendiendo…' : 'VENDER E IMPRIMIR'">VENDER E IMPRIMIR</button>
                            </div>
                        </div>

                        <div x-show="hecho" x-cloak class="rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 p-4 flex flex-wrap items-center gap-3">
                            <p class="flex-1 font-bold text-emerald-800" x-text="hecho ? '✔ Vendido: ' + hecho.numero + ' · S/ ' + hecho.total.toFixed(2) + (hecho.impreso ? ' · impreso' : '') : ''"></p>
                            <a :href="hecho ? hecho.ticket : '#'" target="_blank" class="h-10 px-4 inline-flex items-center rounded-xl bg-white text-sm font-bold text-emerald-700 ring-1 ring-emerald-300">Ver ticket</a>
                            <button type="button" @click="location.reload()" class="h-10 px-4 rounded-xl bg-emerald-600 text-sm font-bold text-white">Seguir vendiendo</button>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </div>

    @if ($elegida)
        <script>
            function ventaPasajes(plano, ocupados, precios) {
                const csrf = @js(csrf_token());
                return {
                    plano, ocupados, precios, categorias: @js($categorias), seleccion: [], tdocod: '03', clinum: '', clinom: '',
                    medio: @js((string) ($medios->first()->id_med_pag ?? '')), paga: 0, error: '', enviando: false, hecho: null,
                    get total() { return Math.round(this.seleccion.reduce((s, p) => s + p.precio, 0) * 100) / 100; },
                    vendible(c) { return c && (c.t === 'A' || c.t === 'H') && !(c.n in this.ocupados); },
                    elegido(c) { return c && this.seleccion.some((p) => p.asiento === c.n); },
                    titulo(c) {
                        if (!c || !(c.t === 'A' || c.t === 'H')) return '';
                        if (c.n in this.ocupados) return 'Asiento ' + c.n + ' vendido a ' + this.ocupados[c.n];
                        return 'Asiento ' + c.n + ' · ' + (this.categorias[c.cat] || c.cat) + ' · ' + (c.pos === 'V' ? 'ventana' : 'pasillo') + ' · S/ ' + (this.precios[c.cat] || 0).toFixed(2);
                    },
                    claseDe(c) {
                        if (!c) return 'invisible';
                        if (!(c.t === 'A' || c.t === 'H')) return 'bg-slate-200 cursor-default';
                        if (c.n in this.ocupados) return 'bg-rose-200 text-rose-800 cursor-not-allowed';
                        if (this.elegido(c)) return 'bg-indigo-600 text-white shadow-lg scale-105';
                        return (c.cat === 'CAMA' || c.cat === 'CAMA 180' ? 'bg-amber-50 ring-amber-400' : 'bg-emerald-50 ring-emerald-400') + ' ring-2 text-gray-800 hover:bg-indigo-100';
                    },
                    tocar(c) {
                        if (!this.vendible(c)) return;
                        const i = this.seleccion.findIndex((p) => p.asiento === c.n);
                        if (i >= 0) { this.seleccion.splice(i, 1); return; }
                        this.seleccion.push({ asiento: c.n, pos: c.pos, cat: c.cat, precio: this.precios[c.cat] || 0, tdicod: '1', doc: '', nombre: '', telefono: '' });
                        this.hecho = null;
                    },
                    async buscar(p) {
                        const doc = String(p.doc || '').trim();
                        if (!/^\d{8}$/.test(doc) || p.nombre) return;
                        try {
                            const d = await (await fetch(@js(url('cobros/cliente')) + '/' + doc, { headers: { Accept: 'application/json' } })).json();
                            if (d.nom) p.nombre = d.nom;
                        } catch (e) {}
                    },
                    async buscarRuc() {
                        if (!/^\d{11}$/.test(this.clinum) || this.clinom) return;
                        try {
                            const d = await (await fetch(@js(url('cobros/cliente')) + '/' + this.clinum, { headers: { Accept: 'application/json' } })).json();
                            if (d.nom) this.clinom = d.nom;
                        } catch (e) {}
                    },
                    async vender(imprimir) {
                        this.error = '';
                        if (this.seleccion.some((p) => !String(p.doc).trim() || !String(p.nombre).trim())) { this.error = 'Completa el documento y el nombre de cada pasajero.'; return; }
                        this.enviando = true;
                        try {
                            const r = await fetch(@js(route('transportes.vender', $elegida->id)), { method: 'POST',
                                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                                body: JSON.stringify({ pasajeros: this.seleccion.map((p) => ({ asiento: p.asiento, tdicod: String(p.doc).trim().length === 8 ? '1' : (p.tdicod || '4'),
                                    doc: String(p.doc).trim(), nombre: String(p.nombre).trim(), telefono: p.telefono || null })),
                                    tdocod: this.tdocod, clinum: this.tdocod === '01' ? this.clinum : null, clinom: this.tdocod === '01' ? this.clinom : null,
                                    id_med_pag: this.medio ? Number(this.medio) : null, paga: this.paga || 0, imprimir }) });
                            if (r.status === 419 || r.status === 401) { location.reload(); return; }
                            const d = await r.json().catch(() => null);
                            if (r.status === 422 && d?.errors) { this.error = Object.values(d.errors)[0][0]; return; }
                            if (!d || !d.ok) { this.error = d?.mensaje || 'No se pudo vender.'; return; }
                            this.seleccion.forEach((p) => { this.ocupados[p.asiento] = p.nombre.toUpperCase(); });
                            this.seleccion = [];
                            this.hecho = d.comprobante;
                            if (imprimir && !d.comprobante.impreso) window.open(d.comprobante.ticket, '_blank');
                        } catch (e) {
                            this.error = 'Sin conexión: no se vendió. Intenta otra vez.';
                        } finally {
                            this.enviando = false;
                        }
                    },
                };
            }
        </script>
    @endif
@endsection
