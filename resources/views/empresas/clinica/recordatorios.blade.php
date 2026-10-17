@extends('layouts.app')
@section('title', 'Recordatorios de citas')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-teal-500 focus:ring-teal-500';
        $tarjeta = 'bg-white rounded-2xl shadow-sm p-5';
        $hora = fn ($h) => \Carbon\Carbon::parse($h)->format('g:i a');
        $esManana = $fecha === now()->addDay()->toDateString();
    @endphp
    <div x-data="recordatorios()" class="space-y-5">
        <div class="rounded-2xl p-5 text-white bg-gradient-to-r from-teal-600 to-emerald-600 shadow-sm flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[220px]">
                <a href="{{ route('clinica.agenda') }}" class="text-sm text-white/80">← Agenda</a>
                <h1 class="text-2xl font-extrabold">📲 Recordatorios de citas</h1>
                <p class="text-sm text-white/85">Citas del {{ \App\Support\ClinicaRecordatorios::fechaTexto($fecha) }}{{ $esManana ? ' (mañana)' : '' }}</p>
            </div>
            <form method="GET" class="flex items-end gap-2">
                <label class="text-xs">Día<input type="date" name="fecha" value="{{ $fecha }}" class="block rounded-lg border-0 text-gray-800 text-sm"></label>
                <button class="h-9 px-4 rounded-lg bg-white/20 font-semibold text-sm">Ver</button>
            </form>
        </div>

        {{-- Estado del envío automático --}}
        <div class="grid sm:grid-cols-3 gap-3">
            <div class="{{ $tarjeta }} !p-4">
                <p class="text-xs font-semibold uppercase text-gray-400">WhatsApp automático</p>
                @if ($cuenta && ($cuenta['plantilla_paciente'] || $cuenta['plantilla_doctor']))
                    <p class="text-lg font-black text-emerald-600">✔ Activo</p>
                    <p class="text-xs text-gray-500">Sale solo cada día desde las {{ $hora($cfg->hora_envio) }} ({{ $cfg->propia ? 'cuenta de la clínica' : 'cuenta del sistema' }}).</p>
                @else
                    <p class="text-lg font-black text-amber-600">A mano</p>
                    <p class="text-xs text-gray-500">Aún no hay cuenta de WhatsApp Business: envíalos con los botones verdes (un clic cada uno).</p>
                @endif
            </div>
            <div class="{{ $tarjeta }} !p-4">
                <p class="text-xs font-semibold uppercase text-gray-400">Al paciente</p>
                <p class="text-lg font-black {{ $cfg->recordar_paciente ? 'text-emerald-600' : 'text-gray-400' }}">{{ $cfg->recordar_paciente ? '✔ Sí' : 'No' }}</p>
                <p class="text-xs text-gray-500">{{ $citas->whereNotNull('enviado')->count() }} de {{ $citas->count() }} recordados</p>
            </div>
            <div class="{{ $tarjeta }} !p-4">
                <p class="text-xs font-semibold uppercase text-gray-400">Al doctor tratante</p>
                <p class="text-lg font-black {{ $cfg->recordar_doctor ? 'text-emerald-600' : 'text-gray-400' }}">{{ $cfg->recordar_doctor ? '✔ Sí' : 'No' }}</p>
                <p class="text-xs text-gray-500">Por WhatsApp{{ $hayCorreo ? ' y correo' : '' }}: su lista de citas del día.</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-5">
            <div class="{{ $tarjeta }} lg:col-span-2 min-w-0">
                <h2 class="font-bold text-gray-800 mb-2"><i class="fas fa-user text-teal-600"></i> Pacientes ({{ $citas->count() }})</h2>
                <div class="divide-y">
                    @forelse ($citas as $c)
                        <div class="py-2.5 flex flex-wrap items-center gap-3">
                            <div class="w-16 font-extrabold text-gray-800">{{ $hora($c->hora) }}</div>
                            <div class="flex-1 min-w-[180px]">
                                <p class="font-semibold">{{ $c->paciente }}</p>
                                <p class="text-xs text-gray-500">{{ collect([$c->doctor_nom, $c->esp_des, $c->motivo, $c->telefono ?: 'sin celular'])->filter()->implode(' · ') }}
                                    · {{ $c->estado === 'CONFIRMADA' ? 'confirmada' : 'por confirmar' }}</p>
                            </div>
                            @if ($c->enviado)
                                <span class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-bold">✔ {{ $c->enviado->canal === 'MANUAL' ? 'Enviado a mano' : 'Enviado solo' }}</span>
                            @endif
                            @if ($c->wa)
                                <a href="{{ $c->wa }}" target="_blank" rel="noopener" @click="anotar('PACIENTE', {{ $c->cit_id }}, null)"
                                   class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold"><i class="fab fa-whatsapp"></i> {{ $c->enviado ? 'Otra vez' : 'Recordar' }}</a>
                            @endif
                        </div>
                    @empty
                        <p class="py-6 text-center text-gray-400">No hay citas por atender ese día.</p>
                    @endforelse
                </div>
            </div>
            <div class="{{ $tarjeta }} min-w-0">
                <h2 class="font-bold text-gray-800 mb-2"><i class="fas fa-user-doctor text-indigo-600"></i> Doctores</h2>
                @forelse ($doctores as $d)
                    <div class="py-2.5 border-b last:border-0">
                        <p class="font-semibold">{{ $d->nombre }} <span class="text-xs font-normal text-gray-500">· {{ $d->citas }} cita(s)</span></p>
                        <p class="text-xs text-gray-500">{{ $d->telefono ?: 'sin celular' }}{{ $d->correo ? ' · '.$d->correo : '' }}</p>
                        <div class="flex flex-wrap gap-2 mt-1">
                            @if ($d->enviado)<span class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-bold">✔ Avisado ({{ mb_strtolower($d->enviado) }})</span>@endif
                            @if ($d->wa)
                                <a href="{{ $d->wa }}" target="_blank" rel="noopener" @click="anotar('DOCTOR', null, {{ $d->id }})" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold"><i class="fab fa-whatsapp"></i> Enviar su lista</a>
                            @else
                                <span class="text-xs text-amber-600">Pon su celular en la configuración ↓</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Las citas de ese día no tienen doctor asignado.</p>
                @endforelse
            </div>
        </div>

        @if ($esAdmin)
            <details class="{{ $tarjeta }}" @if (! $cuenta) open @endif>
                <summary class="font-bold text-gray-800 cursor-pointer"><i class="fas fa-gear text-gray-500"></i> Configuración (solo administrador)</summary>
                <div class="mt-4 grid lg:grid-cols-2 gap-6">
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="f.recordar_paciente" class="rounded text-teal-600"> Recordar al <b>paciente</b> su cita del día siguiente</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="f.recordar_doctor" class="rounded text-teal-600"> Enviar al <b>doctor</b> su lista de citas del día siguiente</label>
                        <label class="block text-xs font-semibold text-gray-500 max-w-[200px]">Hora de envío<input type="time" x-model="f.hora_envio" class="{{ $in }} mt-1"></label>
                        <h3 class="font-bold text-gray-700 pt-2">Celular y correo del personal</h3>
                        <p class="text-xs text-gray-500">A este celular / correo le llega al doctor su lista de citas.</p>
                        <template x-for="p in f.personal" :key="p.IdUsuario">
                            <div class="grid grid-cols-12 gap-2 items-center">
                                <span class="col-span-12 sm:col-span-4 text-sm font-semibold truncate" x-text="p.nombre"></span>
                                <input x-model="p.telefono" placeholder="Celular 9 dígitos" maxlength="20" class="{{ $in }} col-span-5 sm:col-span-3">
                                <input x-model="p.correo" type="email" placeholder="correo@ejemplo.com" maxlength="120" class="{{ $in }} col-span-7 sm:col-span-5">
                            </div>
                        </template>
                        @unless ($hayCorreo)<p class="text-xs text-amber-700">El correo del servidor aún no está configurado: por ahora los avisos al doctor van solo por WhatsApp.</p>@endunless
                    </div>
                    <div class="space-y-3">
                        <h3 class="font-bold text-gray-700">WhatsApp Business (API de Meta) <span class="text-xs font-normal text-gray-500">— opcional, para que salga solo</span></h3>
                        <p class="text-xs text-gray-500">Si lo dejas vacío se usa la cuenta del sistema (si existe). Si no hay ninguna, los recordatorios se envían a mano con los botones verdes.</p>
                        <div class="grid sm:grid-cols-2 gap-2">
                            <label class="block text-xs font-semibold text-gray-500">ID del número (Phone number ID)<input x-model="f.wa_phone_id" inputmode="numeric" class="{{ $in }} mt-1"></label>
                            <label class="block text-xs font-semibold text-gray-500">Idioma de las plantillas<input x-model="f.wa_idioma" placeholder="es" class="{{ $in }} mt-1"></label>
                            <label class="block text-xs font-semibold text-gray-500 sm:col-span-2">Token permanente
                                <input x-model="f.wa_token" type="password" autocomplete="new-password" :placeholder="tieneToken ? '•••••• guardado (escribe uno nuevo para cambiarlo)' : 'EAAG…'" class="{{ $in }} mt-1"></label>
                            <label class="block text-xs font-semibold text-gray-500">Plantilla del paciente<input x-model="f.wa_plantilla_paciente" placeholder="recordatorio_cita" class="{{ $in }} mt-1"></label>
                            <label class="block text-xs font-semibold text-gray-500">Plantilla del doctor<input x-model="f.wa_plantilla_doctor" placeholder="agenda_doctor" class="{{ $in }} mt-1"></label>
                        </div>
                        <label x-show="tieneToken" class="flex items-center gap-2 text-xs text-rose-600"><input type="checkbox" x-model="f.quitar_token" class="rounded"> Quitar la cuenta de WhatsApp de la clínica</label>
                        <div class="rounded-xl bg-gray-50 p-3 text-xs text-gray-600 space-y-1">
                            <p class="font-bold">Plantillas que se crean en Meta (categoría "Utilidad", idioma español):</p>
                            <p><b>recordatorio_cita</b>: Hola @{{1}}, le recordamos su cita en @{{2}} el @{{3}} a las @{{4}}. Por favor confirme su asistencia respondiendo este mensaje.</p>
                            <p><b>agenda_doctor</b>: Hola @{{1}}, el @{{2}} tienes @{{3}} cita(s): @{{4}}</p>
                        </div>
                        <div class="flex flex-wrap gap-2 items-end">
                            <label class="block text-xs font-semibold text-gray-500">Probar con mi celular<input x-model="prueba" placeholder="987654321" class="{{ $in }} mt-1"></label>
                            <button type="button" @click="probar()" :disabled="ocupado" class="px-3 py-2 rounded-lg bg-white border text-sm font-semibold">Enviar prueba</button>
                        </div>
                    </div>
                </div>
                <div class="mt-4 flex justify-end"><button type="button" @click="guardar()" :disabled="ocupado" class="px-5 py-2 rounded-xl bg-teal-600 text-white font-bold text-sm disabled:opacity-40">Guardar configuración</button></div>
            </details>
        @endif

        <div class="{{ $tarjeta }}">
            <h2 class="font-bold text-gray-800 mb-2"><i class="fas fa-list-check text-gray-500"></i> Últimos envíos</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-400 uppercase"><tr><th class="text-left py-1">Cuándo</th><th class="text-left">Para</th><th class="text-left">Cita del</th><th class="text-left">Medio</th><th class="text-left">Resultado</th></tr></thead>
                    <tbody class="divide-y">
                        @forelse ($registro as $r)
                            <tr><td class="py-1.5 whitespace-nowrap">{{ \Carbon\Carbon::parse($r->creado)->format('d/m H:i') }}</td>
                                <td>{{ $r->para === 'PACIENTE' ? $r->paciente : 'Dr(a). '.$r->doctor_nom }}</td>
                                <td class="whitespace-nowrap">{{ \Carbon\Carbon::parse($r->fecha_cita)->format('d/m') }}</td>
                                <td>{{ ['WHATSAPP' => 'WhatsApp', 'CORREO' => 'Correo', 'MANUAL' => 'A mano'][$r->canal] ?? $r->canal }}</td>
                                <td class="{{ $r->estado === 'ENVIADO' ? 'text-emerald-700' : 'text-rose-600' }} text-xs">{{ $r->estado === 'ENVIADO' ? '✔ Enviado' : '✖ '.$r->detalle }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-gray-400">Aún no se envió ningún recordatorio.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function recordatorios() {
            const CSRF = '{{ csrf_token() }}';
            return {
                ocupado: false, prueba: '', tieneToken: @js($cfg->propia),
                f: {
                    recordar_paciente: @js($cfg->recordar_paciente), recordar_doctor: @js($cfg->recordar_doctor), hora_envio: @js(substr($cfg->hora_envio, 0, 5)),
                    wa_phone_id: @js($cfg->propia ? $cfg->wa_phone_id : ''), wa_token: '', wa_plantilla_paciente: @js($cfg->propia ? $cfg->wa_plantilla_paciente : ''),
                    wa_plantilla_doctor: @js($cfg->propia ? $cfg->wa_plantilla_doctor : ''), wa_idioma: @js($cfg->wa_idioma), quitar_token: false,
                    personal: @js($personal->map(fn ($p) => ['IdUsuario' => $p->IdUsuario, 'nombre' => $p->apeusu ?: $p->name, 'telefono' => $p->telefono, 'correo' => $p->correo])->values()),
                },
                async post(url, data) {
                    this.ocupado = true;
                    try {
                        const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data) });
                        const j = await r.json().catch(() => ({}));
                        return r.status === 422 ? { ok: false, mensaje: Object.values(j.errors || {}).flat()[0] } : j;
                    } finally { this.ocupado = false; }
                },
                async guardar() {
                    const r = await this.post('{{ route('clinica.recordatorios.guardar') }}', this.f);
                    tushpaAviso(r.mensaje || 'Error', r.ok ? 'ok' : 'error');
                    if (r.ok) setTimeout(() => location.reload(), 800);
                },
                async probar() { const r = await this.post('{{ route('clinica.recordatorios.probar') }}', { celular: this.prueba }); tushpaAviso(r.mensaje || 'Error', r.ok ? 'ok' : 'error'); },
                anotar(para, cit_id, doctor) { this.post('{{ route('clinica.recordatorios.manual') }}', { para, cit_id, doctor, fecha: @js($fecha) }); },
            };
        }
    </script>
@endsection
