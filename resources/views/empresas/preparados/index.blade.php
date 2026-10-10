@extends('layouts.app')
@section('title', 'Gestión de Preparados')

@section('content')
@php
    $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
    $filas = $platos->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->nombre, 'preparado' => $p->preparado, 'vendido' => $p->vendido,
        'reservado' => $p->reservado, 'ajustes' => $p->ajustes, 'quedan' => max(0, $p->quedan), 'receta' => $p->receta, 'nuevo' => false])->values();
    $catalogo = $disponibles->map(fn ($d) => ['id' => (int) $d->IdProducto, 'nombre' => $d->pronom,
        'nota' => trim(((int) $d->promocion === 8 ? 'entrada ' : '').($d->receta ? '· tiene receta' : ''))])->values();
@endphp
<div class="max-w-6xl mx-auto space-y-5 pb-24" x-data="preparados(@js($filas), @js($catalogo))">
    @include('empresas.partials.alert')

    <section class="rounded-3xl bg-gradient-to-br from-orange-500 to-rose-500 text-white p-5 sm:p-6 shadow-lg">
        <h1 class="text-2xl font-black">🍲 Gestión de Preparados · hoy {{ now()->format('d/m') }}</h1>
        <p class="mt-1 text-orange-50 text-sm">Platos que preparas por cantidad cada día (juanes, tamales, sopa del día, postres…). Cada día empieza en cero.
            Lo enviado a cocina se reserva, lo vendido se resta y cuando se acaba la comanda muestra <b>"AGOTADO HOY"</b>.</p>
    </section>

    <form method="POST" action="{{ route('preparados.guardar') }}" @submit="enviando = true">
        @csrf
        <input type="hidden" name="modo" :value="modo">

        {{-- Qué hago ahora --}}
        <div class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-center gap-3">
            <div class="flex gap-1 bg-gray-100 rounded-xl p-1 text-sm font-bold">
                <button type="button" @click="cambiarModo('preparar')" :class="modo === 'preparar' ? 'bg-white shadow text-emerald-700' : 'text-gray-500'" class="px-4 py-2 rounded-lg">➕ Anotar lo preparado hoy</button>
                <button type="button" @click="cambiarModo('corregir')" :class="modo === 'corregir' ? 'bg-white shadow text-indigo-700' : 'text-gray-500'" class="px-4 py-2 rounded-lg">✏️ Corregir lo que queda</button>
            </div>
            <p class="text-xs text-gray-500 flex-1 min-w-48" x-text="modo === 'preparar' ? 'Escribe cuántas porciones preparaste de cada plato (se suman). Deja vacío lo que no preparaste.' : 'Escribe cuántas porciones quedan de verdad (por si se cayó o se comió alguno).'"></p>
            <button type="button" @click="abierto = !abierto; $nextTick(() => $refs.buscar?.focus())" class="h-10 px-4 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold">+ Agregar platos</button>
        </div>

        {{-- Elegir varios platos de una vez --}}
        <div x-show="abierto" x-cloak class="bg-white rounded-2xl shadow-sm p-4 mt-3 ring-2 ring-orange-200">
            <div class="flex flex-wrap gap-2 items-center">
                <input type="search" x-ref="buscar" x-model="q" placeholder="🔍 Buscar: juane, tamal, sopa…" class="flex-1 min-w-48 h-11 rounded-xl border-gray-300 text-sm">
                <button type="button" x-show="q.trim() && hallados.length" @click="hallados.forEach(d => agregar(d))" class="h-11 px-3 rounded-xl bg-orange-50 text-orange-700 text-sm font-bold">+ Agregar los <span x-text="hallados.length"></span></button>
                <button type="button" @click="abierto = false" class="h-11 px-4 rounded-xl bg-gray-100 text-gray-600 text-sm font-semibold">Listo</button>
            </div>
            <p class="text-xs text-gray-400 mt-2">Toca los platos para agregarlos a la lista; los que no tienen receta salen primero.</p>
            <div class="flex flex-wrap gap-2 mt-3 max-h-64 overflow-y-auto">
                <template x-for="d in hallados" :key="d.id">
                    <button type="button" @click="agregar(d)" class="rounded-xl ring-1 ring-gray-200 hover:ring-orange-400 hover:bg-orange-50 px-3 py-2 text-left text-sm">
                        <span class="font-bold text-gray-700" x-text="d.nombre"></span>
                        <span class="block text-[11px] text-gray-400" x-text="d.nota"></span>
                    </button>
                </template>
                <p x-show="!hallados.length" class="text-sm text-gray-400">No hay más platos que coincidan.</p>
            </div>
        </div>

        {{-- La lista: escribir cantidades y guardar todo junto --}}
        <section class="bg-white rounded-2xl shadow-sm mt-3">
            <div class="divide-y divide-gray-100">
                <template x-for="(f, i) in filas" :key="f.id">
                    <div class="px-4 sm:px-5 py-3 flex flex-wrap items-center gap-4" :class="f.nuevo ? 'bg-orange-50/50' : ''">
                        <div class="flex-1 min-w-44">
                            <p class="font-black text-gray-800"><span x-text="f.nombre"></span>
                                <span x-show="f.nuevo" class="ml-1 text-[10px] font-bold uppercase bg-orange-500 text-white rounded px-1.5 py-0.5">nuevo</span></p>
                            <p class="text-xs text-gray-500" x-show="!f.nuevo">
                                Preparado <span x-text="num(f.preparado)"></span> · vendido <span x-text="num(f.vendido)"></span>
                                <template x-if="f.reservado > 0"><span> · <b class="text-indigo-600" x-text="num(f.reservado) + ' en comandas'"></b></span></template>
                            </p>
                        </div>
                        <div class="text-center w-20" x-show="!f.nuevo">
                            <p class="text-[11px] font-semibold text-gray-400 uppercase">Quedan</p>
                            <p class="text-2xl font-black" :class="f.quedan <= 0 ? 'text-rose-600' : (f.quedan <= 3 ? 'text-amber-500' : 'text-emerald-600')" x-text="num(f.quedan)"></p>
                        </div>
                        <label class="flex items-center gap-2 text-sm font-semibold" :class="modo === 'preparar' ? 'text-emerald-700' : 'text-indigo-700'">
                            <span x-text="modo === 'preparar' ? '+ Preparé' : 'Quedan'"></span>
                            <input type="number" min="0" step="1" :name="'cantidades[' + f.id + ']'" x-model="f.cantidad" placeholder="0"
                                   @keydown.enter.prevent="siguiente($event)" data-cantidad
                                   class="w-24 h-12 rounded-xl border-gray-300 text-center text-lg font-black">
                        </label>
                        <button type="button" x-show="f.nuevo" @click="filas.splice(i, 1)" class="text-xs text-gray-400 hover:text-rose-600">Quitar</button>
                        <button type="button" x-show="!f.nuevo" @click="quitar(f)" class="text-xs text-gray-400 hover:text-rose-600">Quitar</button>
                    </div>
                </template>
                <p x-show="!filas.length" class="p-8 text-center text-gray-400 text-sm">Aún no controlas ningún plato. Toca <b>"+ Agregar platos"</b> ☝️</p>
            </div>
        </section>

        {{-- Guardar todo --}}
        <div class="fixed bottom-0 inset-x-0 z-30 bg-white/95 backdrop-blur border-t border-gray-200" x-show="filas.length">
            <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-3">
                <p class="flex-1 text-sm text-gray-600"><b x-text="conCantidad"></b> plato(s) con cantidad
                    <span x-show="modo === 'corregir'" class="text-indigo-600">· modo corregir</span></p>
                <button :disabled="!conCantidad || enviando" class="h-11 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-black"
                        x-text="enviando ? 'Guardando…' : '💾 Guardar todo'"></button>
            </div>
        </div>
    </form>

    {{-- Quitar un plato del control (fuera del formulario principal) --}}
    <form method="POST" action="{{ route('preparados.controlar') }}" x-ref="quitar" class="hidden">
        @csrf<input type="hidden" name="activo" value="0"><input type="hidden" name="producto" x-ref="quitarId">
    </form>

    @if ($movimientos->isNotEmpty())
        <section class="bg-white rounded-2xl shadow-sm">
            <h2 class="p-5 font-bold text-gray-800 border-b border-gray-100">Anotado hoy</h2>
            <div class="divide-y divide-gray-100 text-sm">
                @foreach ($movimientos as $m)
                    <div class="px-5 py-2.5 flex flex-wrap gap-x-4">
                        <span class="w-14 text-gray-400">{{ \Carbon\Carbon::parse($m->created_at)->format('H:i') }}</span>
                        <span class="flex-1 font-semibold text-gray-700">{{ $m->pronom }}</span>
                        <span class="{{ $m->cantidad >= 0 ? 'text-emerald-700' : 'text-rose-600' }} font-bold">{{ $m->tipo === 'PREPARADO' ? 'Preparó' : 'Corrección' }} {{ $m->cantidad > 0 ? '+' : '' }}{{ $n($m->cantidad) }}</span>
                        <span class="w-32 text-xs text-gray-400 truncate">{{ $m->apeusu ?: $m->name }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>

<script>
    function preparados(filas, catalogo) {
        const norm = (t) => String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
        return {
            filas: filas.map(f => Object.assign({ cantidad: '' }, f)), catalogo, modo: 'preparar', q: '', abierto: !filas.length, enviando: false,
            num(v) { return (Math.round(Number(v) * 100) / 100).toString(); },
            get conCantidad() { return this.filas.filter(f => f.cantidad !== '' && f.cantidad !== null).length; },
            get hallados() {
                const palabras = norm(this.q.trim()).split(/\s+/).filter(Boolean);
                return this.catalogo.filter(d => !this.filas.some(f => f.id === d.id) && palabras.every(w => norm(d.nombre).includes(w))).slice(0, 60);
            },
            agregar(d) {
                if (!this.filas.some(f => f.id === d.id)) this.filas.push({ id: d.id, nombre: d.nombre, nuevo: true, quedan: 0, cantidad: '' });
            },
            cambiarModo(m) {
                this.modo = m;
                // Al corregir se parte de lo que el sistema cree que queda
                this.filas.forEach(f => f.cantidad = m === 'corregir' && !f.nuevo ? this.num(f.quedan) : '');
            },
            siguiente(e) {
                const campos = [...document.querySelectorAll('[data-cantidad]')];
                campos[campos.indexOf(e.target) + 1]?.focus();
            },
            quitar(f) {
                if (!confirm('¿Dejar de controlar porciones de ' + f.nombre + '?')) return;
                this.$refs.quitarId.value = f.id;
                this.$refs.quitar.submit();
            },
        };
    }
</script>
@endsection
