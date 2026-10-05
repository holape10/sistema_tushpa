@extends('layouts.app')
@section('title', 'Reporte de Jornadas (8h)')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @use('App\Support\Asistencia')
    @php
        $h = fn($c) => $c ? \Carbon\Carbon::parse($c)->format('H:i') : null;
    @endphp

    <form method="GET" class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex flex-wrap items-end gap-3 print:hidden">
        <label class="text-sm">Desde<input type="date" name="desde" value="{{ $desde }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <label class="text-sm">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <label class="text-sm flex-1 min-w-[200px]">Trabajador
            <select name="emp" class="block w-full rounded-lg border-gray-300 text-sm">
                <option value="">Todos</option>
                @foreach ($todos as $e)<option value="{{ $e->emp_id }}" @selected(request('emp') == $e->emp_id)>{{ Asistencia::nombre($e) }}</option>@endforeach
            </select></label>
        <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold">Ver</button>
        <a href="{{ request()->fullUrlWithQuery(['excel' => 1]) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold"><i class="fas fa-file-excel"></i> Excel</a>
        <button type="button" onclick="window.print()" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold"><i class="fas fa-print"></i> Imprimir</button>
    </form>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Jornadas</p><p class="text-2xl font-black text-gray-800">{{ $registros->count() }}</p>
            <p class="text-xs text-emerald-600 font-semibold">{{ $totales['conformes'] }} conformes</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Horas laboradas</p><p class="text-2xl font-black text-indigo-700">{{ Asistencia::horas($totales['laborado']) }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Tardanza acumulada</p><p class="text-2xl font-black text-rose-600">{{ Asistencia::horas($totales['tardanza']) }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Tiempo extra en el local</p><p class="text-2xl font-black text-amber-600">{{ Asistencia::horas($totales['extra']) }}</p></div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-700 text-white text-xs uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">Trabajador</th><th class="px-3 py-2">Turno</th>
                    <th class="px-2 py-2">Entrada</th><th class="px-2 py-2">Salida</th><th class="px-2 py-2">Retorno</th><th class="px-2 py-2">Salida final</th>
                    <th class="px-2 py-2 text-right">Laborado</th><th class="px-2 py-2 text-right">Tardanza</th><th class="px-2 py-2 text-right">Extra</th><th class="px-3 py-2">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($registros as $r)
                    @php $c = $r->calc; @endphp
                    <tr class="hover:bg-gray-50 align-top">
                        <td class="px-3 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($r->fecha)->locale('es')->isoFormat('ddd DD/MM') }}</td>
                        <td class="px-3 py-2"><span class="font-semibold text-gray-700 uppercase">{{ Asistencia::nombre($r) }}</span>
                            @if ($r->autorizado_por)<span class="block text-[11px] text-amber-700"><i class="fas fa-user-shield"></i> {{ $r->autorizado_por }}{{ $r->motivo ? ' · ' . $r->motivo : '' }}</span>@endif</td>
                        <td class="px-3 py-2 text-center">
                            @if ($r->codigo)<span class="px-1.5 py-0.5 rounded text-white text-xs font-bold" style="background: {{ $r->color }}">{{ $r->codigo }}</span>
                            @else<span class="text-xs text-gray-400">Sin horario</span>@endif
                        </td>
                        @foreach (['check_in_1', 'check_out_1', 'check_in_2', 'check_out_2'] as $campo)
                            <td class="px-2 py-2 text-center font-mono {{ $r->$campo ? 'text-gray-800' : 'text-gray-300' }}">{{ $h($r->$campo) ?? '—' }}</td>
                        @endforeach
                        <td class="px-2 py-2 text-right font-semibold">{{ Asistencia::horas($c['laborado']) }}</td>
                        <td class="px-2 py-2 text-right {{ $c['tardanza'] ? 'text-rose-600 font-semibold' : 'text-gray-300' }}">{{ Asistencia::horas($c['tardanza']) }}</td>
                        <td class="px-2 py-2 text-right {{ $c['extra'] ? 'text-amber-600 font-semibold' : 'text-gray-300' }}">{{ Asistencia::horas($c['extra']) }}</td>
                        <td class="px-3 py-2 text-center">
                            @if ($c['abierta'])<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-sky-100 text-sky-700">EN CURSO</span>
                            @elseif ($c['conforme'])<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">CONFORME</span>
                            @else<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-700">INCOMPLETO</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="px-4 py-10 text-center text-gray-400">No hay marcaciones en este periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400 mt-2">
        <strong>Laborado</strong>: tiempo dentro del horario del turno (desde la hora de entrada, o la real si llegó tarde, hasta la salida programada, o la real si salió antes).
        <strong>Extra</strong>: tiempo presente en el local por encima de su jornada (mínimo 8 h). <strong>Conforme</strong>: laborado + tardanza cubre la jornada programada.
    </p>
    <style>@media print { @page { size: A4 landscape; margin: 8mm; } aside, header, nav { display: none !important; } }</style>
@endsection
