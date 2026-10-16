@extends('layouts.app')
@section('title', 'Historias clínicas')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')
    @php $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-teal-500 focus:ring-teal-500'; @endphp

    <div x-data="pacientes()" class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-800"><i class="fas fa-notes-medical text-teal-600"></i> Historias clínicas</h1>
                <p class="text-sm text-gray-500">Pacientes y su historia. Las citas del día están en <a href="{{ route('clinica.agenda') }}" class="font-semibold text-teal-700 underline">Agenda</a>.</p>
            </div>
            <div class="flex gap-2">
                <button type="button" @click="nuevo()" class="px-4 py-2 rounded-xl bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700"><i class="fas fa-user-plus"></i> Nuevo paciente</button>
                <a href="{{ route('clinica.tratamientos.index') }}" class="px-4 py-2 rounded-xl bg-white border border-gray-300 text-gray-700 text-sm font-semibold"><i class="fas fa-file-invoice-dollar"></i> Registro de tratamientos</a>
                @if ($esAdmin)
                    <a href="{{ route('clinica.especialidades') }}" class="px-4 py-2 rounded-xl bg-white border border-gray-300 text-gray-700 text-sm font-semibold"><i class="fas fa-gear"></i> Especialidades</a>
                @endif
            </div>
        </div>

        @if ($esAdmin && $especialidades->isEmpty())
            <div class="rounded-xl bg-sky-50 border border-sky-200 px-4 py-3 text-sm text-sky-800">
                Para empezar: crea tus <a href="{{ route('clinica.especialidades') }}" class="font-bold underline">especialidades</a>
                (Medicina general, Odontología, Veterinaria…) con el servicio que se cobra. Luego en <b>Usuarios</b> crea a los doctores con el rol <b>Doctor</b>.
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap gap-3">
            <input type="search" x-model="texto" placeholder="Buscar por nombre, DNI, mascota o N° de historia" class="{{ $in }} flex-1 min-w-[220px]">
            {{-- Todos los pacientes de una especialidad (por sus atenciones o tratamientos) --}}
            <select x-model="espFiltro" class="rounded-lg border-gray-300 text-sm sm:w-64">
                <option value="">Todas las especialidades</option>
                @foreach ($especialidades as $e)<option value="{{ $e->esp_id }}">{{ $e->esp_des }}</option>@endforeach
            </select>
            <p x-show="espFiltro" x-cloak class="w-full text-sm text-teal-700 font-semibold" x-text="filtrados.length + ' paciente(s) de ' + nombreEsp(espFiltro)"></p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                    <th class="px-4 py-2 text-left">Historia</th><th class="px-4 py-2 text-left">Paciente</th><th class="px-4 py-2 text-left">DNI</th>
                    <th class="px-4 py-2 text-left">Edad</th><th class="px-4 py-2 text-left">Origen</th><th class="px-4 py-2 text-center">Atenciones</th><th class="px-4 py-2 text-left">Última</th><th></th></tr></thead>
                <tbody class="divide-y">
                    <template x-for="p in filtrados.slice(0, 300)" :key="p.his_cli_id">
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono font-bold text-gray-600" x-text="p.his_cli_cod"></td>
                            <td class="px-4 py-2"><span class="font-semibold" x-text="p.nombre"></span>
                                <span x-show="p.tipo === 'MASCOTA'" class="ml-1 text-xs text-amber-700" x-text="p.especie ? '🐾 ' + p.especie : '🐾'"></span>
                                <span class="block">
                                    <template x-for="id in p.esp" :key="id"><span class="inline-block mr-1 mt-0.5 px-1.5 py-0.5 rounded bg-teal-50 text-teal-700 text-[10px] font-bold" x-text="nombreEsp(id)"></span></template>
                                </span></td>
                            <td class="px-4 py-2" x-text="p.clinum"></td>
                            <td class="px-4 py-2" x-text="p.edad || ''"></td>
                            <td class="px-4 py-2 text-xs text-gray-500" x-text="p.origen || ''"></td>
                            <td class="px-4 py-2 text-center" x-text="p.atenciones || ''"></td>
                            <td class="px-4 py-2" x-text="p.ultima ? new Date(p.ultima + 'T00:00').toLocaleDateString('es-PE') : ''"></td>
                            <td class="px-4 py-2 text-right">
                                @if ($veClinico)
                                    <a :href="'{{ url('clinica/historia') }}/' + p.his_cli_id" class="px-3 py-1 rounded-lg bg-teal-600 text-white text-xs font-bold">Abrir historia</a>
                                @endif
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!filtrados.length"><td colspan="8" class="px-4 py-8 text-center text-gray-400">Sin pacientes.</td></tr>
                </tbody>
            </table>
        </div>

        {{-- Nuevo paciente --}}
        <div x-show="modal === 'nuevo'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-5 space-y-4" @click.outside="modal = null">
                <h3 class="font-bold text-gray-800">Nuevo paciente</h3>
                @include('empresas.clinica._paciente_form')
                <div class="flex justify-end gap-2">
                    <button type="button" @click="modal = null" class="px-4 py-2 rounded-xl bg-gray-100 text-sm font-semibold">Cancelar</button>
                    <button type="button" @click="guardar()" :disabled="ocupado" class="px-4 py-2 rounded-xl bg-teal-600 text-white text-sm font-bold disabled:opacity-40">Guardar</button>
                </div>
            </div>
        </div>


        <div x-show="aviso" x-cloak class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] px-5 py-3 rounded-xl shadow-lg text-white font-semibold" :class="ok ? 'bg-teal-600' : 'bg-rose-600'" x-text="aviso"></div>
    </div>

    <script>
        function pacientes() {
            const CSRF = '{{ csrf_token() }}';
            return {
                lista: @js($pacientes), texto: '', espFiltro: '', modal: null, ocupado: false, aviso: '', ok: true,
                especialidades: @js($especialidades->pluck('esp_des', 'esp_id')),
                nombreEsp(id) { return this.especialidades[id] || ''; },
                p: {},
                get filtrados() {
                    const t = this.texto.trim().toUpperCase(), esp = Number(this.espFiltro);
                    return this.lista.filter(p => (!esp || (p.esp || []).includes(esp))
                        && (!t || (p.nombre || '').toUpperCase().includes(t) || (p.clinum || '').includes(t) || (p.his_cli_cod || '').toUpperCase().includes(t)));
                },
                avisar(t, ok = true) { if (window.tushpaAviso) return window.tushpaAviso(t, ok); this.aviso = t; this.ok = ok; clearTimeout(this._t); this._t = setTimeout(() => this.aviso = '', 4000); },
                async post(url, data) {
                    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data) });
                    const j = await r.json().catch(() => ({}));
                    return r.status === 422 ? { ok: false, mensaje: Object.values(j.errors || {}).flat()[0] } : j;
                },
                nuevo() { this.p = { tipo: 'PERSONA', tdicod: '1', clinum: '', clinom: '', telefono: '', mascota: '', especie: '', raza: '', sexo: '', fecha_nac: '', origen: '', msg: '' }; this.modal = 'nuevo'; },
                async buscarDoc() {
                    const d = (this.p.clinum || '').trim();
                    if (!/^\d{8}$|^\d{11}$/.test(d)) return;
                    this.p.msg = 'Buscando…';
                    const r = await fetch('{{ url('cobros/cliente') }}/' + d, { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => ({}));
                    if (r.nom) { this.p.clinom = r.nom; this.p.tdicod = r.tdicod || '1'; this.p.telefono = r.tel || this.p.telefono; this.p.msg = '✔ Encontrado'; }
                    else this.p.msg = 'No se encontró: escribe el nombre.';
                },
                async guardar() {
                    this.ocupado = true;
                    const r = await this.post('{{ route('clinica.paciente') }}', this.p);
                    this.ocupado = false;
                    if (!r.ok) return this.avisar(r.mensaje || 'Revisa los datos.', false);
                    @if ($veClinico) location.href = '{{ url('clinica/historia') }}/' + r.his_cli_id; @else this.avisar(r.mensaje); setTimeout(() => location.reload(), 900); @endif
                },
            };
        }
    </script>
@endsection
