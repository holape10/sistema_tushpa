@extends('layouts.app')
@section('title', 'Nuevo Medio de Pago')
@section('content')
    @include('empresas.partials.alert')
    <form action="{{ route('mediospagos.store') }}" method="POST" class="bg-white rounded-2xl shadow-sm p-6 max-w-md space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
            <input type="text" name="nom_med_pag" value="{{ old('nom_med_pag') }}" required placeholder="Ej: YAPE, PLIN, TARJETA"
                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" name="predeterminado" value="1" class="rounded border-gray-300 text-indigo-600">
            Usar como predeterminado
        </label>
        <div class="flex justify-end gap-3">
            <a href="{{ route('mediospagos.index') }}" class="px-4 py-2 text-sm text-gray-600">Cancelar</a>
            <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Guardar</button>
        </div>
    </form>
@endsection