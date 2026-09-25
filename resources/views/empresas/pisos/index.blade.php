@extends('layouts.app')
@section('title', 'Pisos')
@section('content')
    @include('empresas.partials.alert')
    <div class="flex justify-end mb-4">
        <a href="{{ route('pisos.create') }}" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">+ Nuevo Piso</a>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @forelse ($pisos as $p)
            <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center justify-between">
                <span class="font-medium text-gray-700">{{ $p->pis_nom }}</span>
                <div class="space-x-2 text-sm">
                    <a href="{{ route('pisos.edit', $p->pis_id) }}" class="text-indigo-600 hover:underline">Editar</a>
                    <form action="{{ route('pisos.destroy', $p->pis_id) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar?')">
                        @csrf @method('DELETE')<button class="text-red-600 hover:underline">Eliminar</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-gray-400 text-sm">Sin pisos registrados</p>
        @endforelse
    </div>
@endsection