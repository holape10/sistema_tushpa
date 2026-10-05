@extends('layouts.app')
@section('title', 'Listado de Cajas / Turnos')
@section('content')
    @include('empresas.partials.alert')
    <form class="flex flex-wrap items-end gap-2 mb-4">
        <label class="text-sm">Desde<input type="date" name="desde" value="{{ $desde }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <label class="text-sm">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Filtrar</button>
        <a href="{{ route('turnos.index') }}" class="ml-auto px-4 py-2 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">Mi turno actual</a>
    </form>
    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">N°</th><th class="px-4 py-3 text-left">Usuario</th>
                    <th class="px-4 py-3 text-left">Apertura</th><th class="px-4 py-3 text-left">Cierre</th>
                    <th class="px-4 py-3 text-right">Fondo</th><th class="px-4 py-3 text-right">Contado</th>
                    <th class="px-4 py-3 text-center">Estado</th><th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($turnos as $t)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $t->turno }}</td>
                        <td class="px-4 py-3">{{ $t->usuario->apeusu ?? $t->IdUsuario }}</td>
                        <td class="px-4 py-3">{{ $t->apertura?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $t->cierre?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($t->monto, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ $t->montocierre !== null ? number_format($t->montocierre, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-bold {{ $t->estado == 'ABIERTO' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $t->estado }}</span>
                        </td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('turnos.show', $t->id_turno) }}" class="text-indigo-600 hover:underline">Ver reporte</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">Sin turnos en el rango.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $turnos->links() }}</div>
@endsection
