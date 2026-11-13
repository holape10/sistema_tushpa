@extends('layouts.app')
@section('title', 'Conductores y vehículos')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')
    @php
        $in = 'w-full h-10 rounded-lg border-gray-300 text-sm';
    @endphp

    <div class="max-w-6xl space-y-5" x-data="{ conductor: null, vehiculo: null }">
        <div class="flex flex-wrap items-center gap-3">
            <div class="mr-auto">
                <h1 class="text-2xl font-extrabold text-gray-800"><i class="fas fa-id-card text-indigo-600"></i> Conductores y vehículos</h1>
                <p class="text-sm text-gray-500">Los del transporte privado de tus guías. Se guardan solos al emitir una guía; aquí los ves, corriges o agregas.
                    En la guía nueva, al escribir el DNI o la placa se llenan solos.</p>
            </div>
            <a href="{{ route('guias.index') }}" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200">← Guías</a>
        </div>

        {{-- Conductores --}}
        <section class="bg-white rounded-2xl shadow-sm">
            <div class="flex items-center gap-2 px-5 py-4 border-b border-gray-100">
                <h2 class="font-bold text-gray-800 mr-auto"><i class="fas fa-user-tie text-amber-600"></i> Conductores ({{ $conductores->count() }})</h2>
                <button type="button" @click="conductor = { id: '', tdicod: '1', num: '', nombre: '', licencia: '' }" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-bold">+ Conductor</button>
            </div>
            <template x-if="conductor">
                <form method="POST" action="{{ route('guias.transporte.conductor') }}" class="grid sm:grid-cols-6 gap-3 px-5 py-4 bg-indigo-50/40 border-b border-gray-100 text-sm">
                    @csrf
                    <input type="hidden" name="id" :value="conductor.id">
                    <label>Doc.<select name="tdicod" x-model="conductor.tdicod" class="{{ $in }} mt-1"><option value="1">DNI</option><option value="4">Carné ext.</option><option value="7">Pasaporte</option></select></label>
                    <label>N° documento<input name="num" x-model="conductor.num" maxlength="15" required class="{{ $in }} mt-1"
                        @change="if (/^\d{8}$/.test(conductor.num) && !conductor.nombre) fetch(@js(url('guias/conductor')) + '/' + conductor.num, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(d => { if (d.nombres) { conductor.nombre = (d.nombres + ' ' + (d.apellidos || '')).trim(); conductor.licencia = d.licencia || conductor.licencia; } })"></label>
                    <label class="sm:col-span-2">Nombre completo<input name="nombre" x-model="conductor.nombre" maxlength="200" required class="{{ $in }} mt-1 uppercase"></label>
                    <label>Licencia<input name="licencia" x-model="conductor.licencia" maxlength="15" class="{{ $in }} mt-1 uppercase"></label>
                    <div class="flex items-end gap-2"><button class="h-10 px-4 rounded-lg bg-indigo-600 text-white font-bold">Guardar</button>
                        <button type="button" @click="conductor = null" class="h-10 px-3 rounded-lg bg-gray-100">Cancelar</button></div>
                </form>
            </template>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr>
                        <th class="px-4 py-2 text-left">Documento</th><th class="px-4 py-2 text-left">Nombre</th><th class="px-4 py-2 text-left">Licencia</th>
                        <th class="px-4 py-2 text-left">Vence</th><th class="px-4 py-2 text-right">Guías</th><th></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($conductores as $c)
                            @php $uso = $usoConductor[$c->num] ?? null; $vencida = $c->licencia_vence && $c->licencia_vence < now()->toDateString(); @endphp
                            <tr>
                                <td class="px-4 py-2 font-mono">{{ $c->num }}</td>
                                <td class="px-4 py-2 font-semibold text-gray-800">{{ trim($c->nombres.' '.$c->apellidos) }}</td>
                                <td class="px-4 py-2">{{ $c->licencia ?: '—' }} @if ($c->licencia_categoria)<span class="text-xs text-gray-400">{{ $c->licencia_categoria }}</span>@endif</td>
                                <td class="px-4 py-2 {{ $vencida ? 'text-rose-600 font-bold' : 'text-gray-500' }}">{{ $c->licencia_vence ? \Carbon\Carbon::parse($c->licencia_vence)->format('d/m/Y') : '—' }}{{ $vencida ? ' (vencida)' : '' }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ $uso->n ?? 0 }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <button type="button" @click="conductor = {{ \Illuminate\Support\Js::from(['id' => $c->id, 'tdicod' => $c->tdicod, 'num' => $c->num, 'nombre' => trim($c->nombres.' '.$c->apellidos), 'licencia' => (string) $c->licencia]) }}"
                                            class="px-2 py-1 rounded-lg bg-gray-100 text-gray-700 text-xs font-semibold">Editar</button>
                                    <form method="POST" action="{{ route('guias.transporte.eliminar', ['conductor', $c->id]) }}" class="inline" onsubmit="return confirm('¿Quitar este conductor de la lista?')">
                                        @csrf <button class="px-2 py-1 rounded-lg bg-rose-50 text-rose-600 text-xs" title="Quitar"><i class="fas fa-trash"></i></button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Aún no hay conductores: se agregan solos al emitir una guía con transporte privado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Vehículos --}}
        <section class="bg-white rounded-2xl shadow-sm">
            <div class="flex items-center gap-2 px-5 py-4 border-b border-gray-100">
                <h2 class="font-bold text-gray-800 mr-auto"><i class="fas fa-truck text-emerald-600"></i> Vehículos ({{ $vehiculos->count() }})</h2>
                <button type="button" @click="vehiculo = { id: '', placa: '', marca: '', modelo: '', color: '' }" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-bold">+ Vehículo</button>
            </div>
            <template x-if="vehiculo">
                <form method="POST" action="{{ route('guias.transporte.vehiculo') }}" class="grid sm:grid-cols-6 gap-3 px-5 py-4 bg-indigo-50/40 border-b border-gray-100 text-sm">
                    @csrf
                    <input type="hidden" name="id" :value="vehiculo.id">
                    <label>Placa<input name="placa" x-model="vehiculo.placa" maxlength="10" required class="{{ $in }} mt-1 uppercase"
                        @change="if (vehiculo.placa && !vehiculo.marca) fetch(@js(url('guias/placa')) + '/' + encodeURIComponent(vehiculo.placa), { headers: { Accept: 'application/json' } }).then(r => r.json()).then(d => Object.assign(vehiculo, { marca: d.marca || '', modelo: d.modelo || '', color: d.color || '' }))"></label>
                    <label>Marca<input name="marca" x-model="vehiculo.marca" maxlength="60" class="{{ $in }} mt-1 uppercase"></label>
                    <label>Modelo<input name="modelo" x-model="vehiculo.modelo" maxlength="60" class="{{ $in }} mt-1 uppercase"></label>
                    <label>Color<input name="color" x-model="vehiculo.color" maxlength="60" class="{{ $in }} mt-1 uppercase"></label>
                    <div class="flex items-end gap-2 sm:col-span-2"><button class="h-10 px-4 rounded-lg bg-indigo-600 text-white font-bold">Guardar</button>
                        <button type="button" @click="vehiculo = null" class="h-10 px-3 rounded-lg bg-gray-100">Cancelar</button></div>
                </form>
            </template>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr>
                        <th class="px-4 py-2 text-left">Placa</th><th class="px-4 py-2 text-left">Marca</th><th class="px-4 py-2 text-left">Modelo</th>
                        <th class="px-4 py-2 text-left">Color</th><th class="px-4 py-2 text-right">Guías</th><th></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($vehiculos as $v)
                            <tr>
                                <td class="px-4 py-2 font-mono font-bold">{{ $v->placa }}</td>
                                <td class="px-4 py-2">{{ $v->marca ?: '—' }}</td><td class="px-4 py-2">{{ $v->modelo ?: '—' }}</td><td class="px-4 py-2">{{ $v->color ?: '—' }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ $usoPlaca[$v->placa]->n ?? 0 }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <button type="button" @click="vehiculo = {{ \Illuminate\Support\Js::from(['id' => $v->id, 'placa' => $v->placa, 'marca' => (string) $v->marca, 'modelo' => (string) $v->modelo, 'color' => (string) $v->color]) }}"
                                            class="px-2 py-1 rounded-lg bg-gray-100 text-gray-700 text-xs font-semibold">Editar</button>
                                    <form method="POST" action="{{ route('guias.transporte.eliminar', ['vehiculo', $v->id]) }}" class="inline" onsubmit="return confirm('¿Quitar este vehículo de la lista?')">
                                        @csrf <button class="px-2 py-1 rounded-lg bg-rose-50 text-rose-600 text-xs" title="Quitar"><i class="fas fa-trash"></i></button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Aún no hay vehículos: se agregan solos al emitir una guía con transporte privado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
