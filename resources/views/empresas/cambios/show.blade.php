@extends('layouts.app')
@section('title', 'Cambio '.$c->codigo)

@section('content')
{{-- Constancia del cambio de producto (se puede imprimir para el cliente) --}}
@php($soles = fn ($n) => 'S/ '.number_format((float) $n, 2))
<div class="max-w-xl mx-auto space-y-4">
    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3" id="constancia">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs uppercase font-bold text-gray-400">Cambio de producto</p>
                <h1 class="text-2xl font-black text-gray-800">{{ $c->codigo }}</h1>
                <p class="text-sm text-gray-500">{{ date('d/m/Y H:i', strtotime($c->created_at)) }} · de la venta {{ $c->serdoc }}-{{ $c->numdoc }} · {{ $c->ccanom }}</p>
            </div>
            <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 text-xs font-black">✓ HECHO</span>
        </div>
        <div>
            <p class="text-xs font-bold uppercase text-rose-600">Devolvió</p>
            @foreach ($c->detalle->where('tipo', 'DEVUELVE') as $d)
                <p class="flex justify-between text-sm"><span>{{ rtrim(rtrim(number_format($d->cantidad, 3), '0'), '.') }} × {{ $d->descripcion }}</span><span>{{ $soles($d->total) }}</span></p>
            @endforeach
        </div>
        <div>
            <p class="text-xs font-bold uppercase text-emerald-700">Se llevó</p>
            @foreach ($c->detalle->where('tipo', 'LLEVA') as $d)
                <p class="flex justify-between text-sm"><span>{{ rtrim(rtrim(number_format($d->cantidad, 3), '0'), '.') }} × {{ $d->descripcion }}</span><span>{{ $soles($d->total) }}</span></p>
            @endforeach
        </div>
        <div class="border-t pt-2 flex justify-between font-black">
            <span>Diferencia pagada</span><span>{{ $soles($c->diferencia) }}</span>
        </div>
        @if ($c->dif_serie)<p class="text-xs text-gray-500">Comprobante de la diferencia: <b>{{ $c->dif_serie }}-{{ $c->dif_numero }}</b></p>@endif
        @if ($c->motivo)<p class="text-xs text-gray-500">Motivo: {{ $c->motivo }}</p>@endif
    </div>
    <div class="flex flex-wrap gap-2 print:hidden">
        <button type="button" onclick="window.print()" class="h-11 px-5 rounded-xl bg-indigo-600 text-white font-bold">🖨 Imprimir constancia</button>
        <a href="{{ route('ventas.index') }}" class="h-11 px-5 inline-flex items-center rounded-xl bg-gray-100 text-gray-700 font-bold">← Panel de Ventas</a>
    </div>
</div>
@endsection
