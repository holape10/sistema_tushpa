@extends('layouts.app')
@section('title', $codigo)
@section('content')
@use('App\Support\Requerimientos')
@php
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.') ?: '0';
    [$nombreEstado, $color] = Requerimientos::ESTADOS[$r->estado];
    $chip = ['amber' => 'bg-amber-50 text-amber-800 ring-amber-200', 'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'slate' => 'bg-slate-100 text-slate-500 ring-slate-200'];
    $pendiente = $r->estado === 'PENDIENTE';
    $puedeComprar = $pendiente && $atiende;
    $estimado = $lineas->sum(fn ($l) => (float) ($l->comprado ?? $l->cantidad) * (float) ($l->precio ?? $l->costo_actual));
@endphp
<div class="max-w-4xl mx-auto space-y-4" x-data="{ anular: false }">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase text-gray-400">Requerimiento del día</p>
                <h1 class="text-2xl font-extrabold text-gray-800">{{ $codigo }}
                    <span class="align-middle px-2.5 py-1 rounded-full text-xs font-bold ring-1 {{ $chip[$color] }}">{{ $nombreEstado }}</span></h1>
                <p class="text-sm text-gray-600">Para el <b>{{ \Carbon\Carbon::parse($r->fecha_para)->locale('es')->isoFormat('dddd D [de] MMMM') }}</b> · {{ $almacen }}
                    · pidió <b>{{ $pidio }}</b> el {{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y H:i') }}</p>
                @if ($r->observaciones)<p class="text-sm text-gray-600">📌 {{ $r->observaciones }}</p>@endif
                @if ($compra)<p class="text-sm text-emerald-700 font-semibold">✔ Comprado por {{ $atendio }}: compra {{ $compra->com_doc_ser }}-{{ $compra->com_doc_num }} por S/ {{ number_format((float) $compra->total_com, 2) }}</p>@endif
                @if ($r->estado === 'ANULADO')<p class="text-sm text-slate-500">Anulado por {{ $atendio }}: {{ $r->motivo_anulacion }}</p>@endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('requerimientos.imprimir', $r->id) }}" target="_blank" class="h-10 px-4 inline-flex items-center rounded-xl bg-gray-100 hover:bg-gray-200 text-sm font-bold">🖨 Imprimir</a>
                @if ($pendiente)
                    <button type="button" @click="anular = !anular" class="h-10 px-4 rounded-xl bg-white ring-1 ring-rose-200 text-rose-700 text-sm font-bold hover:bg-rose-50">Anular</button>
                @endif
            </div>
        </div>
        <form x-show="anular" x-cloak method="POST" action="{{ route('requerimientos.anular', $r->id) }}" class="flex flex-wrap gap-2">
            @csrf
            <input name="motivo" required maxlength="255" placeholder="¿Por qué se anula?" class="flex-1 min-w-56 h-10 rounded-xl border-gray-300 text-sm">
            <button class="h-10 px-4 rounded-xl bg-rose-600 text-white text-sm font-bold">Confirmar anulación</button>
        </form>
    </div>

    <form method="POST" action="{{ route('requerimientos.marcar', $r->id) }}" class="space-y-4">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm">
            @if ($puedeComprar)
                <p class="px-4 sm:px-5 pt-4 text-sm text-gray-600">🛒 En el mercado: pon <b>cuánto compraste</b> y <b>a cuánto</b> (precio por unidad, con IGV). Si no encontraste algo, pon 0.
                    Lo que dejes vacío se registra tal como se pidió.</p>
            @endif
            <div class="divide-y divide-gray-100">
                @foreach ($lineas->groupBy(fn ($l) => $l->cat_nom ?? 'Sin categoría') as $categoria => $grupo)
                    <p class="px-4 sm:px-5 pt-3 pb-1 text-xs font-bold uppercase text-gray-400">{{ $categoria }}</p>
                    @foreach ($grupo as $l)
                        <div class="flex flex-wrap sm:flex-nowrap items-center gap-3 px-4 sm:px-5 py-3">
                            <div class="flex-1 min-w-48">
                                <p class="font-bold text-gray-800">{{ $l->pronom }}: <span class="text-indigo-700">{{ $num($l->cantidad) }} {{ \App\Support\Recetas::nombreUnidad($l->umecod ?: 'NIU') }}</span></p>
                                <p class="text-xs text-gray-500">Había {{ $num($l->stock) }}{{ (float) $l->minimo > 0 ? ' · mín. '.$num($l->minimo) : '' }}{{ (float) $l->consumo_dia > 0 ? ' · se gasta '.$num($l->consumo_dia).'/día' : '' }}{{ $l->nota ? ' · '.$l->nota : '' }}</p>
                            </div>
                            @if ($puedeComprar)
                                <label class="text-xs text-gray-500">Compré
                                    <input type="number" step="0.001" min="0" name="lineas[{{ $l->id }}][comprado]" value="{{ $l->comprado !== null ? $num($l->comprado) : '' }}" placeholder="{{ $num($l->cantidad) }}" class="block w-24 h-10 rounded-xl border-gray-300 text-right font-bold"></label>
                                <label class="text-xs text-gray-500">Precio unit. S/
                                    <input type="number" step="0.01" min="0" name="lineas[{{ $l->id }}][precio]" value="{{ $l->precio !== null ? (float) $l->precio : '' }}" placeholder="{{ number_format((float) $l->costo_actual, 2, '.', '') }}" class="block w-28 h-10 rounded-xl border-gray-300 text-right"></label>
                            @elseif ($l->comprado !== null)
                                <span class="text-sm {{ (float) $l->comprado > 0 ? 'text-emerald-700' : 'text-rose-600' }} font-semibold">{{ (float) $l->comprado > 0 ? '✔ '.$num($l->comprado).($l->precio !== null ? ' a S/ '.number_format((float) $l->precio, 2) : '') : 'no se compró' }}</span>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
            <p class="px-4 sm:px-5 py-3 text-right text-sm text-gray-600 border-t border-gray-100">Costo aproximado: <b class="text-gray-800">S/ {{ number_format($estimado, 2) }}</b>
                <span class="text-xs text-gray-400">(con los precios marcados o el último costo)</span></p>
        </div>
        @if ($puedeComprar)
            <div class="sticky bottom-3 flex flex-wrap justify-end gap-2">
                <button name="registrar" value="0" class="h-12 px-5 rounded-xl bg-white ring-1 ring-gray-300 font-bold shadow">💾 Guardar lo comprado</button>
                <button name="registrar" value="1" class="h-12 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-lg">✔ Registrar como compra →</button>
            </div>
        @endif
    </form>
</div>
@endsection
