@extends('admin.layout')
@section('title', 'Agenda')

@section('content')
    @use('App\Support\AgendaPanel')
    @php
        $porDia = $eventos->groupBy(fn ($e) => substr($e->inicio, 0, 10));
        $hoy = now()->toDateString();
        $color = fn ($e) => AgendaPanel::COLORES[$e->color] ?? AgendaPanel::COLORES['indigo'];
        $hora = fn ($e) => $e->todo_el_dia ? 'Todo el día' : \Carbon\Carbon::parse($e->inicio)->format('g:i a');
        $datosEvento = fn ($e) => ['id' => $e->id, 'titulo' => $e->titulo, 'detalle' => $e->detalle, 'fecha' => substr($e->inicio, 0, 10),
            'hora' => substr($e->inicio, 11, 5), 'todo_el_dia' => (bool) $e->todo_el_dia, 'general' => (bool) $e->general, 'color' => $e->color,
            'avisos' => $e->avisos, 'autor' => $e->autor, 'puede' => AgendaPanel::puedeEditar($yo, $e)];
    @endphp

    <div x-data="agendaPanel(@js((bool) $yo->telegram_chat_id))" class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-56">
                <h1 class="text-xl font-bold">📅 Agenda</h1>
                <p class="text-sm text-slate-500">La tuya y la general de {{ $marca }} (la ven todos los usuarios de {{ $marca }}). Te avisa por Telegram antes de cada evento.</p>
            </div>
            <button type="button" @click="nuevo('{{ $hoy }}')" class="h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">+ Nuevo evento</button>
        </div>

        {{-- Telegram de este usuario --}}
        <div class="rounded-2xl bg-white shadow-sm p-4 flex flex-wrap items-center gap-3 text-sm">
            @if (! $telegram)
                <p class="text-amber-700">Telegram no está configurado en el servidor: los eventos se guardan, pero no avisan.</p>
            @else
                <template x-if="vinculado">
                    <p class="flex-1">✅ <b>Tu Telegram está vinculado</b>: ahí te llegan los avisos.
                        <button type="button" @click="probar()" class="ml-2 underline text-sky-700">probar</button></p>
                </template>
                <template x-if="vinculado">
                    <form method="POST" action="{{ route('admin.agenda.telegram.quitar') }}" onsubmit="return confirm('¿Dejar de recibir los avisos por Telegram?')">@csrf
                        <button class="text-slate-400 underline">quitar</button></form>
                </template>
                <template x-if="!vinculado">
                    <div class="flex-1 flex flex-wrap items-center gap-2">
                        <span>📲 Para recibir los avisos, vincula tu Telegram:</span>
                        <button type="button" @click="vincular()" class="h-9 px-3 rounded-lg bg-sky-600 text-white font-bold">Vincular mi Telegram</button>
                        <template x-if="enlace">
                            <span class="text-xs text-slate-600">Abre <a :href="enlace" target="_blank" class="font-semibold text-sky-700 underline break-all" x-text="enlace"></a> y toca <b>Iniciar</b>…</span>
                        </template>
                    </div>
                </template>
            @endif
        </div>

        <div class="grid lg:grid-cols-12 gap-4">
            {{-- Mes --}}
            <div class="lg:col-span-9 rounded-2xl bg-white shadow-sm p-4">
                <div class="flex items-center justify-between mb-3">
                    <a href="{{ route('admin.agenda.index', ['mes' => $mes->copy()->subMonth()->format('Y-m')]) }}" class="h-9 px-3 inline-flex items-center rounded-lg bg-slate-100 font-bold">←</a>
                    <p class="font-bold text-lg">{{ ucfirst($mes->locale('es')->isoFormat('MMMM YYYY')) }}
                        @unless ($mes->isSameMonth(now()))<a href="{{ route('admin.agenda.index') }}" class="ml-2 text-xs font-semibold text-indigo-600">Hoy</a>@endunless</p>
                    <a href="{{ route('admin.agenda.index', ['mes' => $mes->copy()->addMonth()->format('Y-m')]) }}" class="h-9 px-3 inline-flex items-center rounded-lg bg-slate-100 font-bold">→</a>
                </div>
                <div class="grid grid-cols-7 text-center text-[11px] font-bold uppercase text-slate-400 mb-1">
                    @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $d)<div>{{ $d }}</div>@endforeach
                </div>
                <div class="grid grid-cols-7 gap-1">
                    @for ($dia = $desde->copy(); $dia->lte($hasta); $dia->addDay())
                        @php $fecha = $dia->toDateString(); $delDia = $porDia[$fecha] ?? collect(); @endphp
                        <div @click.self="nuevo('{{ $fecha }}')" class="min-h-[86px] rounded-lg p-1 text-left cursor-pointer ring-1
                            {{ $fecha === $hoy ? 'ring-indigo-400 bg-indigo-50/60' : 'ring-slate-100 hover:bg-slate-50' }} {{ $dia->month !== $mes->month ? 'opacity-40' : '' }}">
                            <p class="text-xs font-bold {{ $fecha === $hoy ? 'text-indigo-700' : 'text-slate-500' }}" @click="nuevo('{{ $fecha }}')">{{ $dia->day }}</p>
                            @foreach ($delDia->take(3) as $e)
                                <button type="button" @click="abrir(@js($datosEvento($e)))" class="mt-0.5 w-full truncate rounded px-1 py-0.5 text-left text-[11px] font-semibold text-white"
                                        style="background: {{ $color($e) }}" title="{{ $e->titulo }}">{{ $e->todo_el_dia ? '' : \Carbon\Carbon::parse($e->inicio)->format('H:i').' ' }}{{ $e->general ? '👥 ' : '' }}{{ $e->titulo }}</button>
                            @endforeach
                            @if ($delDia->count() > 3)<p class="text-[10px] text-slate-500">+{{ $delDia->count() - 3 }} más</p>@endif
                        </div>
                    @endfor
                </div>
                <p class="mt-2 text-xs text-slate-400">Toca un día para agregar un evento. 👥 = agenda general de {{ $marca }}.</p>
            </div>

            {{-- Próximos --}}
            <div class="lg:col-span-3 rounded-2xl bg-white shadow-sm p-4">
                <p class="font-bold mb-2">Próximos 14 días</p>
                <div class="space-y-2">
                    @forelse ($proximos as $e)
                        <button type="button" @click="abrir(@js($datosEvento($e)))" class="w-full text-left rounded-xl p-2 ring-1 ring-slate-100 hover:bg-slate-50">
                            <p class="text-sm font-semibold"><span class="inline-block h-2 w-2 rounded-full mr-1" style="background: {{ $color($e) }}"></span>{{ $e->general ? '👥 ' : '' }}{{ $e->titulo }}</p>
                            <p class="text-xs text-slate-500">{{ ucfirst(\Carbon\Carbon::parse($e->inicio)->locale('es')->isoFormat('ddd D MMM')) }} · {{ $hora($e) }}</p>
                        </button>
                    @empty
                        <p class="text-sm text-slate-400">Nada en los próximos días.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Evento: nuevo o editar --}}
        <div x-show="f" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 p-2" @keydown.escape.window="f = null">
            <form x-show="f" method="POST" :action="f && f.id ? @js(url(route('admin.agenda.guardar'))) + '/' + f.id : @js(route('admin.agenda.guardar'))"
                  @click.outside="f = null" class="w-full max-w-lg rounded-2xl bg-white p-5 space-y-3 max-h-[92vh] overflow-y-auto">
                @csrf
                <template x-if="f">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="font-bold text-lg" x-text="f.id ? (f.puede ? 'Editar evento' : 'Evento') : 'Nuevo evento'"></p>
                            <button type="button" @click="f = null" class="text-slate-400 text-xl">✕</button>
                        </div>
                        <p x-show="f.id && f.autor" class="text-xs text-slate-500">Creado por <b x-text="f.autor"></b></p>
                        <fieldset :disabled="f.id && !f.puede" class="space-y-3">
                            <input name="titulo" x-model="f.titulo" required maxlength="150" placeholder="¿Qué es? (ej. Reunión con cliente, pagar SUNAT…)" class="w-full h-11 rounded-xl border-slate-300">
                            <div class="grid grid-cols-2 gap-2">
                                <label class="text-xs font-semibold text-slate-500">Fecha<input type="date" name="fecha" x-model="f.fecha" required class="mt-1 w-full h-10 rounded-xl border-slate-300"></label>
                                <label class="text-xs font-semibold text-slate-500" x-show="!f.todo_el_dia">Hora<input type="time" name="hora" x-model="f.hora" class="mt-1 w-full h-10 rounded-xl border-slate-300"></label>
                            </div>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="f.todo_el_dia" class="rounded"> Todo el día</label>
                            <input type="hidden" name="todo_el_dia" :value="f.todo_el_dia ? 1 : 0">
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <label class="flex items-center gap-2 rounded-xl p-2 ring-1 cursor-pointer" :class="!f.general ? 'ring-indigo-400 bg-indigo-50' : 'ring-slate-200'">
                                    <input type="radio" x-model="f.general" :value="false" @change="f.general = false"> 🔒 Solo para mí</label>
                                <label class="flex items-center gap-2 rounded-xl p-2 ring-1 cursor-pointer" :class="f.general ? 'ring-indigo-400 bg-indigo-50' : 'ring-slate-200'">
                                    <input type="radio" x-model="f.general" :value="true" @change="f.general = true"> 👥 General ({{ $marca }})</label>
                            </div>
                            <input type="hidden" name="general" :value="f.general ? 1 : 0">
                            <div>
                                <p class="text-xs font-semibold text-slate-500 mb-1">Avisarme por Telegram</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach (AgendaPanel::AVISOS as $min => $texto)
                                        <label class="text-xs rounded-full px-2.5 py-1 ring-1 cursor-pointer" :class="f.avisos.includes({{ $min }}) ? 'bg-sky-600 text-white ring-sky-600' : 'ring-slate-200'">
                                            <input type="checkbox" name="avisos[]" value="{{ $min }}" class="hidden" :checked="f.avisos.includes({{ $min }})"
                                                   @change="f.avisos.includes({{ $min }}) ? f.avisos = f.avisos.filter(x => x !== {{ $min }}) : f.avisos.push({{ $min }})"> {{ $texto }}</label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex gap-1.5">
                                @foreach (AgendaPanel::COLORES as $nombre => $hex)
                                    <label class="h-7 w-7 rounded-full cursor-pointer ring-2" style="background: {{ $hex }}" :class="f.color === '{{ $nombre }}' ? 'ring-slate-800' : 'ring-transparent'">
                                        <input type="radio" name="color" value="{{ $nombre }}" x-model="f.color" class="hidden"></label>
                                @endforeach
                            </div>
                            <textarea name="detalle" x-model="f.detalle" rows="3" maxlength="1000" placeholder="Detalle (opcional): dirección, enlace de la reunión, qué llevar…" class="w-full rounded-xl border-slate-300 text-sm"></textarea>
                        </fieldset>
                        <div class="flex justify-between gap-2 pt-1" x-show="!f.id || f.puede">
                            <button type="button" x-show="f.id" @click="eliminar(f.id)" class="h-10 px-4 rounded-xl text-rose-600 font-semibold">Eliminar</button>
                            <button class="ml-auto h-10 px-5 rounded-xl bg-indigo-600 text-white font-bold">Guardar</button>
                        </div>
                    </div>
                </template>
            </form>
        </div>
        <form x-ref="borrar" method="POST" class="hidden">@csrf</form>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('agendaPanel', (vinculado) => ({
                f: null, vinculado, enlace: null,
                nuevo(fecha) { this.f = { id: null, titulo: '', detalle: '', fecha, hora: '09:00', todo_el_dia: false, general: false, color: 'indigo', avisos: [60], puede: true }; },
                abrir(e) { this.f = { ...e, avisos: [...e.avisos] }; },
                eliminar(id) {
                    if (!confirm('¿Eliminar este evento?')) return;
                    this.$refs.borrar.action = @js(url(route('admin.agenda.index'))) + '/' + id + '/eliminar';
                    this.$refs.borrar.submit();
                },
                async pedir(url) {
                    const r = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) } });
                    return r.json().catch(() => ({ ok: false, mensaje: 'Error del servidor.' }));
                },
                async vincular() {
                    const r = await this.pedir(@js(route('admin.agenda.telegram.enlace')));
                    if (!r.ok) { alert(r.mensaje); return; }
                    this.enlace = r.enlace;
                    window.open(r.enlace, '_blank');
                    for (let i = 0; i < 45 && !this.vinculado; i++) {
                        await new Promise(ok => setTimeout(ok, 4000));
                        this.vinculado = (await this.pedir(@js(route('admin.agenda.telegram.verificar')))).vinculado;
                    }
                },
                async probar() { const r = await this.pedir(@js(route('admin.agenda.telegram.probar'))); alert(r.mensaje); },
            }));
        });
    </script>
@endsection
