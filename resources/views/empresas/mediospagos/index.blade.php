@extends('layouts.app')
@section('title', 'Medios de Pago')
@section('content')
    @include('empresas.partials.alert')
    <div class="flex justify-end mb-4">
        <a href="{{ route('mediospagos.create') }}" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">+ Nuevo Medio de Pago</a>
    </div>
    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr><th class="px-4 py-3 text-left">Nombre</th><th class="px-4 py-3 text-center">Predeterminado</th><th class="px-4 py-3 text-right">Acciones</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($mediosPagos as $m)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-700">{{ $m->nom_med_pag }}</td>
                        <td class="px-4 py-3 text-center">{{ $m->predeterminado == '1' ? '✅' : '' }}</td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <a href="{{ route('mediospagos.edit', $m->id_med_pag) }}" class="text-indigo-600 hover:underline">Editar</a>
                            <form action="{{ route('mediospagos.destroy', $m->id_med_pag) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar?')">
                                @csrf @method('DELETE')<button class="text-red-600 hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">Sin medios de pago</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection