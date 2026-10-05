@extends('layouts.app')
@section('title', 'Sucursales')
@section('content')
    @include('empresas.partials.alert')
    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Sucursal</th>
                    <th class="px-4 py-3 text-left">Dirección</th>
                    <th class="px-4 py-3 text-left">Factura</th>
                    <th class="px-4 py-3 text-left">Boleta</th>
                    <th class="px-4 py-3 text-left">Nota de venta</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($sucursales as $s)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-700">{{ $s->nombre_comercial }}<span class="block text-xs text-gray-400">{{ $s->tipo_negocio }}</span></td>
                        <td class="px-4 py-3 text-gray-600">{{ $s->direccion }}</td>
                        {{-- Se muestra el próximo número que se emitirá --}}
                        <td class="px-4 py-3 whitespace-nowrap">{{ $s->FseEmpresa }}-{{ str_pad($s->FnuEmpresa + 1, 8, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $s->BseEmpresa }}-{{ str_pad($s->BnuEmpresa + 1, 8, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $s->SerNota }}-{{ str_pad($s->NumNota + 1, 8, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-bold {{ $s->estado == 'Activo' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $s->estado }}</span>
                        </td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('sucursales.edit', $s->id_empresa_negocio) }}" class="text-indigo-600 hover:underline">Editar</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400 mt-2">Las columnas de comprobantes muestran el <strong>próximo número</strong> que se emitirá.</p>
@endsection
