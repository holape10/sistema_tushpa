@extends('layouts.app')
@section('title', 'Granja')
@section('content')
    @include('empresas.partials.alert')

    <details class="mb-4 rounded-2xl bg-white shadow-sm" @if ($lotes->isEmpty()) open @endif>
        <summary class="cursor-pointer px-5 py-3 font-bold text-gray-800"><i class="fas fa-egg text-yellow-500"></i> + Nuevo lote (galpón)</summary>
        <form method="POST" action="{{ route('avicola.granja.crear') }}" class="px-5 pb-5 grid sm:grid-cols-4 gap-3">
            @csrf
            <label class="text-xs font-semibold text-gray-500">Tipo<select name="tipo" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-sm">
                @foreach (\App\Support\AvicolaGranja::TIPOS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-gray-500">Código del lote<input name="codigo" maxlength="30" placeholder="Ej. L-2026-11" class="mt-1 block w-full h-10 rounded-xl border-gray-300 uppercase"></label>
            <label class="text-xs font-semibold text-gray-500">Galpón<input name="galpon" maxlength="60" placeholder="Ej. Galpón 1" class="mt-1 block w-full h-10 rounded-xl border-gray-300"></label>
            <label class="text-xs font-semibold text-gray-500">Línea / raza<input name="linea" maxlength="60" placeholder="Ej. Cobb 500" class="mt-1 block w-full h-10 rounded-xl border-gray-300"></label>
            <label class="text-xs font-semibold text-gray-500">Fecha de ingreso<input type="date" name="fecha_ingreso" value="{{ now()->toDateString() }}" required class="mt-1 block w-full h-10 rounded-xl border-gray-300"></label>
            <label class="text-xs font-semibold text-gray-500">Aves que ingresan<input type="number" min="1" name="aves_iniciales" required class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-center font-bold"></label>
            <label class="text-xs font-semibold text-gray-500">Costo por ave (pollito BB) S/<input type="number" step="0.01" min="0" name="costo_ave" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-right"></label>
            <div class="flex items-end"><button class="w-full h-10 rounded-xl bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-bold">Crear lote</button></div>
        </form>
    </details>

    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse ($lotes as $l)
            <a href="{{ route('avicola.lote', $l->id) }}" class="block bg-white rounded-2xl shadow-sm p-5 hover:ring-2 hover:ring-yellow-300 {{ $l->estado === 'CERRADO' ? 'opacity-60' : '' }}">
                <div class="flex items-start justify-between">
                    <div><p class="text-lg font-extrabold text-gray-800">{{ $l->codigo }}</p>
                        <p class="text-xs text-gray-500">{{ $l->tipo === 'POSTURA' ? '🥚 Postura' : '🐔 Engorde' }}{{ $l->galpon ? ' · '.$l->galpon : '' }}{{ $l->linea ? ' · '.$l->linea : '' }}</p></div>
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $l->estado === 'ABIERTO' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">{{ $l->estado }}</span>
                </div>
                <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-sm">
                    <div class="rounded-xl bg-gray-50 p-2"><dt class="text-[11px] text-gray-500">Edad</dt><dd class="font-bold">{{ $l->r['edad_dias'] }} días</dd></div>
                    <div class="rounded-xl bg-gray-50 p-2"><dt class="text-[11px] text-gray-500">Aves vivas</dt><dd class="font-bold">{{ number_format($l->r['aves_vivas']) }}</dd></div>
                    <div class="rounded-xl {{ $l->r['mortalidad_pct'] > 5 ? 'bg-rose-50 text-rose-700' : 'bg-gray-50' }} p-2"><dt class="text-[11px]">Mortalidad</dt><dd class="font-bold">{{ $l->r['mortalidad_pct'] }}%</dd></div>
                    @if ($l->tipo === 'POSTURA')
                        <div class="rounded-xl bg-yellow-50 p-2"><dt class="text-[11px] text-yellow-700">Huevos</dt><dd class="font-bold">{{ number_format($l->r['huevos']) }}</dd></div>
                        <div class="rounded-xl bg-yellow-50 p-2"><dt class="text-[11px] text-yellow-700">Postura</dt><dd class="font-bold">{{ $l->r['postura_pct'] !== null ? $l->r['postura_pct'].'%' : '—' }}</dd></div>
                    @else
                        <div class="rounded-xl bg-gray-50 p-2"><dt class="text-[11px] text-gray-500">Peso prom.</dt><dd class="font-bold">{{ $l->r['peso_promedio'] !== null ? number_format($l->r['peso_promedio'], 3).' kg' : '—' }}</dd></div>
                        <div class="rounded-xl bg-gray-50 p-2"><dt class="text-[11px] text-gray-500">Conversión</dt><dd class="font-bold">{{ $l->r['conversion'] ?? '—' }}</dd></div>
                    @endif
                    <div class="rounded-xl bg-gray-50 p-2"><dt class="text-[11px] text-gray-500">Costo</dt><dd class="font-bold">S/ {{ number_format($l->r['costo_total'], 0) }}</dd></div>
                </dl>
            </a>
        @empty
            <p class="text-gray-400">Aún no tienes lotes. Crea el primero arriba.</p>
        @endforelse
    </div>
@endsection
