@extends('layouts.app')
@section('title', 'Entradas')

@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    @include('empresas.partials.alert')

    <section class="rounded-3xl bg-gradient-to-br from-emerald-600 to-teal-600 text-white p-5 sm:p-6 shadow-lg">
        <h1 class="text-2xl font-black">🥗 Entradas del menú</h1>
        <p class="mt-1 text-emerald-50 text-sm">Así funciona, en 3 pasos:</p>
        <div class="grid sm:grid-cols-3 gap-3 mt-3 text-sm">
            <div class="rounded-2xl bg-white/15 p-3"><b>1. Crea tus entradas</b><br>SOPA, TEQUEÑOS, ENSALADA… con sus insumos para saber cuánto cuestan y descontarlas del almacén.</div>
            <div class="rounded-2xl bg-white/15 p-3"><b>2. Marca los platos de menú</b><br>Los que llevan entrada (Lomo saltado del menú, Pollo al horno del menú…).</div>
            <div class="rounded-2xl bg-white/15 p-3"><b>3. En la comanda</b><br>Al pedir ese plato, el mozo toca la entrada que quiere cada comensal. La cocina la ve.</div>
        </div>
    </section>

    <div class="grid lg:grid-cols-2 gap-5 items-start">
        {{-- 1. Entradas --}}
        <section class="bg-white rounded-2xl shadow-sm">
            <header class="p-5 border-b border-gray-100">
                <h2 class="font-bold text-gray-800">1. Mis entradas</h2>
                <form method="POST" action="{{ route('entradas.guardar') }}" class="flex flex-wrap gap-2 mt-3">
                    @csrf
                    <input name="nombre" value="{{ old('nombre') }}" required maxlength="150" placeholder="Nombre: SOPA DE CASA, TEQUEÑOS…" class="flex-1 min-w-48 h-11 rounded-xl border-gray-300 text-sm uppercase">
                    <button name="con_receta" value="1" class="h-11 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">+ Crear y poner insumos</button>
                    <button class="h-11 px-4 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold">Solo crear</button>
                </form>
            </header>
            <div class="divide-y divide-gray-100">
                @forelse ($entradas as $e)
                    <div class="px-5 py-3 flex flex-wrap items-center gap-3 {{ $e->activa ? '' : 'opacity-50' }}" x-data="{ editando: false }">
                        <div class="flex-1 min-w-40">
                            <form x-show="editando" x-cloak method="POST" action="{{ route('entradas.actualizar', $e->id) }}" class="flex gap-2">
                                @csrf
                                <input name="nombre" value="{{ $e->nombre }}" maxlength="150" class="flex-1 h-9 rounded-lg border-gray-300 text-sm uppercase">
                                <button class="h-9 px-3 rounded-lg bg-indigo-600 text-white text-xs font-bold">OK</button>
                            </form>
                            <p x-show="!editando" class="font-bold text-gray-800">{{ $e->nombre }}
                                <button type="button" @click="editando = true" class="ml-1 text-xs font-normal text-gray-400 hover:text-indigo-600">✏️</button></p>
                            <p class="text-xs {{ $e->ingredientes ? 'text-gray-500' : 'text-amber-600' }}">
                                {{ $e->ingredientes ? $e->ingredientes.' insumo(s) · cuesta S/ '.number_format($e->costo ?? 0, 2).' por porción' : 'Sin insumos: no descuenta del almacén' }}</p>
                        </div>
                        <a href="{{ route('recetas.editar', $e->id) }}" class="h-9 px-3 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-900 text-xs font-bold flex items-center">🍳 Insumos</a>
                        <form method="POST" action="{{ route('entradas.actualizar', $e->id) }}">
                            @csrf
                            <button class="h-9 px-3 rounded-lg text-xs font-bold {{ $e->activa ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}"
                                    title="{{ $e->activa ? 'Toca si hoy no hay (deja de salir en las comandas)' : 'Volver a ofrecer' }}">{{ $e->activa ? '✔ Disponible' : 'Hoy no hay' }}</button>
                        </form>
                    </div>
                @empty
                    <p class="p-8 text-center text-gray-400 text-sm">Aún no tienes entradas. Crea la primera arriba 👆</p>
                @endforelse
            </div>
        </section>

        {{-- 2. Platos que llevan entrada --}}
        <section class="bg-white rounded-2xl shadow-sm" x-data="{ q: '', norm(t) { return String(t).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); } }">
            <form method="POST" action="{{ route('entradas.platos') }}">
                @csrf
                <header class="p-5 border-b border-gray-100">
                    <h2 class="font-bold text-gray-800">2. ¿Qué platos llevan entrada?</h2>
                    <p class="text-xs text-gray-500">Marca los platos de menú. Los que no marques se venden sin entrada.</p>
                    <input type="search" x-model="q" placeholder="🔍 Buscar plato o categoría…" class="mt-3 w-full h-10 rounded-xl border-gray-300 text-sm">
                </header>
                <div class="max-h-[28rem] overflow-y-auto divide-y divide-gray-50">
                    @forelse ($platos as $p)
                        <label class="flex items-center gap-3 px-5 py-2.5 hover:bg-gray-50 cursor-pointer text-sm" x-show="!q.trim() || norm(@js($p->pronom.' '.$p->cat_nom)).includes(norm(q.trim()))">
                            <input type="checkbox" name="platos[]" value="{{ $p->IdProducto }}" @checked($p->lleva_entrada) class="rounded text-emerald-600">
                            <span class="flex-1 font-semibold text-gray-700">{{ $p->pronom }}</span>
                            <span class="text-xs text-gray-400">{{ $p->cat_nom }}</span>
                        </label>
                    @empty
                        <p class="p-8 text-center text-gray-400 text-sm">No hay platos (productos Preparados).</p>
                    @endforelse
                </div>
                <div class="p-4 border-t border-gray-100">
                    <button class="w-full h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold">Guardar platos con entrada</button>
                </div>
            </form>
        </section>
    </div>
</div>
@endsection
