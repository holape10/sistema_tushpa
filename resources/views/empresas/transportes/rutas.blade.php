@extends('layouts.app')
@section('title', 'Rutas')
@section('content')
    <div class="space-y-4" x-data="{ r: null }">
        @include('empresas.transportes.partials.nav')
        @include('empresas.partials.alert')

        <div class="flex flex-wrap items-center gap-2">
            <h1 class="flex-1 text-2xl font-black text-gray-800"><i class="fas fa-route text-indigo-600"></i> Rutas</h1>
            <button type="button" @click="r = { id: '', origen: '', destino: '', paradas: '', duracion_min: '', precio: '', activo: true }"
                    class="h-10 px-4 rounded-xl bg-indigo-600 text-white text-sm font-bold"><i class="fas fa-plus"></i> Ruta</button>
        </div>

        <template x-if="r">
            <form method="POST" action="{{ route('transportes.rutas.guardar') }}" class="bg-white rounded-2xl shadow-sm p-4 grid sm:grid-cols-6 gap-3 items-end">
                @csrf
                <input type="hidden" name="id" :value="r.id">
                <label class="text-xs font-semibold text-gray-500 sm:col-span-2">Origen
                    <input name="origen" x-model="r.origen" required maxlength="80" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
                <label class="text-xs font-semibold text-gray-500 sm:col-span-2">Destino
                    <input name="destino" x-model="r.destino" required maxlength="80" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
                <label class="text-xs font-semibold text-gray-500">Precio S/
                    <input type="number" step="0.01" min="0" name="precio" x-model="r.precio" required class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                <label class="text-xs font-semibold text-gray-500">Duración (min)
                    <input type="number" min="1" name="duracion_min" x-model="r.duracion_min" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                <label class="text-xs font-semibold text-gray-500 sm:col-span-4">Paradas (opcional)
                    <input name="paradas" x-model="r.paradas" maxlength="255" placeholder="LA OROYA, JAUJA" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
                <label class="flex items-center gap-2 text-sm font-semibold text-gray-600 h-10"><input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" x-model="r.activo" class="rounded"> Activa</label>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="r = null" class="h-10 px-4 rounded-xl bg-slate-100 text-sm font-bold">Cancelar</button>
                    <button class="h-10 px-5 rounded-xl bg-indigo-600 text-white text-sm font-bold">Guardar</button>
                </div>
            </form>
        </template>

        <div class="bg-white rounded-2xl shadow-sm divide-y divide-gray-100">
            @forelse ($rutas as $x)
                <div class="px-4 py-3 flex flex-wrap items-center gap-3 {{ $x->activo ? '' : 'opacity-50' }}">
                    <div class="flex-1 min-w-[14rem]">
                        <p class="font-bold text-gray-800">{{ $x->origen }} → {{ $x->destino }}</p>
                        <p class="text-xs text-gray-500">S/ {{ number_format($x->precio, 2) }}{{ $x->duracion_min ? ' · '.intdiv($x->duracion_min, 60).' h '.($x->duracion_min % 60).' min' : '' }}{{ $x->paradas ? ' · paradas: '.$x->paradas : '' }}{{ $x->activo ? '' : ' · inactiva' }}</p>
                    </div>
                    <button type="button" class="text-sm font-bold text-indigo-600"
                            @click="r = @js(['id' => $x->id, 'origen' => $x->origen, 'destino' => $x->destino, 'paradas' => $x->paradas, 'duracion_min' => $x->duracion_min, 'precio' => (float) $x->precio, 'activo' => (bool) $x->activo])">Editar</button>
                </div>
            @empty
                <p class="p-8 text-center text-gray-400">Aún no tienes rutas: crea la primera con «Ruta».</p>
            @endforelse
        </div>
    </div>
@endsection
