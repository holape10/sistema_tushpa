@extends('layouts.app')
@section('title', 'Editar Producto')

@section('content')
    @include('empresas.partials.alert')

    <form action="{{ route('productos.update', $producto->IdProducto) }}" method="POST" class="bg-white rounded-2xl shadow-sm p-6 max-w-2xl space-y-5">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del producto *</label>
            <input type="text" name="pronom" value="{{ $producto->pronom }}" required
                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Unidad de medida</label>
                <input type="text" name="umecod" value="{{ $producto->umecod }}"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Costo</label>
                <input type="number" step="0.01" name="costo" value="{{ $producto->costo }}"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Precio de venta *</label>
                <input type="number" step="0.01" name="propun" value="{{ $producto->propun }}" required
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select name="proest" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="Activo" @selected($producto->proest == 'Activo')>Activo</option>
                    <option value="Inactivo" @selected($producto->proest == 'Inactivo')>Inactivo</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                <select name="cat_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- Selecciona --</option>
                    @foreach ($categorias as $c)
                        <option value="{{ $c->cat_id }}" @selected($producto->cat_id == $c->cat_id)>{{ $c->cat_nom }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Subcategoría</label>
                <select name="subcat_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- Selecciona --</option>
                    @foreach ($subcategorias as $s)
                        <option value="{{ $s->subcat_id }}" @selected($producto->subcat_id == $s->subcat_id)>{{ $s->subcat_nom }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex justify-end gap-3">
            <a href="{{ route('productos.index') }}" class="px-4 py-2 text-sm text-gray-600">Cancelar</a>
            <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Actualizar</button>
        </div>
    </form>
@endsection