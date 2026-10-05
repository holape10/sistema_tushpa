@extends('layouts.app')
@section('title', 'Transferencias')
@section('content')
    @include('empresas.partials.alert')

    <div class="flex flex-col sm:flex-row justify-between gap-3 mb-4">
        <form method="GET">
            <select name="almacen" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
                <option value="">Todos los almacenes</option>
                @foreach ($almacenes as $a)
                    <option value="{{ $a->id_almacen }}" @selected(request('almacen') == $a->id_almacen)>{{ $a->descripcion }}</option>
                @endforeach
            </select>
        </form>
        @if (auth()->user()->esAdminOCaja())
            <a href="{{ route('transferencias.create') }}" class="inline-flex justify-center items-center px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">+ Nueva transferencia</a>
        @endif
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">N°</th>
                    <th class="px-4 py-3 text-left">Fecha</th>
                    <th class="px-4 py-3 text-left">Origen</th>
                    <th class="px-4 py-3 text-left">Destino</th>
                    <th class="px-4 py-3 text-left">Observación</th>
                    <th class="px-4 py-3 text-right">Productos</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3 text-left">Usuario</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($transferencias as $t)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">TRF-{{ $t->mov_cab_id }}</td>
                        <td class="px-4 py-3">{{ \Carbon\Carbon::parse($t->fecha)->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $t->origen }}</td>
                        <td class="px-4 py-3">{{ $t->destino }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $t->observaciones }}</td>
                        <td class="px-4 py-3 text-right">{{ $t->items }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-bold {{ $t->estado === 'ANULADO' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">{{ $t->estado }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $t->apeusu }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('transferencias.show', $t->mov_cab_id) }}" class="text-indigo-600 hover:underline">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-6 text-center text-gray-400">Aún no hay transferencias.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transferencias->links() }}</div>
@endsection
