@extends('layouts.app')
@section('title', 'Inventario valorizado')
@section('content')
    @php
        $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
        $consulta = array_filter(['almacen' => $idAlmacen, 'fecha' => $fecha, 'metodo' => $metodo, 'categoria' => $categoria, 'q' => $q, 'con_stock' => $conStock ? 1 : 0], fn ($v) => $v !== null && $v !== '');
        $maxCategoria = max(1, $porCategoria->max() ?? 1);
    @endphp

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div>
            <h2 class="text-xl font-bold text-gray-800">Inventario valorizado</h2>
            <p class="text-sm text-gray-500">Cuánto tienes en físico y cuánto vale en dinero{{ $fecha < now()->toDateString() ? ' al '.\Carbon\Carbon::parse($fecha)->format('d/m/Y') : ' hoy' }}.</p>
        </div>
        <div class="ml-auto flex gap-2 print:hidden">
            <a href="{{ route('kardex.index') }}" class="px-4 py-2 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">Kardex</a>
            <button type="button" onclick="window.print()" class="px-4 py-2 rounded-xl bg-gray-800 text-white text-sm font-semibold hover:bg-gray-900">🖨 Imprimir</button>
            <a href="{{ route('kardex.inventario.excel', $consulta) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700">⬇ Excel</a>
        </div>
    </div>

    <form class="bg-white rounded-2xl shadow-sm p-4 mb-4 grid sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end print:hidden">
        <label class="text-sm">Almacén
            <select name="almacen" class="block w-full rounded-lg border-gray-300 text-sm">
                <option value="">Todos (sumados)</option>
                @foreach ($almacenes as $a)
                    <option value="{{ $a->id_almacen }}" @selected($a->id_almacen == $idAlmacen)>{{ $a->descripcion }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Al día
            <input type="date" name="fecha" value="{{ $fecha }}" max="{{ now()->toDateString() }}" class="block w-full rounded-lg border-gray-300 text-sm">
        </label>
        <label class="text-sm">Valorizar con
            <select name="metodo" class="block w-full rounded-lg border-gray-300 text-sm">
                <option value="promedio" @selected($metodo === 'promedio')>Costo promedio (kardex)</option>
                <option value="ultimo" @selected($metodo === 'ultimo')>Último costo de compra</option>
            </select>
        </label>
        <label class="text-sm">Categoría
            <select name="categoria" class="block w-full rounded-lg border-gray-300 text-sm">
                <option value="">Todas</option>
                @foreach ($categorias as $id => $nombre)
                    <option value="{{ $id }}" @selected($id == $categoria)>{{ $nombre }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Buscar
            <input name="q" value="{{ $q }}" placeholder="Nombre o código" class="block w-full rounded-lg border-gray-300 text-sm">
        </label>
        <div class="flex flex-col gap-2">
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="con_stock" value="1" @checked($conStock) class="rounded border-gray-300"> Solo con stock</label>
            <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Consultar</button>
        </div>
    </form>

    {{-- Resumen --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="rounded-2xl p-4 text-white bg-gradient-to-br from-indigo-600 to-violet-600 shadow-sm col-span-2 lg:col-span-1">
            <p class="text-xs uppercase text-indigo-100">Valor del inventario</p>
            <p class="text-3xl font-extrabold">S/ {{ number_format($resumen['valor'], 2) }}</p>
            <p class="text-xs text-indigo-100">{{ $metodo === 'ultimo' ? 'Último costo de compra' : 'Costo promedio ponderado' }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Productos con stock</p><p class="text-2xl font-bold text-gray-800">{{ $resumen['productos'] }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">En o bajo el mínimo</p><p class="text-2xl font-bold text-amber-600">{{ $resumen['bajo_minimo'] }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Stock negativo</p><p class="text-2xl font-bold {{ $resumen['negativos'] ? 'text-red-600' : 'text-gray-800' }}">{{ $resumen['negativos'] }}</p>
            @if ($resumen['negativos'])<p class="text-[11px] text-gray-400">Se vendió sin registrar el ingreso; no restan al valor</p>@endif</div>
    </div>

    @if ($porCategoria->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm p-4 mb-4">
            <p class="text-sm font-semibold text-gray-700 mb-3">¿Dónde está tu dinero? (por categoría)</p>
            <div class="space-y-2">
                @foreach ($porCategoria as $nombre => $valor)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-40 truncate text-gray-600">{{ $nombre }}</span>
                        <div class="flex-1 h-3 rounded-full bg-gray-100 overflow-hidden"><div class="h-full rounded-full bg-indigo-500" style="width: {{ max(2, round($valor / $maxCategoria * 100)) }}%"></div></div>
                        <span class="w-28 text-right font-semibold">S/ {{ number_format($valor, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-3 py-3 text-left">Código</th>
                    <th class="px-3 py-3 text-left">Producto</th>
                    <th class="px-3 py-3 text-left">Categoría</th>
                    @if ($idAlmacen)<th class="px-3 py-3 text-left">Almacén</th>@endif
                    <th class="px-3 py-3 text-right">Stock (físico)</th>
                    <th class="px-3 py-3 text-right">Costo unit.</th>
                    <th class="px-3 py-3 text-right">Valor S/</th>
                    <th class="px-3 py-3 print:hidden"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($filas as $f)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-gray-500">{{ $f['codigo'] }}</td>
                        <td class="px-3 py-2 font-medium">{{ $f['nombre'] }}
                            @if ($f['minimo'] > 0 && $f['cantidad'] <= $f['minimo'])<span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700">MÍNIMO {{ $num($f['minimo']) }}</span>@endif
                        </td>
                        <td class="px-3 py-2 text-gray-500">{{ $f['categoria'] }}</td>
                        @if ($idAlmacen)<td class="px-3 py-2 text-gray-500">{{ $f['almacen'] }}</td>@endif
                        <td class="px-3 py-2 text-right font-semibold {{ $f['cantidad'] < 0 ? 'text-red-600' : '' }}">{{ $f['equivalente'] ?? $num($f['cantidad']).' '.\App\Support\Unidades::nombre($f['unidad'] ?: 'NIU', (float) $f['cantidad']) }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($f['costo'], 2) }}</td>
                        <td class="px-3 py-2 text-right font-bold {{ $f['cantidad'] < 0 ? 'text-red-600' : '' }}">{{ number_format($f['valor'], 2) }}</td>
                        <td class="px-3 py-2 text-right print:hidden">
                            <a href="{{ route('kardex.index', ['producto' => $f['id'], 'almacen' => $idAlmacen ?: $almacenes->first()?->id_almacen, 'vista' => 'valorizado']) }}"
                               class="text-xs font-semibold text-indigo-600 hover:underline">Kardex</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No hay productos con esos filtros.</td></tr>
                @endforelse
            </tbody>
            @if ($filas->total())
                <tfoot class="bg-gray-50 font-bold">
                    <tr>
                        <td class="px-3 py-3" colspan="{{ $idAlmacen ? 6 : 5 }}">TOTAL ({{ $filas->total() }} productos)</td>
                        <td class="px-3 py-3 text-right text-indigo-700">S/ {{ number_format($resumen['valor'], 2) }}</td>
                        <td class="print:hidden"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    <div class="mt-4 print:hidden">{{ $filas->links() }}</div>

    <p class="mt-3 text-xs text-gray-500">
        <b>Costo promedio:</b> el valor sale del kardex (cada compra entra a su costo y cada venta sale al promedio). <b>Último costo:</b> stock × el costo de la última compra registrada.
        @if ($fecha < now()->toDateString()) Para una fecha pasada, el stock es el que dejaba el kardex ese día. @endif
    </p>
@endsection
