@extends('layouts.app')
@section('title', 'Mesas')
@section('content')
    @include('empresas.partials.alert')
    
    <div x-data="mesasLote()" @keydown.escape.window="abierto = false">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Gestión de Mesas</h2>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="abrir()" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition">
                ⚡ Crear varias mesas
            </button>
            <a href="{{ route('mesas.create') }}" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition">
                + Nueva Mesa
            </a>
        </div>
    </div>

    {{-- Crear varias mesas de una vez: "MESA 01" … "MESA 20" en el piso elegido --}}
    <div x-show="abierto" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="abierto = false">
        <form method="POST" action="{{ route('mesas.lote') }}" class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden" @submit="enviando = true">
            @csrf
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4 text-white">
                <h3 class="text-lg font-bold">⚡ Crear varias mesas</h3>
                <p class="text-sm text-emerald-50">Elige el piso y el rango de números. Se crean todas de una vez.</p>
            </div>

            @if ($pisos->isEmpty())
                <div class="p-6 text-sm text-gray-600">
                    Primero crea los pisos o zonas (ej. PISO 01, TERRAZA).
                    <a href="{{ route('pisos.index') }}" class="font-semibold text-indigo-600 hover:underline">Ir a Pisos →</a>
                </div>
            @else
                <div class="p-6 space-y-4">
                    <label class="block">
                        <span class="text-sm font-semibold text-gray-700">Piso / zona</span>
                        <select name="pis_id" x-model="piso" @change="sugerir()" required class="mt-1 w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                            @foreach ($pisos as $p)
                                <option value="{{ $p->pis_id }}">{{ $p->pis_nom }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="block col-span-3 sm:col-span-1">
                            <span class="text-sm font-semibold text-gray-700">Nombre</span>
                            <input name="prefijo" x-model="prefijo" @input="sugerir()" maxlength="30" required class="mt-1 w-full rounded-xl border-gray-300 uppercase focus:border-emerald-500 focus:ring-emerald-500">
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-gray-700">Desde el N°</span>
                            <input name="desde" type="number" min="0" max="9999" x-model.number="desde" required class="mt-1 w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-gray-700">Hasta el N°</span>
                            <input name="hasta" type="number" min="0" max="9999" x-model.number="hasta" required class="mt-1 w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                        </label>
                    </div>

                    {{-- Vista previa --}}
                    <div class="rounded-xl bg-gray-50 border border-gray-200 p-3">
                        <template x-if="error">
                            <p class="text-sm text-amber-700" x-text="error"></p>
                        </template>
                        <template x-if="!error">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Se crearán <b class="text-emerald-700" x-text="nuevas().length"></b> mesas en <b x-text="nombrePiso()"></b>
                                    <span x-show="repetidas().length" class="text-gray-500">(se saltan <b x-text="repetidas().length"></b> que ya existen en este piso)</span>
                                </p>
                                <div class="mt-2 flex flex-wrap gap-1.5 max-h-32 overflow-y-auto">
                                    <template x-for="n in lista()" :key="n">
                                        <span class="rounded-lg px-2 py-1 text-xs font-bold"
                                              :class="existe(n) ? 'bg-gray-200 text-gray-400 line-through' : (enOtroPiso(n) ? 'bg-amber-400 text-amber-950' : 'bg-emerald-500 text-white')" x-text="n"></span>
                                    </template>
                                </div>
                                {{-- Mismo número en otro piso: se permite (PISO 01 - MESA 03 y PISO 02 - MESA 03) si el usuario confirma --}}
                                <div x-show="enOtros().length" class="mt-3 rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900">
                                    <p>⚠ <b x-text="enOtros().length"></b> de estos nombres ya existen en otro piso (en amarillo).
                                        En la comanda y la precuenta saldrá el piso delante, por ejemplo <b x-text="nombrePiso() + ' / ' + enOtros()[0]"></b>.</p>
                                    <label class="mt-2 flex items-center gap-2 font-semibold">
                                        <input type="checkbox" name="repetir_en_otro_piso" value="1" x-model="confirmaRepetir" class="rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                                        Sí, crear igual con esos nombres
                                    </label>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-3">
                <button type="button" @click="abierto = false" class="px-4 py-2 rounded-xl border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-white">Cancelar</button>
                @if ($pisos->isNotEmpty())
                    <button type="submit" :disabled="enviando || !!error || !nuevas().length || (enOtros().length > 0 && !confirmaRepetir)"
                            class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 disabled:opacity-50">
                        <span x-text="enviando ? 'Creando…' : 'Crear ' + nuevas().length + ' mesas'"></span>
                    </button>
                @endif
            </div>
        </form>
    </div>
    </div>

    <script>
        function mesasLote() {
            const porPiso = @json((object) $nombres);
            const pisos = @json($pisos->pluck('pis_nom', 'pis_id'));
            return {
                abierto: {{ $errors->hasAny(['hasta', 'desde', 'prefijo', 'repetir_en_otro_piso']) ? 'true' : 'false' }},
                enviando: false,
                confirmaRepetir: false,
                piso: @json((string) old('pis_id', $pisos->first()->pis_id ?? '')),
                prefijo: @json(old('prefijo', 'MESA')),
                desde: {{ (int) old('desde', 1) }},
                hasta: {{ (int) old('hasta', 10) }},
                abrir() { this.abierto = true; this.enviando = false; this.sugerir(); },
                nombrePiso() { return pisos[this.piso] || ''; },
                delPiso() { return new Set(porPiso[this.piso] || []); },
                // Empieza después del número más alto de ESE piso (PISO 01 con MESA 20 → desde 21; PISO 02 vacío → desde 1)
                sugerir() {
                    const pre = this.prefijo.trim().toUpperCase();
                    let max = 0;
                    this.confirmaRepetir = false;
                    this.delPiso().forEach(n => {
                        const m = n.match(/^(.*?)\s*(\d+)$/);
                        if (m && m[1].trim() === pre) max = Math.max(max, Number(m[2]));
                    });
                    const cuantas = Math.max(1, (this.hasta || 0) - (this.desde || 0) + 1);
                    this.desde = max + 1;
                    this.hasta = max + Math.min(cuantas, 200);
                },
                get error() {
                    if (!this.prefijo.trim()) return 'Escribe el nombre (ej. MESA).';
                    if (this.desde === '' || this.hasta === '' || this.hasta < this.desde) return 'El número final debe ser mayor o igual al inicial.';
                    if (this.hasta - this.desde + 1 > 200) return 'Puedes crear hasta 200 mesas a la vez.';
                    return '';
                },
                nombre(n) { return this.prefijo.trim().toUpperCase() + ' ' + String(n).padStart(Math.max(2, String(this.hasta).length), '0'); },
                lista() {
                    if (this.error) return [];
                    const r = [];
                    for (let n = this.desde; n <= this.hasta; n++) r.push(this.nombre(n));
                    return r;
                },
                existe(n) { return this.delPiso().has(n); },
                enOtroPiso(n) { return Object.entries(porPiso).some(([id, nombres]) => String(id) !== String(this.piso) && nombres.includes(n)); },
                enOtros() { return this.nuevas().filter(n => this.enOtroPiso(n)); },
                nuevas() { return this.lista().filter(n => !this.existe(n)); },
                repetidas() { return this.lista().filter(n => this.existe(n)); },
            };
        }
    </script>

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