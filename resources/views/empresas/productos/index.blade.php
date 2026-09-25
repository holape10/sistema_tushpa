@extends('layouts.app')
@section('title', 'Productos')

@section('content')
    @include('empresas.partials.alert')

    <div class="flex flex-col sm:flex-row justify-between gap-3 mb-4">
        <form method="GET" class="flex-1 max-w-sm">
            <input type="text" name="q" value="{{ $q }}" placeholder="Buscar producto..."
                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </form>
        <a href="{{ route('productos.create') }}"
           class="inline-flex justify-center items-center px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition">
            + Nuevo Producto
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Nombre</th>
                    <th class="px-4 py-3 text-left">Categoría</th>
                    <th class="px-4 py-3 text-right">Precio</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($productos as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-700">{{ $p->pronom }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $p->categoria->cat_nom ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">S/ {{ number_format($p->propun, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs {{ $p->proest == 'Activo' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $p->proest }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <a href="{{ route('productos.edit', $p->IdProducto) }}" class="text-indigo-600 hover:underline">Editar</a>
                            <form action="{{ route('productos.destroy', $p->IdProducto) }}" method="POST" class="inline"
                                  onsubmit="return confirm('¿Eliminar este producto?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">Sin productos registrados</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $productos->links() }}</div>
@endsection