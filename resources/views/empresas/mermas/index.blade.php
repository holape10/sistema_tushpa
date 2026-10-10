@extends('layouts.app')
@section('title', 'Mermas')

@section('content')
@php
    $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
    $motivos = \App\Support\Mermas::MOTIVOS;
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 3), '0'), '.');
@endphp
<div class="max-w-6xl mx-auto space-y-5" x-data="merma()">
    @include('empresas.partials.alert')

    <div class="grid lg:grid-cols-[1fr_360px] gap-5 items-start">
        {{-- Registrar --}}
        <section class="bg-white rounded-2xl shadow-sm p-5 sm:p-6">
            <h1 class="text-xl font-black text-gray-800">🗑️ Registrar una merma</h1>
            <p class="text-sm text-gray-500">Anota aquí lo que se pierde sin venderse. Sale del almacén y sabrás cuánto dinero se está perdiendo.</p>

            {{-- 1. Qué --}}
            <p class="mt-5 text-sm font-bold text-gray-700">1. ¿Qué se perdió?</p>
            <div class="relative mt-2" @click.outside="resultados = []">
                <template x-if="!elegido">
                    <input type="search" x-model="q" @input.debounce.300ms="buscar()" @focus="buscar()" @keydown.enter.prevent="resultados[0] && elegir(resultados[0])"
                           placeholder="Escribe: pollo, lomo saltado, inca kola…" class="w-full h-12 rounded-xl border-gray-300">
                </template>
                <template x-if="elegido">
                    <div class="flex items-center gap-3 h-12 px-4 rounded-xl bg-indigo-50 ring-1 ring-indigo-200">
                        <span class="text-[11px] font-bold uppercase text-indigo-500" x-text="elegido.tipo"></span>
                        <span class="flex-1 font-bold text-indigo-900 truncate" x-text="elegido.nombre"></span>
                        <button type="button" @click="elegido = null; q = ''" class="text-indigo-500 text-sm font-semibold">Cambiar</button>
                    </div>
                </template>
                <div x-show="resultados.length && !elegido" x-cloak class="absolute left-0 right-0 mt-1 bg-white rounded-xl shadow-xl ring-1 ring-gray-200 z-20 max-h-72 overflow-y-auto">
                    <template x-for="r in resultados" :key="r.id">
                        <button type="button" @click="elegir(r)" class="w-full flex justify-between gap-3 px-4 py-2.5 hover:bg-indigo-50 text-left text-sm">
                            <span><span class="font-semibold text-gray-700" x-text="r.nombre"></span> <span class="text-xs text-gray-400" x-text="'· ' + r.tipo"></span></span>
                            <span class="text-xs text-gray-400 whitespace-nowrap" x-text="soles(r.costo) + ' / ' + (r.tipo === 'Plato' || r.tipo === 'Combo' ? 'porción' : r.unidad)"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- 2. Cuánto --}}
            <div x-show="elegido" x-cloak>
                <p class="mt-5 text-sm font-bold text-gray-700">2. ¿Cuánto?</p>
                <div class="flex items-center gap-3 mt-2">
                    <div class="flex">
                        <input type="number" step="any" min="0" x-model.number="cantidad" x-ref="cantidad" placeholder="0" class="w-28 h-12 rounded-l-xl border-gray-300 text-right text-lg font-bold">
                        <select x-model="umecod" class="h-12 rounded-r-xl border-gray-300 border-l-0 bg-gray-50 font-semibold">
                            <template x-for="u in elegido?.unidades || []" :key="u.ume"><option :value="u.ume" x-text="u.nombre" :selected="u.ume === umecod"></option></template>
                        </select>
                    </div>
                    <p class="text-sm text-gray-500">Se pierden <b class="text-rose-600 text-base" x-text="soles(costo)"></b></p>
                </div>

                {{-- 3. Por qué --}}
                <p class="mt-5 text-sm font-bold text-gray-700">3. ¿Por qué?</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-2">
                    @foreach ($motivos as $clave => [$icono, $texto])
                        <button type="button" @click="motivo = '{{ $clave }}'" :class="motivo === '{{ $clave }}' ? 'ring-2 ring-indigo-500 bg-indigo-50 text-indigo-900' : 'ring-1 ring-gray-200 text-gray-600 hover:bg-gray-50'"
                                class="rounded-xl px-3 py-3 text-sm font-semibold text-left flex items-center gap-2"><span class="text-xl">{{ $icono }}</span>{{ $texto }}</button>
                    @endforeach
                </div>
                <input type="text" x-model="observacion" maxlength="200" placeholder="Nota (opcional): ej. se cortó la luz en la noche"
                       class="mt-3 w-full h-11 rounded-xl border-gray-300 text-sm">
                <button type="button" @click="guardar()" :disabled="guardando"
                        class="mt-4 w-full sm:w-auto h-12 px-8 rounded-xl bg-rose-600 hover:bg-rose-700 disabled:opacity-60 text-white font-black"
                        x-text="guardando ? 'Guardando…' : 'Registrar merma'"></button>
            </div>
        </section>

        {{-- Resumen --}}
        <aside class="space-y-4">
            <form class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-2 text-sm">
                <label class="flex-1">Desde<input type="date" name="desde" value="{{ $desde }}" class="block w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                <label class="flex-1">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                <button class="h-10 px-4 rounded-lg bg-gray-800 text-white font-semibold">Ver</button>
            </form>
            <div class="rounded-2xl bg-gradient-to-br from-rose-600 to-orange-500 text-white p-5 shadow-sm">
                <p class="text-sm text-rose-100">Perdido en el periodo</p>
                <p class="text-3xl font-black">{{ $soles($total) }}</p>
                @foreach ($porMotivo as $m => $c)
                    <div class="flex justify-between text-sm mt-1.5"><span>{{ $motivos[$m][0] ?? '' }} {{ $motivos[$m][1] ?? $m }}</span><b>{{ $soles($c) }}</b></div>
                @endforeach
            </div>
            @if ($masPerdidos->isNotEmpty())
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <p class="font-bold text-gray-800 mb-2">Lo que más se pierde</p>
                    @foreach ($masPerdidos as $p)
                        <div class="flex justify-between text-sm py-1"><span class="truncate">{{ $p->nombre }} <span class="text-gray-400">({{ $p->veces }})</span></span><b class="text-rose-600">{{ $soles($p->costo) }}</b></div>
                    @endforeach
                </div>
            @endif
        </aside>
    </div>

    <section class="bg-white rounded-2xl shadow-sm">
        <h2 class="p-5 font-bold text-gray-800 border-b border-gray-100">Mermas registradas</h2>
        <div class="divide-y divide-gray-100">
            @forelse ($mermas as $m)
                <div class="px-5 py-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm {{ $m->estado !== 'ACTIVA' ? 'opacity-50' : '' }}">
                    <span class="w-20 text-gray-400">{{ \Carbon\Carbon::parse($m->fecha)->format('d/m') }}</span>
                    <span class="flex-1 min-w-40 font-semibold text-gray-800">{{ $m->pronom }}
                        <span class="text-gray-500 font-normal">· {{ $num($m->cantidad) }} {{ (int) $m->promocion === 2 || (int) $m->promocion === 6 ? ((float) $m->cantidad == 1 ? 'porción' : 'porciones') : \App\Support\Recetas::nombreUnidad($m->umecod) }}</span>
                        @if ($m->observacion)<span class="block text-xs text-gray-400 font-normal">{{ $m->observacion }}</span>@endif
                    </span>
                    <span class="text-gray-600">{{ $motivos[$m->motivo][0] ?? '' }} {{ $motivos[$m->motivo][1] ?? $m->motivo }}</span>
                    <span class="w-28 text-xs text-gray-400 truncate">{{ $m->apeusu ?: $m->name }}</span>
                    <b class="w-20 text-right {{ $m->estado === 'ACTIVA' ? 'text-rose-600' : 'line-through text-gray-400' }}">{{ $soles($m->costo) }}</b>
                    @if ($m->estado === 'ACTIVA' && $esAdmin)
                        <form method="POST" action="{{ route('mermas.anular', $m->merma_id) }}" onsubmit="return confirm('¿Anular esta merma? Lo que salió regresa al almacén.')">
                            @csrf<button class="text-xs font-semibold text-gray-400 hover:text-rose-600">Anular</button></form>
                    @elseif ($m->estado !== 'ACTIVA')
                        <span class="text-xs font-bold text-gray-400">ANULADA</span>
                    @endif
                </div>
            @empty
                <p class="p-8 text-center text-gray-400 text-sm">No hay mermas en estas fechas. 👍</p>
            @endforelse
        </div>
    </section>
</div>

<script>
    function merma() {
        return {
            q: '', resultados: [], elegido: null, cantidad: null, umecod: 'NIU', motivo: '', observacion: '', guardando: false,
            soles(n) { return 'S/ ' + (Number(n) || 0).toFixed(2); },
            get costo() {
                if (!this.elegido) return 0;
                const u = this.elegido.unidades.find(x => x.ume === this.umecod) || { factor: 1 };
                return (Number(this.cantidad) || 0) * u.factor * this.elegido.costo;
            },
            async buscar() {
                const r = await fetch(@js(route('mermas.productos')) + '?q=' + encodeURIComponent(this.q.trim()), { headers: { Accept: 'application/json' } });
                this.resultados = r.ok ? await r.json() : [];
            },
            elegir(p) {
                this.elegido = p; this.resultados = [];
                this.umecod = (p.unidades[1] || p.unidades[0]).ume;
                this.$nextTick(() => this.$refs.cantidad?.focus());
            },
            async guardar() {
                if (!(Number(this.cantidad) > 0)) return this.avisar('Escribe cuánto se perdió.', 'aviso');
                if (!this.motivo) return this.avisar('Elige el motivo.', 'aviso');
                this.guardando = true;
                try {
                    const r = await fetch(@js(route('mermas.guardar')), { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                        body: JSON.stringify({ producto: this.elegido.id, cantidad: this.cantidad, umecod: this.umecod, motivo: this.motivo, observacion: this.observacion }) });
                    const j = await r.json();
                    if (j.ok) { location.reload(); return; }
                    this.avisar(j.mensaje || Object.values(j.errors || {}).flat()[0] || 'No se pudo guardar.', 'error');
                } catch (e) { this.avisar('Sin conexión. Intenta otra vez.', 'error'); }
                this.guardando = false;
            },
            avisar(t, tipo) { window.tushpaAviso ? window.tushpaAviso(t, tipo) : alert(t); },
        };
    }
</script>
@endsection
