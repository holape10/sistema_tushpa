@extends('layouts.app')
@section('title', 'Editar Piso')
@section('content')
    @include('empresas.partials.alert')
    <form action="{{ route('pisos.update', $piso->pis_id) }}" method="POST" class="bg-white rounded-2xl shadow-sm p-6 max-w-md space-y-5">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del piso *</label>
            <input type="text" name="pis_nom" value="{{ $piso->pis_nom }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="flex justify-end gap-3">
            <a href="{{ route('pisos.index') }}" class="px-4 py-2 text-sm text-gray-600">Cancelar</a>
            <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Actualizar</button>
        </div>
    </form>
@endsection