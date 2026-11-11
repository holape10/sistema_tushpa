@extends('layouts.app')
@section('title', 'Arqueo · Turno '.$turno->turno)
@section('content')
@php
    $nombre = fn ($campo) => ($v = $denominaciones[$campo]) < 1 ? number_format($v * 100).' céntimos' : ($v < 10 ? 'S/ '.$v.' (moneda)' : 'S/ '.$v.' (billete)');
@endphp
<div class="max-w-6xl mx-auto space-y-4" x-data="{ billetes: {}, valores: @js($denominaciones), monto: @js(old('monto', '')), esperado: @js($foto['esperado'] ?? null),
        get suma() { return Object.entries(this.billetes).reduce((s, [c, n]) => s + (Number(n) || 0) * this.valores[c], 0); },
        get contado() { return this.suma > 0 ? this.suma : (Number(this.monto) || 0); } }">
    @include('empresas.partials.alert')

    <div class="bg-white rounded-2xl shadow-sm p-5">
        <a href="{{ route('arqueos.index') }}" class="text-sm text-indigo-600 font-semibold">← Arqueos</a>
        <h1 class="text-xl font-extrabold text-gray-800 mt-1">🧮 Arqueo del turno N° {{ $turno->turno }} · {{ $turno->usuario->apeusu ?? '' }}</h1>
        <p class="text-sm text-gray-500">Abierto el {{ $turno->apertura->format('d/m/Y H:i') }}. Cuenta el efectivo que hay ahora en la caja: el turno <b>sigue abierto</b> y se puede seguir vendiendo.</p>
    </div>

    <div class="grid lg:grid-cols-5 gap-4 items-start">
        {{-- Conteo --}}
        <form method="POST" action="{{ route('arqueos.store', $turno->id_turno) }}" class="lg:col-span-2 bg-white rounded-2xl shadow-sm p-5 space-y-3 lg:sticky lg:top-4">
            @csrf
            <p class="text-sm font-bold text-gray-700">Billetes y monedas <span class="font-normal text-gray-400">(cuántos de cada uno)</span></p>
            <div class="grid grid-cols-2 gap-2">
                @foreach (array_reverse($denominaciones, true) as $campo => $valor)
                    <label class="flex items-center justify-between gap-2 rounded-xl ring-1 ring-gray-200 px-3 py-1.5 text-sm">
                        <span class="text-gray-600 text-xs">{{ $nombre($campo) }}</span>
                        <input type="number" min="0" step="1" name="billetes[{{ $campo }}]" x-model="billetes['{{ $campo }}']" value="{{ old('billetes.'.$campo) }}"
                               class="w-16 h-8 rounded-lg border-gray-300 text-right font-bold">
                    </label>
                @endforeach
            </div>
            <label class="block text-sm text-gray-600" x-show="suma === 0">O escribe solo el total contado
                <input type="number" step="0.01" min="0" name="monto" x-model="monto" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-right font-bold"></label>
            <div class="rounded-xl bg-gray-50 p-3 space-y-1 text-sm">
                <p class="flex justify-between"><span>Contado</span><b class="text-indigo-700" x-text="'S/ ' + contado.toFixed(2)"></b></p>
                <template x-if="esperado !== null">
                    <div class="space-y-1">
                        <p class="flex justify-between"><span>Debe haber</span><b x-text="'S/ ' + Number(esperado).toFixed(2)"></b></p>
                        <p class="flex justify-between font-extrabold" :class="contado - esperado < -0.004 ? 'text-rose-600' : (contado - esperado > 0.004 ? 'text-amber-600' : 'text-emerald-600')">
                            <span x-text="Math.abs(contado - esperado) < 0.005 ? 'Cuadra' : (contado < esperado ? 'Falta' : 'Sobra')"></span>
                            <span x-text="'S/ ' + Math.abs(contado - esperado).toFixed(2)"></span></p>
                    </div>
                </template>
            </div>
            <label class="block text-sm text-gray-600">Observación <span class="text-gray-400">(opcional)</span>
                <input name="observacion" maxlength="300" value="{{ old('observacion') }}" placeholder="Ej.: arqueo sorpresa, cambio de cajero" class="mt-1 w-full h-10 rounded-xl border-gray-300"></label>
            <button class="w-full h-12 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold">Registrar arqueo</button>
        </form>

        {{-- Resumen de la caja ahora --}}
        <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm p-5">
            @if ($foto)
                <p class="text-sm font-bold text-gray-700 mb-3">Cómo está la caja ahora</p>
                @include('empresas.arqueos._resumen', ['f' => $foto])
            @else
                <p class="rounded-xl bg-slate-800 text-white px-4 py-3 text-sm">🙈 Cierre ciego: cuenta y registra. Lo vendido, cuánto debía haber y la diferencia los ve el administrador.</p>
            @endif
        </div>
    </div>
</div>
@endsection
