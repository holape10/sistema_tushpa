@extends('layouts.app')
@section('title', 'Reporte · Estacionamiento')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $duracion = fn ($m) => \App\Support\Estacionamiento::duracion((int) round((float) $m));
        $estados = ['DENTRO' => 'bg-indigo-100 text-indigo-700', 'SOLICITADO' => 'bg-rose-100 text-rose-700', 'SALIO' => 'bg-emerald-100 text-emerald-700', 'ANULADO' => 'bg-gray-200 text-gray-500'];
        $nombreEstado = ['DENTRO' => 'Dentro', 'SOLICITADO' => 'Solicitado', 'SALIO' => 'Salió', 'ANULADO' => 'Anulado'];
    @endphp

    <div class="space-y-5">
        <section class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-800 via-indigo-700 to-blue-700 text-white p-5 sm:p-7 shadow-lg print:shadow-none">
            <x-kene-adorno patron="meandro" />
            <a href="{{ route('estacionamiento.index') }}" class="text-xs text-indigo-200 hover:text-white print:hidden">← Volver al estacionamiento</a>
            <h1 class="text-2xl sm:text-3xl font-black mt-1"><i class="fas fa-chart-column"></i> Reporte de estacionamiento</h1>
            <p class="text-indigo-100 text-sm mt-1">Del {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}{{ $placa ? ' · placa '.$placa : '' }}</p>

            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mt-6">
                <div class="rounded-2xl bg-white/10 p-4"><p class="text-xs text-indigo-200">Vehículos</p><p class="text-2xl font-black">{{ number_format($totales['vehiculos']) }}</p></div>
                <div class="rounded-2xl bg-white/10 p-4"><p class="text-xs text-indigo-200">Cobrado en salidas</p><p class="text-2xl font-black">{{ $soles($totales['cobrado']) }}</p></div>
                <div class="rounded-2xl bg-white/10 p-4"><p class="text-xs text-indigo-200">Pensiones ({{ $totales['pensiones']->cantidad }})</p><p class="text-2xl font-black">{{ $soles($totales['pensiones']->total) }}</p></div>
                <div class="rounded-2xl bg-white/10 p-4"><p class="text-xs text-indigo-200">Estadía promedio</p><p class="text-2xl font-black">{{ $duracion($totales['promedio']) }}</p></div>
                <div class="rounded-2xl bg-white/10 p-4 col-span-2 lg:col-span-1"><p class="text-xs text-indigo-200">Sin cobro · descuentos · anulados</p>
                    <p class="text-lg font-black">{{ $totales['sinCobro'] }} · {{ $soles($totales['descuentos']) }} · {{ $totales['anulados'] }}</p></div>
            </div>
        </section>

        <form class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-3 print:hidden">
            <label class="text-sm">Desde<input type="date" name="desde" value="{{ $desde }}" class="{{ $in }}"></label>
            <label class="text-sm">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="{{ $in }}"></label>
            <label class="text-sm">Placa<input name="placa" value="{{ $placa }}" placeholder="Todas" class="{{ $in }} uppercase"></label>
            <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700"><i class="fas fa-search"></i> Consultar</button>
            <button type="button" onclick="window.print()" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200"><i class="fas fa-print"></i> Imprimir</button>
        </form>

        <div class="grid lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm p-5">
                <h3 class="font-bold text-gray-800 mb-3"><i class="fas fa-chart-column text-indigo-500"></i> Cobrado por día</h3>
                <div class="h-64"><canvas id="graficoDias"></canvas></div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <h3 class="font-bold text-gray-800 mb-3"><i class="fas fa-clock text-amber-500"></i> Hora de llegada</h3>
                <div class="h-64"><canvas id="graficoHoras"></canvas></div>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-5">
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 bg-indigo-50 font-bold text-indigo-900"><i class="fas fa-car-side"></i> Por tipo de vehículo</div>
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500"><tr><th class="px-4 py-2 text-left">Tipo</th><th class="px-4 py-2 text-right">Vehículos</th><th class="px-4 py-2 text-right">Promedio</th><th class="px-4 py-2 text-right">Cobrado</th></tr></thead>
                    <tbody class="divide-y">
                        @forelse ($porTipo as $f)
                            <tr><td class="px-4 py-2 font-semibold">{{ $f->tipo }}</td><td class="px-4 py-2 text-right">{{ $f->vehiculos }}</td>
                                <td class="px-4 py-2 text-right">{{ $duracion($f->promedio) }}</td><td class="px-4 py-2 text-right font-bold">{{ $soles($f->total) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">Sin salidas en el periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 bg-indigo-50 font-bold text-indigo-900"><i class="fas fa-user-tie"></i> Cobrado por usuario</div>
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500"><tr><th class="px-4 py-2 text-left">Usuario</th><th class="px-4 py-2 text-right">Salidas</th><th class="px-4 py-2 text-right">Cobrado</th></tr></thead>
                    <tbody class="divide-y">
                        @forelse ($porUsuario as $f)
                            <tr><td class="px-4 py-2 font-semibold">{{ trim(preg_replace('/^\d{8,11}\s+/', '', (string) $f->usuario)) ?: '—' }}</td>
                                <td class="px-4 py-2 text-right">{{ $f->vehiculos }}</td><td class="px-4 py-2 text-right font-bold">{{ $soles($f->total) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">Sin salidas en el periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b flex items-center justify-between">
                <h3 class="font-bold text-gray-800"><i class="fas fa-ticket text-indigo-500"></i> Tickets</h3>
                <span class="text-xs text-gray-500">{{ $tickets->total() }} ticket(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500 bg-gray-50">
                        <tr><th class="px-3 py-2 text-left">N°</th><th class="px-3 py-2 text-left">Placa</th><th class="px-3 py-2 text-left">Entrada</th><th class="px-3 py-2 text-left">Salida</th>
                            <th class="px-3 py-2 text-left">Tiempo</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2 text-left">Comprobante</th><th class="px-3 py-2 text-left">Estado</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($tickets as $t)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-gray-500">{{ $t->numero }}</td>
                                <td class="px-3 py-2"><span class="font-black" style="font-family: ui-monospace, Consolas, monospace">{{ $t->placa }}</span>
                                    <span class="block text-[11px] text-gray-500">{{ $t->tipo }}{{ $t->espacio ? ' · '.$t->espacio : '' }}{{ $t->valet ? ' · valet' : '' }}{{ $t->abo_id ? ' · abonado' : '' }}</span></td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($t->entrada)->format('d/m H:i') }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $t->salida ? \Carbon\Carbon::parse($t->salida)->format('d/m H:i') : '—' }}</td>
                                <td class="px-3 py-2">{{ $t->minutos !== null ? $duracion($t->minutos) : '—' }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ $t->estado === 'SALIO' ? $soles($t->total) : '—' }}
                                    @if ($t->descuento > 0)<span class="block text-[11px] text-emerald-700" title="{{ $t->motivo }}">desc. {{ $soles($t->descuento) }}</span>@endif
                                    @if ($t->penalidad > 0)<span class="block text-[11px] text-rose-700">ticket perdido</span>@endif</td>
                                <td class="px-3 py-2">@if ($t->IdCpe_cabecera)<a href="{{ url('voucher/'.$t->IdCpe_cabecera) }}" target="_blank" class="text-indigo-600 underline">{{ $t->comprobante }}</a>@endif</td>
                                <td class="px-3 py-2"><span class="text-[11px] font-bold px-2 py-0.5 rounded {{ $estados[$t->estado] ?? '' }}" title="{{ $t->motivo }}">{{ $nombreEstado[$t->estado] ?? $t->estado }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-3 py-10 text-center text-gray-400">No hay tickets en el periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 print:hidden">{{ $tickets->links() }}</div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
        (function () {
            const dias = @js($porDia);
            new Chart(document.getElementById('graficoDias'), {
                type: 'bar',
                data: {
                    labels: dias.map(d => d.dia.slice(8, 10) + '/' + d.dia.slice(5, 7)),
                    datasets: [{ label: 'Cobrado S/', data: dias.map(d => Number(d.total)), backgroundColor: '#6366f1', borderRadius: 6 }],
                },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { afterLabel: c => dias[c.dataIndex].vehiculos + ' vehículo(s)' } } } },
            });
            const horas = @js($porHora);
            new Chart(document.getElementById('graficoHoras'), {
                type: 'bar',
                data: {
                    labels: Array.from({ length: 24 }, (_, h) => String(h).padStart(2, '0')),
                    datasets: [{ label: 'Llegadas', data: Array.from({ length: 24 }, (_, h) => Number(horas[h] || 0)), backgroundColor: '#f59e0b', borderRadius: 4 }],
                },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { ticks: { precision: 0 } } } },
            });
        })();
    </script>
@endsection
