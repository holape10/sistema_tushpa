@extends('layouts.app')
@section('title', 'Atención · ' . $h->his_cli_cod)
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-teal-500 focus:ring-teal-500';
        $mascota = $h->tipo === 'MASCOTA';
        $conOdonto = $esp && $esp->odontograma;
        $conVacunas = $mascota || ($esp && $esp->veterinaria);
        $odontoInicial = json_decode((string) ($a->odontograma ?: $h->odontograma), true) ?: [];
        $datos = collect((array) $a)->only(['esp_id', 'pre_art', 'fre_car', 'fre_res', 'temperatura', 'saturacion', 'peso', 'talla', 'mot_con', 'antecedente',
            'alergia', 'int_qui', 'exa_fis', 'cie10', 'diagnostico', 'tratamiento', 'examenes', 'indicaciones', 'pro_cit']);
        $seccion = 'bg-white rounded-2xl shadow-sm p-5 space-y-3';
    @endphp

    <div x-data="atencion()" class="space-y-5 pb-24">
        <div class="bg-white rounded-2xl shadow-sm p-5 flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('clinica.historia', $h->his_cli_id) }}" class="text-sm text-gray-500">← Historia {{ $h->his_cli_cod }}</a>
                <h1 class="text-xl font-extrabold text-gray-800 mt-1">{{ $mascota ? '🐾 ' . $h->mascota . ' · ' . $h->clinom : $h->clinom }}</h1>
                <p class="text-sm text-gray-500">Atención del {{ \Carbon\Carbon::parse($a->ate_cli_fec)->format('d/m/Y') }}
                    @if (\App\Support\Clinica::edad($h->fecha_nac)) · {{ \App\Support\Clinica::edad($h->fecha_nac) }} @endif
                    @if ($a->ate_cli_est === 'ATENDIDA') · <b class="text-teal-700">TERMINADA</b> @endif</p>
                @if ($h->alergias)<p class="mt-2 inline-block rounded-lg bg-rose-100 text-rose-800 text-sm font-bold px-3 py-1"><i class="fas fa-triangle-exclamation"></i> ALERGIAS: {{ $h->alergias }}</p>@endif
            </div>
            <label class="text-xs font-semibold text-gray-500">Especialidad
                <select x-model="d.esp_id" class="{{ $in }} mt-1">
                    @foreach ($especialidades as $e)<option value="{{ $e->esp_id }}">{{ $e->esp_des }}</option>@endforeach
                </select></label>
        </div>

        <div class="grid lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 space-y-5">
                {{-- Signos vitales --}}
                <section class="{{ $seccion }}">
                    <h2 class="font-bold text-gray-700">Signos vitales</h2>
                    <div class="grid grid-cols-3 sm:grid-cols-7 gap-2">
                        @foreach ([['pre_art', 'PA', '120/80', 'text'], ['fre_car', 'FC (lpm)', '80', 'text'], ['fre_res', 'FR (rpm)', '18', 'text'], ['temperatura', 'T° (°C)', '36.5', 'number'],
                                   ['saturacion', 'SatO₂ %', '98', 'number'], ['peso', 'Peso (kg)', '70', 'number'], ['talla', 'Talla (m)', '1.65', 'number']] as [$campo, $et, $ph, $tipo])
                            <label class="block text-xs font-semibold text-gray-500">{{ $et }}
                                <input type="{{ $tipo }}" step="any" x-model="d.{{ $campo }}" placeholder="{{ $ph }}" class="{{ $in }} mt-1"></label>
                        @endforeach
                    </div>
                    <p x-show="imc" class="text-sm text-gray-600">IMC: <b x-text="imc"></b> <span x-text="imcTexto"></span></p>
                </section>

                <section class="{{ $seccion }}">
                    <label class="block text-sm font-semibold text-gray-700">Motivo de consulta
                        <textarea x-model="d.mot_con" rows="2" class="{{ $in }} mt-1"></textarea></label>
                    <label class="block text-sm font-semibold text-gray-700">Enfermedad actual / anamnesis
                        <textarea x-model="d.antecedente" rows="3" class="{{ $in }} mt-1" placeholder="Tiempo de enfermedad, síntomas, evolución…"></textarea></label>
                    <label class="block text-sm font-semibold text-gray-700">Examen físico
                        <textarea x-model="d.exa_fis" rows="3" class="{{ $in }} mt-1"></textarea></label>
                </section>

                <section class="{{ $seccion }}">
                    <h2 class="font-bold text-gray-700">Diagnóstico</h2>
                    <div class="grid sm:grid-cols-4 gap-2">
                        <label class="block text-xs font-semibold text-gray-500">CIE-10
                            <input x-model="d.cie10" maxlength="100" placeholder="J00, K29.7" class="{{ $in }} mt-1 uppercase"></label>
                        <label class="block text-xs font-semibold text-gray-500 sm:col-span-3">Diagnóstico
                            <textarea x-model="d.diagnostico" rows="2" class="{{ $in }} mt-1" placeholder="Rinofaringitis aguda"></textarea></label>
                    </div>
                    <label class="block text-sm font-semibold text-gray-700">Tratamiento / plan
                        <textarea x-model="d.tratamiento" rows="2" class="{{ $in }} mt-1"></textarea></label>
                </section>

                {{-- Receta --}}
                <section class="{{ $seccion }}">
                    <div class="flex items-center justify-between">
                        <h2 class="font-bold text-gray-700"><i class="fas fa-prescription text-teal-600"></i> Receta</h2>
                        <button type="button" @click="receta.push({ medicamento: '', dosis: '', frecuencia: '', duracion: '', cantidad: '', indicaciones: '' })" class="text-sm font-semibold text-teal-700">+ Medicamento</button>
                    </div>
                    <template x-for="(r, i) in receta" :key="i">
                        <div class="grid grid-cols-12 gap-2 border-b pb-2">
                            <input x-model="r.medicamento" placeholder="Paracetamol 500 mg tableta" class="{{ $in }} col-span-12 sm:col-span-4">
                            <input x-model="r.dosis" placeholder="1 tableta" class="{{ $in }} col-span-6 sm:col-span-2">
                            <input x-model="r.frecuencia" placeholder="cada 8 h" class="{{ $in }} col-span-6 sm:col-span-2">
                            <input x-model="r.duracion" placeholder="5 días" class="{{ $in }} col-span-5 sm:col-span-1">
                            <input x-model="r.cantidad" placeholder="Cant." class="{{ $in }} col-span-5 sm:col-span-2">
                            <button type="button" @click="receta.splice(i, 1)" class="col-span-2 sm:col-span-1 text-rose-500"><i class="fas fa-trash"></i></button>
                            <input x-model="r.indicaciones" placeholder="Indicación (después de comer…)" class="{{ $in }} col-span-12">
                        </div>
                    </template>
                    <p x-show="!receta.length" class="text-sm text-gray-400">Sin medicamentos.</p>
                    <label class="block text-sm font-semibold text-gray-700">Exámenes auxiliares
                        <textarea x-model="d.examenes" rows="2" class="{{ $in }} mt-1" placeholder="Hemograma completo, examen de orina…"></textarea></label>
                    <label class="block text-sm font-semibold text-gray-700">Indicaciones
                        <textarea x-model="d.indicaciones" rows="2" class="{{ $in }} mt-1" placeholder="Reposo, dieta, volver si…"></textarea></label>
                </section>

                @if ($conOdonto)
                    <section class="{{ $seccion }}">
                        <h2 class="font-bold text-gray-700">🦷 Odontograma</h2>
                        <p class="text-xs text-gray-500">Elige un hallazgo y toca la cara del diente (o el diente, si es de pieza completa). Tocar otra vez lo quita. Al terminar la atención queda como odontograma de la historia.</p>
                        @include('empresas.clinica._odontograma')
                    </section>
                @endif

                @if ($conVacunas)
                    <section class="{{ $seccion }}">
                        <div class="flex items-center justify-between">
                            <h2 class="font-bold text-gray-700">💉 Vacunas y desparasitaciones de hoy</h2>
                            <button type="button" @click="vacunas.push({ tipo: 'VACUNA', nombre: '', lote: '', proxima: '' })" class="text-sm font-semibold text-teal-700">+ Agregar</button>
                        </div>
                        <template x-for="(v, i) in vacunas" :key="i">
                            <div class="grid grid-cols-12 gap-2">
                                <select x-model="v.tipo" class="{{ $in }} col-span-6 sm:col-span-2"><option value="VACUNA">Vacuna</option><option value="DESPARASITACION">Desparasitación</option></select>
                                <input x-model="v.nombre" placeholder="Séxtuple / Rabia" class="{{ $in }} col-span-6 sm:col-span-4 uppercase">
                                <input x-model="v.lote" placeholder="Lote" class="{{ $in }} col-span-5 sm:col-span-2">
                                <label class="col-span-5 sm:col-span-3 text-xs text-gray-500 flex items-center gap-1">Próxima <input type="date" x-model="v.proxima" class="{{ $in }}"></label>
                                <button type="button" @click="vacunas.splice(i, 1)" class="col-span-2 sm:col-span-1 text-rose-500"><i class="fas fa-trash"></i></button>
                            </div>
                        </template>
                    </section>
                @endif
            </div>

            <div class="space-y-5">
                {{-- Lo que se cobra --}}
                <section class="{{ $seccion }}">
                    <h2 class="font-bold text-gray-700">Se cobra en caja</h2>
                    @forelse ($servicios as $s)
                        <div class="flex justify-between text-sm border-b py-1">
                            <span>{{ (float) $s->ped_det_can }} × {{ $s->descripcion }}</span>
                            <span class="whitespace-nowrap">S/ {{ number_format($s->ped_det_can * $s->ped_det_pre, 2) }}
                                @if ($s->item_facturado == 0)<button type="button" @click="quitar({{ $s->ped_det_id }})" class="text-rose-500 ml-1"><i class="fas fa-xmark"></i></button>@else <span class="text-teal-600 text-xs">✔ cobrado</span>@endif</span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-500">La consulta de la especialidad se agrega sola al terminar. Aquí puedes sumar procedimientos o productos.</p>
                    @endforelse
                    <div class="flex gap-2">
                        <select x-model="serv.IdProducto" class="{{ $in }}">
                            <option value="">Procedimiento / producto…</option>
                            @foreach ($productos as $p)<option value="{{ $p->IdProducto }}">{{ $p->pronom }} · S/ {{ number_format($p->propun, 2) }}</option>@endforeach
                        </select>
                        <input type="number" min="1" x-model.number="serv.cantidad" class="w-16 rounded-lg border-gray-300 text-sm">
                        <button type="button" @click="agregar()" class="px-3 rounded-lg bg-gray-800 text-white text-sm font-bold">+</button>
                    </div>
                </section>

                <section class="{{ $seccion }}">
                    <label class="block text-sm font-semibold text-gray-700">Próxima cita (control)
                        <input type="date" x-model="d.pro_cit" min="{{ now()->addDay()->toDateString() }}" class="{{ $in }} mt-1"></label>
                    <p class="text-xs text-gray-500">Al terminar, queda agendada en la Agenda con el mismo doctor.</p>
                </section>

                @if ($anteriores->isNotEmpty())
                    <section class="{{ $seccion }}">
                        <h2 class="font-bold text-gray-700">Atenciones anteriores</h2>
                        @foreach ($anteriores as $x)
                            <p class="text-sm"><b>{{ \Carbon\Carbon::parse($x->ate_cli_fec)->format('d/m/Y') }}</b> {{ $x->cie10 }} {{ \Illuminate\Support\Str::limit($x->diagnostico, 70) }}</p>
                        @endforeach
                    </section>
                @endif
            </div>
        </div>

        {{-- Barra fija --}}
        <div class="fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur border-t px-4 py-3 flex flex-wrap justify-end gap-2">
            <a href="{{ route('clinica.receta', $a->ate_cli_id) }}" target="_blank" class="px-4 py-2 rounded-xl bg-white border border-gray-300 text-sm font-semibold"><i class="fas fa-print"></i> Imprimir receta</a>
            <button type="button" @click="guardar(false)" :disabled="ocupado" class="px-4 py-2 rounded-xl bg-gray-800 text-white text-sm font-bold disabled:opacity-40">Guardar</button>
            <button type="button" @click="guardar(true)" :disabled="ocupado" class="px-5 py-2 rounded-xl bg-teal-600 text-white text-sm font-extrabold hover:bg-teal-700 disabled:opacity-40"><i class="fas fa-check"></i> Terminar atención</button>
        </div>

        <div x-show="aviso" x-cloak class="fixed bottom-20 left-1/2 -translate-x-1/2 z-[60] px-5 py-3 rounded-xl shadow-lg text-white font-semibold" :class="ok ? 'bg-teal-600' : 'bg-rose-600'" x-text="aviso"></div>
    </div>

    <script>
        function atencion() {
            const CSRF = '{{ csrf_token() }}';
            return {
                d: @js($datos), receta: @js($receta->map(fn($r) => collect((array) $r)->only(['medicamento', 'dosis', 'frecuencia', 'duracion', 'cantidad', 'indicaciones']))),
                vacunas: @js($vacunas->map(fn($v) => ['tipo' => $v->tipo, 'nombre' => $v->nombre, 'lote' => $v->lote, 'proxima' => $v->proxima])),
                editable: true, piezas: @js((object) $odontoInicial), pincel: 'CARIES',
                denticion: @js(collect(array_keys($odontoInicial))->contains(fn($n) => $n >= 51) ? 'NINO' : (\App\Support\Clinica::edad($h->fecha_nac) && \Carbon\Carbon::parse($h->fecha_nac)->age < 12 ? 'NINO' : 'ADULTO')),
                serv: { IdProducto: '', cantidad: 1 }, ocupado: false, aviso: '', ok: true,
                get imc() { const p = Number(this.d.peso), t = Number(this.d.talla); return {{ $mascota ? 'false' : 'true' }} && p > 0 && t > 0.3 ? (p / (t * t)).toFixed(1) : ''; },
                get imcTexto() { const i = Number(this.imc); return !i ? '' : i < 18.5 ? '(bajo peso)' : i < 25 ? '(normal)' : i < 30 ? '(sobrepeso)' : '(obesidad)'; },
                avisar(t, ok = true) { if (window.tushpaAviso) return window.tushpaAviso(t, ok); this.aviso = t; this.ok = ok; clearTimeout(this._t); this._t = setTimeout(() => this.aviso = '', 3500); },
                async post(url, data, metodo = 'POST') {
                    const r = await fetch(url, { method: metodo, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data || {}) });
                    const j = await r.json().catch(() => ({}));
                    return r.status === 422 ? { ok: false, mensaje: Object.values(j.errors || {}).flat()[0] } : j;
                },
                limpio(o) { return Object.fromEntries(Object.entries(o).map(([k, v]) => [k, v === '' ? null : v])); },
                async guardar(finalizar) {
                    if (finalizar && !confirm('¿Terminar la atención? Recepción podrá cobrarla.')) return;
                    this.ocupado = true;
                    const r = await this.post('{{ route('clinica.atencion.guardar', $a->ate_cli_id) }}', Object.assign(this.limpio(this.d), {
                        receta: this.receta.filter(x => (x.medicamento || '').trim()).map(x => this.limpio(x)),
                        vacunas: this.vacunas.filter(x => (x.nombre || '').trim()).map(x => this.limpio(x)),
                        odontograma: this.piezas, finalizar: finalizar ? 1 : 0,
                    }));
                    this.ocupado = false;
                    this.avisar(r.mensaje || 'Error', !!r.ok);
                    if (r.ok && r.terminada) setTimeout(() => location.href = '{{ route('clinica.historia', $h->his_cli_id) }}', 1200);
                },
                async agregar() {
                    if (!this.serv.IdProducto) return;
                    await this.guardar(false);
                    const r = await this.post('{{ route('clinica.atencion.servicio', $a->ate_cli_id) }}', this.serv);
                    this.avisar(r.mensaje, r.ok); if (r.ok) setTimeout(() => location.reload(), 700);
                },
                async quitar(det) {
                    const r = await this.post('{{ url('clinica/atencion/' . $a->ate_cli_id . '/servicio') }}/' + det, {}, 'DELETE');
                    this.avisar(r.mensaje, r.ok); if (r.ok) setTimeout(() => location.reload(), 700);
                },
            };
        }
    </script>
@endsection
