@extends('layouts.app')
@section('title', 'Arqueo · Turno '.$turno->turno)
@section('content')
@php
    $nombre = fn ($campo) => ($v = $denominaciones[$campo]) < 1 ? number_format($v * 100).' céntimos' : ($v < 10 ? 'S/ '.$v.' (moneda)' : 'S/ '.$v.' (billete)');
@endphp
<div class="max-w-3xl mx-auto space-y-4" x-data="{ billetes: {}, valores: @js($denominaciones), monto: @js(old('monto', '')),
        get suma() { return Object.entries(this.billetes).reduce((s, [c, n]) => s + (Number(n) || 0) * this.valores[c], 0); } }">
    @include('empresas.partials.alert')

    <div class="bg-white rounded-2xl shadow-sm p-5">
        <a href="{{ route('arqueos.index') }}" class="text-sm text-indigo-600 font-semibold">← Arqueos</a>
        <h1 class="text-xl font-extrabold text-gray-800 mt-1">🧮 Arqueo del turno N° {{ $turno->turno }} · {{ $turno->usuario->apeusu ?? '' }}</h1>
        <p class="text-sm text-gray-500">Cuenta el efectivo que hay ahora en la caja. El turno <b>sigue abierto</b>: se puede seguir vendiendo.</p>
        @if ($r)
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 mt-4 text-center">
                @foreach ([['Fondo', $turno->monto], ['Ventas en efectivo', $r['ventasEfectivo']], ['Ingresos', $r['ingresos']], ['Egresos', -$r['egresos']], ['Debe haber', $r['efectivoEsperado']]] as [$t, $v])
                    <div class="rounded-xl {{ $loop->last ? 'bg-emerald-50 text-emerald-800' : 'bg-gray-50 text-gray-700' }} p-2">
                        <p class="text-[11px] uppercase">{{ $t }}</p><p class="font-extrabold">S/ {{ number_format($v, 2) }}</p></div>
                @endforeach
            </div>
        @else
            <p class="mt-3 rounded-xl bg-slate-800 text-white px-4 py-2 text-sm">🙈 Cierre ciego: cuenta y registra; cuánto debía haber lo ve el administrador.</p>
        @endif
    </div>

    <form method="POST" action="{{ route('arqueos.store', $turno->id_turno) }}" class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
        @csrf
        <p class="text-sm font-bold text-gray-700">Billetes y monedas <span class="font-normal text-gray-400">(cuántos de cada uno)</span></p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            @foreach ($denominaciones as $campo => $valor)
                <label class="flex items-center justify-between gap-2 rounded-xl ring-1 ring-gray-200 px-3 py-2 text-sm">
                    <span class="text-gray-600">{{ $nombre($campo) }}</span>
                    <input type="number" min="0" step="1" name="billetes[{{ $campo }}]" x-model="billetes['{{ $campo }}']" value="{{ old('billetes.'.$campo) }}"
                           class="w-20 h-9 rounded-lg border-gray-300 text-right font-bold">
                </label>
            @endforeach
        </div>
        <p class="text-right text-lg">Total contado: <b class="text-indigo-700" x-text="'S/ ' + suma.toFixed(2)"></b></p>
        <label class="block text-sm text-gray-600" x-show="suma === 0">O escribe solo el total contado
            <input type="number" step="0.01" min="0" name="monto" x-model="monto" class="mt-1 w-48 h-10 rounded-xl border-gray-300 text-right font-bold"></label>
        <label class="block text-sm text-gray-600">Observación <span class="text-gray-400">(opcional)</span>
            <input name="observacion" maxlength="300" value="{{ old('observacion') }}" placeholder="Ej.: arqueo sorpresa, cambio de cajero" class="mt-1 w-full h-10 rounded-xl border-gray-300"></label>
        <div class="flex justify-end">
            <button class="h-12 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold">Registrar arqueo</button>
        </div>
    </form>
</div>
@endsection
