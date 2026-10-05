@extends('layouts.app')
@section('title', 'Inventarios')
@section('content')
    @include('empresas.partials.alert')

    <div class="flex flex-col sm:flex-row justify-between gap-3 mb-4">
        <form method="GET" class="flex gap-3">
            <select name="almacen" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
                <option value="">Todos los almacenes</option>
                @foreach ($almacenes as $a)
                    <option value="{{ $a->id_almacen }}" @selected(request('almacen') == $a->id_almacen)>{{ $a->descripcion }}</option>
                @endforeach
            </select>
        </form>
        @if (auth()->user()->esAdmin())
            <a href="{{ route('inventarios.create') }}" class="inline-flex justify-center items-center px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">+ Nuevo inventario</a>
        @endif
    </div>

    <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-4 mb-4 text-sm text-indigo-800">
        Cuenta lo que hay físicamente en el almacén y el sistema deja el stock igual al conteo.
        Si el producto aún no tiene movimientos se registra como <strong>saldo inicial</strong>; si ya tenía, como <strong>ajuste por diferencia de inventario</strong>.
        Puedes contar en pantalla o descargar la plantilla Excel, llenarla y subirla.
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">N°</th>
                    <th class="px-4 py-3 text-left">Fecha</th>
                    <th class="px-4 py-3 text-left">Almacén</th>
                    <th class="px-4 py-3 text-left">Observación</th>
                    <th class="px-4 py-3 text-left">Origen</th>
                    <th class="px-4 py-3 text-right">Productos</th>
                    <th class="px-4 py-3 text-right">Ajuste valorizado</th>
                    <th class="px-4 py-3 text-left">Usuario</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($inventarios as $i)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">INV-{{ $i->inv_cab_id }}</td>
                        <td class="px-4 py-3">{{ \Carbon\Carbon::parse($i->fecha)->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $i->almacen }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $i->observaciones }}</td>
                        <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $i->origen === 'PRODUCTOS' ? 'Importación productos' : 'Conteo' }}</span></td>
                        <td class="px-4 py-3 text-right">{{ $i->items }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap {{ $i->valor_ajuste < 0 ? 'text-red-600' : 'text-green-700' }}">S/ {{ number_format($i->valor_ajuste, 2) }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $i->apeusu }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap"><a href="{{ route('inventarios.show', $i->inv_cab_id) }}" class="text-indigo-600 hover:underline">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-6 text-center text-gray-400">Aún no hay inventarios registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $inventarios->links() }}</div>
@endsection
