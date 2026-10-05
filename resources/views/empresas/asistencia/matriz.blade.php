@extends('layouts.app')
@section('title', 'Matriz de Turnos')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')

    @php
        $listaTurnos = $turnos->map(fn($t) => ['id' => $t->id, 'codigo' => $t->codigo, 'descripcion' => $t->descripcion, 'color' => $t->color,
            'horas' => $t->hora_entrada_1 ? substr($t->hora_entrada_1, 0, 5) . '–' . substr($t->hora_salida_2 ?: $t->hora_salida_1, 0, 5) : ''])->values();
        $inicial = [];
        foreach ($empleados as $e) {
            foreach ($dias as $d) {
                $inicial[$e->emp_id][$d->toDateString()] = (string) ($asignados[$e->emp_id][$d->toDateString()] ?? '');
            }
        }
        $semAnt = $inicio->copy()->subWeek()->toDateString();
        $semSig = $inicio->copy()->addWeek()->toDateString();
    @endphp

    <div x-data="matriz(@js($listaTurnos), @js($inicial))">
        {{-- Semana --}}
        <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-4">
            <div class="flex items-center gap-2">
                <a href="{{ route('asistencia.matriz', ['semana' => $semAnt]) }}" class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center hover:bg-gray-50"><i class="fas fa-chevron-left"></i></a>
                <div class="bg-white rounded-xl shadow-sm px-4 py-2 text-center min-w-[220px]">
                    <p class="text-[10px] uppercase text-gray-400 font-bold">Semana</p>
                    <p class="font-bold text-gray-800">{{ $inicio->format('d/m') }} al {{ $inicio->copy()->addDays(6)->format('d/m/Y') }}</p>
                </div>
                <a href="{{ route('asistencia.matriz', ['semana' => $semSig]) }}" class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center hover:bg-gray-50"><i class="fas fa-chevron-right"></i></a>
                <a href="{{ route('asistencia.matriz') }}" class="px-3 py-2 rounded-xl text-sm font-semibold text-indigo-600 hover:bg-indigo-50">Esta semana</a>
            </div>
            <div class="flex flex-wrap gap-2 lg:ml-auto">
                <form method="POST" action="{{ route('asistencia.matriz.copiar') }}" onsubmit="return confirm('Se copiarán los turnos de la semana anterior en los días que estén vacíos. ¿Continuar?')">
                    @csrf <input type="hidden" name="semana" value="{{ $inicio->toDateString() }}">
                    <button class="px-3 py-2 rounded-xl bg-white shadow-sm text-sm font-semibold text-gray-700 hover:bg-gray-50"><i class="fas fa-copy"></i> Copiar semana anterior</button>
                </form>
                <a href="{{ route('asistencia.matriz', ['semana' => $inicio->toDateString(), 'excel' => 1]) }}" class="px-3 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700"><i class="fas fa-file-excel"></i> Excel</a>
                <a href="{{ route('asistencia.turnos') }}" class="px-3 py-2 rounded-xl bg-white shadow-sm text-sm font-semibold text-gray-700 hover:bg-gray-50"><i class="fas fa-gear"></i> Turnos</a>
            </div>
        </div>

        {{-- Pincel --}}
        <div class="bg-white rounded-2xl shadow-sm p-3 mb-4">
            <p class="text-xs text-gray-500 mb-2"><i class="fas fa-paintbrush"></i> <strong>Pincel:</strong> elige un turno y toca las celdas (o el nombre del día / del trabajador para llenar toda la columna o fila).</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="pincel = null" :class="pincel === null ? 'ring-2 ring-offset-1 ring-gray-800' : ''" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-sm font-semibold">Sin pincel</button>
                <template x-for="t in turnos" :key="t.id">
                    <button type="button" @click="pincel = String(t.id)" :class="pincel === String(t.id) ? 'ring-2 ring-offset-1 ring-gray-800' : ''"
                            class="px-3 py-1.5 rounded-lg text-white text-sm font-semibold" :style="`background:${t.color}`" :title="t.descripcion">
                        <span x-text="t.codigo"></span> <span class="opacity-80 text-xs" x-text="t.horas"></span></button>
                </template>
                <button type="button" @click="pincel = ''" :class="pincel === '' ? 'ring-2 ring-offset-1 ring-gray-800' : ''" class="px-3 py-1.5 rounded-lg border-2 border-dashed border-gray-300 text-gray-500 text-sm font-semibold"><i class="fas fa-eraser"></i> Borrar</button>
            </div>
        </div>

        <form method="POST" action="{{ route('asistencia.matriz.guardar') }}">
            @csrf <input type="hidden" name="semana" value="{{ $inicio->toDateString() }}">
            <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-700 text-white">
                            <th class="sticky left-0 z-10 bg-slate-700 px-3 py-2 text-left min-w-[200px]">Trabajador</th>
                            @foreach ($dias as $d)
                                @php $f = $d->toDateString(); @endphp
                                <th class="px-1 py-2 min-w-[110px] {{ $d->isToday() ? 'bg-indigo-600' : '' }}">
                                    <button type="button" @click="columna('{{ $f }}')" class="w-full hover:underline" title="Aplicar el pincel a todo el día">
                                        <span class="block text-xs font-normal opacity-80">{{ $nombresDias[$d->dayOfWeekIso - 1] }}</span>{{ $d->format('d/m') }}
                                    </button>
                                    @if (isset($feriados[$f]))<span class="block text-[10px] font-semibold text-amber-300 truncate" title="{{ $feriados[$f] }}">★ {{ $feriados[$f] }}</span>@endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($empleados as $e)
                            <tr class="hover:bg-gray-50/50">
                                <td class="sticky left-0 z-10 bg-white px-3 py-2">
                                    <button type="button" @click="fila({{ $e->emp_id }})" class="text-left hover:underline" title="Aplicar el pincel a toda la semana">
                                        <span class="font-semibold text-gray-800 uppercase">{{ $e->emp_nom }}</span>
                                        <span class="block text-xs text-gray-400 uppercase">{{ $e->emp_ape_pat }} · {{ $e->emp_num_doc }}</span>
                                    </button>
                                </td>
                                @foreach ($dias as $d)
                                    @php $f = $d->toDateString(); @endphp
                                    <td class="px-1 py-1.5" @click="pintar({{ $e->emp_id }}, '{{ $f }}')">
                                        <select name="horario[{{ $e->emp_id }}][{{ $f }}]" x-model="asig[{{ $e->emp_id }}]['{{ $f }}']"
                                                :class="pincel !== null ? 'pointer-events-none' : ''" :style="estilo(asig[{{ $e->emp_id }}]['{{ $f }}'])"
                                                class="w-full rounded-lg border-gray-200 text-xs font-bold py-1.5">
                                            <option value="">—</option>
                                            <template x-for="t in turnos" :key="t.id"><option :value="String(t.id)" x-text="t.codigo + (t.horas ? ' ' + t.horas : '')"></option></template>
                                        </select>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No hay trabajadores con asistencia activa. Actívala en <a href="{{ route('usuarios.index') }}" class="text-indigo-600 underline">Usuarios</a>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 mt-4">
                <p class="text-xs text-gray-400">“—” = sin horario: el trabajador puede marcar libremente ese día y no se calcula tardanza.</p>
                <button class="w-full sm:w-auto px-8 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700"><i class="fas fa-floppy-disk"></i> Guardar semana</button>
            </div>
        </form>
    </div>

    <script>
        function matriz(turnos, asig) {
            const porId = Object.fromEntries(turnos.map(t => [String(t.id), t]));
            return {
                turnos, asig, pincel: null,
                estilo(id) { const t = porId[id]; return t ? `background:${t.color};color:#fff;border-color:${t.color}` : ''; },
                pintar(emp, fecha) { if (this.pincel !== null) this.asig[emp][fecha] = this.pincel; },
                fila(emp) { if (this.pincel === null) return alert('Primero elige un turno en el pincel.'); Object.keys(this.asig[emp]).forEach(f => this.asig[emp][f] = this.pincel); },
                columna(fecha) { if (this.pincel === null) return alert('Primero elige un turno en el pincel.'); Object.keys(this.asig).forEach(e => this.asig[e][fecha] = this.pincel); },
            };
        }
    </script>
@endsection
