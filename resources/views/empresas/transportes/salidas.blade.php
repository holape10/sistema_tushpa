@extends('layouts.app')
@section('title', 'Salidas')
@section('content')
    @php
        $categorias = \App\Support\Transportes::CATEGORIAS;
        $estados = ['PROGRAMADA' => 'bg-emerald-100 text-emerald-700', 'PARTIO' => 'bg-slate-200 text-slate-600', 'CANCELADA' => 'bg-rose-100 text-rose-700'];
        $catsUnidad = $unidades->mapWithKeys(fn ($u) => [$u->id => collect(\App\Support\Transportes::asientosDe(json_decode($u->plano, true) ?: []))->pluck('cat')->unique()->values()]);
        $preciosRuta = $rutas->mapWithKeys(fn ($r) => [$r->id => (float) $r->precio]);
    @endphp
    <div class="space-y-4" x-data="{ s: null, cats: @js($catsUnidad), preciosRuta: @js($preciosRuta), nombresCat: @js($categorias),
            nueva() { this.s = { id: '', ruta_id: '', unidad_id: '', salida: '', conductor: '', precio: '', precios: {}, observacion: '', repetir: 0 }; } }">
        @include('empresas.transportes.partials.nav')
        @include('empresas.partials.alert')

        <div class="flex flex-wrap items-center gap-2">
            <h1 class="flex-1 text-2xl font-black text-gray-800"><i class="fas fa-calendar-days text-indigo-600"></i> Salidas</h1>
            <form method="GET"><input type="date" name="desde" value="{{ $desde }}" onchange="this.form.submit()" class="h-10 rounded-xl border-gray-300 text-sm"></form>
            <button type="button" @click="nueva()" :disabled="{{ $rutas->isEmpty() || $unidades->isEmpty() ? 'true' : 'false' }}"
                    class="h-10 px-4 rounded-xl bg-indigo-600 text-white text-sm font-bold disabled:opacity-40"><i class="fas fa-plus"></i> Salida</button>
        </div>
        @if ($rutas->isEmpty() || $unidades->isEmpty())
            <p class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">Para programar salidas primero crea
                @if ($rutas->isEmpty())<a href="{{ route('transportes.rutas') }}" class="font-bold underline">una ruta</a>@endif
                @if ($rutas->isEmpty() && $unidades->isEmpty()) y @endif
                @if ($unidades->isEmpty())<a href="{{ route('transportes.unidades') }}" class="font-bold underline">una unidad con su plano</a>@endif.</p>
        @endif

        {{-- Formulario --}}
        <template x-if="s">
            <form method="POST" action="{{ route('transportes.salidas.guardar') }}" class="bg-white rounded-2xl shadow-sm p-4 grid sm:grid-cols-6 gap-3 items-end">
                @csrf
                <input type="hidden" name="id" :value="s.id">
                <label class="text-xs font-semibold text-gray-500 sm:col-span-2">Ruta
                    <select name="ruta_id" x-model="s.ruta_id" @change="if (!s.precio) s.precio = preciosRuta[s.ruta_id] || ''" required class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                        <option value="">Elige…</option>
                        @foreach ($rutas as $r)<option value="{{ $r->id }}">{{ $r->origen }} → {{ $r->destino }}</option>@endforeach
                    </select></label>
                <label class="text-xs font-semibold text-gray-500 sm:col-span-2">Unidad
                    <select name="unidad_id" x-model="s.unidad_id" required class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                        <option value="">Elige…</option>
                        @foreach ($unidades as $u)<option value="{{ $u->id }}">{{ $u->nombre }}{{ $u->placa ? ' · '.$u->placa : '' }} ({{ $u->asientos }})</option>@endforeach
                    </select></label>
                <label class="text-xs font-semibold text-gray-500 sm:col-span-2">Fecha y hora de salida
                    <input type="datetime-local" name="salida" x-model="s.salida" required class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                <label class="text-xs font-semibold text-gray-500 sm:col-span-2">Conductor / capitán
                    <input name="conductor" x-model="s.conductor" maxlength="120" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
                <label class="text-xs font-semibold text-gray-500">Precio S/
                    <input type="number" step="0.01" min="0" name="precio" x-model="s.precio" required class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                <template x-for="cat in (cats[s.unidad_id] || [])" :key="cat">
                    <label class="text-xs font-semibold text-gray-500"><span x-text="(nombresCat[cat] || cat) + ' S/'"></span>
                        <input type="number" step="0.01" min="0" :name="'precios[' + cat + ']'" x-model="s.precios[cat]" placeholder="igual" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                </template>
                <label class="text-xs font-semibold text-gray-500" x-show="!s.id">Repetir los días siguientes
                    <input type="number" min="0" max="30" name="repetir" x-model="s.repetir" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                <label class="text-xs font-semibold text-gray-500 sm:col-span-3">Observación
                    <input name="observacion" x-model="s.observacion" maxlength="200" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                <div class="sm:col-span-6 flex justify-end gap-2">
                    <button type="button" @click="s = null" class="h-10 px-4 rounded-xl bg-slate-100 text-sm font-bold">Cancelar</button>
                    <button class="h-10 px-5 rounded-xl bg-indigo-600 text-white text-sm font-bold">Guardar</button>
                </div>
            </form>
        </template>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr><th class="px-3 py-2 text-left">Salida</th><th class="px-3 py-2 text-left">Ruta</th><th class="px-3 py-2 text-left">Unidad</th>
                        <th class="px-3 py-2 text-right">Vendidos</th><th class="px-3 py-2 text-left">Estado</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($salidas as $x)
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap font-bold">{{ \Carbon\Carbon::parse($x->salida)->format('d/m/Y H:i') }}</td>
                            <td class="px-3 py-2">{{ $x->origen }} → {{ $x->destino }}</td>
                            <td class="px-3 py-2">{{ $x->unidad }}<span class="block text-xs text-gray-400">{{ $x->conductor }}</span></td>
                            <td class="px-3 py-2 text-right">{{ $x->vendidos }}/{{ $x->asientos }}</td>
                            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $estados[$x->estado] ?? '' }}">{{ $x->estado === 'PARTIO' ? 'PARTIÓ' : $x->estado }}</span></td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('transportes.manifiesto', $x->id) }}" target="_blank" class="text-xs font-bold text-slate-600 mr-2">Manifiesto</a>
                                @if ($x->estado === 'PROGRAMADA')
                                    <a href="{{ route('transportes.index', ['salida' => $x->id]) }}" class="text-xs font-bold text-indigo-600 mr-2">Vender</a>
                                    <button type="button" class="text-xs font-bold text-slate-600 mr-2"
                                            @click="s = @js(['id' => $x->id, 'ruta_id' => (string) $x->ruta_id, 'unidad_id' => (string) $x->unidad_id, 'salida' => \Carbon\Carbon::parse($x->salida)->format('Y-m-d\TH:i'),
                                                'conductor' => $x->conductor, 'precio' => (float) $x->precio, 'precios' => json_decode((string) $x->precios, true) ?: (object) [], 'observacion' => $x->observacion, 'repetir' => 0])">Editar</button>
                                @endif
                                @foreach (['PARTIO' => ['Partió', '¿Marcar que esta salida ya partió? Ya no venderá pasajes.'], 'CANCELADA' => ['Cancelar', '¿Cancelar esta salida?'],
                                    'PROGRAMADA' => ['Reabrir', '¿Volver a abrir la venta de esta salida?']] as $estado => [$texto, $pregunta])
                                    @if ($estado !== $x->estado)
                                        <form method="POST" action="{{ route('transportes.salidas.estado', $x->id) }}" class="inline"
                                              onsubmit="return confirm(@js($pregunta))">@csrf<input type="hidden" name="estado" value="{{ $estado }}">
                                            <button class="text-xs font-bold {{ $estado === 'CANCELADA' ? 'text-rose-600' : 'text-slate-600' }} mr-2">{{ $texto }}</button></form>
                                    @endif
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-10 text-center text-gray-400">No hay salidas desde esa fecha.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
