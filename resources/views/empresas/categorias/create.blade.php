@extends('layouts.app')
@section('title', 'Nueva Categoría')
@section('content')
    @include('empresas.partials.alert')
    <form action="{{ route('categorias.store') }}" method="POST" class="bg-white rounded-2xl shadow-sm p-6 max-w-lg space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
            <input type="text" name="cat_nom" value="{{ old('cat_nom') }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Línea (tipo producto)</label>
            <select name="tip_pro_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">-- Selecciona --</option>
                @foreach ($tipos as $t) <option value="{{ $t->tip_pro_id }}">{{ $t->tip_pro_nom }}</option> @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
            <input type="color" name="color" value="{{ old('color', '#3f4aee') }}" class="w-16 h-10 rounded-lg border-gray-300">
        </div>
        <div class="flex justify-end gap-3">
            <a href="{{ route('categorias.index') }}" class="px-4 py-2 text-sm text-gray-600">Cancelar</a>
            <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Guardar</button>
        </div>
    </form>
@endsection