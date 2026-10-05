@extends('layouts.app')
@section('title', 'Lotes y Vencimientos')
@section('content')
    @include('empresas.partials.alert')

    @php
        $hoy = now()->startOfDay();
        $info = function ($l) use ($hoy, $dias) {
            if (!$l->vencimiento) {
                return ['restan' => null, 'texto' => 'Sin fecha', 'clase' => 'bg-slate-100 text-slate-600', 'barra' => 'bg-slate-300'];
            }
            $r = (int) $hoy->diffInDays($l->vencimiento, false);
            if ($r < 0) return ['restan' => $r, 'texto' => 'Vencido hace ' . abs($r) . ' d', 'clase' => 'bg-rose-100 text-rose-700', 'barra' => 'bg-rose-500'];
            if ($r === 0) return ['restan' => 0, 'texto' => 'Vence HOY', 'clase' => 'bg-rose-100 text-rose-700', 'barra' => 'bg-rose-500'];
            if ($r <= 30) return ['restan' => $r, 'texto' => "En {$r} días", 'clase' => 'bg-orange-100 text-orange-700', 'barra' => 'bg-orange-500'];
            if ($r <= $dias) return ['restan' => $r, 'texto' => "En {$r} días", 'clase' => 'bg-amber-100 text-amber-800', 'barra' => 'bg-amber-400'];
            return ['restan' => $r, 'texto' => "En {$r} días", 'clase' => 'bg-emerald-100 text-emerald-700', 'barra' => 'bg-emerald-500'];
        };
        $cant = fn($n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
        $filtros = ['alerta' => 'Por vencer y vencidos', 'vencidos' => 'Solo vencidos', 'proximos' => "Vencen en {$dias} días", 'vigentes' => 'Vigentes', 'todos' => 'Todos'];
    @endphp

    {{-- Tarjetas --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <a href="{{ route('lotes.index', ['estado' => 'vencidos']) }}" class="bg-white rounded-2xl shadow-sm p-4 border-l-4 border-rose-500 hover:shadow-md transition">
            <p class="text-xs text-gray-500 uppercase font-semibold">Vencidos</p>
            <p class="text-2xl font-extrabold text-rose-600">{{ $resumen['vencidos'] }}</p>
            <p class="text-xs text-gray-400">S/ {{ number_format($resumen['valor_vencidos'], 2) }} a costo</p>
        </a>
        <a href="{{ route('lotes.index', ['estado' => 'proximos']) }}" class="bg-white rounded-2xl shadow-sm p-4 border-l-4 border-orange-500 hover:shadow-md transition">
            <p class="text-xs text-gray-500 uppercase font-semibold">Vencen en 30 días</p>
            <p class="text-2xl font-extrabold text-orange-600">{{ $resumen['en30'] }}</p>
            <p class="text-xs text-gray-400">¡Véndelos primero!</p>
        </a>
        <a href="{{ route('lotes.index', ['estado' => 'proximos']) }}" class="bg-white rounded-2xl shadow-sm p-4 border-l-4 border-amber-400 hover:shadow-md transition">
            <p class="text-xs text-gray-500 uppercase font-semibold">Vencen en {{ $dias }} días</p>
            <p class="text-2xl font-extrabold text-amber-600">{{ $resumen['alerta'] }}</p>
            <p class="text-xs text-gray-400">S/ {{ number_format($resumen['valor_alerta'], 2) }} a costo</p>
        </a>
        <a href="{{ route('lotes.index', ['estado' => 'todos']) }}" class="bg-white rounded-2xl shadow-sm p-4 border-l-4 border-teal-500 hover:shadow-md transition">
            <p class="text-xs text-gray-500 uppercase font-semibold">Lotes con stock</p>
            <p class="text-2xl font-extrabold text-teal-700">{{ $resumen['total'] }}</p>
            <p class="text-xs text-gray-400">en todos los almacenes</p>
        </a>
    </div>

    {{-- Filtros --}}
    <form method="GET" class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex flex-col md:flex-row gap-3">
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar producto, código o lote..." class="flex-1 rounded-lg border-gray-300 text-sm">
        <select name="estado" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
            @foreach ($filtros as $k => $v)<option value="{{ $k }}" @selected($estado === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="almacen" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
            <option value="">Todos los almacenes</option>
            @foreach ($almacenes as $a)<option value="{{ $a->id_almacen }}" @selected(request('almacen') == $a->id_almacen)>{{ $a->descripcion }}</option>@endforeach
        </select>
        <div class="flex gap-2">
            <button class="flex-1 md:flex-none px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Buscar</button>
            <a href="{{ route('lotes.exportar', request()->query()) }}" class="flex-1 md:flex-none text-center px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700">⬇ Excel</a>
        </div>
    </form>

    {{-- Celular: tarjetas --}}
    <div class="md:hidden space-y-3">
        @forelse ($lotes as $l)
            @php $e = $info($l); @endphp
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <div class="flex justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-bold text-gray-800 leading-tight">{{ $l->pronom }}</p>
                        <p class="text-xs text-gray-400">{{ $l->procod }} · {{ $l->almacen }}</p>
                    </div>
                    <span class="shrink-0 h-fit px-2 py-1 rounded-full text-xs font-bold {{ $e['clase'] }}">{{ $e['texto'] }}</span>
                </div>
                <div class="grid grid-cols-3 gap-2 mt-3 text-sm">
                    <div><p class="text-[11px] text-gray-400 uppercase">Lote</p><p class="font-semibold">{{ $l->lote }}</p></div>
                    <div><p class="text-[11px] text-gray-400 uppercase">Vence</p><p class="font-semibold">{{ $l->vencimiento ? \Carbon\Carbon::parse($l->vencimiento)->format('d/m/Y') : '-' }}</p></div>
                    <div class="text-right"><p class="text-[11px] text-gray-400 uppercase">Stock</p><p class="font-bold">{{ $cant($l->stock) }} <span class="text-xs font-normal text-gray-400">{{ $l->umecod }}</span></p></div>
                </div>
            </div>
        @empty
            <p class="bg-white rounded-2xl shadow-sm p-6 text-center text-gray-400 text-sm">✔ No hay lotes con este filtro.</p>
        @endforelse
    </div>

    {{-- Escritorio: tabla --}}
    <div class="hidden md:block bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Producto</th>
                    <th class="px-4 py-3 text-left">Lote</th>
                    <th class="px-4 py-3 text-left">Almacén</th>
                    <th class="px-4 py-3 text-left">Vencimiento</th>
                    <th class="px-4 py-3 text-left">Estado</th>
                    <th class="px-4 py-3 text-right">Stock</th>
                    <th class="px-4 py-3 text-right">Valor (costo)</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($lotes as $l)
                    @php $e = $info($l); @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><span class="font-semibold text-gray-700">{{ $l->pronom }}</span><span class="block text-xs text-gray-400">{{ $l->procod }}</span></td>
                        <td class="px-4 py-3 font-mono text-gray-700">{{ $l->lote }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $l->almacen }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $l->vencimiento ? \Carbon\Carbon::parse($l->vencimiento)->format('d/m/Y') : '-' }}</td>
                        <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-bold whitespace-nowrap {{ $e['clase'] }}">{{ $e['texto'] }}</span></td>
                        <td class="px-4 py-3 text-right font-semibold whitespace-nowrap">{{ $cant($l->stock) }} <span class="text-xs font-normal text-gray-400">{{ $l->umecod }}</span></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">S/ {{ number_format($l->stock * $l->costo, 2) }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('kardex.index', ['producto' => $l->IdProducto, 'almacen' => $l->id_almacen]) }}" class="text-indigo-600 hover:underline text-xs">Kardex</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">✔ No hay lotes con este filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $lotes->links() }}</div>

    {{-- Configuración --}}
    @if (auth()->user()->esAdmin())
        <div class="grid md:grid-cols-2 gap-4 mt-6">
            <form method="POST" action="{{ route('lotes.configuracion') }}" class="bg-white rounded-2xl shadow-sm p-4">
                @csrf
                <p class="font-semibold text-gray-700 text-sm mb-2">¿Con cuántos días de anticipación avisar?</p>
                <div class="flex gap-2">
                    <input type="number" name="dias" value="{{ $dias }}" min="1" max="730" class="w-28 rounded-lg border-gray-300 text-sm">
                    <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Guardar</button>
                </div>
                <p class="text-xs text-gray-400 mt-2">Se usa en el dashboard, en esta pantalla y en el PV Farmacia.</p>
            </form>
            @if ($sinControl)
                <form method="POST" action="{{ route('lotes.configuracion') }}" class="bg-teal-50 border border-teal-200 rounded-2xl p-4"
                      onsubmit="return confirm('Todos tus productos e insumos pedirán lote y vencimiento al comprar. ¿Continuar?')">
                    @csrf
                    <input type="hidden" name="accion" value="todos">
                    <p class="font-semibold text-teal-800 text-sm mb-1">{{ $sinControl }} productos aún no controlan lote</p>
                    <p class="text-xs text-teal-700 mb-3">Si tu negocio es una farmacia, actívalo en todos de una vez (también se puede por producto, al editarlo).</p>
                    <button class="px-4 py-2 rounded-xl bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">Activar en todos los productos</button>
                </form>
            @endif
        </div>
    @endif
@endsection
