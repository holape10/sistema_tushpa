@extends('layouts.app')
@section('title', 'Mesas')
@section('content')
    @include('empresas.partials.alert')
    <div class="flex justify-end mb-4">
        <a href="{{ route('mesas.create') }}" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">+ Nueva Mesa</a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
        @forelse ($mesas as $m)
            <div class="bg-white rounded-2xl shadow-sm p-4 text-center">
                <div class="w-full aspect-square rounded-xl flex items-center justify-center font-bold text-white
                    {{ $m->mes_est == 'Libre' ? 'bg-green-500' : 'bg-red-500' }}">
                    {{ $m->mes_nom }}
                </div>
                <p class="text-xs text-gray-500 mt-2">{{ $m->piso->pis_nom ?? '-' }}</p>
                <div class="mt-2 space-x-2 text-xs">
                    <a href="{{ route('mesas.edit', $m->mes_id) }}" class="text-indigo-600 hover:underline">Editar</a>
                    <form action="{{ route('mesas.destroy', $m->mes_id) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar?')">
                        @csrf @method('DELETE')<button class="text-red-600 hover:underline">Eliminar</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-gray-400 text-sm col-span-full">Sin mesas registradas</p>
        @endforelse
    </div>
@endsection