@extends('layouts.app')
@section('title', 'Mermas avícolas')
@section('content')
    @include('empresas.partials.alert')
    @php
        $tipos = \App\Support\Avicola::TIPOS_MERMA;
        $origenes = ['MANUAL' => 'Registrada aquí', 'VENTA' => 'En una venta', 'INGRESO' => 'Al llegar', 'BENEFICIO' => 'Beneficio', 'GRANJA' => 'Granja'];
        $activas = $mermas->where('estado', 'ACTIVA');
    @endphp

    <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
        @foreach ($tipos as $k => [$icono, $texto])
            @php($m = $activas->where('tipo', $k))
            <div class="rounded-2xl bg-white shadow-sm p-4">
                <p class="text-xs text-gray-500">{{ $icono }} {{ $texto }}</p>
                <p class="text-lg font-extrabold text-gray-800">{{ number_format($m->sum('aves')) }} aves · {{ number_format((float) $m->sum('kg'), 2) }} kg</p>
                <p class="text-xs text-gray-500">S/ {{ number_format((float) $m->sum('costo'), 2) }}</p>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('avicola.mermas.guardar') }}" class="bg-white rounded-2xl shadow-sm p-5 mb-4 grid sm:grid-cols-6 gap-3 items-end">
        @csrf
        <p class="sm:col-span-6 font-bold text-gray-800">Registrar merma <span class="text-xs font-normal text-gray-500">(sale del stock con su costo; la mortalidad de las ventas, los ingresos, el beneficio y la granja se anota sola)</span></p>
        <label class="text-xs font-semibold text-gray-500">Tipo<select name="tipo" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-sm">
            @foreach ($tipos as $k => [$icono, $texto])<option value="{{ $k }}">{{ $icono }} {{ $texto }}</option>@endforeach</select></label>
        <label class="sm:col-span-2 text-xs font-semibold text-gray-500">Producto<select name="IdProducto" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-sm">
            @foreach ($productos as $p)<option value="{{ $p->IdProducto }}">{{ $p->pronom }} (stock {{ number_format((float) $p->stock, 2) }})</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-gray-500">Aves<input type="number" min="0" name="aves" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-center"></label>
        <label class="text-xs font-semibold text-gray-500">Kilos<input type="number" step="0.001" min="0" name="kg" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-right font-bold"></label>
        <button class="h-10 rounded-xl bg-slate-700 hover:bg-slate-800 text-white text-sm font-bold">Registrar</button>
        <input name="observacion" maxlength="150" placeholder="Observación (opcional)" class="sm:col-span-6 h-9 rounded-xl border-gray-300 text-sm">
    </form>

    <form class="flex flex-wrap items-end gap-2 mb-3">
        <label class="text-xs text-gray-500">Desde<input type="date" name="desde" value="{{ $desde->toDateString() }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <label class="text-xs text-gray-500">Hasta<input type="date" name="hasta" value="{{ $hasta->toDateString() }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <button class="h-9 px-3 rounded-lg bg-gray-800 text-white text-sm font-semibold">Ver</button>
    </form>
    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr><th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">Tipo</th><th class="px-3 py-2 text-left">Dónde</th>
                <th class="px-3 py-2 text-left">Producto</th><th class="px-3 py-2 text-right">Aves</th><th class="px-3 py-2 text-right">Kg</th><th class="px-3 py-2 text-right">Costo</th><th class="px-3 py-2 text-left">Nota</th><th></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($mermas as $m)
                    <tr class="{{ $m->estado !== 'ACTIVA' ? 'opacity-40 line-through' : '' }}">
                        <td class="px-3 py-2 text-gray-500">{{ \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">{{ $tipos[$m->tipo][0] ?? '' }} {{ $tipos[$m->tipo][1] ?? $m->tipo }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $origenes[$m->origen] ?? $m->origen }}</td>
                        <td class="px-3 py-2">{{ $m->pronom ?? '—' }}</td><td class="px-3 py-2 text-right">{{ $m->aves ?: '' }}</td>
                        <td class="px-3 py-2 text-right">{{ (float) $m->kg > 0 ? number_format((float) $m->kg, 2) : '' }}</td><td class="px-3 py-2 text-right">{{ number_format((float) $m->costo, 2) }}</td>
                        <td class="px-3 py-2 text-xs text-gray-500">{{ $m->observacion }}</td>
                        <td class="px-3 py-2 text-right">
                            @if ($m->estado === 'ACTIVA' && $m->origen === 'MANUAL' && auth()->user()->esAdmin())
                                <form method="POST" action="{{ route('avicola.mermas.anular', $m->id) }}" onsubmit="return confirm('¿Anular esta merma? Lo que salió vuelve al stock.')">@csrf
                                    <button class="text-xs text-rose-600 hover:underline">Anular</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-3 py-8 text-center text-gray-400">Sin mermas en estas fechas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
