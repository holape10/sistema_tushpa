@extends('layouts.app')
@section('title', 'Kardex de Productos')
@section('content')
    @include('empresas.partials.alert')

    @php
        $num = fn ($n, $d = 2) => rtrim(rtrim(number_format((float) $n, $d), '0'), '.');
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $documento = fn ($m) => $m->IdCpe_cabecera
            ? '<a href="'.route('cobros.voucher', $m->IdCpe_cabecera).'" target="_blank" class="text-indigo-600 hover:underline">'.e($m->serie.'-'.str_pad($m->numero, 8, '0', STR_PAD_LEFT)).'</a>'
            : ($m->mov_cab_id ? 'MOV-'.$m->mov_cab_id : '');
    @endphp

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <h2 class="text-xl font-bold text-gray-800">Kardex de productos</h2>
        <a href="{{ route('kardex.inventario') }}" class="ml-auto px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">📦 Inventario valorizado</a>
    </div>

    <form class="bg-white rounded-2xl shadow-sm p-4 mb-4 grid sm:grid-cols-6 gap-3 items-end">
        <input type="hidden" name="vista" value="{{ $vista }}">
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

    {{-- Pestañas: siempre visibles. Sin producto = resumen de todos; con producto = su detalle --}}
    @php $filtrosBase = ['producto' => $producto?->IdProducto, 'almacen' => $idAlmacen, 'desde' => $desde, 'hasta' => $hasta]; @endphp
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <div class="inline-flex rounded-xl bg-white shadow-sm p-1">
            <a href="{{ route('kardex.index', array_filter($filtrosBase) + ['vista' => 'fisico']) }}"
               class="px-4 py-2 rounded-lg text-sm font-semibold {{ $vista === 'fisico' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">📋 Físico (cantidades)</a>
            <a href="{{ route('kardex.index', array_filter($filtrosBase) + ['vista' => 'valorizado']) }}"
               class="px-4 py-2 rounded-lg text-sm font-semibold {{ $vista === 'valorizado' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">💰 Valorizado (S/)</a>
        </div>
        @if ($producto)
            <a href="{{ route('kardex.index', ['almacen' => $idAlmacen, 'desde' => $desde, 'hasta' => $hasta, 'vista' => $vista]) }}"
               class="px-3 py-2 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">← Todos los productos</a>
        @endif
        <a href="{{ route('kardex.excel', array_filter($filtrosBase) + ['vista' => $vista]) }}" class="ml-auto px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700">⬇ Excel</a>
    </div>

    @if (!$producto)
        @if ($vista === 'valorizado')
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Saldo al {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }}</p><p class="text-xl font-bold">{{ $soles($totales['inicial']) }}</p></div>
                <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Entradas</p><p class="text-xl font-bold text-green-700">{{ $soles($totales['entradas']) }}</p></div>
                <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Salidas (costo)</p><p class="text-xl font-bold text-red-600">{{ $soles($totales['salidas']) }}</p></div>
                <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Saldo al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</p><p class="text-xl font-bold text-indigo-700">{{ $soles($totales['final']) }}</p></div>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-3 py-3 text-left">Producto</th>
                        <th class="px-3 py-3 text-right">Saldo inicial</th>
                        @if ($vista === 'valorizado')<th class="px-3 py-3 text-right">S/</th>@endif
                        <th class="px-3 py-3 text-right text-green-700">Entradas</th>
                        @if ($vista === 'valorizado')<th class="px-3 py-3 text-right text-green-700">S/</th>@endif
                        <th class="px-3 py-3 text-right text-red-600">Salidas</th>
                        @if ($vista === 'valorizado')<th class="px-3 py-3 text-right text-red-600">S/ (costo)</th>@endif
                        <th class="px-3 py-3 text-right text-indigo-700">Saldo final</th>
                        @if ($vista === 'valorizado')<th class="px-3 py-3 text-right">C. unit.</th><th class="px-3 py-3 text-right text-indigo-700">S/</th>@endif
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($resumen as $f)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2"><span class="font-medium">{{ $f['nombre'] }}</span> <span class="text-xs text-gray-400">{{ $f['codigo'] }} · {{ $f['unidad'] }}</span></td>
                            <td class="px-3 py-2 text-right">{{ $num($f['inicial']['cantidad']) }}</td>
                            @if ($vista === 'valorizado')<td class="px-3 py-2 text-right text-gray-500">{{ number_format($f['inicial']['valor'], 2) }}</td>@endif
                            <td class="px-3 py-2 text-right text-green-700 font-semibold">{{ $f['entradas']['cantidad'] ? $num($f['entradas']['cantidad']) : '' }}</td>
                            @if ($vista === 'valorizado')<td class="px-3 py-2 text-right text-green-700">{{ $f['entradas']['valor'] ? number_format($f['entradas']['valor'], 2) : '' }}</td>@endif
                            <td class="px-3 py-2 text-right text-red-600 font-semibold">{{ $f['salidas']['cantidad'] ? $num($f['salidas']['cantidad']) : '' }}</td>
                            @if ($vista === 'valorizado')<td class="px-3 py-2 text-right text-red-600">{{ $f['salidas']['valor'] ? number_format($f['salidas']['valor'], 2) : '' }}</td>@endif
                            <td class="px-3 py-2 text-right font-bold {{ $f['final']['cantidad'] < 0 ? 'text-red-600' : '' }}">{{ $num($f['final']['cantidad']) }}</td>
                            @if ($vista === 'valorizado')
                                <td class="px-3 py-2 text-right">{{ number_format($f['final']['costo'], 2) }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ number_format($f['final']['valor'], 2) }}</td>
                            @endif
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('kardex.index', ['producto' => $f['id'], 'almacen' => $idAlmacen, 'desde' => $desde, 'hasta' => $hasta, 'vista' => $vista]) }}"
                                   class="text-xs font-semibold text-indigo-600 hover:underline whitespace-nowrap">Ver detalle</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="px-4 py-8 text-center text-gray-400">
                            Sin movimientos de productos en este almacén y rango.
                            <div class="mt-3"><a href="{{ route('kardex.movimiento', ['tipo' => 'I']) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700">+ Ingreso de productos</a></div>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $resumen->links() }}</div>
        <p class="mt-2 text-xs text-gray-500">Toca <b>Ver detalle</b> para ver cada movimiento de un producto. Solo los productos e insumos manejan stock.</p>
    @else
        @php
            $entradas = $movimientos->where('mov_tip', 'I')->sum('cantidad');
            $salidas = $movimientos->where('mov_tip', 'E')->sum('cantidad');
            $filtros = ['producto' => $producto->IdProducto, 'almacen' => $idAlmacen, 'desde' => $desde, 'hasta' => $hasta];
        @endphp

        @if ($vista === 'valorizado')
            @php $k = $valorizado; @endphp
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Saldo al {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }}</p>
                    <p class="text-xl font-bold">{{ $soles($k['inicial']['valor']) }}</p><p class="text-xs text-gray-400">{{ $num($k['inicial']['cantidad']) }} {{ $producto->umecod }}</p></div>
                <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Entradas</p><p class="text-xl font-bold text-green-700">{{ $soles($k['entradas']) }}</p></div>
                <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Salidas (costo)</p><p class="text-xl font-bold text-red-600">{{ $soles($k['salidas']) }}</p></div>
                <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Saldo al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</p>
                    <p class="text-xl font-bold text-indigo-700">{{ $soles($k['final']['valor']) }}</p>
                    <p class="text-xs text-gray-400">{{ $num($k['final']['cantidad']) }} {{ $producto->umecod }} · C.U. {{ number_format($k['final']['costo'], 2) }}</p></div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left" rowspan="2">Fecha</th>
                            <th class="px-3 py-2 text-left" rowspan="2">Operación</th>
                            <th class="px-3 py-2 text-left" rowspan="2">Documento</th>
                            <th class="px-3 py-2 text-center bg-green-50 text-green-700" colspan="3">Entradas</th>
                            <th class="px-3 py-2 text-center bg-red-50 text-red-700" colspan="3">Salidas</th>
                            <th class="px-3 py-2 text-center bg-indigo-50 text-indigo-700" colspan="3">Saldo</th>
                        </tr>
                        <tr>
                            @foreach (['bg-green-50', 'bg-red-50', 'bg-indigo-50'] as $color)
                                <th class="px-2 py-2 text-right {{ $color }}">Cant.</th><th class="px-2 py-2 text-right {{ $color }}">C. unit.</th><th class="px-2 py-2 text-right {{ $color }}">Total</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr class="bg-gray-50/70 font-semibold">
                            <td class="px-3 py-2" colspan="9">Saldo inicial</td>
                            <td class="px-2 py-2 text-right">{{ $num($k['inicial']['cantidad']) }}</td>
                            <td class="px-2 py-2 text-right">{{ number_format($k['inicial']['costo'], 2) }}</td>
                            <td class="px-2 py-2 text-right">{{ number_format($k['inicial']['valor'], 2) }}</td>
                        </tr>
                        @forelse ($k['filas'] as $f)
                            @php $m = $f['mov']; $entra = $m->mov_tip === 'I'; @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($m->fecha_mov)->format('d/m/Y') }}</td>
                                <td class="px-3 py-2">{{ $m->cod_tip_ope }} - {{ $m->des_tip_ope }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{!! $documento($m) !!}</td>
                                <td class="px-2 py-2 text-right text-green-700 font-semibold">{{ $entra ? $num($f['cantidad']) : '' }}</td>
                                <td class="px-2 py-2 text-right text-green-700">{{ $entra ? number_format($f['costo'], 2) : '' }}</td>
                                <td class="px-2 py-2 text-right text-green-700">{{ $entra ? number_format($f['total'], 2) : '' }}</td>
                                <td class="px-2 py-2 text-right text-red-600 font-semibold">{{ $entra ? '' : $num($f['cantidad']) }}</td>
                                <td class="px-2 py-2 text-right text-red-600">{{ $entra ? '' : number_format($f['costo'], 2) }}</td>
                                <td class="px-2 py-2 text-right text-red-600">{{ $entra ? '' : number_format($f['total'], 2) }}</td>
                                <td class="px-2 py-2 text-right font-bold {{ $f['saldo']['cantidad'] < 0 ? 'text-red-600' : '' }}">{{ $num($f['saldo']['cantidad']) }}</td>
                                <td class="px-2 py-2 text-right">{{ number_format($f['saldo']['costo'], 2) }}</td>
                                <td class="px-2 py-2 text-right font-semibold">{{ number_format($f['saldo']['valor'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="px-4 py-6 text-center text-gray-400">Sin movimientos en el rango.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-gray-500">
                Valorizado con <b>costo promedio ponderado</b>: cada entrada suma a su costo de compra y cada salida sale al costo promedio del momento.
                Las devoluciones sin costo entran al promedio.
            </p>
        @else
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
    @endif

    <script>
        // El datalist muestra nombres; guardamos el IdProducto real en el campo oculto
        document.getElementById('producto_txt').addEventListener('input', function () {
            const op = [...document.querySelectorAll('#lista_productos option')].find(o => o.value === this.value);
            document.getElementById('producto_id').value = op ? op.dataset.id : '';
        });
    </script>
@endsection
