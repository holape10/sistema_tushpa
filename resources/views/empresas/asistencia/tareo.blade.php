@extends('layouts.app')
@section('title', 'Reporte Tareo')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    @php
        $colores = ['1' => 'bg-emerald-100 text-emerald-700', 'T' => 'bg-amber-100 text-amber-800', '0' => 'bg-rose-100 text-rose-700',
                    'D' => 'bg-slate-100 text-slate-500', 'F' => 'bg-violet-100 text-violet-700'];
        $mesActual = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
        $mesAnterior = [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()];
    @endphp

    <form method="GET" class="bg-white rounded-2xl shadow-sm p-4 mb-4 print:hidden">
        <div class="flex flex-wrap items-end gap-3">
            <label class="text-sm">Desde<input type="date" name="desde" value="{{ $desde }}" class="block rounded-lg border-gray-300 text-sm"></label>
            <label class="text-sm">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block rounded-lg border-gray-300 text-sm"></label>
            <label class="text-sm flex-1 min-w-[200px]">Trabajador
                <select name="emp" class="block w-full rounded-lg border-gray-300 text-sm">
                    <option value="">Todos</option>
                    @foreach ($todos as $e)<option value="{{ $e->emp_id }}" @selected(request('emp') == $e->emp_id)>{{ \App\Support\Asistencia::nombre($e) }}</option>@endforeach
                </select></label>
            <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold">Ver</button>
            <a href="{{ request()->fullUrlWithQuery(['excel' => 1]) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold"><i class="fas fa-file-excel"></i> Excel</a>
            <button type="button" onclick="window.print()" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold"><i class="fas fa-print"></i> Imprimir</button>
        </div>
        <div class="flex gap-2 mt-3 text-xs">
            <a href="{{ route('asistencia.tareo', ['desde' => $mesActual[0], 'hasta' => $mesActual[1]]) }}" class="px-3 py-1 rounded-full bg-gray-100 hover:bg-gray-200 font-semibold">Este mes</a>
            <a href="{{ route('asistencia.tareo', ['desde' => $mesAnterior[0], 'hasta' => $mesAnterior[1]]) }}" class="px-3 py-1 rounded-full bg-gray-100 hover:bg-gray-200 font-semibold">Mes anterior</a>
        </div>
    </form>

    <div class="hidden print:block mb-2 text-center">
        <h2 class="font-bold text-lg">REPORTE DE TAREO</h2>
        <p class="text-sm">Del {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</p>
    </div>

    <div class="flex flex-wrap gap-2 mb-3 text-xs">
        @foreach ($leyendas as $c => $d)
            <span class="px-2 py-1 rounded-lg font-semibold {{ $colores[$c] ?? 'bg-teal-100 text-teal-700' }}"><strong>{{ $c }}</strong> = {{ $d }}</span>
        @endforeach
        <span class="px-2 py-1 rounded-lg font-semibold bg-white text-gray-400"><strong>-</strong> = sin horario ni marcación</span>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="text-xs w-full">
            <thead>
                <tr class="bg-slate-700 text-white">
                    <th class="sticky left-0 z-10 bg-slate-700 px-3 py-2 text-left min-w-[180px]">Trabajador</th>
                    @foreach ($fechas as $f)
                        <th class="px-0.5 py-1 w-7 font-semibold {{ isset($feriados[$f['fecha']]) ? 'bg-violet-600' : (in_array($f['letra'], ['D']) ? 'bg-slate-600' : '') }}"
                            title="{{ $feriados[$f['fecha']] ?? '' }}"><span class="block opacity-70 font-normal">{{ $f['letra'] }}</span>{{ $f['num'] }}</th>
                    @endforeach
                    @foreach (array_keys($leyendas) as $c)<th class="px-1.5 py-1 bg-slate-800">{{ $c }}</th>@endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($matriz as $m)
                    <tr class="hover:bg-gray-50">
                        <td class="sticky left-0 z-10 bg-white px-3 py-1.5 font-semibold text-gray-700 uppercase whitespace-nowrap">{{ \App\Support\Asistencia::nombre($m['empleado']) }}</td>
                        @foreach ($m['dias'] as $letra)
                            <td class="p-0.5 text-center"><span class="block rounded font-bold py-0.5 {{ $letra === '' || $letra === '-' ? 'text-gray-300' : ($colores[$letra] ?? 'bg-teal-100 text-teal-700') }}">{{ $letra }}</span></td>
                        @endforeach
                        @foreach (array_keys($leyendas) as $c)<td class="px-1.5 text-center font-bold bg-gray-50">{{ $m['totales'][$c] ?? 0 }}</td>@endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($fechas) + count($leyendas) + 1 }}" class="px-4 py-10 text-center text-gray-400">No hay trabajadores con asistencia activa.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <style>@media print { @page { size: A4 landscape; margin: 8mm; } aside, header, nav { display: none !important; } }</style>
@endsection
