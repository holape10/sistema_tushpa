@extends('layouts.app')
@section('title', 'Stock de Productos')
@section('content')
    @include('empresas.partials.alert')

    <form class="flex flex-wrap items-end gap-2 mb-4">
        <label class="text-sm">Almacén
            <select name="almacen" class="block rounded-lg border-gray-300 text-sm">
                @foreach ($almacenes as $a)
                    <option value="{{ $a->id_almacen }}" @selected($a->id_almacen == $idAlmacen)>{{ $a->descripcion }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Buscar<input name="q" value="{{ $q }}" placeholder="Nombre del producto" class="block rounded-lg border-gray-300 text-sm"></label>
        <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Filtrar</button>
        <div class="ml-auto flex gap-2">
            <a href="{{ route('kardex.movimiento', ['tipo' => 'I']) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700">+ Ingreso</a>
            <a href="{{ route('kardex.movimiento', ['tipo' => 'E']) }}" class="px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-semibold hover:bg-red-700">− Salida</a>
        </div>
    </form>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Código</th><th class="px-4 py-3 text-left">Producto</th>
                    <th class="px-4 py-3 text-left">Tipo</th><th class="px-4 py-3 text-right">Stock mín.</th>
                    <th class="px-4 py-3 text-right">Stock</th><th class="px-4 py-3 text-right">Valorizado</th><th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($productos as $p)
                    @php $bajo = $p->stock <= $p->stock_min; @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-500">{{ $p->procod }}</td>
                        <td class="px-4 py-3 font-medium text-gray-700">{{ $p->pronom }}</td>
                        <td class="px-4 py-3">{{ $p->tipo_nombre }}</td>
                        <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format($p->stock_min, 2), '0'), '.') }}</td>
                        <td class="px-4 py-3 text-right font-bold {{ $p->stock < 0 ? 'text-red-600' : ($bajo ? 'text-amber-600' : 'text-gray-800') }}">
                            {{ rtrim(rtrim(number_format($p->stock, 2), '0'), '.') }} {{ $p->umecod }}
                            @if ($bajo)<span title="Stock bajo">⚠️</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right text-gray-500">{{ number_format($p->stock * $p->costo, 2) }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('kardex.index', ['producto' => $p->IdProducto, 'almacen' => $idAlmacen]) }}" class="text-indigo-600 hover:underline">Kardex</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin productos con stock.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $productos->links() }}</div>
@endsection
