@extends('layouts.app')
@section('title', 'Transferencia TRF-' . $transferencia->mov_cab_id)
@section('content')
    @include('empresas.partials.alert')

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <a href="{{ route('transferencias.index') }}" class="px-4 py-2 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">← Transferencias</a>
        <button onclick="window.print()" class="ml-auto px-4 py-2 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">Imprimir</button>
        @if ($transferencia->estado !== 'ANULADO' && auth()->user()->esAdminOCaja())
            <form method="POST" action="{{ route('transferencias.anular', $transferencia->mov_cab_id) }}"
                  onsubmit="return confirm('Los productos regresarán del almacén de destino al de origen. ¿Anular la transferencia?')">
                @csrf
                <button class="px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-semibold hover:bg-red-700">Anular</button>
            </form>
        @endif
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-4 mb-4 grid grid-cols-2 sm:grid-cols-5 gap-4 text-sm">
        <div><p class="text-xs text-gray-400 uppercase">Transferencia</p><p class="font-bold text-gray-700">TRF-{{ $transferencia->mov_cab_id }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Fecha</p><p class="font-semibold text-gray-700">{{ \Carbon\Carbon::parse($transferencia->fecha)->format('d/m/Y') }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Origen</p><p class="font-semibold text-gray-700">{{ $transferencia->origen }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Destino</p><p class="font-semibold text-gray-700">{{ $transferencia->destino }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Estado</p>
            <span class="px-2 py-1 rounded-full text-xs font-bold {{ $transferencia->estado === 'ANULADO' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">{{ $transferencia->estado }}</span></div>
        @if ($transferencia->observaciones)
            <div class="col-span-full"><p class="text-xs text-gray-400 uppercase">Observación</p><p class="text-gray-700">{{ $transferencia->observaciones }}</p></div>
        @endif
        <div class="col-span-full text-xs text-gray-400">Registrado por {{ $transferencia->apeusu }}</div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr><th class="px-4 py-3 text-left">Código</th><th class="px-4 py-3 text-left">Producto</th><th class="px-4 py-3 text-left">Unidad</th>
                    <th class="px-4 py-3 text-right">Cantidad</th><th class="px-4 py-3 text-right">Costo unit.</th><th class="px-4 py-3 text-right">Total</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($items as $i)
                    <tr>
                        <td class="px-4 py-2 text-gray-500">{{ $i->procod }}</td>
                        <td class="px-4 py-2 font-medium text-gray-700">{{ $i->pronom }}@if ($i->mov_lote)<span class="block text-xs font-normal text-teal-700">Lote {{ $i->mov_lote }}{{ $i->mov_vencimiento ? ' · vence ' . \Carbon\Carbon::parse($i->mov_vencimiento)->format('d/m/Y') : '' }}</span>@endif</td>
                        <td class="px-4 py-2 text-gray-500">{{ $i->umecod }}</td>
                        <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($i->cantidad, 3), '0'), '.') }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($i->costo, 2) }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($i->cantidad * $i->costo, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 font-semibold">
                <tr><td colspan="5" class="px-4 py-3 text-right">Total valorizado</td><td class="px-4 py-3 text-right">S/ {{ number_format($items->sum(fn($i) => $i->cantidad * $i->costo), 2) }}</td></tr>
            </tfoot>
        </table>
    </div>
@endsection
