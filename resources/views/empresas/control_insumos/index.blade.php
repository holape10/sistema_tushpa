@extends('layouts.app')
@section('title', 'Control de insumos')
@section('content')
@use('App\Support\ControlInsumos')
@php
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.') ?: '0';
    $perdido = $filas->where('valor', '>', 0)->sum('valor');
    $mermas = $filas->sum(fn ($f) => $f->mermas * $f->costo);
    $alertas = $filas->filter(fn ($f) => $f->desviacion !== null && $f->desviacion >= ControlInsumos::ALERTA)->count();
@endphp
<div class="max-w-6xl mx-auto space-y-4">
    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
        <div>
            <h1 class="text-xl font-extrabold text-gray-800">🔍 Control de insumos</h1>
            <p class="text-sm text-gray-500">Lo que se <b>debió gastar</b> según las recetas de lo vendido, lo que se <b>botó con merma</b> registrada y lo que <b>faltó en los conteos</b>.
                El faltante que nadie registró es pérdida sin explicación: desperdicio, porciones grandes o robo. Para que salga, cuenta el almacén (mejor con el inventario ciego).</p>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2 text-sm">
            <label class="font-semibold text-gray-700">Almacén
                <select name="almacen" class="block mt-1 h-10 rounded-xl border-gray-300 text-sm">
                    @foreach ($almacenes as $a)<option value="{{ $a->id_almacen }}" @selected($a->id_almacen === $almacen->id_almacen)>{{ $a->descripcion }}</option>@endforeach
                </select></label>
            <label class="font-semibold text-gray-700">Desde<input type="date" name="desde" value="{{ $desde }}" class="block mt-1 h-10 rounded-xl border-gray-300 text-sm"></label>
            <label class="font-semibold text-gray-700">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block mt-1 h-10 rounded-xl border-gray-300 text-sm"></label>
            <button class="h-10 px-4 rounded-xl bg-indigo-600 text-white font-bold">Ver</button>
        </form>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-center">
            <div class="rounded-xl bg-rose-50 p-3"><p class="text-2xl font-extrabold text-rose-700">S/ {{ number_format($perdido, 2) }}</p><p class="text-xs text-rose-700">faltó sin explicación (en conteos)</p></div>
            <div class="rounded-xl bg-amber-50 p-3"><p class="text-2xl font-extrabold text-amber-700">S/ {{ number_format($mermas, 2) }}</p><p class="text-xs text-amber-700">mermas registradas</p></div>
            <div class="rounded-xl bg-gray-50 p-3"><p class="text-2xl font-extrabold text-gray-800">{{ $alertas }}</p><p class="text-xs text-gray-500">insumos con desviación de {{ ControlInsumos::ALERTA }}% o más</p></div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-xs uppercase text-gray-500 bg-gray-50"><tr>
                <th class="px-4 py-2 text-left">Insumo / producto</th><th class="px-3 py-2 text-right">Debió gastarse</th><th class="px-3 py-2 text-right">Merma</th>
                <th class="px-3 py-2 text-right">Faltó en conteo</th><th class="px-3 py-2 text-right">Sobró</th><th class="px-3 py-2 text-right">Desviación</th><th class="px-4 py-2 text-right">Pérdida S/</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($filas as $f)
                    @php $alerta = $f->desviacion !== null && $f->desviacion >= ControlInsumos::ALERTA; @endphp
                    <tr class="{{ $alerta ? 'bg-rose-50/60' : '' }}">
                        <td class="px-4 py-2 font-semibold text-gray-800">{{ $f->nombre }} <span class="text-xs font-normal text-gray-400">{{ \App\Support\Recetas::nombreUnidad($f->unidad) }}</span></td>
                        <td class="px-3 py-2 text-right">{{ $num($f->teorico) }}</td>
                        <td class="px-3 py-2 text-right text-amber-700">{{ $f->mermas > 0 ? $num($f->mermas) : '—' }}</td>
                        <td class="px-3 py-2 text-right text-rose-700">{{ $f->faltante > 0 ? $num($f->faltante) : '—' }}</td>
                        <td class="px-3 py-2 text-right text-emerald-700">{{ $f->sobrante > 0 ? $num($f->sobrante) : '—' }}</td>
                        <td class="px-3 py-2 text-right font-bold {{ $alerta ? 'text-rose-700' : 'text-gray-600' }}">{{ $f->desviacion !== null ? $f->desviacion.'%' : '—' }}</td>
                        <td class="px-4 py-2 text-right font-bold {{ $f->valor > 0 ? 'text-rose-700' : 'text-gray-500' }}">{{ number_format($f->valor, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">Sin movimientos en esas fechas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
