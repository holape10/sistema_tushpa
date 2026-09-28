@extends('layouts.app')
@section('title', 'Nuevo Producto')

@section('content')
    @include('empresas.partials.alert')

    <form action="{{ route('productos.store') }}" method="POST" x-data="productoForm()" class="space-y-6 max-w-4xl">
        @csrf

        <!-- Tipo -->
        <div class="bg-white rounded-2xl shadow-sm p-5 sm:p-6">
            <label class="block text-sm font-medium text-gray-700 mb-3">¿Qué vas a registrar? *</label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <label class="cursor-pointer">
                    <input type="radio" name="promocion" value="0" x-model="tipo" class="peer hidden" checked>
                    <div class="border-2 rounded-xl p-3 text-center text-sm font-medium peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700 border-gray-200 text-gray-500 transition">
                        🥤 Producto
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="promocion" value="4" x-model="tipo" class="peer hidden">
                    <div class="border-2 rounded-xl p-3 text-center text-sm font-medium peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700 border-gray-200 text-gray-500 transition">
                        🧅 Insumo
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="promocion" value="2" x-model="tipo" class="peer hidden">
                    <div class="border-2 rounded-xl p-3 text-center text-sm font-medium peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700 border-gray-200 text-gray-500 transition">
                        🍽️ Preparado
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="promocion" value="6" x-model="tipo" class="peer hidden">
                    <div class="border-2 rounded-xl p-3 text-center text-sm font-medium peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700 border-gray-200 text-gray-500 transition">
                        🎁 Combo
                    </div>
                </label>
            </div>
            <p class="text-xs text-gray-400 mt-3" x-show="tipo == '0'">Un producto que vendes tal cual (gaseosas, cervezas, snacks).</p>
            <p class="text-xs text-gray-400 mt-3" x-show="tipo == '4'">Materia prima para preparar otros productos (arroz, papa, pollo). No se vende directo.</p>
            <p class="text-xs text-gray-400 mt-3" x-show="tipo == '2'">El plato final que se vende (ej: Ceviche Mixto).</p>
            <p class="text-xs text-gray-400 mt-3" x-show="tipo == '6'">Une varios Productos/Preparados en un solo precio (ej: Combo Cevichero).</p>
        </div>

        <!-- Datos generales -->
        <div class="bg-white rounded-2xl shadow-sm p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                <input type="text" name="pronom" value="{{ old('pronom') }}" required
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                <input type="text" name="procod" value="{{ old('procod') }}" placeholder="Se autogenera si lo dejas vacío"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Unidad de medida *</label>
                <select name="umecod" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($unidades as $u)
                        <option value="{{ $u->umecod }}" @selected($u->umecod == 'NIU')>{{ $u->umenom }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Si no estás seguro, deja "Unidad" — es la que acepta SUNAT sin problema.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Línea</label>
                <select name="tip_pro_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- Selecciona --</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                <select name="cat_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- Selecciona --</option>
                    @foreach ($categorias as $c) <option value="{{ $c->cat_id }}">{{ $c->cat_nom }}</option> @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Subcategoría</label>
                <select name="subcat_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- Selecciona --</option>
                    @foreach ($subcategorias as $s) <option value="{{ $s->subcat_id }}">{{ $s->subcat_nom }}</option> @endforeach
                </select>
            </div>

            <div x-show="tipo != '4'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Precio de venta *</label>
                <input type="number" step="0.01" name="propun" value="{{ old('propun') }}"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Costo</label>
                <input type="number" step="0.01" name="costo" value="{{ old('costo', 0) }}"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        <!-- Armado del combo (solo si tipo = 6) -->
        <div class="bg-white rounded-2xl shadow-sm p-5 sm:p-6" x-show="tipo == '6'" x-cloak>
            <label class="block text-sm font-medium text-gray-700 mb-3">¿Qué incluye este combo? *</label>
            <div class="space-y-2 max-h-80 overflow-y-auto border border-gray-100 rounded-xl p-3">
                @foreach ($itemsParaCombo as $item)
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-gray-600">{{ $item->pronom }}
                            <span class="text-xs text-gray-400">({{ $item->promocion == 2 ? 'Preparado' : 'Producto' }})</span>
                        </span>
                        <input type="number" min="0" step="1" placeholder="0" name="combo_items[{{ $item->IdProducto }}]"
                            class="w-20 rounded-lg border-gray-300 text-sm text-center focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                @endforeach
                @if ($itemsParaCombo->isEmpty())
                    <p class="text-sm text-gray-400">Primero registra al menos un Producto o Preparado para poder armar combos.</p>
                @endif
            </div>
            <p class="text-xs text-gray-400 mt-2">Escribe la cantidad de cada uno que va dentro del combo. Deja en 0 los que no apliquen.</p>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('productos.index') }}" class="px-4 py-2 text-sm text-gray-600">Cancelar</a>
            <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Guardar</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    function productoForm() {
        return { tipo: '{{ old('promocion', '0') }}' }
    }
</script>
@endpush