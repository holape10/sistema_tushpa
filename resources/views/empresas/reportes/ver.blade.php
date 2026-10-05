@extends('layouts.app')
@section('title', $titulo)
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @use('App\Http\Controllers\ReporteController', 'R')
    @php
        $num = fn($tipo) => in_array($tipo, ['money', 'num', 'pct'], true);
        $rangos = ['Hoy' => [now()->toDateString(), now()->toDateString()], 'Esta semana' => [now()->startOfWeek()->toDateString(), now()->toDateString()],
            'Este mes' => [now()->startOfMonth()->toDateString(), now()->toDateString()],
            'Mes anterior' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'Este año' => [now()->startOfYear()->toDateString(), now()->toDateString()]];
        $grafico = $grafico ?? null;
        $datosGrafico = $grafico ? collect($filas)->filter(fn($f) => ($f[$grafico['valor']] ?? 0) != 0)->take(12) : collect();
    @endphp

    {{-- Otros reportes del grupo --}}
    <nav class="flex gap-1 overflow-x-auto bg-white rounded-2xl shadow-sm p-1 mb-4">
        @foreach ($otros as $k => [$t, $g, $d, $i])
            <a href="{{ route('reportes.ver', ['clave' => $k] + request()->only(['desde', 'hasta', 'sucursal'])) }}"
               class="flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold whitespace-nowrap {{ $k === $clave ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-indigo-50' }}"><i class="fas {{ $i }}"></i>{{ $t }}</a>
        @endforeach
    </nav>

    {{-- Filtros --}}
    <form method="GET" class="bg-white rounded-2xl shadow-sm p-4 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            @if ($sucursales->count() > 1)
                <label class="text-sm">Sucursal
                    <select name="sucursal" class="block rounded-lg border-gray-300 text-sm">
                        @foreach ($sucursales as $s)<option value="{{ $s->id_empresa_negocio }}" @selected($filtros['sucursal'] == $s->id_empresa_negocio)>{{ $s->nombre_comercial }}</option>@endforeach
                    </select></label>
            @endif
            <label class="text-sm">Desde<input type="date" name="desde" value="{{ $filtros['desde'] }}" class="block rounded-lg border-gray-300 text-sm"></label>
            <label class="text-sm">Hasta<input type="date" name="hasta" value="{{ $filtros['hasta'] }}" class="block rounded-lg border-gray-300 text-sm"></label>
            @include('empresas.reportes._filtros', ['usa' => $usa ?? []])
            <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold"><i class="fas fa-magnifying-glass"></i> Ver</button>
            <div class="flex gap-2 ml-auto">
                <a href="{{ request()->fullUrlWithQuery(['formato' => 'excel']) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700"><i class="fas fa-file-excel"></i> Excel</a>
                <a href="{{ request()->fullUrlWithQuery(['formato' => 'pdf']) }}" class="px-4 py-2 rounded-xl bg-rose-600 text-white text-sm font-semibold hover:bg-rose-700"><i class="fas fa-file-pdf"></i> PDF</a>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 mt-3">
            @foreach ($rangos as $n => [$d, $h])
                <a href="{{ request()->fullUrlWithQuery(['desde' => $d, 'hasta' => $h]) }}" class="px-3 py-1 rounded-full text-xs font-semibold {{ $filtros['desde'] === $d && $filtros['hasta'] === $h ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">{{ $n }}</a>
            @endforeach
        </div>
    </form>

    {{-- Resumen --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Registros</p><p class="text-2xl font-black text-gray-800">{{ count($filas) }}</p></div>
        @foreach (($resumen ?? collect($totales)->filter(fn($v, $k) => ($columnas[$k][1] ?? '') === 'money')->take(3)->map(fn($v, $k) => [$columnas[$k][0], $v, 'money'])->values()->all()) as $res)
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">{{ $res[0] }}</p>
                <p class="text-2xl font-black {{ isset($res[2]) && $res[1] < 0 ? 'text-rose-600' : 'text-indigo-700' }}">{{ isset($res[2]) ? 'S/ ' . number_format($res[1], 2) : $res[1] }}</p></div>
        @endforeach
    </div>

    @if ($datosGrafico->count() > 1)
        <div class="bg-white rounded-2xl shadow-sm p-4 mb-4"><canvas id="grafico" height="70"></canvas></div>
    @endif
    @if (!empty($nota))<div class="mb-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-2"><i class="fas fa-circle-info"></i> {{ $nota }}</div>@endif

    {{-- Tabla --}}
    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-700 text-white text-xs uppercase">
                <tr>@foreach ($columnas as [$t, $tipo])<th class="px-3 py-2 whitespace-nowrap {{ $num($tipo) ? 'text-right' : 'text-left' }}">{{ $t }}</th>@endforeach</tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($filas as $f)
                    <tr class="hover:bg-gray-50">
                        @foreach ($columnas as $k => [$t, $tipo])
                            @php $v = $f[$k] ?? ''; @endphp
                            <td class="px-3 py-1.5 {{ $num($tipo) ? 'text-right whitespace-nowrap' : '' }} {{ $num($tipo) && (float) $v < 0 ? 'text-rose-600' : '' }} {{ $loop->first ? 'font-semibold text-gray-700' : '' }}">
                                @if ($tipo === 'pct')
                                    <span class="inline-flex items-center gap-2"><span class="hidden sm:inline-block w-16 h-1.5 rounded-full bg-gray-100"><span class="block h-1.5 rounded-full bg-indigo-500" style="width: {{ max(0, min(100, (float) $v)) }}%"></span></span>{{ R::texto($v, $tipo) }}</span>
                                @else{{ R::texto($v, $tipo) }}@endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columnas) }}" class="px-4 py-10 text-center text-gray-400">Sin datos en el periodo elegido.</td></tr>
                @endforelse
            </tbody>
            @if ($filas)
                <tfoot class="bg-slate-100 font-bold">
                    <tr>@foreach (array_keys($columnas) as $i => $k)<td class="px-3 py-2 {{ $num($columnas[$k][1]) ? 'text-right whitespace-nowrap' : '' }}">{{ $i === 0 ? 'TOTALES' : (isset($totales[$k]) ? R::texto($totales[$k], $columnas[$k][1]) : '') }}</td>@endforeach</tr>
                </tfoot>
            @endif
        </table>
    </div>
    <p class="text-xs text-gray-400 mt-2">{{ $descripcion }} Las notas de crédito restan. Los comprobantes anulados no se incluyen.</p>

    @if ($datosGrafico->count() > 1)
        @php $graf = ['labels' => $datosGrafico->map(fn($f) => mb_substr((string) R::texto($f[$grafico['etiqueta']], $columnas[$grafico['etiqueta']][1]), 0, 22))->values(),
            'data' => $datosGrafico->map(fn($f) => round((float) $f[$grafico['valor']], 2))->values(), 'label' => $columnas[$grafico['valor']][0]]; @endphp
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
        <script>
            (() => {
                const g = @json($graf);
                if (window.Chart) new Chart(document.getElementById('grafico'), { type: 'bar',
                    data: { labels: g.labels, datasets: [{ label: g.label, data: g.data, backgroundColor: '#6366f1', borderRadius: 6 }] },
                    options: { plugins: { legend: { display: false } } } });
            })();
        </script>
    @endif
@endsection
