@extends('layouts.app')
@section('title', 'Sucursales')
@section('content')
    @include('empresas.partials.alert')
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-800">Sucursales</h1>
            <p class="text-sm text-gray-500">Cada sucursal tiene sus propias series, correlativos, almacén, caja y usuarios. Cada usuario entra directo a la suya.</p>
        </div>
        <a href="{{ route('sucursales.create') }}" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">+ Nueva sucursal</a>
    </div>
    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Sucursal</th>
                    <th class="px-4 py-3 text-left">Dirección</th>
                    <th class="px-4 py-3 text-left">Factura</th>
                    <th class="px-4 py-3 text-left">Boleta</th>
                    <th class="px-4 py-3 text-left">Nota de venta</th>
                    <th class="px-4 py-3 text-left">Guía</th>
                    <th class="px-4 py-3 text-center">Usuarios</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($sucursales as $s)
                    <tr class="hover:bg-gray-50 {{ $s->id_empresa_negocio === $actual ? 'bg-indigo-50/50' : '' }}">
                        <td class="px-4 py-3 font-medium text-gray-700">{{ $s->nombre_comercial }}
                            @if ($s->id_empresa_negocio === $actual)<span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-600 text-white">ESTÁS AQUÍ</span>@endif
                            <span class="block text-xs text-gray-400">{{ $s->tipo_negocio }}</span></td>
                        <td class="px-4 py-3 text-gray-600">{{ $s->direccion }}<span class="block text-xs text-gray-400">{{ $s->distrito }}{{ $s->provincia ? ' - '.$s->provincia : '' }}</span></td>
                        {{-- Se muestra el próximo número que se emitirá --}}
                        <td class="px-4 py-3 whitespace-nowrap">{{ $s->FseEmpresa }}-{{ str_pad($s->FnuEmpresa + 1, 8, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $s->BseEmpresa }}-{{ str_pad($s->BnuEmpresa + 1, 8, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $s->SerNota }}-{{ str_pad($s->NumNota + 1, 8, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $s->SerGuia ?: 'T001' }}-{{ str_pad(($s->NumGuia ?? 0) + 1, 8, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-3 text-center">{{ $usuarios[$s->id_empresa_negocio] ?? 0 }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-bold {{ $s->estado == 'Activo' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $s->estado }}</span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($s->id_empresa_negocio !== $actual && $s->estado === 'Activo')
                                <form method="POST" action="{{ route('sucursales.cambiar') }}" class="inline"
                                      onsubmit="return confirm('¿Trabajar en {{ $s->nombre_comercial }}? Tus ventas, caja y reportes serán de esa sucursal hasta que vuelvas a cambiar.')">
                                    @csrf <input type="hidden" name="id" value="{{ $s->id_empresa_negocio }}">
                                    <button class="mr-3 text-emerald-700 font-semibold hover:underline">Trabajar aquí</button>
                                </form>
                            @endif
                            <a href="{{ route('sucursales.edit', $s->id_empresa_negocio) }}" class="text-indigo-600 hover:underline">Editar</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400 mt-2">Las columnas de comprobantes muestran el <strong>próximo número</strong> que se emitirá. Para que un trabajador sea de otra sucursal, en <strong>Usuarios</strong> elige su "Sucursal de trabajo".</p>
@endsection
