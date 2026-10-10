@extends('layouts.app')
@section('title', 'Receta · '.$plato->pronom)

@section('content')
@php $esEntrada = (int) $plato->promocion === \App\Models\Producto::OPCION; @endphp
<div class="max-w-6xl mx-auto" x-data="receta()" x-init="iniciar()">
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="{{ $esEntrada ? route('entradas.index') : route('recetas.index') }}" class="h-10 w-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-gray-500 hover:text-indigo-600" title="Volver">←</a>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ $esEntrada ? 'Insumos de la entrada' : 'Receta de' }}</p>
            <h1 class="text-xl sm:text-2xl font-black text-gray-800 truncate">{{ $plato->pronom }}</h1>
        </div>
        @if ($otros->isNotEmpty())
            <select @change="copiarDe($event.target.value); $event.target.value = ''" class="h-10 rounded-xl border-gray-300 text-sm max-w-56">
                <option value="">📋 Copiar receta de…</option>
                @foreach ($otros as $o)<option value="{{ $o->IdProducto }}">{{ $o->pronom }}</option>@endforeach
            </select>
        @endif
    </div>

    <div class="grid lg:grid-cols-[1fr_340px] gap-5 items-start">
        {{-- Ingredientes --}}
        <section class="bg-white rounded-2xl shadow-sm">
            <header class="p-5 border-b border-gray-100">
                <h2 class="font-bold text-gray-800">Ingredientes de <u>una porción</u></h2>
                <p class="text-sm text-gray-500">Busca cada ingrediente y escribe cuánto lleva un plato. Ej.: 220 g de fideo, 1 unid. de huevo, 30 ml de aceite.</p>
            </header>

            <div class="divide-y divide-gray-100">
                <template x-for="(l, i) in lineas" :key="l.insumo">
                    <div class="p-4 flex flex-wrap items-center gap-x-3 gap-y-2">
                        <div class="flex-1 min-w-40">
                            <p class="font-bold text-gray-800 text-sm" x-text="l.nombre"></p>
                            <label class="text-xs text-gray-500 flex items-center gap-1 mt-0.5">
                                Lo compro a S/
                                <input type="number" step="0.01" min="0" x-model.number="l.costo" class="w-20 h-7 px-1.5 rounded-md border-gray-300 text-xs"
                                       :class="!(l.costo > 0) ? 'border-rose-400 bg-rose-50' : ''">
                                el <span x-text="nombreUnidad(l.ume_insumo)"></span>
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="number" step="any" min="0" x-model.number="l.cantidad" :id="'cant-' + l.insumo" placeholder="0"
                                   class="w-24 h-11 rounded-l-xl border-gray-300 text-right font-bold">
                            <select x-model="l.umecod" class="h-11 rounded-r-xl border-gray-300 border-l-0 bg-gray-50 text-sm font-semibold">
                                <template x-for="u in l.unidades" :key="u.ume"><option :value="u.ume" x-text="u.nombre" :selected="u.ume === l.umecod"></option></template>
                            </select>
                        </div>
                        <div class="w-24 text-right">
                            <p class="text-[11px] text-gray-400">Cuesta</p>
                            <p class="font-bold text-gray-800" x-text="soles(costoLinea(l))"></p>
                        </div>
                        <button type="button" @click="lineas.splice(i, 1)" class="h-9 w-9 rounded-lg text-rose-500 hover:bg-rose-50 text-lg" title="Quitar">×</button>
                    </div>
                </template>
                <p x-show="!lineas.length" class="p-8 text-center text-gray-400 text-sm">Todavía no hay ingredientes. Búscalos abajo 👇</p>
            </div>

            {{-- Buscar o crear ingrediente --}}
            <div class="p-4 bg-gray-50 rounded-b-2xl relative" @click.outside="resultados = []">
                <div class="relative">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                    <input type="search" x-model="q" @input.debounce.300ms="buscar()" @focus="buscar()" @keydown.enter.prevent="resultados[0] && agregar(resultados[0])"
                           placeholder="Agregar ingrediente: escribe fideo, pollo, aceite…" class="w-full h-12 pl-10 rounded-xl border-gray-300">
                </div>
                <div x-show="q.trim() || resultados.length" x-cloak class="absolute left-4 right-4 mt-1 bg-white rounded-xl shadow-xl ring-1 ring-gray-200 z-20 max-h-80 overflow-y-auto">
                    <template x-for="r in resultados" :key="r.insumo">
                        <button type="button" @click="agregar(r)" class="w-full flex justify-between items-center gap-3 px-4 py-2.5 hover:bg-indigo-50 text-left text-sm">
                            <span class="font-semibold text-gray-700" x-text="r.nombre"></span>
                            <span class="text-xs text-gray-400 whitespace-nowrap" x-text="r.costo > 0 ? soles(r.costo) + ' / ' + nombreUnidad(r.ume_insumo) : 'sin costo'"></span>
                        </button>
                    </template>
                    <div x-show="q.trim().length > 1" class="border-t border-gray-100 p-3 bg-indigo-50/60">
                        <p class="text-xs font-bold text-indigo-800 mb-2">¿No está? Créalo aquí mismo:</p>
                        <div class="flex flex-wrap gap-2 items-center text-sm">
                            <span class="font-bold text-gray-800" x-text="q.trim().toUpperCase()"></span>
                            <span class="text-gray-500">lo compro por</span>
                            <select x-model="nuevo.umecod" class="h-9 rounded-lg border-gray-300 text-sm">
                                <option value="KGM">kilo</option><option value="LTR">litro</option><option value="NIU">unidad</option>
                            </select>
                            <span class="text-gray-500">a S/</span>
                            <input type="number" step="0.01" min="0" x-model.number="nuevo.costo" placeholder="0.00" class="w-24 h-9 rounded-lg border-gray-300 text-sm">
                            <button type="button" @click="crear()" class="h-9 px-4 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold">+ Crear</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Resultado --}}
        <aside class="lg:sticky lg:top-4 space-y-4">
            @if ($esEntrada)
                <div class="bg-white rounded-2xl shadow-sm p-5 text-center">
                    <p class="text-sm text-gray-500">Cada porción de esta entrada cuesta</p>
                    <p class="text-4xl font-black text-emerald-700 mt-1" x-text="soles(total)"></p>
                    <p class="text-xs text-gray-400 mt-2">Se suma al costo del plato de menú que la lleva, y sus insumos salen del almacén cuando se cobra.</p>
                    <p x-show="sinCosto" class="mt-3 rounded-xl bg-rose-50 text-rose-800 text-xs p-3 text-left">⚠ Hay insumos <b>sin costo</b> (en rojo): escribe a cuánto los compras.</p>
                </div>
            @else
            <div class="bg-white rounded-2xl shadow-sm p-5 text-center">
                <div class="mx-auto w-44 h-44 rounded-full flex items-center justify-center"
                     :style="'background: conic-gradient(' + estado.color + ' ' + Math.min(100, fc || 0) + '%, #eef2f7 0)'">
                    <div class="w-32 h-32 rounded-full bg-white flex flex-col items-center justify-center">
                        <span class="text-3xl font-black" :style="'color:' + estado.color" x-text="fc !== null && total > 0 ? fc.toFixed(1) + '%' : '—'"></span>
                        <span class="text-[11px] font-semibold text-gray-400 uppercase">Food cost</span>
                    </div>
                </div>
                <p class="mt-3 text-lg font-black" :style="'color:' + estado.color" x-text="total > 0 ? estado.texto : 'Agrega ingredientes'"></p>
                <p class="text-sm text-gray-500" x-show="total > 0" x-text="'De cada S/ 10 que cobras, S/ ' + (fc / 10).toFixed(2) + ' son ingredientes.'"></p>

                <dl class="mt-4 text-sm divide-y divide-gray-100 text-left">
                    <div class="flex justify-between py-2"><dt class="text-gray-500">Precio de venta</dt><dd class="font-bold">{{ 'S/ '.number_format($precio, 2) }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-gray-500">Costo de ingredientes</dt><dd class="font-bold" x-text="soles(total)"></dd></div>
                    <div class="flex justify-between py-2"><dt class="text-gray-500">Ganas por plato</dt><dd class="font-black text-emerald-700" x-text="soles(precio - total)"></dd></div>
                </dl>
                <p x-show="total > 0 && fc > {{ \App\Support\Recetas::BIEN }}" class="mt-3 rounded-xl bg-amber-50 text-amber-900 text-xs p-3 text-left">
                    💡 Para quedar en {{ (int) \App\Support\Recetas::META }}%, el precio debería ser <b x-text="soles(sugerido)"></b>.
                    También puedes bajar la porción o buscar un ingrediente más barato.
                </p>
                <p x-show="sinCosto" class="mt-3 rounded-xl bg-rose-50 text-rose-800 text-xs p-3 text-left">
                    ⚠ Hay ingredientes <b>sin costo</b> (en rojo): escribe a cuánto los compras para que el cálculo sea real.
                </p>
            </div>
            @endif

            <button type="button" @click="guardar()" :disabled="guardando"
                    class="w-full h-12 rounded-2xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white font-black shadow-lg shadow-indigo-200"
                    x-text="guardando ? 'Guardando…' : '💾 Guardar receta'"></button>
            @if ($siguiente && ! $esEntrada)
                <a href="{{ route('recetas.editar', $siguiente->IdProducto) }}" class="block text-center text-sm text-indigo-600 font-semibold">Siguiente sin receta: {{ $siguiente->pronom }} →</a>
            @endif
            <p class="text-xs text-gray-400 text-center px-2">Al vender este plato se descuentan sus ingredientes del almacén. Si anulas la venta, regresan.</p>
        </aside>
    </div>
</div>

<script>
    function receta() {
        const UNIDADES = @js(\App\Support\Recetas::NOMBRES);
        return {
            lineas: @js($lineas), precio: {{ (float) $precio }}, q: '', resultados: [], guardando: false,
            nuevo: { umecod: 'KGM', costo: null },
            iniciar() {},
            nombreUnidad(u) { return UNIDADES[u] || String(u).toLowerCase(); },
            soles(n) { return 'S/ ' + (Number(n) || 0).toFixed(2); },
            factor(l) { return (l.unidades.find(u => u.ume === l.umecod) || { factor: 1 }).factor; },
            costoLinea(l) { return (Number(l.cantidad) || 0) * this.factor(l) * (Number(l.costo) || 0); },
            get total() { return this.lineas.reduce((s, l) => s + this.costoLinea(l), 0); },
            get fc() { return this.precio > 0 ? this.total / this.precio * 100 : null; },
            get sugerido() { return Math.ceil(this.total / {{ \App\Support\Recetas::META / 100 }} * 2) / 2; },
            get sinCosto() { return this.lineas.some(l => !(l.costo > 0)); },
            get estado() {
                const fc = this.fc;
                if (fc === null || this.total <= 0) return { color: '#94a3b8', texto: '' };
                if (fc <= {{ \App\Support\Recetas::BIEN }}) return { color: '#059669', texto: '¡Muy bien! 👍' };
                if (fc <= {{ \App\Support\Recetas::ALERTA }}) return { color: '#d97706', texto: 'Revisa el precio o la porción' };
                return { color: '#dc2626', texto: 'Ganas poco con este plato' };
            },
            async buscar() {
                const r = await fetch(@js(route('recetas.insumos')) + '?q=' + encodeURIComponent(this.q.trim()), { headers: { Accept: 'application/json' } });
                this.resultados = r.ok ? (await r.json()).filter(x => !this.lineas.some(l => l.insumo === x.insumo)) : [];
            },
            agregar(ins) {
                if (!this.lineas.some(l => l.insumo === ins.insumo)) this.lineas.push(Object.assign({}, ins));
                this.q = ''; this.resultados = [];
                this.$nextTick(() => document.getElementById('cant-' + ins.insumo)?.focus());
            },
            async crear() {
                const r = await this.post(@js(route('recetas.insumo.crear')), { nombre: this.q.trim(), umecod: this.nuevo.umecod, costo: this.nuevo.costo });
                if (r.ok) { this.agregar(r.insumo); this.nuevo = { umecod: 'KGM', costo: null }; this.avisar('Ingrediente creado: ' + r.insumo.nombre, 'ok'); }
                else this.avisar(r.mensaje || 'No se pudo crear.', 'error');
            },
            async copiarDe(id) {
                if (!id) return;
                if (this.lineas.length && !confirm('¿Reemplazar los ingredientes actuales por los del otro plato?')) return;
                const r = await fetch(@js(url('/recetas')) + '/' + id + '/receta', { headers: { Accept: 'application/json' } });
                if (r.ok) { this.lineas = await r.json(); this.avisar('Receta copiada: ajusta las cantidades y guarda.', 'info'); }
            },
            async guardar() {
                const vacias = this.lineas.filter(l => !(Number(l.cantidad) > 0));
                if (vacias.length) { this.avisar('Escribe la cantidad de: ' + vacias.map(l => l.nombre).join(', '), 'aviso'); return; }
                this.guardando = true;
                const r = await this.post(@js(route('recetas.guardar', $plato->IdProducto)), {
                    lineas: this.lineas.map(l => ({ insumo: l.insumo, cantidad: l.cantidad, umecod: l.umecod, costo: l.costo })),
                });
                this.guardando = false;
                this.avisar(r.mensaje || (r.ok ? 'Guardado.' : 'No se pudo guardar.'), r.ok ? 'ok' : 'error');
            },
            async post(url, datos) {
                try {
                    const r = await fetch(url, { method: 'POST', body: JSON.stringify(datos), headers: {
                        'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) } });
                    const j = await r.json();
                    if (r.status === 422 && j.errors) return { ok: false, mensaje: Object.values(j.errors).flat()[0] };
                    return j;
                } catch (e) { return { ok: false, mensaje: 'Sin conexión. Intenta otra vez.' }; }
            },
            avisar(t, tipo) { window.tushpaAviso ? window.tushpaAviso(t, tipo) : alert(t); },
        };
    }
</script>
@endsection
