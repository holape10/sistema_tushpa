@extends('layouts.app')
@section('title', 'Empresas')

@section('content')
    @include('empresas.partials.alert')

    <div class="flex flex-col sm:flex-row justify-between gap-3 mb-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Empresas Registradas</h2>
            <p class="text-gray-500 text-sm mt-1">Gestiona las empresas del sistema</p>
        </div>

        <a href="{{ route('empresa.config') }}"
           class="inline-flex justify-center items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition whitespace-nowrap">
            <i class="fas fa-plus"></i>
            Nueva Empresa
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">RUC</th>
                    <th class="px-4 py-3 text-left">Razón Social</th>
                    <th class="px-4 py-3 text-left">Dirección</th>
                    <th class="px-4 py-3 text-left">Estado</th>
                    <th class="px-4 py-3 text-left">Tipo Negocio</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($empresas as $empresa)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-700">{{ $empresa->IdEmpresa }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $empresa->NomEmpresa }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ Str::limit($empresa->DirEmpresa, 40) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs {{ $empresa->EstEmpresa == 'Activo' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $empresa->EstEmpresa }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if($empresa->id_tipo_sistema == 1)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-600">RESTAURANTE</span>
                            @elseif($empresa->id_tipo_sistema == 2)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-purple-50 text-purple-600">COMERCIO</span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <a href="{{ route('empresas.edit', $empresa->IdEmpresa) }}" 
                               class="text-indigo-600 hover:underline inline-flex items-center gap-1">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-400">
                            <i class="fas fa-folder-open text-3xl mb-2"></i>
                            <p>No hay empresas registradas</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($empresas->hasPages())
        <div class="mt-4">{{ $empresas->links() }}</div>
    @endif
@endsection