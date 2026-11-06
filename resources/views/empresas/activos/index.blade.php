@extends('layouts.app')
@section('title', 'Activos fijos')
@section('content')
@php
    $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
    $vigentes = $activos->where('de_baja', false);
@endphp
<div class="max-w-7xl mx-auto space-y-4">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-56">
            <h1 class="text-xl font-extrabold text-gray-800">🏗️ Activos fijos</h1>
            <p class="text-sm text-gray-500">Tus bienes (equipos, muebles, vehículos, local), su depreciación de cada mes con su asiento contable, y su valor de hoy.</p>
        </div>
        <a href="{{ route('activos.create') }}" class="h-10 px-5 inline-flex items-center rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">+ Nuevo activo</a>
    </div>

    <div class="grid gap-3 md:grid-cols-4">
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-bold uppercase text-gray-500">Costo</p><p class="text-2xl font-extrabold">{{ $soles($vigentes->sum('costo')) }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-bold uppercase text-gray-500">Depreciación acumulada</p><p class="text-2xl font-extrabold text-amber-700">{{ $soles($vigentes->sum('acumulada')) }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-bold uppercase text-gray-500">Valor neto</p><p class="text-2xl font-extrabold text-indigo-700">{{ $soles($vigentes->sum('neto')) }}</p></div>
        {{-- Depreciación del mes --}}
        <div class="bg-white rounded-2xl shadow-sm p-4 space-y-2">
            <p class="text-xs font-bold uppercase text-gray-500">Depreciación del mes</p>
            <form method="POST" action="{{ route('activos.depreciar') }}" class="flex gap-2">@csrf
                <input type="month" name="mes" value="{{ $sugerido }}" max="{{ now()->format('Y-m') }}" class="flex-1 min-w-0 h-9 rounded-xl border-gray-300 text-sm">
                <button class="h-9 px-3 rounded-xl bg-gray-800 hover:bg-gray-900 text-white text-sm font-bold">Procesar</button></form>
            @if ($ultimo)
                <form method="POST" action="{{ route('activos.deshacer') }}" onsubmit="return confirm('¿Deshacer la depreciación de este mes? Se borra su asiento.')"
                      class="text-xs text-gray-500">@csrf <input type="hidden" name="periodo" value="{{ $ultimo }}">
                    Último: {{ \App\Support\Contabilidad\Contabilidad::nombrePeriodo((string) $ultimo) }} · <button class="text-rose-600 hover:underline">deshacer</button></form>
            @else
                <p class="text-xs text-gray-400">Aún no se procesó ningún mes.</p>
            @endif
        </div>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-2 text-sm">
        <span class="text-gray-500">Cuadro al</span>
        <input type="date" name="fecha" value="{{ $fecha }}" onchange="this.form.submit()" class="h-9 rounded-xl border-gray-300 text-sm">
        <a href="{{ route('activos.excel', ['fecha' => $fecha]) }}" class="ml-auto h-9 px-4 inline-flex items-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">⬇ Excel</a>
    </form>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr><th class="text-left px-4 py-2">Activo</th><th class="text-left px-4 py-2">Categoría</th><th class="text-right px-4 py-2">Costo</th>
                    <th class="text-right px-4 py-2">Tasa</th><th class="text-right px-4 py-2">Dep. del año</th><th class="text-right px-4 py-2">Dep. acumulada</th><th class="text-right px-4 py-2">Valor neto</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($activos as $a)
                    <tr class="{{ $a->de_baja ? 'opacity-50' : '' }} hover:bg-gray-50 cursor-pointer" onclick="location.href='{{ route('activos.show', $a->id) }}'">
                        <td class="px-4 py-2"><span class="font-mono text-xs text-gray-400">{{ $a->codigo }}</span> <span class="font-semibold">{{ $a->descripcion }}</span>
                            <span class="block text-xs text-gray-400">{{ $a->ubicacion ?: '' }}{{ $a->centro_codigo ? ' · centro '.$a->centro_codigo : '' }}{{ $a->de_baja ? ' · DE BAJA' : '' }}</span></td>
                        <td class="px-4 py-2 text-gray-600">{{ $categorias[$a->categoria][0] ?? $a->categoria }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">{{ $soles($a->costo) }}</td>
                        <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($a->tasa, 2), '0'), '.') }}%</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">{{ $soles($a->del_ejercicio) }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap text-amber-700">{{ $soles($a->acumulada) }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap font-bold">{{ $soles($a->neto) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">Registra tus activos con “+ Nuevo activo”.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
