@extends('layouts.app')
@section('title', 'Producción del día')
@section('content')
@php
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.') ?: '0';
    $unidad = fn ($u) => \App\Support\Recetas::nombreUnidad($u ?: 'NIU');
@endphp
<div class="max-w-4xl mx-auto space-y-4" x-data="{ texto: '' }">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-extrabold text-gray-800">🍳 Producción del día</h1>
            <p class="text-sm text-gray-500">Pon cuántos platos vas a preparar: con sus recetas el sistema calcula los insumos que necesitas, los compara con tu almacén y te dice qué falta pedir.</p>
        </div>
        <a href="{{ route('requerimientos.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">← Requerimientos</a>
    </div>

    <form method="POST" action="{{ route('requerimientos.produccion') }}" class="space-y-4">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm">
            <div class="flex flex-wrap items-center gap-2 p-4 border-b border-gray-100">
                <label class="text-sm font-semibold text-gray-700">Almacén
                    <select name="almacen" class="ml-1 h-10 rounded-xl border-gray-300 text-sm">
                        @foreach ($almacenes as $a)<option value="{{ $a->id_almacen }}" @selected($a->id_almacen === $almacen->id_almacen)>{{ $a->descripcion }}</option>@endforeach
                    </select></label>
                <input type="search" x-model="texto" placeholder="Buscar plato…" class="ml-auto h-10 w-full sm:w-64 rounded-xl border-gray-300 text-sm">
            </div>
            <div class="max-h-[28rem] overflow-y-auto divide-y divide-gray-100">
                @forelse ($platos as $p)
                    <label class="flex items-center gap-3 px-4 py-2.5" x-show="!texto || @js(mb_strtolower($p->pronom)).includes(texto.toLowerCase())">
                        <span class="flex-1 font-semibold text-gray-800">{{ $p->pronom }}</span>
                        <input type="number" min="0" step="1" name="platos[{{ $p->IdProducto }}]" value="{{ isset($cantidades[$p->IdProducto]) ? $num($cantidades[$p->IdProducto]) : '' }}" placeholder="0"
                               class="w-24 h-10 rounded-xl border-gray-300 text-right font-bold">
                        <span class="w-12 text-xs text-gray-500">platos</span>
                    </label>
                @empty
                    <p class="px-5 py-10 text-center text-gray-400">No hay platos con receta. Arma sus recetas en "Recetas y Food Cost" para planificar la producción.</p>
                @endforelse
            </div>
        </div>
        <div class="flex justify-end"><button class="h-12 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold">Calcular insumos</button></div>

        @if ($resultado !== null)
            <div class="bg-white rounded-2xl shadow-sm">
                <p class="p-4 sm:p-5 font-bold text-gray-800 border-b border-gray-100">Insumos para lo planificado
                    <span class="font-normal text-sm text-gray-500">· faltan {{ $resultado->where('falta', '>', 0)->count() }} de {{ $resultado->count() }}</span></p>
                <div class="divide-y divide-gray-100">
                    @forelse ($resultado as $i)
                        <div class="flex flex-wrap items-center gap-3 px-4 sm:px-5 py-3 {{ $i->falta > 0 ? 'bg-rose-50/50' : '' }}">
                            <div class="flex-1 min-w-48">
                                <p class="font-bold text-gray-800">{{ $i->nombre }}</p>
                                <p class="text-xs text-gray-500">Para: {{ $i->platos }}</p>
                            </div>
                            <span class="text-sm text-gray-600">necesitas <b>{{ $num($i->necesario) }}</b> · hay {{ $num($i->stock) }} {{ $unidad($i->unidad) }}</span>
                            <span class="w-32 text-right text-sm font-bold {{ $i->falta > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $i->falta > 0 ? 'faltan '.$num($i->falta) : '✔ alcanza' }}</span>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-center text-gray-400">Pon la cantidad de al menos un plato.</p>
                    @endforelse
                </div>
            </div>
            @if ($resultado->where('falta', '>', 0)->isNotEmpty())
                <div class="sticky bottom-3 flex justify-end">
                    <button name="pedir" value="1" class="h-12 px-6 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold shadow-lg">📝 Pedir lo que falta →</button>
                </div>
            @endif
        @endif
    </form>
</div>
@endsection
