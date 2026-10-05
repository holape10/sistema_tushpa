@extends('layouts.app')
@section('title', 'Motivos de Tardanza')
@section('content')
    @include('empresas.partials.alert')

    <div class="max-w-3xl" x-data="{ editar: null }">
        <form method="POST" action="{{ route('asistencia.motivos.guardar') }}" class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex flex-col sm:flex-row gap-2">
            @csrf
            <input name="descripcion" required maxlength="100" placeholder="Nuevo motivo (ej. CITA MÉDICA)" class="flex-1 rounded-lg border-gray-300 text-sm uppercase">
            <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">+ Agregar</button>
        </form>
        <p class="text-xs text-gray-500 mb-3">Estos motivos aparecen cuando el administrador autoriza una tardanza o un ingreso en día de descanso en el kiosko.</p>

        <div class="bg-white rounded-2xl shadow-sm divide-y divide-gray-100">
            @forelse ($motivos as $m)
                <div class="px-4 py-3 flex items-center gap-3">
                    <template x-if="editar !== {{ $m->id }}">
                        <div class="flex-1 flex items-center gap-3">
                            <span class="flex-1 font-semibold {{ $m->estado === 'Activo' ? 'text-gray-700' : 'text-gray-400 line-through' }}">{{ $m->descripcion }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $m->estado === 'Activo' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $m->estado }}</span>
                            <button type="button" @click="editar = {{ $m->id }}" class="text-indigo-600 text-sm hover:underline">Editar</button>
                            <form method="POST" action="{{ route('asistencia.motivos.eliminar', $m->id) }}" onsubmit="return confirm('¿Eliminar este motivo?')">
                                @csrf @method('DELETE')<button class="text-rose-600 text-sm hover:underline">Eliminar</button>
                            </form>
                        </div>
                    </template>
                    <template x-if="editar === {{ $m->id }}">
                        <form method="POST" action="{{ route('asistencia.motivos.guardar', $m->id) }}" class="flex-1 flex flex-col sm:flex-row gap-2">
                            @csrf
                            <input name="descripcion" value="{{ $m->descripcion }}" required maxlength="100" class="flex-1 rounded-lg border-gray-300 text-sm uppercase">
                            <select name="estado" class="rounded-lg border-gray-300 text-sm">
                                <option @selected($m->estado === 'Activo')>Activo</option><option @selected($m->estado === 'Inactivo')>Inactivo</option>
                            </select>
                            <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold">Guardar</button>
                            <button type="button" @click="editar = null" class="px-4 py-2 rounded-xl bg-gray-100 text-sm font-semibold">Cancelar</button>
                        </form>
                    </template>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-gray-400 text-sm">Aún no hay motivos.</p>
            @endforelse
        </div>
    </div>
@endsection
