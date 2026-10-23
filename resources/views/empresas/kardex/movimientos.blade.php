@extends('layouts.app')
@section('title', 'Ingresos y salidas')
@section('content')
    @include('empresas.partials.alert')

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <h2 class="text-xl font-bold text-gray-800 mr-auto">Ingresos y salidas de productos</h2>
        <a href="{{ route('kardex.movimiento', ['tipo' => 'I']) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold">+ Ingreso</a>
        <a href="{{ route('kardex.movimiento', ['tipo' => 'E']) }}" class="px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-semibold">+ Salida</a>
    </div>

    <form class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex flex-wrap items-end gap-3">
        <label class="text-sm">Tipo
            <select name="tipo" class="block rounded-lg border-gray-300 text-sm">
                <option value="">Ingresos y salidas</option>
                <option value="I" @selected($tipo === 'I')>Solo ingresos</option>
                <option value="E" @selected($tipo === 'E')>Solo salidas</option>
            </select></label>
        <label class="text-sm">Desde<input type="date" name="desde" value="{{ $desde }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <label class="text-sm">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold">Ver</button>
    </form>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">N°</th><th class="px-4 py-3 text-left">Fecha</th><th class="px-4 py-3 text-left">Tipo</th>
                    <th class="px-4 py-3 text-left">Operación</th><th class="px-4 py-3 text-left">Almacén</th><th class="px-4 py-3 text-left">Observación</th>
                    <th class="px-4 py-3 text-right">Ítems</th><th class="px-4 py-3 text-right">Total S/</th><th class="px-4 py-3 text-left">Usuario</th><th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($lista as $r)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-semibold"><a href="{{ route('kardex.movimiento.ver', $r->mov_cab_id) }}" class="text-indigo-600 hover:underline">MOV-{{ $r->mov_cab_id }}</a></td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($r->fecha)->format('d/m/Y') }}</td>
                        <td class="px-4 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $r->mov_tip === 'I' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $r->mov_tip === 'I' ? 'Ingreso' : 'Salida' }}</span></td>
                        <td class="px-4 py-2">{{ $r->des_tip_ope }}</td>
                        <td class="px-4 py-2">{{ $r->almacen }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ $r->observaciones }}</td>
                        <td class="px-4 py-2 text-right">{{ $r->items }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format((float) $r->total, 2) }}</td>
                        <td class="px-4 py-2">{{ $r->apeusu }}</td>
                        <td class="px-4 py-2 text-right"><a href="{{ route('kardex.movimiento.ver', $r->mov_cab_id) }}" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 text-xs font-semibold hover:bg-gray-200">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-8 text-center text-gray-400">Sin ingresos ni salidas en estas fechas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $lista->links() }}</div>
@endsection
