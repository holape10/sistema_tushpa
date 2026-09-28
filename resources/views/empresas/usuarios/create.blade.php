@extends('layouts.app')
@section('title', 'Nuevo Usuario')
@section('content')
    @include('empresas.partials.alert')
    
    <div class="max-w-7xl mx-auto">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Nuevo Usuario</h2>
            <p class="text-sm text-gray-500 mt-1">Complete la información para registrar un nuevo usuario en el sistema</p>
        </div>

        <form action="{{ route('usuarios.store') }}" method="POST" class="space-y-6">
            @csrf
            
            <!-- Información Principal -->
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Información de Acceso
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Usuario (login) *</label>
                        <input type="text" name="email" value="{{ old('email') }}" required
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="usuario@ejemplo.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nombre corto *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Nombre de visualización">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contraseña *</label>
                        <input type="password" name="password" required
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="••••••••">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nombres y apellidos *</label>
                        <input type="text" name="apeusu" value="{{ old('apeusu') }}" required
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Ingrese nombres y apellidos completos">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rol *</label>
                        <select name="role_id" required 
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Selecciona --</option>
                            @foreach ($roles as $r) 
                                <option value="{{ $r->id }}">{{ $r->description }}</option> 
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Permisos y Módulos -->
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    Permisos del Sistema
                </h3>
                <p class="text-sm text-gray-500 mb-4">Seleccione los módulos a los que tendrá acceso este usuario</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach ($modulos as $grupo => $items)
                        <div class="border border-gray-200 rounded-xl p-4 bg-gray-50 hover:bg-gray-100 transition">
                            <h4 class="text-xs font-bold text-gray-700 uppercase mb-3 pb-2 border-b border-gray-300">
                                {{ $grupo }}
                            </h4>
                            <div class="space-y-2">
                                @foreach ($items as $mod)
                                    <label class="flex items-start gap-2 cursor-pointer group">
                                        <input type="checkbox" name="modulos[]" value="{{ $mod->mod_id }}" 
                                            class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="text-sm text-gray-600 group-hover:text-gray-900">
                                            {{ $mod->mod_nom }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="flex justify-end gap-3">
                <a href="{{ route('usuarios.index') }}" 
                    class="px-6 py-2.5 border border-gray-300 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    Cancelar
                </a>
                <button type="submit" 
                    class="px-6 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition shadow-sm">
                    Guardar Usuario
                </button>
            </div>
        </form>
    </div>
@endsection