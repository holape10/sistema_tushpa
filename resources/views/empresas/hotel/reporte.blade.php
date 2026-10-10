@extends('layouts.app')
@section('title', 'Reporte del Hotel')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
@php
    $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
    $var = function ($ahora, $antes) {
        if ($antes <= 0) return $ahora > 0 ? ['▲ nuevo', 'text-emerald-300'] : ['—', 'text-white/60'];
        $p = ($ahora - $antes) / $antes * 100;
        return [($p >= 0 ? '▲ ' : '▼ ').number_format(abs($p), 0).'% vs periodo anterior', $p >= 0 ? 'text-emerald-300' : 'text-rose-300'];
    };
    $rangos = [
        'Hoy' => [now()->toDateString(), now()->toDateString()],
        'Ayer' => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
        '7 días' => [now()->subDays(6)->toDateString(), now()->toDateString()],
        'Este mes' => [now()->startOfMonth()->toDateString(), now()->toDateString()],
        'Mes anterior' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
    ];
    $hora = fn ($h) => \Carbon\Carbon::createFromTime($h)->format('g a');
    $pico = array_search(max($kpi['estadias'] ? $porHora : [1]), $porHora);
    $estrella = $porHabitacion->first();
    $mezcla = ['Habitación' => $kpi['hospedaje'], 'Horas extra' => $kpi['extra'], 'Consumos' => $kpi['consumo']];
    $difOcup = $kpi['ocupacion'] - $kpi['ocupacion_antes'];
@endphp
<div class="max-w-7xl mx-auto space-y-5">

    {{-- Cabecera y periodo --}}
    <section class="rounded-3xl bg-gradient-to-br from-purple-800 via-fuchsia-700 to-rose-600 text-white p-5 sm:p-7 shadow-xl relative overflow-hidden">
        <i class="fas fa-hotel absolute -right-6 -bottom-8 text-[10rem] opacity-10"></i>
        <div class="relative flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-60">
                <a href="{{ route('hotel.index') }}" class="text-xs text-fuchsia-200 hover:text-white print:hidden">← Volver al hotel</a>
                <h1 class="text-2xl sm:text-3xl font-black mt-1">📊 Reporte del Hotel</h1>
                <p class="text-fuchsia-100 text-sm">{{ \Carbon\Carbon::parse($desde)->translatedFormat('d M Y') }} — {{ \Carbon\Carbon::parse($hasta)->translatedFormat('d M Y') }} · {{ $kpi['habitaciones'] }} habitaciones</p>
            </div>
            <form class="flex flex-wrap items-end gap-2 print:hidden">
                <div class="flex flex-wrap gap-1">
                    @foreach ($rangos as $n => [$a, $b])
                        <a href="?desde={{ $a }}&hasta={{ $b }}" class="px-3 py-1.5 rounded-full text-xs font-bold {{ $desde === $a && $hasta === $b ? 'bg-white text-fuchsia-800' : 'bg-white/15 hover:bg-white/25' }}">{{ $n }}</a>
                    @endforeach
                </div>
                <input type="date" name="desde" value="{{ $desde }}" class="h-9 rounded-lg border-0 text-gray-800 text-sm">
                <input type="date" name="hasta" value="{{ $hasta }}" class="h-9 rounded-lg border-0 text-gray-800 text-sm">
                <button class="h-9 px-4 rounded-lg bg-white text-fuchsia-800 text-sm font-black">Ver</button>
                <button type="button" onclick="window.print()" class="h-9 px-3 rounded-lg bg-white/15 hover:bg-white/25 text-sm font-bold">🖨️</button>
            </form>
        </div>

        {{-- Lo más importante --}}
        <div class="relative grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6">
            @php [$t1, $c1] = $var($kpi['total'], $kpi['total_antes']); [$t2, $c2] = $var($kpi['estadias'], $kpi['estadias_antes']); @endphp
            <div class="rounded-2xl bg-white/10 p-4">
                <p class="text-xs font-semibold text-fuchsia-100 uppercase">Ventas del hotel</p>
                <p class="text-2xl sm:text-3xl font-black mt-1">{{ $soles($kpi['total']) }}</p>
                <p class="text-xs font-bold {{ $c1 }}">{{ $t1 }}</p>
            </div>
            <div class="rounded-2xl bg-white/10 p-4 flex items-center gap-3">
                <div class="w-16 h-16 rounded-full shrink-0 flex items-center justify-center" style="background: conic-gradient(#fde047 {{ $kpi['ocupacion'] }}%, rgba(255,255,255,.2) 0)">
                    <div class="w-12 h-12 rounded-full bg-fuchsia-800 flex items-center justify-center text-sm font-black">{{ number_format($kpi['ocupacion'], 0) }}%</div>
                </div>
                <div>
                    <p class="text-xs font-semibold text-fuchsia-100 uppercase">Ocupación</p>
                    <p class="text-xs font-bold {{ $difOcup >= 0 ? 'text-emerald-300' : 'text-rose-300' }}">{{ $difOcup >= 0 ? '▲' : '▼' }} {{ number_format(abs($difOcup), 1) }} puntos</p>
                </div>
            </div>
            <div class="rounded-2xl bg-white/10 p-4">
                <p class="text-xs font-semibold text-fuchsia-100 uppercase">Estadías</p>
                <p class="text-2xl sm:text-3xl font-black mt-1">{{ $kpi['estadias'] }}</p>
                <p class="text-xs font-bold {{ $c2 }}">{{ $t2 }}</p>
            </div>
            <div class="rounded-2xl bg-white/10 p-4">
                <p class="text-xs font-semibold text-fuchsia-100 uppercase">Ticket promedio</p>
                <p class="text-2xl sm:text-3xl font-black mt-1">{{ $soles($kpi['ticket']) }}</p>
                <p class="text-xs text-fuchsia-100">Se quedan {{ $kpi['duracion'] }} h en promedio</p>
            </div>
        </div>
    </section>

    {{-- En pocas palabras --}}
    @if ($kpi['estadias'])
        <section class="bg-white rounded-2xl shadow-sm p-5">
            <h2 class="font-black text-gray-800 mb-3">💡 En pocas palabras</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                @if ($estrella && $estrella->ingresos > 0)
                    <div class="rounded-xl bg-amber-50 p-3"><span class="text-2xl">🏆</span><p class="text-amber-900">Tu habitación estrella es la <b>{{ $estrella->nombre }}</b>: {{ $soles($estrella->ingresos) }} en {{ $estrella->estadias }} estadías.</p></div>
                @endif
                <div class="rounded-xl bg-indigo-50 p-3"><span class="text-2xl">🕗</span><p class="text-indigo-900">La hora con más llegadas es <b>{{ $hora($pico) }}</b>. Ten habitaciones listas antes de esa hora.</p></div>
                <div class="rounded-xl bg-emerald-50 p-3"><span class="text-2xl">⏱️</span><p class="text-emerald-900">Las horas extra dejaron <b>{{ $soles($kpi['extra']) }}</b> ({{ $kpi['horas_extra'] }} h).</p></div>
                @if ($kpi['sin_exceso'] || $kpi['quitados_monto'] > 0)
                    <div class="rounded-xl bg-rose-50 p-3"><span class="text-2xl">⚠️</span><p class="text-rose-900">Se dejó de cobrar <b>{{ $soles($kpi['sin_exceso_monto'] + $kpi['quitados_monto']) }}</b> (tiempo de más regalado y consumos quitados). Revisa la bitácora abajo.</p></div>
                @else
                    <div class="rounded-xl bg-emerald-50 p-3"><span class="text-2xl">✅</span><p class="text-emerald-900">No se regaló tiempo ni se quitaron consumos en este periodo.</p></div>
                @endif
            </div>
        </section>
    @endif

    {{-- Indicadores secundarios --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach ([
            ['Habitación', $soles($kpi['hospedaje']), 'fa-bed', 'text-fuchsia-600'],
            ['Horas extra', $soles($kpi['extra']), 'fa-clock', 'text-amber-500'],
            ['Consumos', $soles($kpi['consumo']), 'fa-wine-bottle', 'text-sky-500'],
            ['Por cobrar', $soles($kpi['pendiente']), 'fa-hourglass-half', $kpi['pendiente'] > 0 ? 'text-rose-500' : 'text-gray-300'],
        ] as [$t, $v, $i, $c])
            <div class="bg-white rounded-2xl shadow-sm p-4 flex items-center gap-3">
                <i class="fas {{ $i }} text-2xl {{ $c }} w-8 text-center"></i>
                <div><p class="text-xs text-gray-500 font-semibold">{{ $t }}</p><p class="text-lg font-black text-gray-800">{{ $v }}</p></div>
            </div>
        @endforeach
    </div>

    {{-- Gráficos --}}
    <div class="grid lg:grid-cols-3 gap-5">
        <section class="lg:col-span-2 bg-white rounded-2xl shadow-sm p-5">
            <h3 class="font-black text-gray-800 mb-1">Ventas y ocupación por día</h3>
            <p class="text-xs text-gray-400 mb-3">Barras: lo vendido (habitación, horas extra y consumos). Línea: % de ocupación.</p>
            <div class="h-80"><canvas id="gDias"></canvas></div>
        </section>
        <section class="bg-white rounded-2xl shadow-sm p-5">
            <h3 class="font-black text-gray-800 mb-1">¿De dónde vienen tus ventas?</h3>
            <p class="text-xs text-gray-400 mb-3">Parte de cada tipo en el total.</p>
            <div class="h-64"><canvas id="gMezcla"></canvas></div>
        </section>
        <section class="bg-white rounded-2xl shadow-sm p-5">
            <h3 class="font-black text-gray-800 mb-1">¿A qué hora llegan?</h3>
            <p class="text-xs text-gray-400 mb-3">Ingresos por hora del día.</p>
            <div class="h-64"><canvas id="gHoras"></canvas></div>
        </section>
        <section class="lg:col-span-2 bg-white rounded-2xl shadow-sm p-5">
            <h3 class="font-black text-gray-800 mb-1">Ventas por habitación</h3>
            <p class="text-xs text-gray-400 mb-3">Cuánto dejó cada habitación en el periodo.</p>
            <div style="height: {{ max(220, min(640, $porHabitacion->count() * 30)) }}px"><canvas id="gHab"></canvas></div>
        </section>
    </div>

    <div class="grid lg:grid-cols-3 gap-5">
        {{-- Habitaciones --}}
        <section class="lg:col-span-2 bg-white rounded-2xl shadow-sm overflow-hidden">
            <h3 class="font-black text-gray-800 p-5 pb-3">🛏️ Detalle por habitación</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr>
                        <th class="px-4 py-2 text-left">Habitación</th><th class="px-3 py-2 text-right">Estadías</th><th class="px-3 py-2 text-right">Horas ocupada</th>
                        <th class="px-3 py-2 text-left w-48">Ocupación</th><th class="px-4 py-2 text-right">Ventas</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($porHabitacion as $h)
                            <tr>
                                <td class="px-4 py-2"><b class="text-gray-800">{{ $h->nombre }}</b> <span class="text-xs text-gray-400">{{ $h->tipo }} · {{ $h->piso }}</span></td>
                                <td class="px-3 py-2 text-right">{{ $h->estadias }}</td>
                                <td class="px-3 py-2 text-right">{{ $h->horas }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2"><div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden"><div class="h-full rounded-full bg-gradient-to-r from-fuchsia-500 to-rose-500" style="width: {{ min(100, $h->ocupacion) }}%"></div></div>
                                        <span class="text-xs font-bold text-gray-600 w-10 text-right">{{ number_format($h->ocupacion, 0) }}%</span></div>
                                </td>
                                <td class="px-4 py-2 text-right font-black text-gray-800 whitespace-nowrap">{{ $soles($h->ingresos) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="space-y-5">
            <section class="bg-white rounded-2xl shadow-sm p-5">
                <h3 class="font-black text-gray-800 mb-3">⏳ Servicios preferidos</h3>
                @forelse ($servicios as $s)
                    <div class="flex justify-between text-sm py-1.5 border-b border-gray-50"><span>{{ $s->nombre }} <span class="text-gray-400">×{{ $s->veces }}</span></span><b>{{ $soles($s->monto) }}</b></div>
                @empty
                    <p class="text-sm text-gray-400">Sin estadías en el periodo.</p>
                @endforelse
            </section>
            <section class="bg-white rounded-2xl shadow-sm p-5">
                <h3 class="font-black text-gray-800 mb-3">🍾 Lo más consumido</h3>
                @forelse ($consumosTop as $c)
                    <div class="flex justify-between text-sm py-1.5 border-b border-gray-50"><span>{{ $c->nombre }} <span class="text-gray-400">×{{ rtrim(rtrim(number_format($c->cantidad, 2), '0'), '.') }}</span></span><b>{{ $soles($c->monto) }}</b></div>
                @empty
                    <p class="text-sm text-gray-400">Sin consumos a la habitación.</p>
                @endforelse
            </section>
            <section class="bg-white rounded-2xl shadow-sm p-5">
                <h3 class="font-black text-gray-800 mb-3">📅 Reservas</h3>
                <div class="grid grid-cols-2 gap-2 text-center text-sm">
                    <div class="rounded-xl bg-emerald-50 p-2"><p class="text-xl font-black text-emerald-700">{{ $reservas['INGRESADA'] ?? 0 }}</p><p class="text-xs text-emerald-800">Llegaron</p></div>
                    <div class="rounded-xl bg-indigo-50 p-2"><p class="text-xl font-black text-indigo-700">{{ max(0, ($reservas['PENDIENTE'] ?? 0) - $noLlegaron) }}</p><p class="text-xs text-indigo-800">Por llegar</p></div>
                    <div class="rounded-xl bg-amber-50 p-2"><p class="text-xl font-black text-amber-700">{{ $noLlegaron }}</p><p class="text-xs text-amber-800">No llegaron</p></div>
                    <div class="rounded-xl bg-gray-50 p-2"><p class="text-xl font-black text-gray-600">{{ $reservas['CANCELADA'] ?? 0 }}</p><p class="text-xs text-gray-600">Canceladas</p></div>
                </div>
            </section>
        </div>
    </div>

    {{-- Bitácora --}}
    <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="p-5 pb-3 flex flex-wrap items-center gap-2">
            <h3 class="font-black text-gray-800 flex-1">🔎 Bitácora: lo que conviene revisar</h3>
            <span class="text-xs text-gray-500">Salidas sin cobrar el tiempo de más, consumos quitados, anulaciones y cambios de habitación · {{ $kpi['anuladas'] }} ingreso(s) anulado(s)</span>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse ($eventos as $ev)
                @php [$nom, $col] = \App\Support\HotelReporte::EVENTOS[$ev->tipo] ?? [$ev->tipo, '#64748b']; @endphp
                <div class="px-5 py-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                    <span class="w-28 text-gray-400">{{ \Carbon\Carbon::parse($ev->created_at)->format('d/m H:i') }}</span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold text-white" style="background: {{ $col }}">{{ $nom }}</span>
                    <span class="font-semibold text-gray-700">Hab. {{ $ev->hab_nom }}</span>
                    <span class="flex-1 min-w-48 text-gray-600">{{ $ev->detalle }}@if ($ev->minutos) · {{ $ev->minutos }} min @endif</span>
                    <span class="text-xs text-gray-500">{{ $ev->usuario }}@if ($ev->autorizo && $ev->autorizo !== $ev->usuario) · autorizó {{ $ev->autorizo }}@endif</span>
                    @if ($ev->monto)<b class="w-24 text-right {{ in_array($ev->tipo, ['SALIDA_SIN_EXCESO', 'CONSUMO_QUITADO', 'ANULACION']) ? 'text-rose-600' : 'text-gray-700' }}">{{ $soles($ev->monto) }}</b>@endif
                </div>
            @empty
                <p class="p-8 text-center text-gray-400 text-sm">Nada que revisar en este periodo. 👍</p>
            @endforelse
        </div>
    </section>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
    (function () {
        if (!window.Chart) return;
        const soles = v => 'S/ ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        Chart.defaults.font.family = 'ui-sans-serif, system-ui, sans-serif';
        Chart.defaults.color = '#64748b';
        const dias = @js($porDia->values());
        const corto = d => { const [y, m, dd] = d.split('-'); return dd + '/' + m; };
        const morado = '#a21caf', ambar = '#f59e0b', celeste = '#0ea5e9';

        new Chart(document.getElementById('gDias'), {
            data: {
                labels: dias.map(d => corto(d.dia)),
                datasets: [
                    { type: 'bar', label: 'Habitación', data: dias.map(d => d.hospedaje), backgroundColor: morado, borderRadius: 6, stack: 'v', yAxisID: 'y' },
                    { type: 'bar', label: 'Horas extra', data: dias.map(d => d.extra), backgroundColor: ambar, borderRadius: 6, stack: 'v', yAxisID: 'y' },
                    { type: 'bar', label: 'Consumos', data: dias.map(d => d.consumo), backgroundColor: celeste, borderRadius: 6, stack: 'v', yAxisID: 'y' },
                    { type: 'line', label: 'Ocupación %', data: dias.map(d => d.ocupacion), borderColor: '#e11d48', backgroundColor: '#e11d48', tension: .35, pointRadius: 3, yAxisID: 'y2' },
                ],
            },
            options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: c => ' ' + c.dataset.label + ': ' + (c.dataset.yAxisID === 'y2' ? (c.parsed.y ?? 0) + '%' : soles(c.parsed.y)) } } },
                scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, ticks: { callback: v => 'S/ ' + v } },
                    y2: { position: 'right', min: 0, max: 100, grid: { display: false }, ticks: { callback: v => v + '%' } } } },
        });

        const mezcla = @js($mezcla);
        new Chart(document.getElementById('gMezcla'), {
            type: 'doughnut',
            data: { labels: Object.keys(mezcla), datasets: [{ data: Object.values(mezcla), backgroundColor: [morado, ambar, celeste], borderWidth: 0 }] },
            options: { maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom' },
                tooltip: { callbacks: { label: c => { const t = c.dataset.data.reduce((a, b) => a + b, 0) || 1; return ' ' + c.label + ': ' + soles(c.parsed) + ' (' + Math.round(c.parsed / t * 100) + '%)'; } } } } },
        });

        const horas = @js(array_values($porHora));
        const max = Math.max(...horas);
        new Chart(document.getElementById('gHoras'), {
            type: 'bar',
            data: { labels: horas.map((_, h) => (h % 12 || 12) + (h < 12 ? 'a' : 'p')),
                datasets: [{ label: 'Llegadas', data: horas, borderRadius: 4, backgroundColor: horas.map(v => v === max && max > 0 ? '#e11d48' : '#f0abfc') }] },
            options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { ticks: { precision: 0 } } } },
        });

        const habs = @js($porHabitacion->map(fn ($h) => ['n' => $h->nombre, 'v' => $h->ingresos])->values());
        new Chart(document.getElementById('gHab'), {
            type: 'bar',
            data: { labels: habs.map(h => 'Hab. ' + h.n), datasets: [{ label: 'Ventas', data: habs.map(h => h.v), borderRadius: 6,
                backgroundColor: habs.map((_, i) => i === 0 ? '#a21caf' : '#d8b4fe') }] },
            options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ' ' + soles(c.parsed.x) } } },
                scales: { x: { ticks: { callback: v => 'S/ ' + v } }, y: { grid: { display: false } } } },
        });
    })();
</script>
@endsection
