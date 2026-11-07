@extends('layouts.app')
@section('title', 'Inventario ciego N° '.$c->id)
@section('content')
@use('App\Support\ConteosCiegos')
@php
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.') ?: '0';
    $faltante = $lineas->where('valor', '<', 0)->sum('valor');
    $sobrante = $lineas->where('valor', '>', 0)->sum('valor');
    $conDiferencia = $lineas->filter(fn ($l) => abs($l->diferencia) > 0.0001);
@endphp
<div class="max-w-4xl mx-auto space-y-4">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-extrabold text-gray-800">🙈 Inventario ciego N° {{ $c->id }} <span class="text-sm font-bold text-gray-500">· {{ ConteosCiegos::ESTADOS[$c->estado][0] }}</span></h1>
                <p class="text-sm text-gray-600">{{ $almacen }} · contó <b>{{ $conto }}</b> el {{ \Carbon\Carbon::parse($c->created_at)->format('d/m/Y H:i') }}{{ $c->observaciones ? ' · '.$c->observaciones : '' }}</p>
            </div>
            <a href="{{ route('conteo_ciego.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">← Conteos</a>
        </div>
        <div class="grid grid-cols-3 gap-3 text-center">
            <div class="rounded-xl bg-gray-50 p-3"><p class="text-2xl font-extrabold text-gray-800">{{ $conDiferencia->count() }}<span class="text-sm text-gray-400">/{{ $lineas->count() }}</span></p><p class="text-xs text-gray-500">con diferencia</p></div>
            <div class="rounded-xl bg-rose-50 p-3"><p class="text-2xl font-extrabold text-rose-700">S/ {{ number_format(abs($faltante), 2) }}</p><p class="text-xs text-rose-700">faltante</p></div>
            <div class="rounded-xl bg-emerald-50 p-3"><p class="text-2xl font-extrabold text-emerald-700">S/ {{ number_format($sobrante, 2) }}</p><p class="text-xs text-emerald-700">sobrante</p></div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-xs uppercase text-gray-500 bg-gray-50"><tr>
                <th class="px-4 py-2 text-left">Producto</th><th class="px-3 py-2 text-right">Sistema</th><th class="px-3 py-2 text-right">Contado</th>
                <th class="px-3 py-2 text-right">Diferencia</th><th class="px-4 py-2 text-right">Valor S/</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($lineas as $l)
                    <tr class="{{ $l->diferencia < 0 ? 'bg-rose-50/50' : '' }}">
                        <td class="px-4 py-2 font-semibold text-gray-800">{{ $l->pronom }}</td>
                        <td class="px-3 py-2 text-right">{{ $num($l->sistema) }}</td>
                        <td class="px-3 py-2 text-right">{{ $num($l->contado) }}</td>
                        <td class="px-3 py-2 text-right font-bold {{ $l->diferencia < 0 ? 'text-rose-700' : ($l->diferencia > 0 ? 'text-emerald-700' : 'text-gray-400') }}">{{ $l->diferencia > 0 ? '+' : '' }}{{ $num($l->diferencia) }}</td>
                        <td class="px-4 py-2 text-right {{ $l->valor < 0 ? 'text-rose-700' : '' }}">{{ number_format($l->valor, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($c->estado === 'PENDIENTE')
        <div class="flex flex-wrap justify-end gap-2">
            <form method="POST" action="{{ route('conteo_ciego.descartar', $c->id) }}" onsubmit="return confirm('¿Descartar el conteo? El stock no cambia.')">@csrf
                <button class="h-12 px-5 rounded-xl bg-white ring-1 ring-gray-300 font-bold">Descartar (mandar a contar de nuevo)</button></form>
            <form method="POST" action="{{ route('conteo_ciego.aplicar', $c->id) }}" onsubmit="return confirm('¿Ajustar el stock con estas diferencias?')">@csrf
                <button class="h-12 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold">✔ Ajustar el stock</button></form>
        </div>
        <p class="text-xs text-right text-gray-400">Al ajustar se aplica la diferencia sobre el stock de ahora: lo vendido desde que se contó no se pierde.</p>
    @endif
</div>
@endsection
