@extends('layouts.app')
@section('title', 'Kardex de Productos')
@section('content')
    @include('empresas.partials.alert')

    <form class="bg-white rounded-2xl shadow-sm p-4 mb-4 grid sm:grid-cols-6 gap-3 items-end">
        <label class="sm:col-span-2 text-sm">Producto
            <input list="lista_productos" id="producto_txt" placeholder="Escribe para buscar..." autocomplete="off"
                   value="{{ $producto ? $producto->pronom : '' }}" class="block w-full rounded-lg border-gray-300 text-sm">
            <input type="hidden" name="producto" id="producto_id" value="{{ $producto?->IdProducto }}">
            <datalist id="lista_productos">
                @foreach ($productos as $p)
                    <option data-id="{{ $p->IdProducto }}" value="{{ $p->pronom }}">{{ $p->procod }}</option>
                @endforeach
            </datalist>
        </label>
        <label class="text-sm">Almacén
            <select name="almacen" class="block w-full rounded-lg border-gray-300 text-sm">
                @foreach ($almacenes as $a)
                    <option value="{{ $a->id_almacen }}" @selected($a->id_almacen == $idAlmacen)>{{ $a->descripcion }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Desde<input type="date" name="desde" value="{{ $desde }}" class="block w-full rounded-lg border-gray-300 text-sm"></label>
        <label class="text-sm">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block w-full rounded-lg border-gray-300 text-sm"></label>
        <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Consultar</button>
    </form>

    @if (!$producto)
        <div class="bg-white rounded-2xl shadow-sm p-8 text-center text-gray-400">
            Elige un producto para ver su kardex. Solo los <strong>productos</strong> e <strong>insumos</strong> manejan stock.
            <div class="mt-4 flex justify-center gap-2">
                <a href="{{ route('kardex.movimiento', ['tipo' => 'I']) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700">+ Ingreso de productos</a>
                <a href="{{ route('kardex.stock') }}" class="px-4 py-2 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">Ver stock</a>
            </div>
        </div>
    @else
        @php
            $entradas = $movimientos->where('mov_tip', 'I')->sum('cantidad');
            $salidas = $movimientos->where('mov_tip', 'E')->sum('cantidad');
        @endphp
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Saldo al {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }}</p><p class="text-xl font-bold">{{ rtrim(rtrim(number_format($saldoInicial, 2), '0'), '.') }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Entradas</p><p class="text-xl font-bold text-green-700">{{ rtrim(rtrim(number_format($entradas, 2), '0'), '.') }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Salidas</p><p class="text-xl font-bold text-red-600">{{ rtrim(rtrim(number_format($salidas, 2), '0'), '.') }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Stock actual</p><p class="text-xl font-bold {{ $stockActual < 0 ? 'text-red-600' : 'text-indigo-700' }}">{{ rtrim(rtrim(number_format($stockActual, 2), '0'), '.') }} {{ $producto->umecod }}</p></div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-3 py-3 text-left">Fecha</th>
                        <th class="px-3 py-3 text-left">Operación</th>
                        <th class="px-3 py-3 text-left">Documento</th>
                        <th class="px-3 py-3 text-left">Detalle</th>
                        <th class="px-3 py-3 text-right">Saldo ant.</th>
                        <th class="px-3 py-3 text-right">Entrada</th>
                        <th class="px-3 py-3 text-right">Salida</th>
                        <th class="px-3 py-3 text-right">Saldo</th>
                        <th class="px-3 py-3 text-right">Costo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($movimientos as $m)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($m->fecha_mov)->format('d/m/Y') }}<br><span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($m->fecha_hora)->format('H:i') }}</span></td>
                            <td class="px-3 py-2">{{ $m->cod_tip_ope }} - {{ $m->des_tip_ope }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($m->IdCpe_cabecera)
                                    <a href="{{ route('cobros.voucher', $m->IdCpe_cabecera) }}" target="_blank" class="text-indigo-600 hover:underline">{{ $m->serie }}-{{ str_pad($m->numero, 8, '0', STR_PAD_LEFT) }}</a>
                                @elseif ($m->mov_cab_id)
                                    MOV-{{ $m->mov_cab_id }}
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                {{ $m->cliente ?? $m->descripcion }}
                                @if ($m->combo_nom)<span class="text-xs text-gray-400">(combo: {{ $m->combo_nom }})</span>@endif
                                @if ($m->apeusu)<div class="text-xs text-gray-400">{{ $m->apeusu }}</div>@endif
                            </td>
                            <td class="px-3 py-2 text-right text-gray-500">{{ rtrim(rtrim(number_format($m->stock_inicial, 2), '0'), '.') }}</td>
                            <td class="px-3 py-2 text-right text-green-700 font-semibold">{{ $m->mov_tip == 'I' ? rtrim(rtrim(number_format($m->cantidad, 2), '0'), '.') : '' }}</td>
                            <td class="px-3 py-2 text-right text-red-600 font-semibold">{{ $m->mov_tip == 'E' ? rtrim(rtrim(number_format($m->cantidad, 2), '0'), '.') : '' }}</td>
                            <td class="px-3 py-2 text-right font-bold {{ $m->stock < 0 ? 'text-red-600' : '' }}">{{ rtrim(rtrim(number_format($m->stock, 2), '0'), '.') }}</td>
                            <td class="px-3 py-2 text-right text-gray-500">{{ number_format($m->costo, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-6 text-center text-gray-400">Sin movimientos en el rango.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <script>
        // El datalist muestra nombres; guardamos el IdProducto real en el campo oculto
        document.getElementById('producto_txt').addEventListener('input', function () {
            const op = [...document.querySelectorAll('#lista_productos option')].find(o => o.value === this.value);
            document.getElementById('producto_id').value = op ? op.dataset.id : '';
        });
    </script>
@endsection
