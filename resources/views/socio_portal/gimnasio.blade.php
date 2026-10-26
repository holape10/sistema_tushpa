@extends('socio_portal.layout')
@section('titulo', 'Mi gimnasio')
@section('subtitulo', 'Mi gimnasio')

@section('acciones')
    <form method="POST" action="{{ route('socio.portal.salir') }}" class="ml-2">
        @csrf
        <button class="text-sm font-semibold bg-white/10 hover:bg-white/20 rounded-xl px-3 py-2">Salir</button>
    </form>
@endsection

@section('contenido')
<script>
    function portalGym() {
        return {
            d: @js($datos),
            tab: @js($errors->has('congelar') || $errors->has('desde') || $errors->has('acepto') ? 'congelar' : 'plan'),
            nutri: 0,
            async refrescar() {
                try {
                    const r = await fetch('{{ route('socio.portal.estado') }}', { headers: { 'Accept': 'application/json' } });
                    if (r.status === 401) { location.reload(); return; }
                    if (r.ok) this.d = await r.json();
                } catch (e) {}
            },
            get fondo() { return { green: 'from-emerald-500 to-emerald-700', amber: 'from-amber-400 to-orange-600', sky: 'from-sky-500 to-indigo-600', red: 'from-rose-500 to-rose-700' }[this.d.color] || 'from-slate-500 to-slate-700'; },
            soles(n) { return 'S/ ' + Number(n || 0).toFixed(2); },
        };
    }
</script>

@php
    $manana = now()->addDay()->toDateString();
    $comidas = [['desayuno', 'Desayuno'], ['media_manana', 'Media mañana'], ['almuerzo', 'Almuerzo'], ['media_tarde', 'Media tarde'], ['cena', 'Cena'], ['indicaciones', 'Indicaciones']];
@endphp

<div x-data="portalGym()" x-init="setInterval(() => refrescar(), 30000)" class="space-y-5">
    @if (session('aviso'))
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('aviso') }}</div>
    @endif
    @if ($cambiarClave)
        <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            <b>Por seguridad crea tu propia contraseña</b> (hoy entras con tu DNI). Hazlo abajo en <a href="#clave" class="underline font-semibold">Cambiar contraseña</a>.
        </div>
    @endif

    {{-- Estado --}}
    <section class="rounded-3xl text-white p-6 shadow-lg bg-gradient-to-br relative isolate overflow-hidden" :class="fondo">
        <x-kene-adorno patron="escalera" />
        <div class="flex items-center gap-4">
            @if ($socio->foto && is_file(public_path($socio->foto)))
                <img src="{{ asset($socio->foto) }}" alt="" class="w-16 h-16 rounded-2xl object-cover ring-4 ring-white/30">
            @endif
            <div class="min-w-0">
                <p class="text-sm opacity-90">Hola, {{ \Illuminate\Support\Str::of($socio->clinom)->title() }}</p>
                <p class="text-4xl font-black tracking-wide" x-text="d.texto"></p>
            </div>
        </div>
        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
            <div class="bg-white/15 rounded-2xl py-3">
                <p class="text-2xl font-black" x-text="d.dias"></p><p class="text-[11px] opacity-90">días restantes</p>
            </div>
            <div class="bg-white/15 rounded-2xl py-3">
                <p class="text-lg font-black leading-7" x-text="d.vence || '—'"></p><p class="text-[11px] opacity-90">vence</p>
            </div>
            <div class="bg-white/15 rounded-2xl py-3">
                <p class="text-2xl font-black" x-text="d.mes"></p><p class="text-[11px] opacity-90">días entrenados este mes</p>
            </div>
        </div>
        <p x-show="d.plan" class="mt-3 text-sm opacity-95" x-text="'Plan ' + d.plan"></p>
        <p x-show="d.estado === 'POR_VENCER'" class="mt-2 text-sm font-bold bg-black/20 rounded-xl px-3 py-2" x-text="'⏰ Tu plan vence el ' + d.vence + '. Renuévalo en recepción para seguir entrenando.'"></p>
        <p x-show="d.congelado" class="mt-2 text-sm bg-black/20 rounded-xl px-3 py-2" x-text="d.congelado ? 'Congelado del ' + d.congelado.desde + ' al ' + d.congelado.hasta + ' (' + d.congelado.motivo + '). Si vienes antes, necesitas aprobación del administrador o pagar la rutina del día.' : ''"></p>
    </section>

    {{-- Saldo de lo pagado por partes --}}
    <div x-show="d.deuda && d.deuda.total > 0" x-cloak class="rounded-2xl px-4 py-3 text-sm font-semibold"
         :class="d.deuda?.vencido ? 'bg-rose-50 border border-rose-200 text-rose-800' : 'bg-violet-50 border border-violet-200 text-violet-800'"
         x-text="d.deuda ? (d.deuda.vencido ? '⚠️ Tienes un saldo VENCIDO de ' + soles(d.deuda.total) + '. Pásalo en recepción.' : '💳 Tienes un saldo de ' + soles(d.deuda.total) + ' para pagar hasta el ' + d.deuda.vence + '.') : ''"></div>

    {{-- Pestañas --}}
    <nav class="flex gap-2 overflow-x-auto pb-1">
        <template x-for="t in [['plan', '🏋️ Mi plan'], ['rutina', '💪 Mi rutina'], ['medidas', '📏 Mis medidas'], ['nutricion', '🥗 Nutrición'], ['congelar', '❄️ Congelar'], ['carnet', '🆔 Carnet'], ['asistencia', '📅 Asistencia']]">
            <button type="button" @click="tab = t[0]" class="px-4 py-2 rounded-full text-sm font-bold whitespace-nowrap"
                    :class="tab === t[0] ? 'bg-emerald-700 text-white' : 'bg-white text-slate-600'" x-text="t[1]"></button>
        </template>
    </nav>

    {{-- Mi plan --}}
    <section x-show="tab === 'plan'" class="bg-white rounded-2xl shadow-sm divide-y">
        <template x-for="m in d.membresias">
            <div class="p-4 flex justify-between gap-3">
                <div>
                    <p class="font-semibold text-slate-700" x-text="m.plan"></p>
                    <p class="text-xs text-slate-400"><span x-text="m.inicio + ' al ' + m.fin"></span>
                        <template x-if="m.pdf"><span> · <a :href="m.pdf" target="_blank" class="text-emerald-700 font-semibold underline" x-text="m.comprobante"></a></span></template></p>
                </div>
                <p class="font-extrabold text-emerald-700 whitespace-nowrap" x-text="soles(m.precio)"></p>
            </div>
        </template>
        <p x-show="!d.membresias.length" class="p-8 text-center text-slate-400">Aún no tienes planes. Acércate a recepción.</p>
        <div x-show="d.condiciones" class="p-4 bg-rose-50 text-rose-800 text-xs"><b>🏷️ Plan de promoción:</b> <span x-text="d.condiciones"></span></div>
    </section>

    {{-- Mi rutina (la arma el entrenador cada mes) --}}
    <section x-show="tab === 'rutina'" x-cloak class="space-y-3">
        <template x-if="d.rutina">
            <div class="space-y-3">
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <p class="text-lg font-extrabold text-slate-800" x-text="'Rutina de ' + d.rutina.mes"></p>
                    <p class="text-xs text-slate-500" x-text="(d.rutina.objetivo ? d.rutina.objetivo + ' · ' : '') + 'Tu entrenador: ' + (d.rutina.entrenador || '—')"></p>
                    <p x-show="!d.rutina.vigente" class="mt-2 text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2">Es tu última rutina. Pide a tu entrenador la de este mes.</p>
                    <div x-show="d.rutina.calentamiento.length" class="mt-3">
                        <p class="text-[11px] font-black uppercase text-emerald-800">🔥 Calentamiento</p>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <template x-for="c in d.rutina.calentamiento"><span class="px-3 py-1.5 rounded-xl bg-emerald-50 text-sm font-semibold text-emerald-800" x-text="c.nombre + ' ' + c.min + ' min'"></span></template>
                        </div>
                    </div>
                </div>
                <template x-for="dia in d.rutina.dias">
                    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                        <p class="px-4 py-2.5 bg-emerald-700 text-white font-black text-sm" x-text="dia.dia + (dia.grupo ? ' · ' + dia.grupo : '')"></p>
                        <div class="divide-y">
                            <template x-for="e in dia.ejercicios">
                                <div class="px-4 py-2.5 flex items-center gap-3">
                                    <span class="flex-1 font-semibold text-slate-700 text-sm" x-text="e.nombre"></span>
                                    <span x-show="e.series || e.reps" class="text-sm font-black text-emerald-700 whitespace-nowrap" x-text="(e.series || '') + (e.reps ? ' x ' + e.reps : '')"></span>
                                    <span x-show="e.nota" class="text-xs text-slate-500" x-text="e.nota"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                <p x-show="d.rutina.indicaciones" class="bg-white rounded-2xl shadow-sm p-4 text-sm text-slate-700 whitespace-pre-line" x-text="d.rutina.indicaciones"></p>
            </div>
        </template>
        <p x-show="!d.rutina" class="bg-white rounded-2xl p-8 text-center text-slate-400">Tu entrenador aún no te asignó una rutina.</p>
    </section>

    {{-- Mis medidas --}}
    <section x-show="tab === 'medidas'" x-cloak class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table x-show="d.medidas.length" class="w-full text-xs">
            <thead class="bg-slate-50 text-slate-500"><tr><th class="px-3 py-2 text-left">Fecha</th><th class="px-2 py-2">Peso</th><th class="px-2 py-2">IMC</th><th class="px-2 py-2">% Grasa</th>
                <th class="px-2 py-2">Cintura</th><th class="px-2 py-2">Cadera</th><th class="px-2 py-2">Brazo</th><th class="px-2 py-2">Muslo</th></tr></thead>
            <tbody class="divide-y">
                <template x-for="m in d.medidas">
                    <tr class="text-center">
                        <td class="px-3 py-2 text-left font-semibold" x-text="m.fecha_txt"></td>
                        <td class="px-2 py-2" x-text="m.peso ? m.peso + ' kg' : '—'"></td>
                        <td class="px-2 py-2 font-bold" x-text="m.imc ?? '—'"></td>
                        <td class="px-2 py-2" x-text="m.grasa ? m.grasa + '%' : '—'"></td>
                        <td class="px-2 py-2" x-text="m.cintura ?? '—'"></td>
                        <td class="px-2 py-2" x-text="m.cadera ?? '—'"></td>
                        <td class="px-2 py-2" x-text="m.brazo ?? '—'"></td>
                        <td class="px-2 py-2" x-text="m.muslo ?? '—'"></td>
                    </tr>
                </template>
            </tbody>
        </table>
        <p x-show="!d.medidas.length" class="p-8 text-center text-slate-400">Aún no tienes medidas registradas. Pídele a tu entrenador que te las tome.</p>
    </section>

    {{-- Nutrición --}}
    <section x-show="tab === 'nutricion'" x-cloak class="space-y-3">
        <div x-show="d.nutricion.length > 1" class="flex gap-2 overflow-x-auto">
            <template x-for="(n, i) in d.nutricion">
                <button type="button" @click="nutri = i" class="px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap" :class="nutri === i ? 'bg-emerald-700 text-white' : 'bg-white text-slate-600'" x-text="i === 0 ? 'Actual · ' + n.fecha_txt : n.fecha_txt"></button>
            </template>
        </div>
        <template x-if="d.nutricion[nutri]">
            <div class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
                <div>
                    <p class="text-lg font-extrabold text-slate-800" x-text="d.nutricion[nutri].objetivo"></p>
                    <p class="text-xs text-slate-500" x-text="'Tu entrenador: ' + (d.nutricion[nutri].entrenador || '—') + ' · ' + d.nutricion[nutri].fecha_txt"></p>
                </div>
                <div class="grid grid-cols-4 gap-2 text-center">
                    <div class="bg-slate-50 rounded-xl py-2"><p class="font-black" x-text="d.nutricion[nutri].peso ? d.nutricion[nutri].peso + ' kg' : '—'"></p><p class="text-[10px] text-slate-500">Peso</p></div>
                    <div class="bg-slate-50 rounded-xl py-2"><p class="font-black" x-text="d.nutricion[nutri].imc ?? '—'"></p><p class="text-[10px] text-slate-500">IMC</p></div>
                    <div class="bg-slate-50 rounded-xl py-2"><p class="font-black" x-text="d.nutricion[nutri].grasa ? d.nutricion[nutri].grasa + '%' : '—'"></p><p class="text-[10px] text-slate-500">Grasa</p></div>
                    <div class="bg-slate-50 rounded-xl py-2"><p class="font-black" x-text="d.nutricion[nutri].calorias ?? '—'"></p><p class="text-[10px] text-slate-500">kcal/día</p></div>
                </div>
                @foreach ($comidas as [$k, $nombre])
                    <div x-show="d.nutricion[nutri].{{ $k }}" class="rounded-xl bg-emerald-50 p-3">
                        <p class="text-[11px] font-black uppercase text-emerald-800">{{ $nombre }}</p>
                        <p class="text-sm text-slate-700 whitespace-pre-line" x-text="d.nutricion[nutri].{{ $k }}"></p>
                    </div>
                @endforeach
            </div>
        </template>
        <p x-show="!d.nutricion.length" class="bg-white rounded-2xl p-8 text-center text-slate-400">Tu entrenador aún no te asignó un plan de nutrición.</p>
    </section>

    {{-- Congelar --}}
    <section x-show="tab === 'congelar'" x-cloak class="space-y-3">
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <h2 class="font-bold text-slate-700">¿No podrás venir unos días?</h2>
            <p class="text-sm text-slate-500 mt-1">Congela tu plan (viaje, enfermedad…): esos días se suman al final y tu plan vuelve a correr solo cuando termine.</p>
            @if ($errors->hasAny(['congelar', 'desde', 'hasta', 'motivo', 'acepto']))
                <div class="mt-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-2 text-sm">{{ $errors->first() }}</div>
            @endif
            <template x-if="d.puede_congelar">
                <form method="POST" action="{{ route('socio.portal.congelar') }}" class="mt-4 space-y-3"
                      onsubmit="return confirm('¿Congelar tu plan en esas fechas? Una vez registrado NO podrás editarlo ni anularlo. Si vienes antes, necesitarás la aprobación del administrador o pagar la rutina del día.')">
                    @csrf
                    <p class="text-xs font-semibold text-sky-700 bg-sky-50 rounded-lg px-3 py-2" x-text="'Tu plan te permite congelar ' + d.congelar_max + ' días; te quedan ' + d.congelar_quedan + '.'"></p>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-sm">Desde<input type="date" name="desde" value="{{ old('desde') }}" min="{{ $manana }}" required class="block w-full h-11 rounded-xl border-slate-300 mt-1"></label>
                        <label class="text-sm">Hasta<input type="date" name="hasta" value="{{ old('hasta') }}" min="{{ $manana }}" required class="block w-full h-11 rounded-xl border-slate-300 mt-1"></label>
                    </div>
                    <label class="block text-sm">Motivo
                        <select name="motivo" required class="block w-full h-11 rounded-xl border-slate-300 mt-1">
                            @foreach ($motivos as $m)<option @selected(old('motivo') === $m)>{{ $m }}</option>@endforeach
                        </select></label>
                    <label class="block text-sm">Detalle <span class="text-slate-400">(opcional)</span>
                        <input name="detalle" value="{{ old('detalle') }}" maxlength="200" placeholder="Ej. viaje a Lima por trabajo" class="block w-full h-11 rounded-xl border-slate-300 mt-1"></label>
                    <label class="flex items-start gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="acepto" value="1" required class="mt-0.5 rounded">
                        <span>Entiendo que <b>no podré editarlo ni anularlo</b>, y que si vengo antes no podré entrar sin aprobación del administrador o pagando la rutina del día.</span></label>
                    <button class="w-full h-12 rounded-xl bg-sky-600 text-white font-bold">❄️ Congelar mi plan</button>
                </form>
            </template>
            <p x-show="!d.puede_congelar" class="mt-4 text-sm text-slate-500 bg-slate-50 rounded-xl px-4 py-3"
               x-text="!d.plan ? 'Necesitas un plan vigente para congelar.' : (d.congelar_max ? 'Ya usaste todos los días que tu plan permite congelar.' : 'Tu plan no permite congelar desde aquí. Consulta en recepción.')"></p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm divide-y">
            <template x-for="c in d.congelamientos">
                <div class="p-4">
                    <p class="font-semibold text-slate-700" x-text="'❄️ ' + c.desde + ' al ' + c.hasta + ' · ' + c.dias + (c.dias === 1 ? ' día' : ' días')"></p>
                    <p class="text-xs text-slate-400" x-text="c.motivo + (c.detalle ? ': ' + c.detalle : '') + (c.origen === 'ADMIN' ? ' · registrado por el gimnasio' : '')"></p>
                    <p class="text-xs text-sky-700" x-show="c.nota" x-text="c.nota"></p>
                </div>
            </template>
            <p x-show="!d.congelamientos.length" class="p-6 text-center text-slate-400 text-sm">No tienes congelamientos.</p>
        </div>
    </section>

    {{-- Carnet --}}
    <section x-show="tab === 'carnet'" x-cloak>
        <div class="bg-white rounded-3xl shadow-sm overflow-hidden max-w-sm mx-auto">
            <div class="bg-emerald-800 text-white px-5 py-3 flex justify-between items-center">
                <span class="font-bold">{{ $negocio->nombre_comercial ?: $negocio->NomEmpresa }}</span>
                <span class="text-xs">N° {{ $socio->codigo }}</span>
            </div>
            <div class="p-5 text-center">
                <div class="w-56 h-56 mx-auto [&_svg]:w-full [&_svg]:h-full" x-html="d.carnet"></div>
                <p class="mt-3 font-extrabold text-lg">{{ $socio->clinom }}</p>
                <span class="inline-block mt-2 px-3 py-1 rounded-full text-sm font-bold text-white bg-gradient-to-r" :class="fondo" x-text="d.texto"></span>
            </div>
            <p class="bg-emerald-50 text-emerald-800 text-xs text-center py-2">Muestra este QR en recepción para entrar</p>
        </div>
    </section>

    {{-- Asistencia --}}
    <section x-show="tab === 'asistencia'" x-cloak class="bg-white rounded-2xl shadow-sm divide-y">
        <template x-for="a in d.asistencias">
            <div class="px-4 py-3 flex items-center gap-3 text-sm">
                <span x-text="a.ok ? '✅' : '⛔'"></span>
                <span class="font-semibold text-slate-700 capitalize flex-1" x-text="a.fecha"></span>
                <span class="text-slate-500" x-text="a.hora"></span>
            </div>
        </template>
        <p x-show="!d.asistencias.length" class="p-8 text-center text-slate-400">Aún no registras ingresos.</p>
    </section>

    {{-- Contraseña --}}
    <section id="clave" class="bg-white rounded-2xl shadow-sm p-5">
        <h2 class="font-bold text-slate-700">Cambiar contraseña</h2>
        @if ($errors->has('password'))
            <div class="mt-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-2 text-sm">{{ $errors->first('password') }}</div>
        @endif
        <form method="POST" action="{{ route('socio.portal.clave') }}" class="mt-3 grid sm:grid-cols-3 gap-2">
            @csrf
            <input type="password" name="password" required minlength="6" placeholder="Nueva contraseña" autocomplete="new-password" class="h-11 rounded-xl border-slate-300">
            <input type="password" name="password_confirmation" required minlength="6" placeholder="Repítela" autocomplete="new-password" class="h-11 rounded-xl border-slate-300">
            <button class="h-11 rounded-xl bg-emerald-700 text-white font-bold">Guardar</button>
        </form>
    </section>
</div>
@endsection
