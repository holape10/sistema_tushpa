@extends('layouts.app')
@section('title', 'Usuarios')
@section('content')
    @include('empresas.partials.alert')
    <div class="flex justify-end mb-4">
        <a href="{{ route('usuarios.create') }}" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">+ Nuevo Usuario</a>
    </div>
    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr><th class="px-4 py-3 text-left">Usuario</th><th class="px-4 py-3 text-left">Nombres</th><th class="px-4 py-3 text-center">Estado</th><th class="px-4 py-3 text-right">Acciones</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($usuarios as $u)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-700">{{ $u->email }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $u->apeusu }}</td>
                        <td class="px-4 py-3 text-center">{{ $u->estusu ? 'Activo' : 'Inactivo' }}</td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <a href="{{ route('usuarios.edit', $u->IdUsuario) }}" class="text-indigo-600 hover:underline">Editar</a>
                            <form action="{{ route('usuarios.destroy', $u->IdUsuario) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar?')">
                                @csrf @method('DELETE')<button class="text-red-600 hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection