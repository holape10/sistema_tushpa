@extends('layouts.app')
@section('title', 'Historia ' . $h->his_cli_cod)
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')
    @php
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-teal-500 focus:ring-teal-500';
        $mascota = $h->tipo === 'MASCOTA';
        $edad = \App\Support\Clinica::edad($h->fecha_nac);
    @endphp

    <div x-data="historia()" class="space-y-5">
        {{-- Cabecera del paciente --}}
        <div class="bg-white rounded-2xl shadow-sm p-5 flex flex-wrap gap-4 items-start justify-between">
            <div>
                <a href="{{ route('clinica.index') }}" class="text-sm text-gray-500">← Pacientes</a>
                <h1 class="text-2xl font-extrabold text-gray-800 mt-1">{{ $mascota ? '🐾 ' . $h->mascota : $h->clinom }}</h1>
                <p class="text-sm text-gray-600">
                    <span class="font-mono font-bold">{{ $h->his_cli_cod }}</span>
                    @if ($mascota) · {{ $h->especie }} {{ $h->raza }} · Dueño: {{ $h->clinom }} @endif
                    · DNI {{ $h->clinum }}
                    @if ($edad) · {{ $edad }} @endif
                    @if ($h->sexo) · {{ $h->sexo === 'M' ? ($mascota ? 'Macho' : 'Masculino') : ($mascota ? 'Hembra' : 'Femenino') }} @endif
                    @if ($h->telefono) · Tel. {{ $h->telefono }} @endif
                </p>
                @if ($h->alergias)
                    <p class="mt-2 inline-block rounded-lg bg-rose-100 text-rose-800 text-sm font-bold px-3 py-1"><i class="fas fa-triangle-exclamation"></i> ALERGIAS: {{ $h->alergias }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2 items-end">
                <form method="POST" action="{{ route('clinica.nueva_atencion', $h->his_cli_id) }}" class="flex gap-2 items-end">
                    @csrf
                    <select name="esp_id" class="rounded-lg border-gray-300 text-sm">
                        @foreach ($especialidades as $e)<option value="{{ $e->esp_id }}">{{ $e->esp_des }}</option>@endforeach
                    </select>
                    <button class="px-4 py-2 rounded-xl bg-teal-600 text-white text-sm font-bold hover:bg-teal-700"><i class="fas fa-stethoscope"></i> Nueva atención</button>
                </form>
                <a href="{{ route('clinica.imprimir', $h->his_cli_id) }}" target="_blank" class="px-4 py-2 rounded-xl bg-white border border-gray-300 text-sm font-semibold"><i class="fas fa-print"></i> Imprimir historia</a>
                <button type="button" @click="const t = prompt('¿Para qué tratamiento es el consentimiento? (ej. Extracción de la pieza 38)'); if (t) window.open('{{ route('clinica.consentimiento', $h->his_cli_id) }}?tratamiento=' + encodeURIComponent(t), '_blank')"
                        class="px-4 py-2 rounded-xl bg-white border border-gray-300 text-sm font-semibold"><i class="fas fa-file-signature"></i> Consentimiento</button>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-5">
            {{-- Ficha permanente --}}
            <div class="lg:col-span-1 space-y-5">
                <form @submit.prevent="guardarFicha()" class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
                    <h2 class="font-bold text-gray-700">Ficha del paciente</h2>
                    <label class="block text-xs font-semibold text-gray-500">Alergias
                        <textarea x-model="f.alergias" rows="2" class="{{ $in }} mt-1" placeholder="Penicilina, AINES…"></textarea></label>
                    <label class="block text-xs font-semibold text-gray-500">Antecedentes {{ $mascota ? '' : 'personales y familiares' }}
                        <textarea x-model="f.antecedentes" rows="4" class="{{ $in }} mt-1" placeholder="{{ $mascota ? 'Enfermedades previas, cirugías, esterilizado…' : 'Diabetes, HTA, cirugías, padre con…' }}"></textarea></label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block text-xs font-semibold text-gray-500">Nacimiento<input type="date" x-model="f.fecha_nac" class="{{ $in }} mt-1"></label>
                        <label class="block text-xs font-semibold text-gray-500">Sexo
                            <select x-model="f.sexo" class="{{ $in }} mt-1"><option value="">—</option><option value="M">{{ $mascota ? 'Macho' : 'Masculino' }}</option><option value="F">{{ $mascota ? 'Hembra' : 'Femenino' }}</option></select></label>
                        @if ($mascota)
                            <label class="block text-xs font-semibold text-gray-500">Especie<input x-model="f.especie" class="{{ $in }} mt-1 uppercase"></label>
                            <label class="block text-xs font-semibold text-gray-500">Raza<input x-model="f.raza" class="{{ $in }} mt-1 uppercase"></label>
                        @else
                            <label class="block text-xs font-semibold text-gray-500">Grupo sanguíneo<input x-model="f.grupo_sanguineo" maxlength="5" class="{{ $in }} mt-1" placeholder="O+"></label>
                            <label class="block text-xs font-semibold text-gray-500">Ocupación<input x-model="f.ocupacion" class="{{ $in }} mt-1"></label>
                        @endif
                    </div>
                    <label class="block text-xs font-semibold text-gray-500">Contacto de emergencia<input x-model="f.contacto_emergencia" class="{{ $in }} mt-1" placeholder="Nombre y teléfono"></label>
                    <label class="block text-xs font-semibold text-gray-500">¿Cómo nos conoció?
                        <select x-model="f.origen" class="{{ $in }} mt-1"><option value="">—</option>@foreach (\App\Support\Clinica::ORIGENES as $o)<option>{{ $o }}</option>@endforeach</select></label>
                    <button class="w-full py-2 rounded-xl bg-gray-800 text-white text-sm font-bold">Guardar ficha</button>
                </form>

                @if ($proximas->isNotEmpty())
                    <div class="bg-white rounded-2xl shadow-sm p-5">
                        <h2 class="font-bold text-gray-700 mb-2">Próximas citas</h2>
                        @foreach ($proximas as $c)
                            <p class="text-sm">{{ \Carbon\Carbon::parse($c->fecha)->locale('es')->isoFormat('ddd D MMM') }} · {{ substr($c->hora, 0, 5) }} · {{ $c->motivo }}</p>
                        @endforeach
                    </div>
                @endif

                @if ($mascota || $vacunas->isNotEmpty())
                    <div class="bg-white rounded-2xl shadow-sm p-5">
                        <h2 class="font-bold text-gray-700 mb-2">Vacunas y desparasitaciones</h2>
                        @forelse ($vacunas as $v)
                            <div class="text-sm border-b py-1.5">
                                <b>{{ $v->nombre }}</b> <span class="text-xs text-gray-500">{{ $v->tipo === 'VACUNA' ? 'vacuna' : 'desparasitación' }} · {{ \Carbon\Carbon::parse($v->fecha)->format('d/m/Y') }}</span>
                                @if ($v->proxima)<span class="block text-xs {{ $v->proxima < now()->toDateString() ? 'text-rose-600 font-bold' : 'text-teal-700' }}">Próxima: {{ \Carbon\Carbon::parse($v->proxima)->format('d/m/Y') }}</span>@endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">Sin registros.</p>
                        @endforelse
                    </div>
                @endif

                @if ($conOdontograma && $h->odontograma)
                    <div class="bg-white rounded-2xl shadow-sm p-5 overflow-x-auto" x-data="{ editable: false, piezas: @js((object) \App\Support\Clinica::limpiarOdontograma(json_decode($h->odontograma, true) ?: [])), pincel: '', denticion: @js(collect(array_keys(json_decode($h->odontograma, true) ?: []))->contains(fn($n) => $n >= 51) ? 'NINO' : 'ADULTO') }">
                        <h2 class="font-bold text-gray-700 mb-2">Odontograma actual</h2>
                        @include('empresas.clinica._odontograma')
                    </div>
                @endif
            </div>

            {{-- Atenciones --}}
            <div class="lg:col-span-2 space-y-3">
                <h2 class="font-bold text-gray-700">Atenciones ({{ $atenciones->count() }})</h2>
                @forelse ($atenciones as $a)
                    <details class="bg-white rounded-2xl shadow-sm" @if ($loop->first) open @endif>
                        <summary class="px-5 py-3 cursor-pointer flex flex-wrap items-center gap-2">
                            <span class="font-bold text-gray-800">{{ \Carbon\Carbon::parse($a->ate_cli_fec)->format('d/m/Y') }}</span>
                            <span class="text-sm text-gray-500">{{ $a->esp_des }} · {{ $a->doctor_nom }}</span>
                            @if ($a->ate_cli_est !== 'ATENDIDA')<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">EN CURSO</span>@endif
                            <span class="ml-auto text-sm font-semibold text-gray-700 truncate max-w-xs">{{ $a->cie10 }} {{ \Illuminate\Support\Str::limit($a->diagnostico, 60) }}</span>
                        </summary>
                        <div class="px-5 pb-4 text-sm space-y-2 border-t pt-3">
                            @php $vitales = array_filter(['PA' => $a->pre_art, 'FC' => $a->fre_car, 'FR' => $a->fre_res, 'T°' => $a->temperatura, 'SatO₂' => $a->saturacion ? $a->saturacion . '%' : null, 'Peso' => $a->peso ? $a->peso . ' kg' : null, 'Talla' => $a->talla ? $a->talla . ' m' : null]); @endphp
                            @if ($vitales)<p class="text-xs text-gray-600">@foreach ($vitales as $k => $v)<span class="mr-3"><b>{{ $k }}</b> {{ $v }}</span>@endforeach</p>@endif
                            @foreach (['mot_con' => 'Motivo', 'antecedente' => 'Enfermedad actual', 'exa_fis' => 'Examen físico', 'diagnostico' => 'Diagnóstico', 'tratamiento' => 'Tratamiento', 'examenes' => 'Exámenes', 'indicaciones' => 'Indicaciones'] as $campo => $titulo)
                                @if ($a->$campo)<p><b class="text-gray-700">{{ $titulo }}:</b> {!! nl2br(e($a->$campo)) !!}</p>@endif
                            @endforeach
                            @if (!empty($recetas[$a->ate_cli_id]))
                                <div><b class="text-gray-700">Receta:</b>
                                    <ul class="list-disc ml-5">@foreach ($recetas[$a->ate_cli_id] as $r)<li>{{ $r->medicamento }} {{ $r->dosis }} — {{ $r->frecuencia }} {{ $r->duracion ? 'por ' . $r->duracion : '' }}</li>@endforeach</ul></div>
                            @endif
                            <div class="flex gap-2 pt-2">
                                <a href="{{ route('clinica.atencion', $a->ate_cli_id) }}" class="px-3 py-1.5 rounded-lg bg-gray-100 text-xs font-bold">{{ $a->ate_cli_est === 'ATENDIDA' ? 'Ver / corregir' : 'Continuar atención' }}</a>
                                <a href="{{ route('clinica.receta', $a->ate_cli_id) }}" target="_blank" class="px-3 py-1.5 rounded-lg bg-gray-100 text-xs font-bold"><i class="fas fa-prescription"></i> Receta</a>
                            </div>
                        </div>
                    </details>
                @empty
                    <div class="bg-white rounded-2xl shadow-sm p-8 text-center text-gray-400">Aún no tiene atenciones. Presiona <b>Nueva atención</b>.</div>
                @endforelse
            </div>
        </div>

        <div x-show="aviso" x-cloak class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] px-5 py-3 rounded-xl shadow-lg text-white font-semibold" :class="ok ? 'bg-teal-600' : 'bg-rose-600'" x-text="aviso"></div>
    </div>

    <script>
        function historia() {
            return {
                f: @js(collect((array) $h)->only(['alergias', 'antecedentes', 'fecha_nac', 'sexo', 'especie', 'raza', 'grupo_sanguineo', 'ocupacion', 'contacto_emergencia', 'origen'])),
                aviso: '', ok: true,
                async guardarFicha() {
                    const r = await fetch('{{ route('clinica.ficha', $h->his_cli_id) }}', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify(this.f) });
                    const j = await r.json().catch(() => ({}));
                    this.ok = r.ok && j.ok; this.aviso = j.mensaje || Object.values(j.errors || {}).flat()[0] || 'Error';
                    setTimeout(() => this.aviso = '', 3000);
                },
            };
        }
    </script>
@endsection
