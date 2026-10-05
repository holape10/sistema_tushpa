@extends('layouts.app')
@section('title', 'Registro de Empleados')
@section('content')
    @include('empresas.partials.alert')

    <form class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex flex-wrap items-end gap-3">
        <label class="text-sm flex-1 min-w-60">Empleado
            <input name="q" value="{{ $q }}" placeholder="Nombre, usuario o DNI" class="block w-full rounded-lg border-gray-300 text-sm"></label>
        <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Buscar</button>
        <a href="{{ route('usuarios.create') }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700">+ Nuevo empleado</a>
    </form>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-3 py-3 text-left">Rol</th><th class="px-3 py-3 text-left">Usuario</th>
                    <th class="px-3 py-3 text-center">Cód. móvil</th><th class="px-3 py-3 text-left">Nombre completo</th>
                    <th class="px-3 py-3 text-left">Dirección</th><th class="px-3 py-3 text-left">Celular</th>
                    <th class="px-3 py-3 text-center">Asistencia</th><th class="px-3 py-3 text-center">Estado</th>
                    <th class="px-3 py-3 text-right">Opciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($usuarios as $u)
                    @php
                        $colorRol = match ($u->rol) { 'admin' => 'bg-red-600', 'caja' => 'bg-amber-500', 'mozo' => 'bg-sky-600', default => 'bg-gray-500' };
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-bold text-white {{ $colorRol }}">{{ $u->rol_nombre ?? 'Sin rol' }}</span></td>
                        <td class="px-3 py-2 text-gray-700">{{ $u->email }}</td>
                        <td class="px-3 py-2 text-center">@if ($u->codigo_movil)<span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-xs font-bold">{{ $u->codigo_movil }}</span>@endif</td>
                        <td class="px-3 py-2 font-semibold text-gray-800">{{ $u->apeusu }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $u->empleado->emp_dir ?? '' }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $u->empleado->emp_cel ?? '' }}</td>
                        <td class="px-3 py-2 text-center">
                            <span class="px-2 py-0.5 rounded text-xs font-bold text-white {{ ($u->empleado->asistencia ?? 0) ? 'bg-green-600' : 'bg-red-500' }}">{{ ($u->empleado->asistencia ?? 0) ? 'SÍ' : 'NO' }}</span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <span class="px-2 py-0.5 rounded text-xs font-bold text-white {{ $u->estusu ? 'bg-green-700' : 'bg-rose-500' }}">{{ $u->estusu ? 'ACTIVO' : 'INACTIVO' }}</span>
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap space-x-2">
                            <a href="{{ route('usuarios.edit', $u->IdUsuario) }}" class="text-indigo-600 hover:underline">Editar</a>
                            @if ((int) $u->IdUsuario !== (int) auth()->id())
                                <form action="{{ route('usuarios.destroy', $u->IdUsuario) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar a {{ addslashes($u->apeusu) }}?')">
                                    @csrf @method('DELETE')<button class="text-red-600 hover:underline">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">Sin empleados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $usuarios->links() }}</div>
@endsection
