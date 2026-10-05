@extends('layouts.app')
@section('title', 'Inventario INV-' . $inventario->inv_cab_id)
@section('content')
    @include('empresas.partials.alert')

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <a href="{{ route('inventarios.index') }}" class="px-4 py-2 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">← Inventarios</a>
        <a href="{{ route('inventarios.exportar', $inventario->inv_cab_id) }}" class="ml-auto px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700">⬇ Exportar Excel</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-4 mb-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
        <div><p class="text-xs text-gray-400 uppercase">Inventario</p><p class="font-bold text-gray-700">INV-{{ $inventario->inv_cab_id }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Almacén</p><p class="font-semibold text-gray-700">{{ $inventario->almacen }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Fecha</p><p class="font-semibold text-gray-700">{{ \Carbon\Carbon::parse($inventario->fecha)->format('d/m/Y') }}</p></div>
        <div><p class="text-xs text-gray-400 uppercase">Usuario</p><p class="font-semibold text-gray-700">{{ $inventario->apeusu }}</p></div>
        @if ($inventario->observaciones)
            <div class="col-span-full"><p class="text-xs text-gray-400 uppercase">Observación</p><p class="text-gray-700">{{ $inventario->observaciones }}</p></div>
        @endif
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Código</th>
                    <th class="px-4 py-3 text-left">Producto</th>
                    <th class="px-4 py-3 text-right">Stock sistema</th>
                    <th class="px-4 py-3 text-right">Stock físico</th>
                    <th class="px-4 py-3 text-right">Diferencia</th>
                    <th class="px-4 py-3 text-right">Costo unit.</th>
                    <th class="px-4 py-3 text-right">Valor ajuste</th>
                    <th class="px-4 py-3 text-left">Tipo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @php $total = 0; @endphp
                @foreach ($detalle as $d)
                    @php $valor = $d->diferencia * $d->costo; $total += $valor; @endphp
                    <tr>
                        <td class="px-4 py-2 text-gray-500">{{ $d->procod }}</td>
                        <td class="px-4 py-2 font-medium text-gray-700">{{ $d->pronom }} <span class="text-xs text-gray-400">{{ $d->umecod }}</span></td>
                        <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($d->stock_sistema, 3), '0'), '.') }}</td>
                        <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($d->stock_fisico, 3), '0'), '.') }}</td>
                        <td class="px-4 py-2 text-right font-semibold {{ $d->diferencia > 0 ? 'text-green-600' : ($d->diferencia < 0 ? 'text-red-600' : 'text-gray-400') }}">
                            {{ $d->diferencia > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($d->diferencia, 3), '0'), '.') }}
                        </td>
                        <td class="px-4 py-2 text-right">{{ number_format($d->costo, 2) }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($valor, 2) }}</td>
                        <td class="px-4 py-2 text-xs">
                            <span class="px-2 py-0.5 rounded-full {{ $d->cod_tip_ope === '16' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ $d->cod_tip_ope === '16' ? 'Saldo inicial' : 'Ajuste' }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 font-semibold">
                <tr><td colspan="6" class="px-4 py-3 text-right">Ajuste valorizado total</td><td class="px-4 py-3 text-right">S/ {{ number_format($total, 2) }}</td><td></td></tr>
            </tfoot>
        </table>
    </div>
@endsection
