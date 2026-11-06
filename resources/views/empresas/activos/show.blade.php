@extends('layouts.app')
@section('title', $a->codigo.' '.$a->descripcion)
@section('content')
@php
    $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
    $neto = round((float) $a->costo - $acumulado, 2);
    $avance = (float) $a->costo > 0 ? min(100, round($acumulado / ((float) $a->costo - (float) $a->valor_residual ?: 1) * 100)) : 0;
@endphp
<div class="max-w-5xl mx-auto space-y-4">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 flex flex-wrap items-start gap-3">
        <div class="flex-1 min-w-56">
            <a href="{{ route('activos.index') }}" class="text-xs font-semibold text-indigo-700 hover:underline">← Activos fijos</a>
            <h1 class="text-xl font-extrabold text-gray-800"><span class="font-mono text-gray-400">{{ $a->codigo }}</span> {{ $a->descripcion }}
                @if ($a->estado === 'BAJA')<span class="ml-1 align-middle rounded-full bg-gray-200 text-gray-600 px-2 py-0.5 text-xs font-bold">DE BAJA</span>@endif</h1>
            <p class="text-sm text-gray-500">{{ $categorias[$a->categoria][0] ?? $a->categoria }}{{ $a->marca_modelo ? ' · '.$a->marca_modelo : '' }}{{ $a->serie ? ' · serie '.$a->serie : '' }}</p>
            <p class="text-xs text-gray-400">Comprado el {{ \Carbon\Carbon::parse($a->fecha_adquisicion)->format('d/m/Y') }}{{ $a->documento ? ' ('.$a->documento.')' : '' }} · en uso desde
                {{ \Carbon\Carbon::parse($a->fecha_uso)->format('d/m/Y') }}{{ $a->ubicacion ? ' · '.$a->ubicacion : '' }}{{ $a->responsable ? ' · '.$a->responsable : '' }}{{ $centro ? ' · centro '.$centro->codigo.' '.$centro->nombre : '' }}</p>
        </div>
        @if ($a->estado === 'ACTIVO')<a href="{{ route('activos.edit', $a->id) }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-white ring-1 ring-gray-300 text-sm font-semibold hover:bg-gray-50">Editar</a>@endif
    </div>

    <div class="grid gap-3 sm:grid-cols-4">
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-bold uppercase text-gray-500">Costo</p><p class="text-xl font-extrabold">{{ $soles($a->costo) }}</p>
            <p class="text-xs text-gray-400">Cuenta {{ $a->cuenta_activo }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-bold uppercase text-gray-500">Cuota mensual</p><p class="text-xl font-extrabold">{{ $soles($cuota) }}</p>
            <p class="text-xs text-gray-400">{{ rtrim(rtrim(number_format($a->tasa, 2), '0'), '.') }}% al año · {{ $a->cuenta_gasto }} / {{ $a->cuenta_depreciacion }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-bold uppercase text-gray-500">Depreciado</p><p class="text-xl font-extrabold text-amber-700">{{ $soles($acumulado) }}</p>
            <div class="mt-1 h-2 rounded-full bg-gray-100"><div class="h-2 rounded-full bg-amber-500" style="width: {{ $avance }}%"></div></div></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-bold uppercase text-gray-500">Valor neto</p><p class="text-xl font-extrabold text-indigo-700">{{ $soles($neto) }}</p></div>
    </div>

    @if ($a->estado === 'BAJA')
        <div class="rounded-xl bg-gray-100 text-gray-700 px-4 py-3 text-sm">Dado de baja el {{ \Carbon\Carbon::parse($a->fecha_baja)->format('d/m/Y') }}: {{ $a->motivo_baja }}</div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm">
        <p class="px-4 py-3 font-bold text-gray-700 border-b border-gray-100">Depreciación mes a mes</p>
        <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto text-sm">
            @if ((float) $a->depreciacion_inicial > 0)
                <div class="px-4 py-2 flex justify-between"><span class="text-gray-500">Depreciado antes del sistema</span><span class="font-semibold">{{ $soles($a->depreciacion_inicial) }}</span></div>
            @endif
            @forelse ($meses as $m)
                <div class="px-4 py-2 flex justify-between"><span>{{ \App\Support\Contabilidad\Contabilidad::nombrePeriodo($m->periodo) }}</span><span class="font-semibold">{{ $soles($m->monto) }}</span></div>
            @empty
                <p class="px-4 py-6 text-center text-gray-400">Aún no se procesó la depreciación de ningún mes. Hazlo desde la lista de activos.</p>
            @endforelse
        </div>
    </div>

    @if ($a->estado === 'ACTIVO')
        <details class="bg-white rounded-2xl shadow-sm">
            <summary class="cursor-pointer list-none px-4 py-3 text-sm font-semibold text-rose-600">Dar de baja (se vendió, se malogró o se perdió) ▾</summary>
            <form method="POST" action="{{ route('activos.baja', $a->id) }}" class="px-4 pb-4 flex flex-wrap items-end gap-2"
                  onsubmit="return confirm('¿Dar de baja este activo? Se crea su asiento y deja de depreciarse.')">
                @csrf
                <label class="text-xs font-semibold text-gray-600">Fecha<input type="date" name="fecha" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required class="mt-1 block h-10 rounded-xl border-gray-300 text-sm"></label>
                <label class="flex-1 min-w-56 text-xs font-semibold text-gray-600">Motivo<input name="motivo" required minlength="4" maxlength="255" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
                <button class="h-10 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-bold">Dar de baja</button>
                <p class="w-full text-xs text-gray-400">Asiento: sale el costo ({{ $a->cuenta_activo }}), se cancela lo depreciado ({{ $a->cuenta_depreciacion }}) y el valor que faltaba depreciar va a gasto (659).</p>
            </form>
        </details>
    @endif
</div>
@endsection
