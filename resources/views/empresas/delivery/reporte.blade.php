@extends('layouts.app')
@section('title', 'Reporte Delivery')
@section('content')
    @php
        $s = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $m = fn ($n) => $n === null ? '–' : $n.' min';
    @endphp

    <div class="max-w-6xl space-y-5">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[240px]">
                <h1 class="text-2xl font-extrabold text-gray-800">⏱️ Reporte de delivery</h1>
                <p class="text-sm text-gray-500">Tiempos promedio de cada paso. <b>Cocina</b>: del pedido a "Listo". <b>Espera</b>: de "Listo" a que sale el motorizado. <b>Viaje</b>: de la salida a la entrega.</p>
            </div>
            <form class="flex flex-wrap items-end gap-2 text-sm">
                <label>Desde<input type="date" name="desde" value="{{ $desde }}" class="block rounded-lg border-gray-300 text-sm"></label>
                <label>Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block rounded-lg border-gray-300 text-sm"></label>
                <button class="h-10 px-4 rounded-lg bg-indigo-600 text-white font-semibold">Ver</button>
            </form>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
            @foreach ([['Entregas', $general['pedidos'], $web.' por la carta QR'], ['Tiempo total', $m($general['total_min']), 'promedio'], ['Cocina', $m($general['cocina']), 'promedio'],
                       ['Espera', $m($general['espera']), 'promedio'], ['Viaje', $m($general['viaje']), 'promedio'], ['Más de 45 min', $general['tarde'], 'pedidos']] as [$titulo, $valor, $nota])
                <div class="bg-white rounded-2xl shadow-sm p-4">
                    <p class="text-xs text-gray-500">{{ $titulo }}</p>
                    <p class="text-2xl font-black text-gray-900">{{ $valor }}</p>
                    <p class="text-[11px] text-gray-400">{{ $nota }}</p>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <h2 class="px-5 pt-4 font-bold text-gray-800">Por motorizado</h2>
            <table class="w-full text-sm mt-2">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr><th class="p-3 text-left">Motorizado</th><th class="p-3 text-right">Entregas</th><th class="p-3 text-right">Espera</th><th class="p-3 text-right">Viaje</th>
                        <th class="p-3 text-right">Total</th><th class="p-3 text-right">+45 min</th><th class="p-3 text-right">Ventas</th><th class="p-3 text-right">Envíos</th></tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($porMotorizado as $nombre => $r)
                        <tr><td class="p-3 font-semibold">{{ $nombre }}</td><td class="p-3 text-right">{{ $r['pedidos'] }}</td><td class="p-3 text-right">{{ $m($r['espera']) }}</td>
                            <td class="p-3 text-right">{{ $m($r['viaje']) }}</td><td class="p-3 text-right">{{ $m($r['total_min']) }}</td>
                            <td class="p-3 text-right {{ $r['tarde'] ? 'text-rose-600 font-bold' : '' }}">{{ $r['tarde'] }}</td>
                            <td class="p-3 text-right">{{ $s($r['ventas']) }}</td><td class="p-3 text-right">{{ $s($r['envios']) }}</td></tr>
                    @empty
                        <tr><td colspan="8" class="p-6 text-center text-gray-400">No hay entregas en estas fechas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <h2 class="px-5 pt-4 font-bold text-gray-800">Entregas</h2>
            <table class="w-full text-sm mt-2">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr><th class="p-3 text-left">Pedido</th><th class="p-3 text-left">Entregado</th><th class="p-3 text-left">Motorizado</th><th class="p-3 text-right">Cocina</th>
                        <th class="p-3 text-right">Espera</th><th class="p-3 text-right">Viaje</th><th class="p-3 text-right">Total</th><th class="p-3 text-right">Importe</th></tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($pedidos as $p)
                        <tr>
                            <td class="p-3"><b>#{{ $p->id }}</b> {{ $p->cliente }} @if ($p->web)<span class="text-[10px] font-bold bg-emerald-100 text-emerald-700 rounded px-1">CARTA QR</span>@endif</td>
                            <td class="p-3 whitespace-nowrap">{{ $p->fecha }}</td><td class="p-3">{{ $p->motorizado }}</td>
                            <td class="p-3 text-right">{{ $m($p->cocina) }}</td><td class="p-3 text-right">{{ $m($p->espera) }}</td><td class="p-3 text-right">{{ $m($p->viaje) }}</td>
                            <td class="p-3 text-right font-bold {{ $p->total_min > 45 ? 'text-rose-600' : '' }}">{{ $m($p->total_min) }}</td>
                            <td class="p-3 text-right">{{ $s($p->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
