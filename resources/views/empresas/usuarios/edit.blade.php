@extends('layouts.app')
@section('title', 'Editar Usuario')
@section('content')
    @include('empresas.partials.alert')
    <form action="{{ route('usuarios.update', $usuario->IdUsuario) }}" method="POST" class="bg-white rounded-2xl shadow-sm p-6 max-w-3xl space-y-6">
        @csrf @method('PUT')
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Usuario (login)</label>
                <input type="text" value="{{ $usuario->email }}" disabled class="w-full rounded-lg border-gray-200 bg-gray-100 text-sm text-gray-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombres y apellidos *</label>
                <input type="text" name="apeusu" value="{{ $usuario->apeusu }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nueva contraseña</label>
                <input type="password" name="password" placeholder="Dejar en blanco para no cambiarla" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select name="estusu" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="1" @selected($usuario->estusu == 1)>Activo</option>
                    <option value="0" @selected($usuario->estusu == 0)>Inactivo</option>
                </select>
            </div>
        </div>

        <div>
            <p class="text-sm font-medium text-gray-700 mb-2">Opciones del menú que tendrá este usuario</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-gray-50 rounded-xl p-4">
                @foreach ($modulos as $grupo => $items)
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-1">{{ $grupo }}</p>
                        @foreach ($items as $mod)
                            <label class="flex items-center gap-2 text-sm text-gray-600 py-0.5">
                                <input type="checkbox" name="modulos[]" value="{{ $mod->mod_id }}"
                                    @checked(in_array($mod->mod_id, $modulosAsignados))
                                    class="rounded border-gray-300 text-indigo-600">
                                {{ $mod->mod_nom }}
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('usuarios.index') }}" class="px-4 py-2 text-sm text-gray-600">Cancelar</a>
            <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Actualizar</button>
        </div>
    </form>
@endsection