@extends('layouts.app')
@section('title', 'Enviar a otra sede')
@section('content')
@php
    $lista = $productos->map(fn ($p) => ['id' => (int) $p->IdProducto, 'nombre' => $p->pronom, 'categoria' => $p->cat_nom ?? 'Sin categoría',
        'unidad' => \App\Support\Recetas::nombreUnidad($p->umecod ?: 'NIU'), 'stock' => (float) $p->stock, 'cantidad' => '',
        'buscar' => mb_strtolower(\Illuminate\Support\Str::ascii($p->pronom.' '.($p->cat_nom ?? '')))])->values();
@endphp
<div class="max-w-3xl mx-auto space-y-4" x-data="envioSede(@js($lista))">
    @if (session('success'))<div class="rounded-xl bg-sky-50 border border-sky-200 text-sky-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-1">
        <h1 class="text-xl font-extrabold text-gray-800">🚚 Enviar a otra sede</h1>
        <p class="text-sm text-gray-500">Sale de tu almacén ahora y entra al de la otra sede cuando lo reciban (confirman lo que llegó).
            Si esa sede <b>no tiene el producto</b> en su catálogo, se le crea solo, copiado del tuyo (código, nombre, unidad, costo y categoría).</p>
    </div>

    <form method="POST" action="{{ route('requerimientos.enviar') }}" class="space-y-4" @submit="if (!enviados.length) { $event.preventDefault(); alert('Pon la cantidad que envías de al menos un producto.'); }">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm p-4 grid sm:grid-cols-3 gap-3">
            <label class="text-sm font-semibold text-gray-700">Enviar a
                <select name="destino" required class="mt-1 w-full h-11 rounded-xl border-gray-300">
                    <option value="">Elige la sede…</option>
                    @foreach ($sedes as $s)<option value="{{ $s->id_empresa_negocio }}" @selected((int) old('destino') === (int) $s->id_empresa_negocio)>{{ $s->nombre_comercial }}</option>@endforeach
                </select></label>
            <label class="text-sm font-semibold text-gray-700">Sale de
                <select name="id_almacen_origen" class="mt-1 w-full h-11 rounded-xl border-gray-300" onchange="location.href = @js(route('requerimientos.enviar')) + '?almacen=' + this.value">
                    @foreach ($almacenes as $a)<option value="{{ $a->id_almacen }}" @selected($a->id_almacen === $almacen->id_almacen)>{{ $a->descripcion }}</option>@endforeach
                </select></label>
            <label class="text-sm font-semibold text-gray-700">Observaciones
                <input name="observaciones" maxlength="400" value="{{ old('observaciones') }}" placeholder="Ej.: reposición del lunes" class="mt-1 w-full h-11 rounded-xl border-gray-300"></label>
        </div>

        <div class="bg-white rounded-2xl shadow-sm">
            <div class="p-4 border-b border-gray-100 flex flex-wrap items-center gap-2">
                <input type="search" x-model="texto" placeholder="Buscar producto o insumo…" class="h-10 flex-1 min-w-56 rounded-xl border-gray-300 text-sm">
                <label class="text-sm text-gray-600"><input type="checkbox" x-model="soloConStock" class="rounded border-gray-300"> Solo con stock</label>
            </div>
            <div class="divide-y divide-gray-100">
                <template x-for="p in visibles" :key="p.id">
                    <label class="flex items-center gap-3 px-4 py-2.5" :class="Number(p.cantidad) > 0 ? 'bg-sky-50/60' : ''">
                        <span class="flex-1"><span class="block font-semibold text-gray-800" x-text="p.nombre"></span>
                            <span class="text-xs text-gray-500"><span x-text="p.categoria"></span> · hay <b x-text="p.stock"></b> <span x-text="p.unidad"></span></span></span>
                        <input type="number" step="0.001" min="0" :max="p.stock" x-model="p.cantidad" placeholder="0" class="w-28 h-11 rounded-xl border-gray-300 text-right font-bold">
                        <span class="w-16 text-xs text-gray-500" x-text="p.unidad"></span>
                    </label>
                </template>
                <p x-show="!visibles.length" class="px-5 py-10 text-center text-gray-400">No hay productos con esa búsqueda.</p>
            </div>
        </div>
        <template x-for="(p, i) in enviados" :key="p.id">
            <div><input type="hidden" :name="`items[${i}][id]`" :value="p.id"><input type="hidden" :name="`items[${i}][cantidad]`" :value="p.cantidad"></div>
        </template>
        <div class="sticky bottom-3 flex justify-end">
            <button class="h-12 px-6 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold shadow-lg">🚚 Enviar (<span x-text="enviados.length"></span> productos)</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('envioSede', (productos) => ({
            productos, texto: '', soloConStock: true,
            get enviados() { return this.productos.filter(p => Number(p.cantidad) > 0); },
            get visibles() {
                const t = this.texto.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
                return this.productos.filter(p => (!t || p.buscar.includes(t)) && (!this.soloConStock || p.stock > 0 || Number(p.cantidad) > 0));
            },
        }));
    });
</script>
@endpush
