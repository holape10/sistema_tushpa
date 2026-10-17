@extends('admin.layout')
@section('title', 'Prospectos')

@section('content')
    @php
        $plantillas = [
            'cartas' => "Hola {nombre} 👋, te saludamos de TUSHPA. Vimos que creaste tu carta digital ({enlace}). ¿Te gustaría que tus clientes también pidan desde la mesa, con boletas SUNAT, cocina y caja? Pruébalo gratis 15 días: https://".config('tenancy.dominio')."/registro",
            'pruebas' => "Hola {nombre} 👋, te saludamos de TUSHPA. ¿Cómo te va con tu sistema? Si necesitas ayuda para configurarlo o quieres activar tu plan con descuento, aquí estamos.",
            'mensajes' => "Hola {nombre} 👋, te saludamos de TUSHPA por tu mensaje en nuestra web. ¿En qué te podemos ayudar?",
        ];
    @endphp
    <div x-data="prospectos(@js($plantillas[$tab]), @js('prospectos_msg_'.$tab))">
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-bold">Prospectos</h1>
                <p class="text-sm text-slate-500">Negocios que llegaron por tu web. Escríbeles tus ofertas por WhatsApp o correo.</p>
            </div>
            <a href="{{ route('admin.prospectos.excel', request()->query()) }}" class="ml-auto px-4 h-10 rounded-xl bg-emerald-600 text-white text-sm font-bold flex items-center gap-2">⬇ Excel</a>
        </div>

        <div class="flex flex-wrap items-center gap-2 mb-4">
            <div class="flex gap-1 p-1 bg-white rounded-xl shadow-sm text-sm">
                @foreach (\App\Http\Controllers\Admin\ProspectoController::TABS as $valor => $texto)
                    <a href="{{ route('admin.prospectos.index', ['tab' => $valor]) }}" class="px-3 py-1.5 rounded-lg font-semibold {{ $tab === $valor ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                        {{ $texto }} <span class="opacity-70">({{ $totales[$valor] }})</span></a>
                @endforeach
            </div>
            <form method="GET" class="ml-auto flex gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if ($tab === 'cartas')
                    <select name="estado" onchange="this.form.submit()" class="rounded-xl border-slate-300 text-sm">
                        <option value="">Todos</option><option value="sin" @selected($estado === 'sin')>Aún sin contactar</option><option value="con" @selected($estado === 'con')>Ya contactados</option>
                    </select>
                @endif
                <input name="q" value="{{ $q }}" placeholder="Buscar nombre, correo o celular" class="rounded-xl border-slate-300 text-sm w-60">
            </form>
        </div>

        {{-- Mensaje que se envía a cada uno ({nombre} y {enlace} se reemplazan) --}}
        <div class="bg-white rounded-2xl shadow-sm p-4 mb-4">
            <div class="flex flex-wrap items-center gap-2 mb-2">
                <p class="font-bold text-sm">📣 Tu mensaje u oferta</p>
                <span class="text-xs text-slate-400">Usa {nombre} y {enlace}. Se guarda en este navegador.</span>
                <button type="button" @click="msg = base; guardar()" class="ml-auto text-xs text-slate-500 underline">Restaurar</button>
                <button type="button" @click="copiarCorreos()" class="px-3 py-1.5 rounded-lg bg-slate-100 text-xs font-semibold" x-text="copiado ? '✔ Copiados' : '✉ Copiar los {{ $correos->count() }} correos'"></button>
            </div>
            <textarea x-model="msg" @input.debounce.500ms="guardar()" rows="3" class="w-full rounded-xl border-slate-300 text-sm"></textarea>
        </div>

        <div class="bg-white rounded-2xl shadow-sm divide-y divide-slate-100">
            @forelse ($filas as $f)
                @php $cel = preg_replace('/\D/', '', (string) $f->telefono); $cel = strlen($cel) === 9 ? '51'.$cel : $cel; @endphp
                <div class="px-4 py-3 flex flex-wrap items-center gap-3 {{ $f->contactado ? 'bg-emerald-50/40' : '' }}" x-data="{ nota: false }">
                    <div class="flex-1 min-w-[220px]">
                        <p class="font-semibold">{{ $f->nombre }}
                            @if ($f->contactado)<span class="ml-1 px-1.5 rounded bg-emerald-100 text-emerald-700 text-[10px] font-bold">CONTACTADO</span>@endif
                            @if ($f->estado === 'BLOQUEADA')<span class="ml-1 px-1.5 rounded bg-rose-100 text-rose-700 text-[10px] font-bold">BLOQUEADA</span>@endif</p>
                        <p class="text-xs text-slate-500">{{ $f->detalle }} · {{ $f->fecha ? \Carbon\Carbon::parse($f->fecha)->format('d/m/Y') : '' }}</p>
                        <p class="text-xs text-slate-400">{{ $f->email }}{{ $f->telefono ? ' · '.$f->telefono : '' }}{{ $f->nota ? ' · '.$f->nota : '' }}</p>
                    </div>
                    @if ($f->enlace)<a href="{{ $f->enlace }}" target="_blank" class="text-xs text-indigo-600 font-semibold">Ver carta</a>@endif
                    @if (strlen($cel) >= 10)
                        <a :href="'https://wa.me/{{ $cel }}?text=' + encodeURIComponent(texto(@js($f->nombre), @js($f->enlace)))" target="_blank" rel="noopener"
                           class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold">WhatsApp</a>
                    @endif
                    @if ($f->email)
                        <a :href="'mailto:{{ $f->email }}?subject=' + encodeURIComponent('TUSHPA - ' + @js($f->nombre)) + '&body=' + encodeURIComponent(texto(@js($f->nombre), @js($f->enlace)))"
                           class="px-3 py-1.5 rounded-lg bg-sky-50 text-sky-700 text-xs font-bold">Correo</a>
                    @endif
                    @if ($tab === 'cartas')
                        <button type="button" @click="nota = !nota" class="px-3 py-1.5 rounded-lg bg-slate-100 text-xs font-semibold">{{ $f->contactado ? 'Nota / quitar marca' : '✔ Contactado' }}</button>
                        <form x-show="nota" x-cloak method="POST" action="{{ route('admin.prospectos.contactado', $f->id) }}" class="w-full flex gap-2">@csrf
                            <input name="nota" value="{{ $f->nota }}" maxlength="200" placeholder="Nota: le interesó, llamar el lunes…" class="flex-1 rounded-lg border-slate-300 text-sm">
                            <button class="px-3 rounded-lg bg-emerald-600 text-white text-xs font-bold">Guardar como contactado</button>
                            @if ($f->contactado)<button name="quitar" value="1" class="px-3 rounded-lg bg-slate-100 text-xs font-semibold">Quitar marca</button>@endif
                        </form>
                    @endif
                </div>
            @empty
                <p class="px-4 py-10 text-center text-slate-400">Aún no hay prospectos aquí.</p>
            @endforelse
        </div>
    </div>

    <script>
        function prospectos(base, clave) {
            let guardado = null;
            try { guardado = localStorage.getItem(clave); } catch (e) {}
            return {
                base, msg: guardado || base, copiado: false,
                guardar() { try { localStorage.setItem(clave, this.msg); } catch (e) {} },
                texto(nombre, enlace) { return this.msg.replaceAll('{nombre}', nombre || '').replaceAll('{enlace}', enlace || ''); },
                copiarCorreos() { navigator.clipboard.writeText(@js($correos->implode(', '))); this.copiado = true; setTimeout(() => this.copiado = false, 2000); },
            };
        }
    </script>
@endsection
