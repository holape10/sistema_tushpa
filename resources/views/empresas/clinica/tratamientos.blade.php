@extends('layouts.app')
@section('title', 'Registro de Tratamientos')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $s = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $enCurso = $tratamientos->where('estado', '!=', 'CANCELADO');
    @endphp

    <div x-data="registroGeneral()" class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('clinica.index') }}" class="text-sm text-gray-500">← Historias clínicas</a>
                <h1 class="text-2xl font-extrabold text-gray-800 mt-1">💳 Registro de Tratamientos</h1>
                <p class="text-sm text-gray-500">Cada tratamiento con su costo, las sesiones, los adelantos, el saldo y la firma del paciente.</p>
            </div>
            <div class="relative w-full sm:w-80" @click.outside="sugerencias = []">
                <input type="search" x-model="buscaPaciente" @input.debounce.300ms="buscar()" placeholder="+ Nuevo tratamiento: busca al paciente…"
                       class="w-full rounded-xl border-teal-500 text-sm focus:ring-teal-500">
                <ul x-show="sugerencias.length" x-cloak class="absolute z-30 left-0 right-0 mt-1 bg-white rounded-xl shadow-xl ring-1 ring-black/5 max-h-64 overflow-y-auto">
                    <template x-for="p in sugerencias" :key="p.id">
                        <li @click="abrir(p.id, p.nombre, true)" class="px-3 py-2 cursor-pointer hover:bg-teal-50 border-b last:border-0">
                            <p class="text-sm font-semibold" x-text="p.nombre"></p><p class="text-xs text-gray-400" x-text="p.codigo + ' · DNI ' + p.doc"></p>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ([['Tratamientos en curso', $enCurso->count()], ['Saldo por cobrar', $s($enCurso->sum('saldo'))],
                       ['Cancelados', $tratamientos->where('estado', 'CANCELADO')->count()], ['Total pagado', $s($tratamientos->sum('pagado'))]] as [$titulo, $valor])
                <div class="bg-white rounded-2xl shadow-sm px-4 py-3"><p class="text-xs text-gray-500">{{ $titulo }}</p><p class="text-2xl font-black text-gray-800">{{ $valor }}</p></div>
            @endforeach
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap gap-3">
            <input type="search" x-model="texto" placeholder="Buscar paciente, DNI, historia o tratamiento" class="flex-1 min-w-[220px] rounded-lg border-gray-300 text-sm">
            <select x-model="esp" class="rounded-lg border-gray-300 text-sm">
                <option value="">Todas las especialidades</option>
                @foreach ($especialidades as $e)<option value="{{ $e->esp_id }}">{{ $e->esp_des }}</option>@endforeach
            </select>
            <select x-model="estado" class="rounded-lg border-gray-300 text-sm">
                <option value="">En curso y cancelados</option><option value="EN CURSO">Solo en curso (con saldo)</option><option value="CANCELADO">Solo cancelados</option>
            </select>
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr><th class="px-4 py-2 text-left">Paciente</th><th class="px-4 py-2 text-left">Tratamiento</th><th class="px-4 py-2 text-right">Costo</th>
                        <th class="px-4 py-2 text-right">Pagado</th><th class="px-4 py-2 text-right">Saldo</th><th class="px-4 py-2 text-left">Última sesión</th><th></th></tr>
                </thead>
                <tbody class="divide-y">
                    <template x-for="t in filtrados" :key="t.tra_id">
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2"><span class="font-semibold" x-text="t.paciente"></span>
                                <span class="block text-xs text-gray-400" x-text="t.his_cli_cod + ' · DNI ' + t.clinum"></span></td>
                            <td class="px-4 py-2"><span x-text="t.descripcion"></span><span class="block text-xs text-teal-700 font-semibold" x-text="t.esp_des || ''"></span></td>
                            <td class="px-4 py-2 text-right" x-text="soles(t.costo)"></td>
                            <td class="px-4 py-2 text-right text-emerald-700" x-text="soles(t.pagado)"></td>
                            <td class="px-4 py-2 text-right">
                                <span x-show="t.estado !== 'CANCELADO'" class="font-bold text-rose-600" x-text="soles(t.saldo)"></span>
                                <span x-show="t.estado === 'CANCELADO'" class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-extrabold">CANCELADO</span>
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-500" x-text="t.ultima ? new Date(String(t.ultima).replace(' ', 'T')).toLocaleDateString('es-PE') + ' · ' + t.sesiones + ' sesión(es)' : 'sin sesiones'"></td>
                            <td class="px-4 py-2 text-right"><button type="button" @click="abrir(t.his_cli_id, t.paciente)" class="px-3 py-1 rounded-lg bg-teal-600 text-white text-xs font-bold whitespace-nowrap">Ver / registrar</button></td>
                        </tr>
                    </template>
                    <tr x-show="!filtrados.length"><td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin tratamientos con esos filtros.</td></tr>
                </tbody>
            </table>
        </div>

        {{-- Registro de tratamientos del paciente elegido --}}
        <div x-show="paciente" x-cloak class="fixed inset-0 z-40 bg-black/40 flex items-start justify-center p-3 sm:p-6 overflow-y-auto">
            <div class="bg-gray-100 rounded-2xl shadow-xl w-full max-w-5xl p-4 sm:p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-extrabold text-gray-800" x-text="paciente?.nombre"></h2>
                    <button type="button" @click="cerrar()" class="text-3xl leading-none text-gray-400">&times;</button>
                </div>
                <div x-data="registroTratamientos()" @abrir-paciente.window="cargar($event.detail.id).then(() => $event.detail.nuevo && nuevoTratamiento())">
                    @include('empresas.clinica._tratamientos', ['especialidadesTrat' => $especialidades])
                </div>
            </div>
        </div>
    </div>

    <script>
        function registroGeneral() {
            return {
                todos: @js($tratamientos->values()), texto: '', esp: '', estado: '', paciente: null, buscaPaciente: '', sugerencias: [],
                soles: n => 'S/ ' + (Number(n) || 0).toFixed(2),
                get filtrados() {
                    const t = this.texto.trim().toUpperCase();
                    return this.todos.filter(x => (!this.esp || String(x.esp_id) === this.esp) && (!this.estado || x.estado === this.estado)
                        && (!t || [x.paciente, x.clinum, x.his_cli_cod, x.descripcion].some(v => String(v || '').toUpperCase().includes(t))));
                },
                async buscar() {
                    const q = this.buscaPaciente.trim();
                    if (q.length < 2) { this.sugerencias = []; return; }
                    this.sugerencias = await fetch('{{ route('clinica.buscar') }}?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => []);
                },
                abrir(id, nombre, nuevo = false) {
                    this.sugerencias = []; this.buscaPaciente = '';
                    this.paciente = { id, nombre };
                    this.$nextTick(() => window.dispatchEvent(new CustomEvent('abrir-paciente', { detail: { id, nuevo } })));
                },
                // Al cerrar se actualizan los saldos de la lista
                cerrar() { this.paciente = null; location.reload(); },
            };
        }
    </script>
@endsection
