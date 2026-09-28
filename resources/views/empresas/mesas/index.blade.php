@extends('layouts.app')
@section('title', 'Mesas')
@section('content')
    @include('empresas.partials.alert')
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Gestión de Mesas</h2>
        <a href="{{ route('mesas.create') }}" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition">
            + Nueva Mesa
        </a>
    </div>

    <!-- Buscador -->
    <div class="mb-6">
        <div class="relative max-w-md">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <input 
                type="text" 
                id="searchInput"
                placeholder="Buscar por nombre de mesa o piso..." 
                class="block w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
            >
            <button 
                id="clearSearch"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 hidden"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <p class="text-xs text-gray-500 mt-2" id="resultsCount">Mostrando {{ $mesas->count() }} mesas</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Mesa</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Piso</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200" id="mesasTableBody">
                    @forelse ($mesas as $m)
                        <tr class="hover:bg-gray-50 transition mesa-row" 
                            data-mesa="{{ strtolower($m->mes_nom) }}" 
                            data-piso="{{ strtolower($m->piso->pis_nom ?? '') }}">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center font-bold text-white text-sm
                                        {{ $m->mes_est == 'Libre' ? 'bg-green-500' : 'bg-red-500' }}">
                                        {{ substr($m->mes_nom, 0, 2) }}
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $m->mes_nom }}</div>
                                        <div class="text-sm text-gray-500">ID: {{ $m->mes_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-gray-700">{{ $m->piso->pis_nom ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                    {{ $m->mes_est == 'Libre' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $m->mes_est }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end space-x-3">
                                    <a href="{{ route('mesas.edit', $m->mes_id) }}" 
                                       class="text-indigo-600 hover:text-indigo-900 text-sm font-medium hover:underline">
                                        Editar
                                    </a>
                                    <form action="{{ route('mesas.destroy', $m->mes_id) }}" method="POST" class="inline" onsubmit="return confirm('¿Estás seguro de eliminar esta mesa?')">
                                        @csrf 
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900 text-sm font-medium hover:underline">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="text-gray-400">
                                    <svg class="mx-auto h-12 w-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                    </svg>
                                    <p class="text-sm">Sin mesas registradas</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const clearButton = document.getElementById('clearSearch');
            const tableBody = document.getElementById('mesasTableBody');
            const rows = tableBody.querySelectorAll('.mesa-row');
            const resultsCount = document.getElementById('resultsCount');
            const totalRows = rows.length;

            // Función de búsqueda
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase().trim();
                
                // Mostrar/ocultar botón de limpiar
                if (searchTerm.length > 0) {
                    clearButton.classList.remove('hidden');
                } else {
                    clearButton.classList.add('hidden');
                }

                let visibleCount = 0;

                rows.forEach(row => {
                    const mesaName = row.getAttribute('data-mesa');
                    const pisoName = row.getAttribute('data-piso');
                    
                    if (mesaName.includes(searchTerm) || pisoName.includes(searchTerm)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Actualizar contador
                resultsCount.textContent = `Mostrando ${visibleCount} de ${totalRows} mesas`;
            });

            // Botón de limpiar búsqueda
            clearButton.addEventListener('click', function() {
                searchInput.value = '';
                clearButton.classList.add('hidden');
                
                rows.forEach(row => {
                    row.style.display = '';
                });
                
                resultsCount.textContent = `Mostrando ${totalRows} mesas`;
                searchInput.focus();
            });

            // Atajo de teclado: ESC para limpiar
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && searchInput.value.length > 0) {
                    searchInput.value = '';
                    clearButton.classList.add('hidden');
                    
                    rows.forEach(row => {
                        row.style.display = '';
                    });
                    
                    resultsCount.textContent = `Mostrando ${totalRows} mesas`;
                }
            });
        });
    </script>
@endsection