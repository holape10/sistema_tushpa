@extends('layouts.app')
@section('title', ($cab->mov_tip === 'I' ? 'Ingreso' : 'Salida').' MOV-'.$cab->mov_cab_id)
@section('content')
    <style>@media print { aside, nav, header, .no-print { display: none !important; } body { background: #fff; } }</style>
    @php
        $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 5), '0'), '.');
        $ingreso = $cab->mov_tip === 'I';
        $total = $items->sum(fn ($m) => (float) $m->cantidad * (float) $m->costo);
    @endphp

    <div class="no-print flex flex-wrap items-center gap-2 mb-4">
        <a href="{{ route('kardex.movimientos', ['tipo' => $cab->mov_tip]) }}" class="text-sm text-gray-500">← Ingresos y salidas</a>
        <div class="ml-auto flex gap-2">
            <a href="{{ route('kardex.movimiento', ['tipo' => $cab->mov_tip]) }}" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold">+ Nuevo {{ $ingreso ? 'ingreso' : 'salida' }}</a>
            <button type="button" onclick="window.print()" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold">🖨 Imprimir</button>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-5 mb-4">
        <div class="flex flex-wrap items-start gap-4">
            <div class="mr-auto">
                <p class="text-xs font-bold uppercase {{ $ingreso ? 'text-green-600' : 'text-red-600' }}">{{ $ingreso ? 'Ingreso' : 'Salida' }} de productos</p>
                <h1 class="text-2xl font-black text-gray-800">MOV-{{ $cab->mov_cab_id }}</h1>
                <p class="text-sm text-gray-600">{{ $cab->cod_tip_ope }} - {{ $cab->des_tip_ope }}</p>
            </div>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                <dt class="text-gray-500">Fecha</dt><dd class="font-semibold">{{ \Carbon\Carbon::parse($cab->fecha)->format('d/m/Y') }}</dd>
                <dt class="text-gray-500">Almacén</dt><dd class="font-semibold">{{ $cab->almacen ?? '—' }}</dd>
                <dt class="text-gray-500">Registró</dt><dd>{{ $cab->apeusu ?? '—' }}</dd>
                @if ($cab->observaciones)<dt class="text-gray-500">Observación</dt><dd>{{ $cab->observaciones }}</dd>@endif
            </dl>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Código</th>
                    <th class="px-4 py-3 text-left">Producto</th>
                    <th class="px-4 py-3 text-right">Cantidad</th>
                    <th class="px-4 py-3 text-right">Costo unit.</th>
                    <th class="px-4 py-3 text-right">Total S/</th>
                    @if ($ingreso)<th class="px-4 py-3 text-left">Lote / vence</th>@endif
                    <th class="px-4 py-3 text-right">Stock después</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($items as $m)
                    <tr>
                        <td class="px-4 py-2 text-gray-500">{{ $m->procod }}</td>
                        <td class="px-4 py-2"><span class="font-medium text-gray-800">{{ $m->pronom }}</span>
                            @if ($m->series)<span class="block text-xs text-sky-700 font-mono">Series: {{ $m->series }}</span>@endif</td>
                        <td class="px-4 py-2 text-right font-semibold {{ $ingreso ? 'text-green-700' : 'text-red-600' }}">
                            {{ $num($m->cantidad) }} <span class="text-xs text-gray-400">{{ $m->umecod }}</span>
                            @if ($m->equivalente)<span class="block text-xs font-normal text-gray-500">{{ $m->equivalente }}</span>@endif</td>
                        <td class="px-4 py-2 text-right">{{ number_format((float) $m->costo, 2) }}</td>
                        <td class="px-4 py-2 text-right font-semibold">{{ number_format((float) $m->cantidad * (float) $m->costo, 2) }}</td>
                        @if ($ingreso)
                            <td class="px-4 py-2 text-xs">{{ $m->mov_lote ?: '—' }}@if ($m->mov_vencimiento) · {{ \Carbon\Carbon::parse($m->mov_vencimiento)->format('d/m/Y') }}@endif</td>
                        @endif
                        <td class="px-4 py-2 text-right text-gray-500">{{ $num($m->stock) }}
                            @if ($eq = \App\Support\Unidades::mixto((float) $m->stock, $m->umecod ?: 'NIU', $m->ume_equivalente, (float) $m->factor_equivalente))
                                <span class="block text-xs">{{ $eq }}</span>
                            @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin productos.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-50 font-bold">
                <tr><td class="px-4 py-3" colspan="4">{{ $items->count() }} producto(s)</td><td class="px-4 py-3 text-right">S/ {{ number_format($total, 2) }}</td><td colspan="{{ $ingreso ? 2 : 1 }}"></td></tr>
            </tfoot>
        </table>
    </div>
@endsection
