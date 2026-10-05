@extends('layouts.app')
@section('title', 'Gestionar Turnos')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')
    @if (session('aviso'))<div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2 text-sm">{{ session('aviso') }}</div>@endif

    @php
        $tipos = ['TRABAJO' => ['Trabajo', 'fa-briefcase', 'Tiene horario de entrada y salida.'], 'DESCANSO' => ['Descanso', 'fa-mug-hot', 'Día libre. Marcar requiere autorización.'],
                  'LEYENDA' => ['Licencia / leyenda', 'fa-umbrella-beach', 'Vacaciones, descanso médico, licencia… Sale con su código en el tareo.']];
        $hora = fn($h) => $h ? substr($h, 0, 5) : null;
    @endphp

    <div x-data="{ form: null,
        nuevo() { this.form = { id: null, codigo: '', descripcion: '', tipo: 'TRABAJO', hora_entrada_1: '08:00', hora_salida_1: '13:00', hora_entrada_2: '', hora_salida_2: '', tolerancia_minutos: 10, color: '#2563eb' } },
        minutos() {
            if (!this.form || this.form.tipo !== 'TRABAJO') return 0;
            const m = (a, b) => { if (!a || !b) return 0; let [h1, m1] = a.split(':').map(Number), [h2, m2] = b.split(':').map(Number); let d = (h2 * 60 + m2) - (h1 * 60 + m1); return d <= 0 ? d + 1440 : d; };
            return m(this.form.hora_entrada_1, this.form.hora_salida_1) + m(this.form.hora_entrada_2, this.form.hora_salida_2);
        } }">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <p class="text-sm text-gray-500">Los turnos se asignan a cada trabajador en la <a href="{{ route('asistencia.matriz') }}" class="text-indigo-600 font-semibold hover:underline">Matriz de turnos</a>.</p>
            <button type="button" @click="nuevo()" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700"><i class="fas fa-plus"></i> Nuevo turno</button>
        </div>

        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($turnos as $t)
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden flex flex-col">
                    <div class="h-1.5" style="background: {{ $t->color }}"></div>
                    <div class="p-4 flex-1">
                        <div class="flex items-start gap-3">
                            <span class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-black text-lg shrink-0" style="background: {{ $t->color }}">{{ $t->codigo }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-gray-800 truncate">{{ $t->descripcion }}</p>
                                <p class="text-xs text-gray-500"><i class="fas {{ $tipos[$t->tipo][1] }}"></i> {{ $tipos[$t->tipo][0] }}</p>
                            </div>
                        </div>
                        @if ($t->tipo === 'TRABAJO')
                            <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                                <div class="rounded-xl bg-gray-50 px-3 py-2"><p class="text-[10px] uppercase text-gray-400">Bloque 1</p>
                                    <p class="font-bold font-mono">{{ $hora($t->hora_entrada_1) }} – {{ $hora($t->hora_salida_1) }}</p></div>
                                <div class="rounded-xl bg-gray-50 px-3 py-2"><p class="text-[10px] uppercase text-gray-400">Bloque 2</p>
                                    <p class="font-bold font-mono">{!! $t->hora_entrada_2 ? $hora($t->hora_entrada_2) . ' – ' . $hora($t->hora_salida_2) : '<span class="text-gray-400 font-normal font-sans">Corrido</span>' !!}</p></div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500"><i class="fas fa-clock"></i> Tolerancia: <strong>{{ $t->tolerancia_minutos }} min</strong></p>
                        @else
                            <p class="mt-3 text-xs text-gray-500">{{ $tipos[$t->tipo][2] }}</p>
                        @endif
                    </div>
                    <div class="px-4 py-2 border-t border-gray-100 flex justify-end gap-3 text-sm">
                        <button type="button" class="text-indigo-600 hover:underline"
                                @click="form = @js(['id' => $t->id, 'codigo' => $t->codigo, 'descripcion' => $t->descripcion, 'tipo' => $t->tipo,
                                    'hora_entrada_1' => $hora($t->hora_entrada_1) ?? '', 'hora_salida_1' => $hora($t->hora_salida_1) ?? '',
                                    'hora_entrada_2' => $hora($t->hora_entrada_2) ?? '', 'hora_salida_2' => $hora($t->hora_salida_2) ?? '',
                                    'tolerancia_minutos' => $t->tolerancia_minutos, 'color' => $t->color])">Editar</button>
                        @unless (isset($enUso[$t->id]))
                            <form method="POST" action="{{ route('asistencia.turnos.eliminar', $t->id) }}" onsubmit="return confirm('¿Eliminar este turno?')">
                                @csrf @method('DELETE')<button class="text-rose-600 hover:underline">Eliminar</button>
                            </form>
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Modal --}}
        <template x-if="form">
            <div class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="form = null" @keydown.escape.window="form = null">
                <form method="POST" :action="form.id ? '{{ url('asistencia/turnos') }}/' + form.id : '{{ route('asistencia.turnos.guardar') }}'"
                      class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[95vh] overflow-y-auto">
                    @csrf
                    <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>
                    <div class="px-5 py-4 border-b flex justify-between items-center">
                        <h3 class="font-bold text-gray-800" x-text="form.id ? 'Editar turno' : 'Nuevo turno'"></h3>
                        <button type="button" @click="form = null" class="text-gray-400 text-2xl leading-none">&times;</button>
                    </div>
                    <div class="p-5 space-y-4 text-sm">
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ($tipos as $v => [$nom, $ico])
                                <label class="cursor-pointer rounded-xl border-2 p-2 text-center" :class="form.tipo === '{{ $v }}' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-500'">
                                    <input type="radio" name="tipo" value="{{ $v }}" x-model="form.tipo" class="sr-only">
                                    <i class="fas {{ $ico }} text-lg"></i><span class="block text-xs font-bold mt-1">{{ $nom }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-4 gap-3">
                            <label>Código<input name="codigo" x-model="form.codigo" maxlength="5" required class="block w-full mt-1 rounded-lg border-gray-300 uppercase font-bold text-center"></label>
                            <label class="col-span-2">Descripción<input name="descripcion" x-model="form.descripcion" maxlength="100" required class="block w-full mt-1 rounded-lg border-gray-300 uppercase"></label>
                            <label>Color<input type="color" name="color" x-model="form.color" class="block w-full h-[38px] mt-1 rounded-lg border-gray-300"></label>
                        </div>
                        <template x-if="form.tipo === 'TRABAJO'">
                            <div class="space-y-3">
                                <div class="rounded-xl border border-gray-200 p-3">
                                    <p class="text-xs font-bold text-gray-500 uppercase mb-2">Bloque 1</p>
                                    <div class="grid grid-cols-2 gap-3">
                                        <label>Entrada<input type="time" name="hora_entrada_1" x-model="form.hora_entrada_1" required class="block w-full mt-1 rounded-lg border-gray-300"></label>
                                        <label>Salida<input type="time" name="hora_salida_1" x-model="form.hora_salida_1" required class="block w-full mt-1 rounded-lg border-gray-300"></label>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-gray-200 p-3">
                                    <p class="text-xs font-bold text-gray-500 uppercase mb-2">Bloque 2 (después del refrigerio) — opcional</p>
                                    <div class="grid grid-cols-2 gap-3">
                                        <label>Retorno<input type="time" name="hora_entrada_2" x-model="form.hora_entrada_2" class="block w-full mt-1 rounded-lg border-gray-300"></label>
                                        <label>Salida final<input type="time" name="hora_salida_2" x-model="form.hora_salida_2" class="block w-full mt-1 rounded-lg border-gray-300"></label>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">Déjalo vacío si el turno es corrido (sin refrigerio).</p>
                                </div>
                                <div class="grid grid-cols-2 gap-3 items-end">
                                    <label>Tolerancia (minutos)<input type="number" name="tolerancia_minutos" x-model="form.tolerancia_minutos" min="0" max="120" class="block w-full mt-1 rounded-lg border-gray-300"></label>
                                    <p class="rounded-xl px-3 py-2 text-center font-bold" :class="minutos() >= 480 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                                       x-text="'Total: ' + Math.floor(minutos() / 60) + 'h ' + String(minutos() % 60).padStart(2, '0') + 'm'"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="px-5 py-3 border-t flex justify-end gap-2">
                        <button type="button" @click="form = null" class="px-4 py-2 rounded-xl bg-gray-100 font-semibold text-gray-700">Cancelar</button>
                        <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700">Guardar</button>
                    </div>
                </form>
            </div>
        </template>
    </div>
@endsection
