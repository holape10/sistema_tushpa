@extends('layouts.app')
@section('title', 'Categorías')
@section('content')
    @include('empresas.partials.alert')
    <div class="flex justify-end mb-4">
        <a href="{{ route('categorias.create') }}" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">+ Nueva Categoría</a>
    </div>
    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr><th class="px-4 py-3 text-left">Nombre</th><th class="px-4 py-3 text-left">Color</th><th class="px-4 py-3 text-right">Acciones</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($categorias as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-700">{{ $c->cat_nom }}</td>
                        <td class="px-4 py-3"><span class="inline-block w-4 h-4 rounded-full" style="background:{{ $c->color }}"></span></td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <a href="{{ route('categorias.edit', $c->cat_id) }}" class="text-indigo-600 hover:underline">Editar</a>
                            <form action="{{ route('categorias.destroy', $c->cat_id) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar?')">
                                @csrf @method('DELETE')<button class="text-red-600 hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">Sin categorías</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection