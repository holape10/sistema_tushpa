@extends('layouts.app')
@section('title', 'Nuevo requerimiento')
@section('content')
@php
    $lista = $productos->map(fn ($p) => [
        'id' => $p->id, 'nombre' => $p->nombre, 'unidad' => \App\Support\Recetas::nombreUnidad($p->unidad), 'categoria' => $p->categoria ?? 'Sin categoría',
        'insumo' => $p->tipo === 4, 'stock' => $p->stock, 'minimo' => $p->minimo, 'consumo' => $p->consumo_dia, 'dias' => $p->dias,
        'estado' => $p->estado, 'cantidad' => $p->sugerido > 0 ? $p->sugerido : '', 'nota' => '',
        'buscar' => mb_strtolower(\Illuminate\Support\Str::ascii($p->nombre.' '.($p->categoria ?? ''))),
    ])->values();
    $resumen = $productos->countBy('estado');
@endphp
<div class="max-w-5xl mx-auto space-y-4" x-data="requerimiento(@js($lista), @js(old('items') ? 'todo' : 'faltan'))">
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-extrabold text-gray-800">📝 Nuevo requerimiento</h1>
                <p class="text-sm text-gray-500">Lo que hay en tu almacén, lo que falta y lo que se acabaría en {{ \App\Support\Requerimientos::DIAS_PREVISION }} días según lo que se gastó la última semana.
                    La cantidad ya viene sugerida: cámbiala si hace falta.</p>
            </div>
            <div class="flex flex-col items-end gap-1 text-sm font-semibold">
                <a href="{{ route('requerimientos.produccion') }}" class="text-emerald-700 hover:underline">🍳 Planificar la producción del día →</a>
                <a href="{{ route('requerimientos.index') }}" class="text-indigo-700 hover:underline">Ver mis requerimientos →</a>
            </div>
        </div>
        @if ($desdeProduccion)
            <p class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">🍳 Las cantidades vienen de la <b>producción del día</b> que planificaste: es lo que falta para preparar esos platos. Revisa y envía.</p>
        @endif
        <div class="grid grid-cols-3 gap-3 text-center">
            <button type="button" @click="vista = 'faltan'" class="rounded-xl bg-rose-50 p-3 ring-1 ring-rose-100"><p class="text-2xl font-extrabold text-rose-700">{{ ($resumen['agotado'] ?? 0) }}</p><p class="text-xs text-rose-700">se acabaron</p></button>
            <button type="button" @click="vista = 'faltan'" class="rounded-xl bg-amber-50 p-3 ring-1 ring-amber-100"><p class="text-2xl font-extrabold text-amber-700">{{ ($resumen['falta'] ?? 0) }}</p><p class="text-xs text-amber-700">llegaron a su mínimo</p></button>
            <button type="button" @click="vista = 'faltan'" class="rounded-xl bg-sky-50 p-3 ring-1 ring-sky-100"><p class="text-2xl font-extrabold text-sky-700">{{ ($resumen['se_acaba'] ?? 0) }}</p><p class="text-xs text-sky-700">no alcanzan para {{ \App\Support\Requerimientos::DIAS_PREVISION }} días</p></button>
        </div>
    </div>

    <form method="POST" action="{{ route('requerimientos.store') }}" class="space-y-4" @submit="if (!pedidos.length) { $event.preventDefault(); alert('Pon la cantidad de al menos un producto.'); }">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm p-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <label class="text-sm font-semibold text-gray-700">Almacén
                <select name="id_almacen" class="mt-1 w-full h-11 rounded-xl border-gray-300" onchange="location.href = @js(route('requerimientos.create')) + '?almacen=' + this.value">
                    @foreach ($almacenes as $a)<option value="{{ $a->id_almacen }}" @selected($a->id_almacen === $almacen->id_almacen)>{{ $a->descripcion }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm font-semibold text-gray-700">Para el día
                <input type="date" name="fecha_para" value="{{ old('fecha_para', now()->toDateString()) }}" min="{{ now()->toDateString() }}" class="mt-1 w-full h-11 rounded-xl border-gray-300">
            </label>
            <label class="text-sm font-semibold text-gray-700">Observaciones
                <input name="observaciones" value="{{ old('observaciones') }}" maxlength="500" placeholder="Ej.: para el almuerzo del sábado" class="mt-1 w-full h-11 rounded-xl border-gray-300">
            </label>
            <label class="text-sm font-semibold text-gray-700">¿Quién lo consigue?
                <select name="sucursal_origen" class="mt-1 w-full h-11 rounded-xl border-gray-300">
                    <option value="">🛒 Se compra (mercado o proveedor)</option>
                    @foreach ($sedes as $sede)
                        <option value="{{ $sede->id_empresa_negocio }}" @selected((int) old('sucursal_origen') === (int) $sede->id_empresa_negocio)>🏬 Pedir a la sede {{ $sede->nombre_comercial }}</option>
                    @endforeach
                </select>
            </label>
            <label class="sm:col-span-2 lg:col-span-4 flex items-center gap-2 rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800 ring-1 ring-rose-100">
                <input type="checkbox" name="urgente" value="1" @checked(old('urgente')) class="rounded border-rose-300 text-rose-600">
                🚨 Es urgente (le llega marcado como URGENTE al celular del administrador)
            </label>
        </div>

        <div class="bg-white rounded-2xl shadow-sm">
            <div class="flex flex-wrap items-center gap-2 p-4 border-b border-gray-100">
                <button type="button" @click="vista = 'faltan'" class="h-9 px-3 rounded-full text-sm font-semibold ring-1" :class="vista === 'faltan' ? 'bg-gray-800 text-white ring-gray-800' : 'bg-white text-gray-700 ring-gray-200'">⚠️ Lo que falta</button>
                <button type="button" @click="vista = 'todo'" class="h-9 px-3 rounded-full text-sm font-semibold ring-1" :class="vista === 'todo' ? 'bg-gray-800 text-white ring-gray-800' : 'bg-white text-gray-700 ring-gray-200'">Todos ({{ $productos->count() }})</button>
                <button type="button" @click="vista = 'pedidos'" class="h-9 px-3 rounded-full text-sm font-semibold ring-1" :class="vista === 'pedidos' ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-indigo-50 text-indigo-700 ring-indigo-100'">🛒 Lo que pido (<span x-text="pedidos.length"></span>)</button>
                <input type="search" x-model="texto" placeholder="Buscar producto o insumo…" class="ml-auto h-9 w-full sm:w-64 rounded-xl border-gray-300 text-sm">
            </div>
            <div class="divide-y divide-gray-100">
                <template x-for="p in visibles" :key="p.id">
                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-3 px-4 py-3" :class="Number(p.cantidad) > 0 ? 'bg-indigo-50/40' : ''">
                        <div class="flex-1 min-w-48">
                            <p class="font-bold text-gray-800"><span x-text="p.nombre"></span>
                                <span class="ml-1 text-xs font-semibold" :class="color(p.estado)" x-text="etiqueta(p.estado)"></span></p>
                            <p class="text-xs text-gray-500">
                                <span x-text="p.categoria"></span> · hay <b x-text="n(p.stock) + ' ' + p.unidad"></b>
                                <template x-if="p.minimo > 0"><span> · mín. <span x-text="n(p.minimo)"></span></span></template>
                                <template x-if="p.consumo > 0"><span> · se gasta <span x-text="n(p.consumo)"></span>/día<template x-if="p.dias !== null"><span> (alcanza <span x-text="p.dias"></span> día(s))</span></template></span></template>
                            </p>
                        </div>
                        <input type="number" step="0.001" min="0" x-model="p.cantidad" placeholder="0" class="w-28 h-11 rounded-xl border-gray-300 text-right font-bold">
                        <span class="w-16 text-xs text-gray-500" x-text="p.unidad"></span>
                        <input x-model="p.nota" maxlength="150" placeholder="Nota (marca, tamaño…)" class="w-full sm:w-48 h-11 rounded-xl border-gray-300 text-sm">
                    </div>
                </template>
                <p x-show="!visibles.length" class="px-5 py-10 text-center text-gray-400" x-text="vista === 'faltan' ? '✔ No falta nada en este almacén. Mira «Todos» si quieres pedir algo más.' : 'No hay productos con esa búsqueda.'"></p>
            </div>
        </div>

        <template x-for="(p, i) in pedidos" :key="p.id">
            <div>
                <input type="hidden" :name="`items[${i}][id]`" :value="p.id">
                <input type="hidden" :name="`items[${i}][cantidad]`" :value="p.cantidad">
                <input type="hidden" :name="`items[${i}][nota]`" :value="p.nota">
            </div>
        </template>
        <div class="sticky bottom-3 flex justify-end">
            <button class="h-12 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold shadow-lg">Enviar requerimiento (<span x-text="pedidos.length"></span> productos)</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('requerimiento', (productos, vista) => ({
            productos, vista, texto: '',
            get pedidos() { return this.productos.filter(p => Number(p.cantidad) > 0); },
            get visibles() {
                const t = this.texto.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
                return this.productos.filter(p => (!t || p.buscar.includes(t))
                    && (this.vista === 'todo' || t || (this.vista === 'pedidos' ? Number(p.cantidad) > 0 : p.estado !== 'ok' || Number(p.cantidad) > 0)));
            },
            n(v) { return String(Math.round(Number(v) * 1000) / 1000); },
            etiqueta(e) { return { agotado: '· se acabó', falta: '· llegó a su mínimo', se_acaba: '· se acabaría pronto', ok: '' }[e]; },
            color(e) { return { agotado: 'text-rose-600', falta: 'text-amber-700', se_acaba: 'text-sky-700', ok: '' }[e]; },
        }));
    });
</script>
@endpush
