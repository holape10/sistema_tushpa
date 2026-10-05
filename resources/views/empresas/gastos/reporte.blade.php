@extends('layouts.app')
@section('title', 'Reporte de Gastos')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'];
        $totMes = array_fill(1, 12, 0);
        foreach ($matriz as $c) { foreach ($c['meses'] as $m => $v) { $totMes[$m] += $v; } }
        $series = collect($matriz)->map(fn($c, $n) => ['label' => $n, 'backgroundColor' => $c['color'],
            'data' => array_map(fn($m) => $c['meses'][$m] ?? 0, range(1, 12))])->values();
    @endphp
    <div class="flex items-center gap-2 mb-4">
        <a href="{{ route('gastos.reporte', ['anio' => $anio - 1]) }}" class="w-9 h-9 rounded-xl bg-white shadow-sm flex items-center justify-center"><i class="fas fa-chevron-left"></i></a>
        <span class="px-4 py-2 rounded-xl bg-white shadow-sm font-bold">{{ $anio }}</span>
        <a href="{{ route('gastos.reporte', ['anio' => $anio + 1]) }}" class="w-9 h-9 rounded-xl bg-white shadow-sm flex items-center justify-center"><i class="fas fa-chevron-right"></i></a>
        <a href="{{ route('gastos.index') }}" class="ml-auto text-sm font-semibold text-indigo-600 hover:underline">← Gastos</a>
    </div>
    <div class="bg-white rounded-2xl shadow-sm p-4 mb-4"><canvas id="grafico" height="90"></canvas></div>
    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-700 text-white uppercase"><tr><th class="px-3 py-2 text-left">Categoría</th>@foreach ($meses as $m)<th class="px-2 py-2 text-right">{{ $m }}</th>@endforeach<th class="px-3 py-2 text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($matriz as $cat => $c)
                    <tr class="hover:bg-gray-50"><td class="px-3 py-1.5 font-semibold"><span class="inline-block w-2.5 h-2.5 rounded-full mr-1" style="background: {{ $c['color'] }}"></span>{{ $cat }}</td>
                        @for ($m = 1; $m <= 12; $m++)<td class="px-2 py-1.5 text-right">{{ isset($c['meses'][$m]) ? number_format($c['meses'][$m], 2) : '' }}</td>@endfor
                        <td class="px-3 py-1.5 text-right font-bold">{{ number_format(array_sum($c['meses']), 2) }}</td></tr>
                @empty
                    <tr><td colspan="14" class="px-4 py-10 text-center text-gray-400 text-sm">Sin gastos en {{ $anio }}.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-slate-100 font-bold"><tr><td class="px-3 py-2">TOTAL</td>@for ($m = 1; $m <= 12; $m++)<td class="px-2 py-2 text-right">{{ $totMes[$m] ? number_format($totMes[$m], 2) : '' }}</td>@endfor
                <td class="px-3 py-2 text-right">{{ number_format(array_sum($totMes), 2) }}</td></tr></tfoot>
        </table>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
        if (window.Chart) new Chart(document.getElementById('grafico'), {
            type: 'bar',
            data: { labels: @json($meses), datasets: @json($series) },
            options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10 } } }, scales: { x: { stacked: true }, y: { stacked: true, ticks: { callback: v => 'S/ ' + v } } } },
        });
    </script>
@endsection
