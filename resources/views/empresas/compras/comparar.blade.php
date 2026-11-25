@extends('layouts.app')
@section('title', 'Comparar proveedores')

@section('content')
{{-- ¿A qué proveedor le compro más barato? Sale de las compras registradas: el último costo por unidad de cada proveedor --}}
@php($soles = fn ($n) => 'S/ '.number_format((float) $n, 2))
<div class="max-w-5xl mx-auto space-y-4">
    <section class="rounded-3xl bg-gradient-to-br from-sky-600 to-indigo-700 text-white p-5 sm:p-6 shadow-lg">
        <h1 class="text-2xl sm:text-3xl font-black">🏷️ ¿A quién le compro más barato?</h1>
        <p class="mt-1 text-white/90">Por cada producto, el último costo por unidad que te dio cada proveedor en tus compras de los últimos {{ (int) ($dias / 30) }} meses.
            Arriba salen los que más ahorrarías comprándole al más barato.</p>
    </section>

    <form class="flex gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar producto…" class="flex-1 h-11 rounded-xl border-gray-300 text-sm">
        <button class="h-11 px-5 rounded-xl bg-indigo-600 text-white font-bold">Buscar</button>
        <a href="{{ route('compras.index') }}" class="h-11 px-4 inline-flex items-center rounded-xl bg-gray-100 text-gray-600 font-semibold">← Compras</a>
    </form>

    @forelse ($productos as $p)
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <div class="flex flex-wrap items-center gap-2 mb-2">
                <p class="font-extrabold text-gray-800 flex-1">{{ $p->nombre }}</p>
                @if ($p->ahorro > 0)
                    <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 text-xs font-black">Ahorras {{ $soles($p->ahorro) }} por unidad con {{ $p->mejor->proveedor }}</span>
                @elseif ($p->proveedores->count() > 1)
                    <span class="px-3 py-1 rounded-full bg-sky-100 text-sky-800 text-xs font-bold">✓ Ya le compras al más barato</span>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($p->proveedores as $v)
                    <span class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm ring-1 {{ $loop->first && $p->proveedores->count() > 1 ? 'ring-emerald-300 bg-emerald-50' : 'ring-gray-200' }}">
                        @if ($loop->first && $p->proveedores->count() > 1)<span>🥇</span>@endif
                        <b class="text-gray-800">{{ $v->proveedor }}</b>
                        <span class="font-black {{ $loop->first ? 'text-emerald-700' : 'text-gray-700' }}">{{ $soles($v->costo) }}</span>
                        <span class="text-xs text-gray-400">{{ date('d/m/Y', strtotime($v->fecha)) }}{{ $v === $p->ultimo ? ' · la última' : '' }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    @empty
        <p class="bg-white rounded-2xl shadow-sm p-8 text-center text-gray-500">Todavía no hay compras registradas{{ $q !== '' ? ' de ese producto' : '' }} en los últimos meses.</p>
    @endforelse
</div>
@endsection
