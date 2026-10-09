@extends('layouts.app')
@section('title', 'Motorizados')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')
    @php $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

    <div class="max-w-4xl space-y-5" x-data="{ m: { mot_id: '', nombre: '', telefono: '', placa: '', activo: true } }">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-800"><i class="fas fa-motorcycle text-indigo-600"></i> Motorizados</h1>
            <p class="text-sm text-gray-500">Se eligen al hacer la comanda de delivery. El reporte <a href="{{ route('reportes.ver', 'ventas-delivery') }}" class="text-indigo-600 font-semibold underline">Ventas Delivery</a> filtra por motorizado.</p>
        </div>

        <form method="POST" action="{{ route('motorizados.guardar') }}" class="bg-white rounded-2xl shadow-sm p-5 grid sm:grid-cols-[1fr_160px_130px_auto] gap-3 items-end text-sm">
            @csrf
            <input type="hidden" name="mot_id" :value="m.mot_id">
            <label>Nombre<input name="nombre" x-model="m.nombre" required minlength="2" maxlength="100" class="{{ $in }} mt-1 uppercase"></label>
            <label>Celular<input name="telefono" x-model="m.telefono" maxlength="20" class="{{ $in }} mt-1"></label>
            <label>Placa<input name="placa" x-model="m.placa" maxlength="10" class="{{ $in }} mt-1 uppercase"></label>
            <div class="flex gap-2 items-center">
                <label class="flex items-center gap-1 pb-2"><input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" x-model="m.activo" class="rounded"> Activo</label>
                <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-bold" x-text="m.mot_id ? 'Guardar' : 'Agregar'"></button>
                <button type="button" x-show="m.mot_id" @click="m = { mot_id: '', nombre: '', telefono: '', placa: '', activo: true }" class="px-3 py-2 rounded-lg bg-gray-100">Nuevo</button>
            </div>
        </form>

        <div class="bg-white rounded-2xl shadow-sm divide-y">
            @forelse ($motorizados as $mo)
                <div class="flex items-center gap-3 px-5 py-3 {{ $mo->activo ? '' : 'opacity-50' }}">
                    <span class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fas fa-motorcycle"></i></span>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-800">{{ $mo->nombre }}{{ $mo->activo ? '' : ' (inactivo)' }}</p>
                        <p class="text-xs text-gray-500">{{ $mo->telefono ?: 'Sin celular' }}{{ $mo->placa ? ' · Placa '.$mo->placa : '' }} · {{ $pedidosMes[$mo->mot_id] ?? 0 }} pedido(s) este mes</p>
                    </div>
                    <button type="button" @click="m = @js(['mot_id' => $mo->mot_id, 'nombre' => $mo->nombre, 'telefono' => $mo->telefono, 'placa' => $mo->placa, 'activo' => (bool) $mo->activo]); window.scrollTo({ top: 0, behavior: 'smooth' })"
                            class="text-sm text-indigo-600 font-semibold">Editar</button>
                    <form method="POST" action="{{ route('motorizados.eliminar', $mo->mot_id) }}" onsubmit="return confirm('¿Eliminar a {{ $mo->nombre }}?')">
                        @csrf <button class="text-sm text-rose-600 font-semibold">Eliminar</button>
                    </form>
                </div>
            @empty
                <p class="p-10 text-center text-gray-400">Aún no hay motorizados. Agrégalos arriba o desde la comanda de delivery con el botón "+".</p>
            @endforelse
        </div>
    </div>
@endsection
