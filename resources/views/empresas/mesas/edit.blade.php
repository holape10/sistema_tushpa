@extends('layouts.app')
@section('title', 'Editar Mesa')
@section('content')
    @include('empresas.partials.alert')
    <form action="{{ route('mesas.update', $mesa->mes_id) }}" method="POST" class="bg-white rounded-2xl shadow-sm p-6 max-w-md space-y-5">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre / número de mesa *</label>
            <input type="text" name="mes_nom" value="{{ $mesa->mes_nom }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Piso *</label>
            <select name="pis_id" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach ($pisos as $p)
                    <option value="{{ $p->pis_id }}" @selected($mesa->pis_id == $p->pis_id)>{{ $p->pis_nom }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
            <select name="mes_est" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="Libre" @selected($mesa->mes_est == 'Libre')>Libre</option>
                <option value="Ocupada" @selected($mesa->mes_est == 'Ocupada')>Ocupada</option>
            </select>
        </div>
        <div class="flex justify-end gap-3">
            <a href="{{ route('mesas.index') }}" class="px-4 py-2 text-sm text-gray-600">Cancelar</a>
            <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Actualizar</button>
        </div>
    </form>
@endsection