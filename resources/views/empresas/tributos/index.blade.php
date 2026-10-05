@extends('layouts.app')
@section('title', 'Resumen Tributario')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')
    @php
        $s = fn($n) => 'S/ ' . number_format((float) $n, 2);
        $ultimo = end($meses) ?: null;
        $esNrus = $cfg->regimen === 'NRUS';
        $etiquetas = array_map(fn($m) => mb_substr($m['nombre'], 0, 3), $meses);
        $egresos = array_map(fn($m) => round($m['compras'] + $m['gastos'] + $m['remuneraciones'] + $m['essalud'], 2), $meses);
    @endphp

    <div x-data="{ config: {{ $errors->any() ? 'true' : 'false' }} }">
        {{-- Encabezado --}}
        <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-4">
            <div class="flex items-center gap-2">
                <a href="{{ route('tributos.index', ['anio' => $anio - 1]) }}" class="w-9 h-9 rounded-xl bg-white shadow-sm flex items-center justify-center"><i class="fas fa-chevron-left"></i></a>
                <span class="px-4 py-2 rounded-xl bg-white shadow-sm font-black">{{ $anio }}</span>
                <a href="{{ route('tributos.index', ['anio' => $anio + 1]) }}" class="w-9 h-9 rounded-xl bg-white shadow-sm flex items-center justify-center"><i class="fas fa-chevron-right"></i></a>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="px-3 py-1.5 rounded-full bg-indigo-100 text-indigo-800 font-bold"><i class="fas fa-landmark"></i> {{ $regimenes[$cfg->regimen] }}</span>
                @if ($cfg->exonerado_igv)<span class="px-3 py-1.5 rounded-full bg-teal-100 text-teal-800 font-bold">Exonerado de IGV</span>@endif
                <span class="text-gray-500">UIT {{ $anio }}: S/ {{ number_format($uit, 0) }}</span>
            </div>
            <div class="flex gap-2 lg:ml-auto">
                <button type="button" @click="config = !config" class="px-4 py-2 rounded-xl bg-white shadow-sm text-sm font-semibold text-gray-700"><i class="fas fa-gear"></i> Régimen</button>
                <a href="{{ request()->fullUrlWithQuery(['excel' => 1]) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold"><i class="fas fa-file-excel"></i> Excel</a>
            </div>
        </div>

        {{-- Configuración --}}
        <form x-show="config" x-cloak method="POST" action="{{ route('tributos.config') }}" class="bg-white rounded-2xl shadow-sm p-4 mb-4 grid sm:grid-cols-2 lg:grid-cols-5 gap-3 text-sm items-end">
            @csrf
            <label class="lg:col-span-2">Régimen tributario
                <select name="regimen" class="block w-full mt-1 rounded-lg border-gray-300">
                    @foreach ($regimenes as $k => $v)<option value="{{ $k }}" @selected($cfg->regimen === $k)>{{ $v }}</option>@endforeach
                </select></label>
            <label>Coeficiente de pago a cuenta<input type="number" step="0.0001" name="coeficiente" value="{{ $cfg->coeficiente }}" placeholder="Vacío = 1.5 %" class="block w-full mt-1 rounded-lg border-gray-300"></label>
            <label>Saldo a favor IGV al inicio<input type="number" step="0.01" name="saldo_favor_inicial" value="{{ $cfg->saldo_favor_inicial }}" class="block w-full mt-1 rounded-lg border-gray-300"></label>
            <label class="flex items-center gap-2 pb-2"><input type="checkbox" name="exonerado_igv" value="1" @checked($cfg->exonerado_igv) class="rounded"> Exonerado de IGV (Amazonía)</label>
            <button class="sm:col-span-2 lg:col-span-5 justify-self-start px-5 py-2 rounded-xl bg-indigo-600 text-white font-semibold">Guardar</button>
        </form>

        @foreach ($alertas as $a)
            <div class="mb-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm"><i class="fas fa-triangle-exclamation"></i> {{ $a }}</div>
        @endforeach

        {{-- Mes actual --}}
        @if ($ultimo)
            <div class="rounded-2xl bg-gradient-to-br from-slate-800 to-indigo-900 text-white p-5 mb-4">
                <p class="text-xs uppercase tracking-wider text-indigo-200">Estimado a pagar por {{ $ultimo['nombre'] }} {{ $anio }}</p>
                <p class="text-4xl font-black mt-1">{{ $s($ultimo['total_pagar']) }}</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-sm">
                    <div class="rounded-xl bg-white/10 p-3"><p class="text-indigo-200 text-xs">IGV {{ $cfg->exonerado_igv || $esNrus ? '(no aplica)' : '' }}</p><p class="text-xl font-bold">{{ $s($ultimo['igv_pagar']) }}</p>
                        @if ($ultimo['saldo_favor'] > 0)<p class="text-xs text-emerald-300">Saldo a favor: {{ $s($ultimo['saldo_favor']) }}</p>@endif</div>
                    <div class="rounded-xl bg-white/10 p-3"><p class="text-indigo-200 text-xs">{{ $esNrus ? 'Cuota Nuevo RUS' : 'Renta (pago a cuenta)' }} · {{ $ultimo['renta_tasa'] }}</p><p class="text-xl font-bold">{{ $s($ultimo['renta']) }}</p></div>
                    <div class="rounded-xl bg-white/10 p-3"><p class="text-indigo-200 text-xs">Planilla (EsSalud, ONP, 5ta)</p><p class="text-xl font-bold">{{ $s($ultimo['essalud'] + $ultimo['onp'] + $ultimo['quinta']) }}</p></div>
                    <div class="rounded-xl bg-white/10 p-3"><p class="text-indigo-200 text-xs">Retención 4ta (honorarios)</p><p class="text-xl font-bold">{{ $s($ultimo['cuarta']) }}</p></div>
                </div>
            </div>
        @endif

        <div class="grid lg:grid-cols-3 gap-4 mb-4">
            <div class="bg-white rounded-2xl shadow-sm p-4 lg:col-span-2"><p class="font-bold text-gray-700 mb-2">Ventas vs. gastos {{ $anio }}</p><canvas id="grafico" height="120"></canvas></div>
            <div class="space-y-3">
                <div class="bg-white rounded-2xl shadow-sm p-4">
                    <p class="text-xs text-gray-500">Ventas netas del año</p><p class="text-2xl font-black text-emerald-700">{{ $s($tot['ventas'] ?? 0) }}</p>
                    <p class="text-xs text-gray-500 mt-2">Costo de ventas + gastos + planilla</p>
                    <p class="text-lg font-bold text-rose-600">{{ $s(($tot['costo_ventas'] ?? 0) + ($tot['gastos'] ?? 0) + ($tot['igv_sin_credito'] ?? 0) + ($tot['remuneraciones'] ?? 0) + ($tot['essalud'] ?? 0)) }}</p>
                    <p class="text-xs text-gray-500 mt-2">Utilidad estimada</p>
                    <p class="text-2xl font-black {{ ($tot['utilidad'] ?? 0) < 0 ? 'text-rose-600' : 'text-indigo-700' }}">{{ $s($tot['utilidad'] ?? 0) }}</p>
                </div>
                @if ($anual)
                    <div class="bg-white rounded-2xl shadow-sm p-4 text-sm">
                        <p class="font-bold text-gray-700 mb-2">Impuesto a la Renta anual (proyección)</p>
                        <div class="flex justify-between"><span>Utilidad tributaria estimada</span><span class="font-semibold">{{ $s($anual['utilidad']) }}</span></div>
                        <div class="flex justify-between"><span>Impuesto ({{ $cfg->regimen === 'RMT' ? '10 % hasta 15 UIT, 29.5 % exceso' : '29.5 %' }})</span><span class="font-semibold">{{ $s($anual['impuesto']) }}</span></div>
                        <div class="flex justify-between"><span>(−) Pagos a cuenta del año</span><span class="font-semibold">{{ $s($anual['pagos']) }}</span></div>
                        <div class="flex justify-between border-t mt-1 pt-1 font-black {{ $anual['regularizar'] > 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                            <span>{{ $anual['regularizar'] > 0 ? 'Por pagar en la declaración anual' : 'Saldo a favor' }}</span><span>{{ $s(abs($anual['regularizar'])) }}</span></div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Detalle mensual --}}
        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="text-white uppercase">
                    <tr class="bg-slate-800"><th></th><th colspan="2" class="px-2 py-1 border-l border-slate-600">Ventas</th><th colspan="3" class="px-2 py-1 border-l border-slate-600">Compras y gastos</th>
                        <th colspan="2" class="px-2 py-1 border-l border-slate-600">IGV</th><th class="px-2 py-1 border-l border-slate-600">Renta</th><th colspan="2" class="px-2 py-1 border-l border-slate-600">Laborales</th><th colspan="2" class="border-l border-slate-600"></th></tr>
                    <tr class="bg-slate-700">
                        <th class="px-2 py-2 text-left">Mes</th><th class="px-2 py-2 text-right border-l border-slate-600">Base</th><th class="px-2 py-2 text-right">IGV</th>
                        <th class="px-2 py-2 text-right border-l border-slate-600">Mercadería</th><th class="px-2 py-2 text-right">Gastos</th><th class="px-2 py-2 text-right">Crédito fiscal</th>
                        <th class="px-2 py-2 text-right border-l border-slate-600">A pagar</th><th class="px-2 py-2 text-right">Saldo a favor</th>
                        <th class="px-2 py-2 text-right border-l border-slate-600">{{ $esNrus ? 'Cuota' : 'Pago a cuenta' }}</th>
                        <th class="px-2 py-2 text-right border-l border-slate-600">EsSalud+ONP+5ta</th><th class="px-2 py-2 text-right">4ta</th>
                        <th class="px-2 py-2 text-right border-l border-slate-600">Total a pagar</th><th class="px-2 py-2 text-right">Utilidad</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($meses as $m)
                        <tr class="hover:bg-gray-50">
                            <td class="px-2 py-1.5 font-semibold">{{ $m['nombre'] }}</td>
                            <td class="px-2 py-1.5 text-right border-l">{{ number_format($m['ventas'], 2) }}</td><td class="px-2 py-1.5 text-right">{{ number_format($m['igv_ventas'], 2) }}</td>
                            <td class="px-2 py-1.5 text-right border-l">{{ number_format($m['compras'], 2) }}</td><td class="px-2 py-1.5 text-right">{{ number_format($m['gastos'], 2) }}</td>
                            <td class="px-2 py-1.5 text-right">{{ number_format($m['credito'], 2) }}</td>
                            <td class="px-2 py-1.5 text-right border-l font-semibold">{{ number_format($m['igv_pagar'], 2) }}</td><td class="px-2 py-1.5 text-right text-emerald-700">{{ $m['saldo_favor'] ? number_format($m['saldo_favor'], 2) : '' }}</td>
                            <td class="px-2 py-1.5 text-right border-l font-semibold">{{ number_format($m['renta'], 2) }}<span class="block text-[10px] text-gray-400">{{ $m['renta_tasa'] }}</span></td>
                            <td class="px-2 py-1.5 text-right border-l">{{ number_format($m['essalud'] + $m['onp'] + $m['quinta'], 2) }}</td><td class="px-2 py-1.5 text-right">{{ number_format($m['cuarta'], 2) }}</td>
                            <td class="px-2 py-1.5 text-right border-l font-black text-indigo-700">{{ number_format($m['total_pagar'], 2) }}</td>
                            <td class="px-2 py-1.5 text-right {{ $m['utilidad'] < 0 ? 'text-rose-600' : '' }}">{{ number_format($m['utilidad'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="px-4 py-10 text-center text-gray-400 text-sm">Sin movimientos en {{ $anio }}.</td></tr>
                    @endforelse
                </tbody>
                @if ($meses)
                    <tfoot class="bg-slate-100 font-bold">
                        <tr><td class="px-2 py-2">TOTAL</td>
                            <td class="px-2 py-2 text-right border-l">{{ number_format($tot['ventas'], 2) }}</td><td class="px-2 py-2 text-right">{{ number_format($tot['igv_ventas'], 2) }}</td>
                            <td class="px-2 py-2 text-right border-l">{{ number_format($tot['compras'], 2) }}</td><td class="px-2 py-2 text-right">{{ number_format($tot['gastos'], 2) }}</td><td class="px-2 py-2 text-right">{{ number_format($tot['credito'], 2) }}</td>
                            <td class="px-2 py-2 text-right border-l">{{ number_format($tot['igv_pagar'], 2) }}</td><td></td>
                            <td class="px-2 py-2 text-right border-l">{{ number_format($tot['renta'], 2) }}</td>
                            <td class="px-2 py-2 text-right border-l">{{ number_format($tot['essalud'] + $tot['onp'] + $tot['quinta'], 2) }}</td><td class="px-2 py-2 text-right">{{ number_format($tot['cuarta'], 2) }}</td>
                            <td class="px-2 py-2 text-right border-l text-indigo-700">{{ number_format($tot['total_pagar'], 2) }}</td><td class="px-2 py-2 text-right">{{ number_format($tot['utilidad'], 2) }}</td></tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div class="mt-4 rounded-2xl bg-white shadow-sm p-4 text-xs text-gray-500 space-y-1">
            <p><i class="fas fa-circle-info text-indigo-500"></i> <strong>Es una estimación</strong> con lo registrado en el sistema (ventas electrónicas, compras, gastos y planilla) para que planifiques. La declaración la presentas tú o tu contador (PDT 621 IGV-Renta, PLAME y renta anual) y puede tener ajustes que aquí no se ven (percepciones, retenciones, pérdidas de años anteriores, gastos no deducibles…).</p>
            <p>Para que el cálculo sea correcto: registra todas tus <strong>compras de mercadería</strong> en Compras, los <strong>gastos</strong> (luz, agua, alquiler, honorarios) en Gastos —puedes importarlos del SIRE— y cierra la <strong>planilla</strong> de cada mes.</p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
        if (window.Chart) new Chart(document.getElementById('grafico'), {
            type: 'bar',
            data: {
                labels: @json($etiquetas),
                datasets: [
                    { label: 'Ventas', data: @json(array_column($meses, 'ventas')), backgroundColor: '#10b981', borderRadius: 6 },
                    { label: 'Compras + gastos + planilla', data: @json($egresos), backgroundColor: '#f43f5e', borderRadius: 6 },
                    { type: 'line', label: 'Impuestos estimados', data: @json(array_column($meses, 'total_pagar')), borderColor: '#4f46e5', backgroundColor: '#4f46e5', tension: .3 },
                ],
            },
            options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { ticks: { callback: v => 'S/ ' + v.toLocaleString('es-PE') } } } },
        });
    </script>
@endsection
