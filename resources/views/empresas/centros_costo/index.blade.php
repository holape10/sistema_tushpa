@extends('layouts.app')
@section('title', 'Centros de costo')
@section('content')
@use('App\Support\CentrosCosto')
@php $c = $editar; @endphp
<div class="max-w-6xl mx-auto space-y-4">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-56">
            <h1 class="text-xl font-extrabold text-gray-800">🎯 Centros de costo</h1>
            <p class="text-sm text-gray-500">Dónde ganas y dónde gastas: cada local, área o proyecto. Los gastos se asignan a un centro y al centralizar van a su cuenta 9x (destino).</p>
        </div>
        <a href="{{ route('centros_costo.reporte') }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">📊 Resultado por centro</a>
        <form method="POST" action="{{ route('centros_costo.locales') }}">@csrf
            <button class="h-10 px-4 rounded-xl bg-white ring-1 ring-gray-300 text-sm font-semibold hover:bg-gray-50">Crear uno por cada local</button></form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr><th class="text-left px-4 py-2">Código</th><th class="text-left px-4 py-2">Centro</th><th class="text-left px-4 py-2">Tipo</th>
                    <th class="text-left px-4 py-2">Destino contable</th><th class="text-right px-4 py-2">Gastos</th><th class="px-4 py-2"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($centros as $x)
                    <tr class="{{ $x->activo ? '' : 'opacity-50' }}">
                        <td class="px-4 py-2 font-mono font-bold">{{ $x->codigo }}</td>
                        <td class="px-4 py-2 font-semibold">{{ $x->nombre }}{{ $x->activo ? '' : ' (inactivo)' }}</td>
                        <td class="px-4 py-2">{{ CentrosCosto::TIPOS[$x->tipo] ?? $x->tipo }}{{ $x->tipo === 'LOCAL' ? ' · '.($sucursales[$x->id_empresa_negocio] ?? '') : '' }}</td>
                        <td class="px-4 py-2">{{ $x->cuenta_destino }} · {{ CentrosCosto::DESTINOS[$x->cuenta_destino] ?? '' }}</td>
                        <td class="px-4 py-2 text-right">{{ $gastosPorCentro[$x->id] ?? 0 }}</td>
                        <td class="px-4 py-2 text-right"><a href="{{ route('centros_costo.index', ['editar' => $x->id]) }}" class="text-xs font-semibold text-indigo-700 hover:underline">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Aún no tienes centros de costo. Empieza con “Crear uno por cada local” o registra uno abajo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ $c ? route('centros_costo.update', $c->id) : route('centros_costo.store') }}" class="bg-white rounded-2xl shadow-sm p-4 space-y-3"
          x-data="{ tipo: @js(old('tipo', $c->tipo ?? 'AREA')) }">
        @csrf @if ($c) @method('PUT') @endif
        <p class="font-bold text-gray-700">{{ $c ? 'Editar '.$c->codigo : 'Nuevo centro de costo' }}</p>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <label class="text-xs font-semibold text-gray-600">Código *<input name="codigo" value="{{ old('codigo', $c->codigo ?? '') }}" required maxlength="10" placeholder="ADM" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm uppercase"></label>
            <label class="text-xs font-semibold text-gray-600 lg:col-span-2">Nombre *<input name="nombre" value="{{ old('nombre', $c->nombre ?? '') }}" required maxlength="100" placeholder="Administración" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-600">Tipo
                <select name="tipo" x-model="tipo" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm">
                    @foreach (CentrosCosto::TIPOS as $k => $n)<option value="{{ $k }}">{{ $n }}</option>@endforeach
                </select></label>
            <label class="text-xs font-semibold text-gray-600" x-show="tipo === 'LOCAL'" x-cloak>Local
                <select name="id_empresa_negocio" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm">
                    @foreach ($sucursales as $id => $nombre)<option value="{{ $id }}" @selected(old('id_empresa_negocio', $c->id_empresa_negocio ?? null) == $id)>{{ $nombre }}</option>@endforeach
                </select></label>
            <label class="text-xs font-semibold text-gray-600">Destino contable (clase 9)
                <select name="cuenta_destino" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm">
                    @foreach (CentrosCosto::DESTINOS as $k => $n)<option value="{{ $k }}" @selected(old('cuenta_destino', $c->cuenta_destino ?? '94') == $k)>{{ $k }} · {{ $n }}</option>@endforeach
                </select></label>
            @if ($c)
                <label class="flex items-center gap-2 text-sm text-gray-600 mt-6"><input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" @checked(old('activo', $c->activo)) class="rounded text-indigo-600"> Activo</label>
            @endif
        </div>
        <p class="text-xs text-gray-400">Local: sus ventas y su costo de ventas cuentan en su resultado, y los gastos que se registren en ese local van a él solos.</p>
        <div class="flex justify-end gap-2">
            @if ($c)<a href="{{ route('centros_costo.index') }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-gray-100 text-sm font-semibold">Cancelar</a>@endif
            <button class="h-10 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">Guardar</button>
        </div>
    </form>
</div>
@endsection
