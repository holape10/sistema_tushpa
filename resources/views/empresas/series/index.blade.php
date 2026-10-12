@extends('layouts.app')
@section('title', 'Números de serie')
@section('content')
    @php
        $colores = ['DISPONIBLE' => 'bg-emerald-100 text-emerald-700', 'VENDIDO' => 'bg-sky-100 text-sky-700', 'BAJA' => 'bg-gray-200 text-gray-600'];
    @endphp

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div>
            <h2 class="text-xl font-bold text-gray-800">Números de serie</h2>
            <p class="text-sm text-gray-500">Busca una serie (o IMEI) para ver de qué compra vino, a quién se vendió y su garantía.</p>
        </div>
    </div>

    <form class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex flex-wrap items-end gap-3">
        <label class="text-sm flex-1 min-w-[240px]">Serie, producto o cliente
            <input name="q" value="{{ $q }}" autofocus placeholder="Escanea o escribe la serie…" class="block w-full rounded-lg border-gray-300 text-sm uppercase">
        </label>
        <div class="flex flex-wrap gap-2 text-sm">
            @foreach (['' => 'Todas', 'DISPONIBLE' => 'Disponibles', 'VENDIDO' => 'Vendidas', 'BAJA' => 'De baja'] as $valor => $texto)
                <label class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border cursor-pointer {{ (string) $estado === $valor ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-200 text-gray-600' }}">
                    <input type="radio" name="estado" value="{{ $valor }}" @checked((string) $estado === $valor) class="hidden" onchange="this.form.submit()">
                    {{ $texto }} @if ($valor)<span class="opacity-70">({{ $totales[$valor] ?? 0 }})</span>@endif
                </label>
            @endforeach
        </div>
        <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Buscar</button>
    </form>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-3 py-3 text-left">Serie</th>
                    <th class="px-3 py-3 text-left">Producto</th>
                    <th class="px-3 py-3 text-left">Estado</th>
                    <th class="px-3 py-3 text-left">Ingreso</th>
                    <th class="px-3 py-3 text-left">Venta</th>
                    <th class="px-3 py-3 text-left">Garantía</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($series as $s)
                    @php $vigente = $s->garantia_hasta && $s->garantia_hasta >= now()->toDateString(); @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-mono font-bold">{{ $s->serie }}</td>
                        <td class="px-3 py-2">{{ $s->pronom }} <span class="text-xs text-gray-400">{{ $s->procod }}</span>
                            @if ($s->almacen)<div class="text-xs text-gray-400">{{ $s->almacen }}</div>@endif</td>
                        <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $colores[$s->estado] ?? '' }}">{{ $s->estado }}</span></td>
                        <td class="px-3 py-2 text-gray-600">
                            {{ $s->fecha_ingreso ? \Carbon\Carbon::parse($s->fecha_ingreso)->format('d/m/Y') : '' }}
                            @if ($s->com_cab_id)<div class="text-xs">Compra {{ $s->com_doc_ser }}-{{ $s->com_doc_num }} · {{ $s->prov_raz }}</div>
                            @elseif ($s->mov_cab_id)<div class="text-xs">Ingreso MOV-{{ $s->mov_cab_id }}</div>@endif
                        </td>
                        <td class="px-3 py-2 text-gray-600">
                            @if ($s->IdCpe_cabecera)
                                {{ \Carbon\Carbon::parse($s->fecha_venta)->format('d/m/Y') }}
                                <a href="{{ route('cobros.voucher', $s->IdCpe_cabecera) }}" target="_blank" class="block text-xs text-indigo-600 hover:underline">{{ $s->serdoc }}-{{ str_pad($s->numdoc, 8, '0', STR_PAD_LEFT) }}</a>
                                <div class="text-xs">{{ $s->cliente }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($s->garantia_hasta)
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $vigente ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                    {{ $vigente ? 'Vigente' : 'Vencida' }} · {{ \Carbon\Carbon::parse($s->garantia_hasta)->format('d/m/Y') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">
                        {{ $q !== '' ? 'No se encontró esa serie.' : 'Aún no hay series. Marca el producto como "Vender con número de serie" y registra sus series al comprar.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $series->links() }}</div>
@endsection
