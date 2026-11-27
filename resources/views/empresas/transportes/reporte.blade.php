@extends('layouts.app')
@section('title', 'Reporte de viajes')
@section('content')
    @php
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $totCobrado = $salidas->sum('cobrado');
        $totGastos = $salidas->sum('gastos');
        $totAsientos = $salidas->sum('asientos');
    @endphp
    <div class="space-y-4">
        @include('empresas.transportes.partials.nav')
        @include('empresas.partials.alert')

        <form method="GET" class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-2">
            <label class="text-xs font-semibold text-gray-500">Desde<input type="date" name="desde" value="{{ $desde }}" class="mt-1 block h-10 rounded-lg border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-500">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="mt-1 block h-10 rounded-lg border-gray-300 text-sm"></label>
            <button class="h-10 px-4 rounded-xl bg-indigo-600 text-sm font-bold text-white">Ver</button>
        </form>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
            @foreach ([['Salidas', $salidas->count(), 'text-gray-800'], ['Ocupación', $totAsientos ? round($salidas->sum('vendidos') * 100 / $totAsientos).'%' : '—', 'text-indigo-700'],
                ['Cobrado', $soles($totCobrado), 'text-emerald-700'], ['Gastos', $soles($totGastos), 'text-rose-700'], ['Ganancia', $soles($totCobrado - $totGastos), 'text-gray-900']] as [$t, $v, $c])
                <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">{{ $t }}</p><p class="text-2xl font-black {{ $c }}">{{ $v }}</p></div>
            @endforeach
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-gray-500"><tr>
                    <th class="px-3 py-2 text-left">Salida</th><th class="px-3 py-2 text-left">Ruta</th><th class="px-3 py-2 text-left">Unidad</th>
                    <th class="px-3 py-2 text-right">Vendidos</th><th class="px-3 py-2 text-right">Subieron</th><th class="px-3 py-2 text-right">Cobrado</th>
                    <th class="px-3 py-2 text-left">Gastos</th><th class="px-3 py-2 text-right">Ganancia</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($salidas as $s)
                        <tr class="align-top">
                            <td class="px-3 py-2 font-bold whitespace-nowrap">{{ \Carbon\Carbon::parse($s->salida)->format('d/m H:i') }}</td>
                            <td class="px-3 py-2">{{ $s->origen }} → {{ $s->destino }}</td>
                            <td class="px-3 py-2">{{ $s->unidad }}</td>
                            <td class="px-3 py-2 text-right">{{ $s->vendidos }}/{{ $s->asientos }}</td>
                            <td class="px-3 py-2 text-right">{{ $s->subieron }}</td>
                            <td class="px-3 py-2 text-right font-bold text-emerald-700">{{ $soles($s->cobrado) }}</td>
                            <td class="px-3 py-2 min-w-[16rem]">
                                @foreach ($gastos[$s->id] ?? [] as $g)
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="flex-1">{{ $g->concepto }}</span><span class="font-bold">{{ $soles($g->monto) }}</span>
                                        <form method="POST" action="{{ route('transportes.gastos.quitar', $g->id) }}" onsubmit="return confirm('¿Quitar este gasto?')">@csrf
                                            <button class="text-rose-500" title="Quitar"><i class="fas fa-xmark"></i></button></form>
                                    </div>
                                @endforeach
                                <form method="POST" action="{{ route('transportes.gastos.guardar') }}" class="mt-1 flex gap-1">@csrf
                                    <input type="hidden" name="salida_id" value="{{ $s->id }}">
                                    <input name="concepto" required maxlength="80" placeholder="Combustible, peaje…" class="flex-1 h-8 rounded-lg border-gray-300 text-xs">
                                    <input name="monto" type="number" step="any" min="0.01" required placeholder="S/" class="w-20 h-8 rounded-lg border-gray-300 text-xs">
                                    <button class="h-8 px-2 rounded-lg bg-slate-800 text-xs font-bold text-white">+</button>
                                </form>
                            </td>
                            <td class="px-3 py-2 text-right font-black {{ $s->cobrado - $s->gastos < 0 ? 'text-rose-700' : 'text-gray-900' }}">{{ $soles($s->cobrado - $s->gastos) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-6 text-center text-gray-400">No hay salidas en esas fechas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($cajeros->isNotEmpty())
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <h2 class="font-black text-gray-800 mb-2">Por cajero</h2>
                <div class="grid sm:grid-cols-3 gap-2">
                    @foreach ($cajeros as $c)
                        <div class="rounded-xl bg-slate-50 p-3"><p class="font-bold text-gray-700">{{ $c->cajero }}</p>
                            <p class="text-sm text-gray-500">{{ $c->pasajes }} pasajes · <b class="text-emerald-700">{{ $soles($c->cobrado) }}</b></p></div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
