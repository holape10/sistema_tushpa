@extends('layouts.app')
@section('title', 'Agenda de citas')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')
    @php $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-teal-500 focus:ring-teal-500'; @endphp

    <div x-data="agenda()" x-init="cargar(); setInterval(() => cargar(), 30000)" class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-800"><i class="fas fa-calendar-days text-teal-600"></i> Agenda de citas</h1>
                <p class="text-sm text-gray-500" x-text="fechaLarga(fecha)"></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="mover(-1)" class="w-9 h-9 rounded-lg bg-white border"><i class="fas fa-chevron-left"></i></button>
                <input type="date" x-model="fecha" @change="cargar()" class="rounded-lg border-gray-300 text-sm">
                <button type="button" @click="mover(1)" class="w-9 h-9 rounded-lg bg-white border"><i class="fas fa-chevron-right"></i></button>
                <button type="button" @click="fecha = hoy; cargar()" class="px-3 h-9 rounded-lg bg-white border text-sm font-semibold">Hoy</button>
                <select x-model="filtroDoctor" class="rounded-lg border-gray-300 text-sm">
                    <option value="">Todos los doctores</option>
                    @foreach ($doctores as $d)<option value="{{ $d->IdUsuario }}">{{ $d->nombre }}</option>@endforeach
                </select>
                <button type="button" @click="nuevaCita()" class="px-4 h-9 rounded-xl bg-teal-600 text-white text-sm font-bold"><i class="fas fa-plus"></i> Nueva cita</button>
                <a href="{{ route('clinica.index') }}" class="px-3 h-9 leading-9 rounded-xl bg-white border text-sm font-semibold">Pacientes</a>
            </div>
        </div>

        @if ($doctores->isEmpty())
            <div class="rounded-xl bg-sky-50 border border-sky-200 px-4 py-3 text-sm text-sky-800">
                No hay doctores todavía: en <b>Usuarios</b> crea a cada doctor con el rol <b>Doctor</b>.
            </div>
        @endif

        <div class="grid lg:grid-cols-3 gap-5">
            {{-- Citas del día --}}
            <div class="lg:col-span-2 space-y-2">
                <template x-for="c in visibles" :key="c.cit_id">
                    <div class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-center gap-3 border-l-4" :class="borde(c.estado)">
                        <div class="w-16 text-center">
                            <p class="text-lg font-extrabold text-gray-800" x-text="c.hora.slice(0, 5)"></p>
                            <p class="text-[11px] text-gray-400" x-text="c.duracion + ' min'"></p>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-800 truncate" x-text="c.paciente"></p>
                            <p class="text-xs text-gray-500" x-text="[c.his_cli_cod, c.esp_des, c.doctor_nom, c.motivo, c.telefono].filter(Boolean).join(' · ')"></p>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold" :class="color(c.estado)" x-text="nombreEstado(c.estado)"></span>
                        <div class="flex flex-wrap gap-1 w-full sm:w-auto">
                            <template x-if="!['ATENDIDA', 'CANCELADA', 'NO_ASISTIO'].includes(c.estado)">
                                <div class="flex flex-wrap gap-1">
                                    <button type="button" x-show="c.estado === 'PROGRAMADA'" @click="estado(c, 'CONFIRMADA')" class="px-2 py-1 rounded-lg bg-sky-100 text-sky-800 text-xs font-bold">Confirmó</button>
                                    <button type="button" x-show="c.estado !== 'EN_ESPERA'" @click="estado(c, 'EN_ESPERA')" class="px-2 py-1 rounded-lg bg-amber-100 text-amber-800 text-xs font-bold">Llegó</button>
                                    @if ($veClinico)
                                        <a :href="'{{ url('clinica/citas') }}/' + c.cit_id + '/atender'" class="px-3 py-1 rounded-lg bg-teal-600 text-white text-xs font-bold"><i class="fas fa-stethoscope"></i> Atender</a>
                                    @endif
                                    <button type="button" @click="editar(c)" class="px-2 py-1 rounded-lg bg-gray-100 text-xs" title="Cambiar hora"><i class="fas fa-pen"></i></button>
                                    <button type="button" @click="estado(c, 'NO_ASISTIO')" class="px-2 py-1 rounded-lg bg-gray-100 text-xs">No vino</button>
                                    <button type="button" @click="estado(c, 'CANCELADA')" class="px-2 py-1 rounded-lg bg-gray-100 text-rose-600 text-xs">Cancelar</button>
                                </div>
                            </template>
                            @if ($veClinico)
                                <a x-show="c.ate_cli_id" :href="'{{ url('clinica/atencion') }}/' + c.ate_cli_id" class="px-2 py-1 rounded-lg bg-gray-100 text-xs font-bold">Ver atención</a>
                            @endif
                        </div>
                    </div>
                </template>
                <div x-show="!visibles.length" class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">No hay citas este día.</div>
            </div>

            {{-- Por cobrar --}}
            <div class="space-y-3">
                @if ($puedeCobrar)
                    <div class="bg-white rounded-2xl shadow-sm p-5">
                        <h2 class="font-bold text-gray-700 mb-2"><i class="fas fa-cash-register text-teal-600"></i> Por cobrar ({{ $porCobrar->count() }})</h2>
                        @forelse ($porCobrar as $p)
                            <div class="flex items-center justify-between gap-2 border-b py-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold truncate">{{ $p->tipo === 'MASCOTA' ? $p->mascota . ' · ' . $p->clinom : $p->clinom }}</p>
                                    <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($p->ate_cli_fec)->format('d/m') }} · {{ $p->doctor_nom }}</p>
                                </div>
                                <a href="{{ url('cobrarmesa/' . $p->ped_id) }}" class="px-3 py-1.5 rounded-lg bg-teal-600 text-white text-xs font-bold whitespace-nowrap">Cobrar S/ {{ number_format($p->ped_tot, 2) }}</a>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">Nada pendiente. Cuando el doctor termina una atención aparece aquí.</p>
                        @endforelse
                    </div>
                @endif
                <div class="bg-white rounded-2xl shadow-sm p-5 text-sm space-y-1">
                    <p class="font-bold text-gray-700">Resumen del día</p>
                    <p>Citas: <b x-text="citas.length"></b> · En espera: <b x-text="citas.filter(c => c.estado === 'EN_ESPERA').length"></b> · Atendidas: <b x-text="citas.filter(c => c.estado === 'ATENDIDA').length"></b></p>
                </div>
            </div>
        </div>

        {{-- Nueva / editar cita --}}
        <div x-show="modal" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-start justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-5 space-y-4" @click.outside="modal = false">
                <h3 class="font-bold text-gray-800" x-text="f.cit_id ? 'Cambiar cita' : 'Nueva cita'"></h3>

                <div x-show="!f.cit_id" class="space-y-2">
                    <div class="flex gap-3 text-sm">
                        <label class="flex items-center gap-1"><input type="radio" :value="false" x-model="esNuevo" @change="esNuevo = false"> Paciente registrado</label>
                        <label class="flex items-center gap-1"><input type="radio" :value="true" x-model="esNuevo" @change="esNuevo = true"> Paciente nuevo</label>
                    </div>
                    <div x-show="!esNuevo" class="relative">
                        <input x-model="q" @input.debounce.300ms="buscar()" placeholder="Nombre, DNI o N° de historia" class="{{ $in }}">
                        <div x-show="sugerencias.length" class="absolute z-10 w-full bg-white border rounded-lg shadow mt-1 max-h-56 overflow-y-auto">
                            <template x-for="s in sugerencias" :key="s.id">
                                <button type="button" @click="elegir(s)" class="block w-full text-left px-3 py-2 text-sm hover:bg-teal-50">
                                    <b x-text="s.nombre"></b> <span class="text-xs text-gray-500" x-text="s.codigo + ' · ' + s.doc"></span></button>
                            </template>
                        </div>
                        <p x-show="f.his_cli_id" class="text-sm text-teal-700 mt-1" x-text="'✔ ' + elegido"></p>
                    </div>
                    <div x-show="esNuevo">@include('empresas.clinica._paciente_form')</div>
                </div>
                <p x-show="f.cit_id" class="text-sm font-semibold" x-text="elegido"></p>

                <div class="grid grid-cols-2 gap-2">
                    <label class="block text-xs font-semibold text-gray-500">Doctor
                        <select x-model="f.doctor" class="{{ $in }} mt-1"><option value="">—</option>@foreach ($doctores as $d)<option value="{{ $d->IdUsuario }}">{{ $d->nombre }}</option>@endforeach</select></label>
                    <label class="block text-xs font-semibold text-gray-500">Especialidad
                        <select x-model="f.esp_id" class="{{ $in }} mt-1"><option value="">—</option>@foreach ($especialidades as $e)<option value="{{ $e->esp_id }}">{{ $e->esp_des }}</option>@endforeach</select></label>
                    <label class="block text-xs font-semibold text-gray-500">Fecha<input type="date" x-model="f.fecha" class="{{ $in }} mt-1"></label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block text-xs font-semibold text-gray-500">Hora<input type="time" x-model="f.hora" step="300" class="{{ $in }} mt-1"></label>
                        <label class="block text-xs font-semibold text-gray-500">Minutos<input type="number" x-model.number="f.duracion" min="5" step="5" class="{{ $in }} mt-1"></label>
                    </div>
                    <label class="block text-xs font-semibold text-gray-500 col-span-2">Motivo<input x-model="f.motivo" maxlength="200" class="{{ $in }} mt-1" placeholder="Consulta, control, dolor de muela…"></label>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="modal = false" class="px-4 py-2 rounded-xl bg-gray-100 text-sm font-semibold">Cancelar</button>
                    <button type="button" @click="guardar()" :disabled="ocupado" class="px-4 py-2 rounded-xl bg-teal-600 text-white text-sm font-bold disabled:opacity-40">Guardar cita</button>
                </div>
            </div>
        </div>

        <div x-show="aviso" x-cloak class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] px-5 py-3 rounded-xl shadow-lg text-white font-semibold" :class="ok ? 'bg-teal-600' : 'bg-rose-600'" x-text="aviso"></div>
    </div>

    <script>
        function agenda() {
            const CSRF = '{{ csrf_token() }}';
            return {
                hoy: '{{ now()->toDateString() }}', fecha: '{{ $fecha }}', citas: [], filtroDoctor: '{{ $yo ?? '' }}',
                modal: false, esNuevo: false, q: '', sugerencias: [], elegido: '', ocupado: false, aviso: '', ok: true,
                f: {}, p: {},
                get visibles() { return this.citas.filter(c => !this.filtroDoctor || String(c.doctor) === String(this.filtroDoctor)); },
                fechaLarga(f) { return new Date(f + 'T00:00').toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); },
                nombreEstado(e) { return { PROGRAMADA: 'Programada', CONFIRMADA: 'Confirmada', EN_ESPERA: 'En sala', ATENDIDA: 'Atendida', NO_ASISTIO: 'No vino', CANCELADA: 'Cancelada' }[e] || e; },
                color(e) { return { PROGRAMADA: 'bg-gray-100 text-gray-700', CONFIRMADA: 'bg-sky-100 text-sky-800', EN_ESPERA: 'bg-amber-100 text-amber-800', ATENDIDA: 'bg-teal-100 text-teal-800', NO_ASISTIO: 'bg-rose-100 text-rose-700', CANCELADA: 'bg-gray-200 text-gray-500 line-through' }[e]; },
                borde(e) { return { EN_ESPERA: 'border-amber-400', ATENDIDA: 'border-teal-500', CONFIRMADA: 'border-sky-400', CANCELADA: 'border-gray-200 opacity-60', NO_ASISTIO: 'border-rose-300 opacity-60' }[e] || 'border-gray-300'; },
                avisar(t, ok = true) { this.aviso = t; this.ok = ok; clearTimeout(this._t); this._t = setTimeout(() => this.aviso = '', 3500); },
                async post(url, data) {
                    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data || {}) });
                    const j = await r.json().catch(() => ({}));
                    return r.status === 422 ? { ok: false, mensaje: Object.values(j.errors || {}).flat()[0] } : j;
                },
                async cargar() {
                    const d = await fetch('{{ route('clinica.agenda.datos') }}?fecha=' + this.fecha, { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => null);
                    if (d) this.citas = d.citas;
                },
                mover(dias) { const x = new Date(this.fecha + 'T00:00'); x.setDate(x.getDate() + dias); this.fecha = x.toISOString().slice(0, 10); this.cargar(); },
                async estado(c, e) {
                    if (e === 'CANCELADA' && !confirm('¿Cancelar la cita de ' + c.paciente + '?')) return;
                    const r = await this.post('{{ url('clinica/citas') }}/' + c.cit_id + '/estado', { estado: e });
                    this.avisar(r.mensaje, r.ok); this.cargar();
                },
                nuevaCita() {
                    this.f = { cit_id: null, his_cli_id: null, doctor: this.filtroDoctor || '', esp_id: '', fecha: this.fecha, hora: '09:00', duracion: 30, motivo: '' };
                    this.p = { tipo: 'PERSONA', tdicod: '1', clinum: '', clinom: '', telefono: '', mascota: '', especie: '', raza: '', sexo: '', fecha_nac: '', origen: '', msg: '' };
                    this.esNuevo = false; this.q = ''; this.sugerencias = []; this.elegido = ''; this.modal = true;
                },
                editar(c) {
                    this.f = { cit_id: c.cit_id, his_cli_id: c.his_cli_id, doctor: c.doctor || '', esp_id: c.esp_id || '', fecha: c.fecha, hora: c.hora.slice(0, 5), duracion: c.duracion, motivo: c.motivo || '' };
                    this.elegido = c.paciente; this.modal = true;
                },
                async buscar() {
                    if (this.q.trim().length < 2) { this.sugerencias = []; return; }
                    this.sugerencias = await fetch('{{ route('clinica.buscar') }}?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => []);
                },
                elegir(s) { this.f.his_cli_id = s.id; this.elegido = s.nombre + ' (' + s.codigo + ')'; this.sugerencias = []; this.q = s.nombre; },
                async buscarDoc() {
                    const d = (this.p.clinum || '').trim();
                    if (!/^\d{8}$|^\d{11}$/.test(d)) return;
                    this.p.msg = 'Buscando…';
                    const r = await fetch('{{ url('cobros/cliente') }}/' + d, { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => ({}));
                    if (r.nom) { this.p.clinom = r.nom; this.p.tdicod = r.tdicod || '1'; this.p.telefono = r.tel || this.p.telefono; this.p.msg = '✔ Encontrado'; } else this.p.msg = 'No se encontró: escribe el nombre.';
                },
                async guardar() {
                    if (!this.f.cit_id && !this.esNuevo && !this.f.his_cli_id) return this.avisar('Elige al paciente o marca "Paciente nuevo".', false);
                    this.ocupado = true;
                    const datos = Object.assign({}, this.f, { doctor: this.f.doctor || null, esp_id: this.f.esp_id || null });
                    if (!this.f.cit_id && this.esNuevo) { datos.his_cli_id = null; datos.nuevo = this.p; }
                    const r = await this.post('{{ route('clinica.cita') }}', datos);
                    this.ocupado = false;
                    this.avisar(r.mensaje || 'Revisa los datos.', !!r.ok);
                    if (r.ok) { this.modal = false; if (this.f.fecha !== this.fecha) { this.fecha = this.f.fecha; } this.cargar(); }
                },
            };
        }
    </script>
@endsection
