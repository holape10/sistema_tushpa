@extends('layouts.app')
@section('title', 'Editar Producto')

@section('content')
    @include('empresas.partials.alert')

    <form action="{{ route('productos.update', $producto->IdProducto) }}" method="POST" class="space-y-6 max-w-4xl">
        @csrf @method('PUT')

        <!-- Tipo (solo lectura, no se puede cambiar después de creado) -->
        <div class="bg-white rounded-2xl shadow-sm p-5 sm:p-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
            <span class="inline-block px-3 py-1 rounded-full text-sm font-medium bg-indigo-50 text-indigo-600">
                {{ $producto->tipo_nombre }}
            </span>
            <p class="text-xs text-gray-400 mt-2">El tipo no se puede cambiar una vez creado. Si te equivocaste, elimina este registro y crea uno nuevo.</p>
        </div>

        <!-- Datos generales -->
        <div class="bg-white rounded-2xl shadow-sm p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                <input type="text" name="pronom" value="{{ $producto->pronom }}" required
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                <input type="text" value="{{ $producto->procod }}" disabled
                    class="w-full rounded-lg border-gray-200 bg-gray-100 text-sm text-gray-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Unidad de medida *</label>
                <select name="umecod" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($unidades as $u)
                        <option value="{{ $u->umecod }}" @selected($producto->umecod == $u->umecod)>{{ $u->umenom }}</option>
                    @endforeach
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

            @if ($producto->promocion != 4)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Precio de venta *</label>
                    <input type="number" step="0.01" name="propun" value="{{ $producto->propun }}" required
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Costo</label>
                <input type="number" step="0.01" name="costo" value="{{ $producto->costo }}"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select name="proest" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="Activo" @selected($producto->proest == 'Activo')>Activo</option>
                    <option value="Inactivo" @selected($producto->proest == 'Inactivo')>Inactivo</option>
                </select>
            </div>
        </div>

        <!-- Contenido del combo (solo si el producto es tipo Combo) -->
        @if ($producto->promocion == 6)
            <div class="bg-white rounded-2xl shadow-sm p-5 sm:p-6">
                <label class="block text-sm font-medium text-gray-700 mb-3">¿Qué incluye este combo?</label>
                <div class="space-y-2 max-h-80 overflow-y-auto border border-gray-100 rounded-xl p-3">
                    @foreach ($itemsParaCombo as $item)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-gray-600">{{ $item->pronom }}
                                <span class="text-xs text-gray-400">({{ $item->promocion == 2 ? 'Preparado' : 'Producto' }})</span>
                            </span>
                            <input type="number" min="0" step="1"
                                value="{{ $comboActual[$item->IdProducto] ?? 0 }}"
                                name="combo_items[{{ $item->IdProducto }}]"
                                class="w-20 rounded-lg border-gray-300 text-sm text-center focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-2">Cambia las cantidades y guarda para actualizar el contenido del combo.</p>
            </div>
        @endif

        <div class="flex justify-end gap-3">
            <a href="{{ route('productos.index') }}" class="px-4 py-2 text-sm text-gray-600">Cancelar</a>
            <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Actualizar</button>
        </div>
    </form>
@endsection