@extends('layouts.app')
@section('title', 'Inventario ciego')
@section('content')
@php
    $lista = $productos->map(fn ($p) => ['id' => (int) $p->IdProducto, 'nombre' => $p->pronom, 'categoria' => $p->cat_nom ?? 'Sin categoría',
        'unidad' => \App\Support\Recetas::nombreUnidad($p->umecod ?: 'NIU'), 'contado' => '',
        'buscar' => mb_strtolower(\Illuminate\Support\Str::ascii($p->pronom.' '.($p->cat_nom ?? '')))])->values();
@endphp
<div class="max-w-3xl mx-auto space-y-4" x-data="conteoCiego(@js($lista))">
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-2">
        <h1 class="text-xl font-extrabold text-gray-800">🙈 Inventario ciego</h1>
        <p class="text-sm text-gray-500">Cuenta lo que hay físicamente y escríbelo. No verás cuánto dice el sistema: así el conteo es honesto.
            Lo que no cuentes queda igual. El administrador revisa las diferencias.
            Con <b>📷</b> la IA cuenta el producto, las cajas o la ruma en una foto y tú confirmas la cantidad.</p>
    </div>

    <form method="POST" action="{{ route('conteo_ciego.store') }}" class="space-y-4" @submit="if (!contados.length) { $event.preventDefault(); alert('Pon lo que contaste de al menos un producto.'); }">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm p-4 grid sm:grid-cols-2 gap-3">
            <label class="text-sm font-semibold text-gray-700">Almacén
                <select name="id_almacen" class="mt-1 w-full h-11 rounded-xl border-gray-300">
                    @foreach ($almacenes as $a)<option value="{{ $a->id_almacen }}">{{ $a->descripcion }}</option>@endforeach
                </select></label>
            <label class="text-sm font-semibold text-gray-700">Observaciones
                <input name="observaciones" maxlength="255" placeholder="Ej.: conteo del lunes, cámara de frío" class="mt-1 w-full h-11 rounded-xl border-gray-300"></label>
        </div>
        <div class="bg-white rounded-2xl shadow-sm">
            <div class="p-4 border-b border-gray-100 flex flex-wrap items-center gap-2">
                <input type="search" x-model="texto" placeholder="Buscar producto o insumo…" class="h-10 flex-1 min-w-56 rounded-xl border-gray-300 text-sm">
                <span class="text-sm text-gray-500">Contados: <b x-text="contados.length"></b> de {{ $productos->count() }}</span>
            </div>
            <div class="divide-y divide-gray-100">
                <template x-for="p in visibles" :key="p.id">
                    <label class="flex items-center gap-3 px-4 py-2.5" :class="p.contado !== '' ? 'bg-emerald-50/50' : ''">
                        <span class="flex-1"><span class="block font-semibold text-gray-800" x-text="p.nombre"></span><span class="text-xs text-gray-400" x-text="p.categoria"></span></span>
                        <button type="button" @click.prevent="conFoto(p)" title="Contar con foto (IA)" class="shrink-0 h-11 w-11 rounded-xl bg-indigo-50 text-indigo-700 hover:bg-indigo-100">📷</button>
                        <input type="number" step="0.001" min="0" x-model="p.contado" :name="p.contado !== '' ? `contados[${p.id}]` : null" placeholder="—"
                               class="w-28 h-11 rounded-xl border-gray-300 text-right font-bold">
                        <span class="w-16 text-xs text-gray-500" x-text="p.unidad"></span>
                    </label>
                </template>
            </div>
        </div>
        <div class="sticky bottom-3 flex justify-end">
            <button class="h-12 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold shadow-lg">Enviar conteo (<span x-text="contados.length"></span>)</button>
        </div>
    </form>
    @include('empresas.partials.contar_foto')
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('conteoCiego', (productos) => ({
            productos, texto: '',
            async conFoto(p) {
                const cantidad = await window.contarConFoto(p.id, p.nombre);
                if (cantidad !== null) p.contado = cantidad;
            },
            get contados() { return this.productos.filter(p => p.contado !== '' && p.contado !== null); },
            get visibles() {
                const t = this.texto.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
                return this.productos.filter(p => !t || p.buscar.includes(t));
            },
        }));
    });
</script>
@endpush
