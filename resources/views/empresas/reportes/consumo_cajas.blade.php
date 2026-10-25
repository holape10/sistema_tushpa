@extends('layouts.app')
@section('title', 'Consumo por Caja y Barra')
@section('content')
    @php
        $s = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $cant = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
        $hoy = now()->toDateString();
        $rangos = [
            'Ayer y hoy' => [now()->subDay()->toDateString(), $hoy],
            'Hoy' => [$hoy, $hoy],
            'Últimos 7 días' => [now()->subDays(6)->toDateString(), $hoy],
            'Este mes' => [now()->startOfMonth()->toDateString(), $hoy],
        ];
        $url = fn (array $extra) => route('consumo_cajas.index', array_filter(array_merge(['desde' => $desde, 'hasta' => $hasta, 'ver' => $ver], $extra), fn ($v) => $v !== null));
        $nombreVer = $ver ? str_replace(['caja:', 'barra:'], ['Caja ', ''], $ver) : 'Todas las cajas';
        $totalVentas = $cajas->sum('ventas');
        $totalCortesias = $cajas->sum('cortesias');
    @endphp
    <style>@media print { aside, header, nav, .no-print { display: none !important; } main { padding: 0 !important; } }</style>

    <div class="max-w-6xl space-y-5">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-sky-800 via-indigo-700 to-violet-700 text-white shadow-lg">
            <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-white/10"></div>
            <div class="relative p-6 sm:p-8">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="flex-1 min-w-[240px]">
                        <p class="text-sky-100 text-sm font-semibold tracking-wide uppercase">🍹 Consumo por caja y barra</p>
                        <h1 class="text-2xl sm:text-3xl font-black mt-1">Qué se vendió, qué se regaló y qué salió del almacén</h1>
                        <p class="text-sky-50 text-sm mt-1">{{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }} · ventas vigentes (sin anuladas)</p>
                    </div>
                    <div class="no-print flex gap-2">
                        <a href="{{ route('consumo_cajas.excel', array_filter(['desde' => $desde, 'hasta' => $hasta, 'ver' => $ver])) }}" class="px-3 py-2 rounded-xl bg-white/15 hover:bg-white/25 text-sm font-semibold">📊 Excel</a>
                        <button type="button" onclick="window.print()" class="px-3 py-2 rounded-xl bg-white/15 hover:bg-white/25 text-sm font-semibold">🖨 Imprimir</button>
                    </div>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6">
                    @foreach ([['Ventas cobradas', $s($totalVentas)], ['Cortesías (lo regalado)', $s($totalCortesias)], ['Comprobantes', $cajas->sum('comprobantes')], ['Cajas', $cajas->count()]] as [$titulo, $valor])
                        <div class="rounded-2xl bg-white/10 backdrop-blur px-4 py-3 ring-1 ring-white/15">
                            <p class="text-xs text-sky-50">{{ $titulo }}</p>
                            <p class="text-2xl font-black">{{ $valor }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Fechas --}}
        <form method="GET" class="no-print bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-3">
            <label class="text-xs font-semibold text-gray-500">Desde<input type="date" name="desde" value="{{ $desde }}" class="block mt-1 rounded-lg border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-500">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block mt-1 rounded-lg border-gray-300 text-sm"></label>
            @if ($ver)<input type="hidden" name="ver" value="{{ $ver }}">@endif
            <button class="h-10 px-4 rounded-lg bg-indigo-600 text-white text-sm font-bold">Ver</button>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($rangos as $nombre => [$d, $h])
                    <a href="{{ $url(['desde' => $d, 'hasta' => $h]) }}" class="px-3 py-1.5 rounded-full text-xs font-semibold {{ $desde === $d && $hasta === $h ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">{{ $nombre }}</a>
                @endforeach
            </div>
        </form>

        {{-- Barras y sus cajas: tocar una para ver su detalle --}}
        <section class="grid md:grid-cols-2 gap-4">
            @forelse ($barras as $b)
                <div class="bg-white rounded-2xl shadow-sm p-4 {{ $ver === 'barra:'.$b->barra ? 'ring-2 ring-indigo-500' : '' }}">
                    <a href="{{ $url(['ver' => 'barra:'.$b->barra]) }}" class="flex items-center gap-2">
                        <span class="text-lg font-black text-gray-800 flex-1">🍸 {{ $b->barra }}</span>
                        <span class="text-right"><span class="block text-xl font-black text-indigo-700">{{ $s($b->ventas) }}</span>
                            <span class="block text-xs text-gray-400">{{ $b->comprobantes }} comprobantes · cortesías {{ $s($b->cortesias) }}</span></span>
                    </a>
                    <div class="mt-3 grid gap-2">
                        @foreach ($cajas->where('barra', $b->barra) as $c)
                            <a href="{{ $url(['ver' => 'caja:'.$c->caja]) }}"
                               class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm {{ $ver === 'caja:'.$c->caja ? 'bg-indigo-600 text-white' : 'bg-gray-50 hover:bg-indigo-50' }}">
                                <span class="font-bold flex-1">🖥 {{ $c->caja }}</span>
                                <span>{{ $s($c->ventas) }}</span>
                                <span class="text-xs {{ $ver === 'caja:'.$c->caja ? 'text-indigo-100' : 'text-gray-400' }}">· {{ $c->comprobantes }} comp.</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="md:col-span-2 bg-white rounded-2xl shadow-sm p-8 text-center text-gray-400">No hay ventas en estas fechas.</div>
            @endforelse
        </section>
        @if ($cajas->contains('caja', \App\Support\ConsumoCajas::SIN_CAJA))
            <p class="no-print text-xs text-gray-500">"SIN CAJA": ventas cobradas en una PC que no eligió "¿Qué caja?" (o antes de este reporte, si no se pudo saber la caja por la impresión).</p>
        @endif

        <div class="no-print flex flex-wrap items-center gap-2">
            <span class="text-sm text-gray-500">Viendo:</span>
            <span class="px-3 py-1 rounded-full bg-indigo-100 text-indigo-800 text-sm font-bold">{{ $nombreVer }}</span>
            @if ($ver)<a href="{{ $url(['ver' => null]) }}" class="text-sm text-indigo-600 hover:underline">Ver todas las cajas</a>@endif
        </div>

        <div class="grid lg:grid-cols-2 gap-5 items-start">
            {{-- Productos vendidos y regalados --}}
            <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b"><h2 class="font-bold text-gray-800">🧾 Productos ({{ $nombreVer }})</h2>
                    <p class="text-xs text-gray-400">Lo que se cobró y lo que se dio de cortesía.</p></div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Producto</th><th class="px-2 py-2 text-right">Vendidos</th><th class="px-2 py-2 text-right">Total</th><th class="px-4 py-2 text-right">Cortesía</th></tr></thead>
                    <tbody class="divide-y">
                        @forelse ($productos as $p)
                            <tr>
                                <td class="px-4 py-1.5 font-semibold text-gray-700">{{ $p->nombre }}</td>
                                <td class="px-2 py-1.5 text-right">{{ $p->vendidos ? $cant($p->vendidos) : '' }}</td>
                                <td class="px-2 py-1.5 text-right">{{ $p->vendidos ? $s($p->total) : '' }}</td>
                                <td class="px-4 py-1.5 text-right text-amber-700">@if ($p->cortesias){{ $cant($p->cortesias) }} <span class="text-xs text-gray-400">({{ $s($p->valor_cortesias) }})</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">Sin ventas.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($productos->isNotEmpty())
                        <tfoot class="bg-gray-50 font-bold"><tr><td class="px-4 py-2">Total</td><td class="px-2 py-2 text-right">{{ $cant($productos->sum('vendidos')) }}</td>
                            <td class="px-2 py-2 text-right">{{ $s($productos->sum('total')) }}</td><td class="px-4 py-2 text-right text-amber-700">{{ $s($productos->sum('valor_cortesias')) }}</td></tr></tfoot>
                    @endif
                </table>
            </section>

            {{-- Lo que salió del almacén --}}
            <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b"><h2 class="font-bold text-gray-800">📦 Insumos y productos que salieron del almacén</h2>
                    <p class="text-xs text-gray-400">Según las recetas de lo vendido y regalado (las cortesías también gastan).</p></div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Insumo / producto</th><th class="px-2 py-2 text-right">Salió</th><th class="px-4 py-2 text-right">Costo</th></tr></thead>
                    <tbody class="divide-y">
                        @forelse ($insumos as $i)
                            <tr>
                                <td class="px-4 py-1.5 font-semibold text-gray-700">{{ $i->nombre }}</td>
                                <td class="px-2 py-1.5 text-right">{{ $i->texto }}</td>
                                <td class="px-4 py-1.5 text-right">{{ $s($i->costo) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">Nada salió del almacén.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($insumos->isNotEmpty())
                        <tfoot class="bg-gray-50 font-bold"><tr><td class="px-4 py-2" colspan="2">Costo total de lo que salió</td><td class="px-4 py-2 text-right">{{ $s($insumos->sum('costo')) }}</td></tr></tfoot>
                    @endif
                </table>
            </section>
        </div>
    </div>
@endsection
