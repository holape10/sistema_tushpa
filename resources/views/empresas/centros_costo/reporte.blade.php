@extends('layouts.app')
@section('title', 'Resultado por centro de costo')
@section('content')
@php
    $soles = fn ($n) => ($n < 0 ? '- ' : '').'S/ '.number_format(abs((float) $n), 2);
    $total = fn ($campo) => $filas->sum($campo);
@endphp
<div class="max-w-7xl mx-auto space-y-4">
    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-56">
            <a href="{{ route('centros_costo.index') }}" class="text-xs font-semibold text-indigo-700 hover:underline">← Centros de costo</a>
            <h1 class="text-xl font-extrabold text-gray-800">📊 Resultado por centro de costo</h1>
            <p class="text-sm text-gray-500">Ventas sin IGV y su costo (locales), gastos sin el IGV que se usa como crédito fiscal, y depreciación de los activos.</p>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <label class="text-xs font-semibold text-gray-500">Desde<input type="date" name="desde" value="{{ $desde }}" class="block mt-1 h-10 rounded-xl border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-500">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block mt-1 h-10 rounded-xl border-gray-300 text-sm"></label>
            <button class="h-10 px-4 rounded-xl bg-gray-800 text-white text-sm font-semibold">Ver</button>
            <a href="{{ route('centros_costo.excel', ['desde' => $desde, 'hasta' => $hasta]) }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">⬇ Excel</a>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr><th class="text-left px-4 py-2">Centro</th><th class="text-right px-4 py-2">Ventas</th><th class="text-right px-4 py-2">Costo de ventas</th>
                    <th class="text-right px-4 py-2">Gastos</th><th class="text-right px-4 py-2">Depreciación</th><th class="text-right px-4 py-2">Resultado</th><th class="text-right px-4 py-2">Margen</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($filas as $f)
                    <tr class="align-top">
                        <td class="px-4 py-2">
                            <p class="font-bold text-gray-800"><span class="font-mono">{{ $f->codigo }}</span> {{ $f->nombre }}</p>
                            @if ($f->porCategoria)
                                <details><summary class="cursor-pointer text-xs text-indigo-700">Gastos por categoría</summary>
                                    <ul class="mt-1 text-xs text-gray-600">@foreach ($f->porCategoria as $cat => $m)<li class="flex justify-between gap-4"><span>{{ $cat }}</span><span>{{ $soles($m) }}</span></li>@endforeach</ul>
                                </details>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">{{ $f->ventas ? $soles($f->ventas) : '—' }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap text-gray-600">{{ $f->costo ? $soles(-$f->costo) : '—' }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap text-gray-600">{{ $f->gastos ? $soles(-$f->gastos) : '—' }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap text-gray-600">{{ $f->depreciacion ? $soles(-$f->depreciacion) : '—' }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap font-extrabold {{ $f->resultado < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $soles($f->resultado) }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">{{ $f->margen !== null ? $f->margen.'%' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">Sin centros de costo. Créalos primero.</td></tr>
                @endforelse
            </tbody>
            @if ($filas->count())
                <tfoot class="bg-slate-50 font-bold">
                    <tr><td class="px-4 py-2">Total</td><td class="px-4 py-2 text-right">{{ $soles($total('ventas')) }}</td><td class="px-4 py-2 text-right">{{ $soles(-$total('costo')) }}</td>
                        <td class="px-4 py-2 text-right">{{ $soles(-$total('gastos')) }}</td><td class="px-4 py-2 text-right">{{ $soles(-$total('depreciacion')) }}</td>
                        <td class="px-4 py-2 text-right {{ $total('resultado') < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $soles($total('resultado')) }}</td><td></td></tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
