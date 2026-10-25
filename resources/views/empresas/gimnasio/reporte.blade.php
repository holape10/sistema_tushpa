@extends('layouts.app')
@section('title', 'Reporte del Gimnasio')
@section('content')
    @php
        $s = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $hoyIso = now()->toDateString();
        $rangos = [
            'Hoy' => [$hoyIso, $hoyIso],
            'Últimos 7 días' => [now()->subDays(6)->toDateString(), $hoyIso],
            'Este mes' => [now()->startOfMonth()->toDateString(), $hoyIso],
            'Mes pasado' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        ];
        $fecha = fn ($f) => $f ? \Carbon\Carbon::parse($f)->format('d/m/Y') : '';
        // WhatsApp para recuperar al cliente (celular de 9 dígitos de Perú)
        $wa = function ($c, string $tipo) use ($negocio, $fecha) {
            $cel = preg_replace('/\D/', '', (string) $c->telefono);
            if (strlen($cel) < 9) {
                return null;
            }
            $nombre = explode(' ', trim($c->clinom))[0];
            $texto = $tipo === 'vence'
                ? "Hola {$nombre}, te saludamos de {$negocio}. Tu plan {$c->plan} vence el {$fecha($c->vence)}. ¡Renuévalo y sigue entrenando con nosotros! 💪"
                : "Hola {$nombre}, te saludamos de {$negocio}. Te extrañamos: tu plan venció el {$fecha($c->vence)}. ¡Vuelve a entrenar! Renueva en recepción. 💪";

            return 'https://wa.me/'.(strlen($cel) === 9 ? '51'.$cel : $cel).'?text='.rawurlencode($texto);
        };
        $max = fn ($c, $campo) => max(1, (float) collect($c)->max($campo));
    @endphp
    <style>@media print { aside, header, nav, .no-print { display: none !important; } main { padding: 0 !important; } section { break-inside: avoid; } }</style>

    <div class="max-w-6xl space-y-5">
        {{-- Portada --}}
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-orange-600 via-orange-500 to-amber-400 text-white shadow-lg">
            <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-white/10"></div>
            <div class="relative p-6 sm:p-8">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="flex-1 min-w-[240px]">
                        <a href="{{ route('gimnasio.index') }}" class="no-print text-sm text-orange-100 hover:text-white">← Gimnasio</a>
                        <p class="text-orange-100 text-sm font-semibold tracking-wide uppercase mt-1">🏋️ Reporte del gimnasio</p>
                        <h1 class="text-2xl sm:text-3xl font-black mt-1">{{ $negocio }}</h1>
                        <p class="text-orange-50 text-sm mt-1">{{ $desde === $hasta ? $fecha($desde) : $fecha($desde).' al '.$fecha($hasta) }}</p>
                    </div>
                    <button type="button" onclick="window.print()" class="no-print px-3 py-2 rounded-xl bg-white/15 hover:bg-white/25 text-sm font-semibold">🖨 Imprimir</button>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 mt-6">
                    @foreach ([['Ventas de planes', $s($totalVentas), $cantidadVentas.' venta(s)'], ['Clientes nuevos', $nuevas, $s($montoNuevas)],
                               ['Renovaciones', $renovaciones, $s($montoRenovaciones)], ['Rutinas del día', $rutinas, $s($montoRutinas)],
                               ['Personas que vinieron', $personasUnicas, $asistencias.' ingreso(s)'], ['Promedio por día', $promedioDiario, 'personas']] as [$t, $v, $sub])
                        <div class="rounded-2xl bg-white/15 backdrop-blur px-4 py-3 ring-1 ring-white/20">
                            <p class="text-[11px] text-orange-50">{{ $t }}</p>
                            <p class="text-2xl font-black">{{ $v }}</p>
                            <p class="text-[11px] text-orange-50">{{ $sub }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Filtros --}}
        <section class="no-print bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-3">
            <div class="flex flex-wrap gap-2">
                @foreach ($rangos as $nombre => [$d, $h])
                    <a href="{{ route('gimnasio.reporte', ['desde' => $d, 'hasta' => $h]) }}"
                       class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $desde === $d && $hasta === $h ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ $nombre }}</a>
                @endforeach
            </div>
            <form class="flex flex-wrap items-end gap-2 text-sm ml-auto">
                <label>Desde<input type="date" name="desde" value="{{ $desde }}" class="block rounded-lg border-gray-300 text-sm"></label>
                <label>Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block rounded-lg border-gray-300 text-sm"></label>
                <button class="h-10 px-4 rounded-lg bg-orange-500 text-white font-semibold">Ver</button>
            </form>
        </section>

        {{-- Situación de hoy --}}
        <section class="bg-white rounded-2xl shadow-sm p-5">
            <h2 class="font-bold text-gray-800 mb-3">📌 Clientes activos hoy</h2>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                @foreach (['VIGENTE' => ['Vigentes', 'text-emerald-600'], 'POR_VENCER' => ['Por vencer', 'text-amber-500'], 'CONGELADO' => ['Congelados', 'text-sky-500'],
                           'VENCIDO' => ['Vencidos', 'text-rose-600'], 'SIN_PLAN' => ['Sin plan', 'text-gray-500']] as $k => [$t, $color])
                    <div class="rounded-xl bg-gray-50 px-4 py-3"><p class="text-xs text-gray-500">{{ $t }}</p><p class="text-3xl font-black {{ $color }}">{{ $situacion[$k] ?? 0 }}</p></div>
                @endforeach
            </div>
        </section>

        {{-- Ventas --}}
        <div class="grid lg:grid-cols-3 gap-5">
            <section class="bg-white rounded-2xl shadow-sm p-5 lg:col-span-2">
                <h2 class="font-bold text-gray-800 mb-3">💳 Ventas por plan</h2>
                @forelse ($porPlan as $p)
                    <div class="mb-2">
                        <div class="flex justify-between text-sm"><span class="font-semibold">{{ $p->nombre }} <span class="text-gray-400 font-normal">· {{ $p->cantidad }}</span></span><span class="font-bold">{{ $s($p->monto) }}</span></div>
                        <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden"><div class="h-full bg-orange-500 rounded-full" style="width: {{ $p->monto / $max($porPlan, 'monto') * 100 }}%"></div></div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Sin ventas en el periodo.</p>
                @endforelse
            </section>
            <section class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
                <div>
                    <h2 class="font-bold text-gray-800 mb-2">💵 Por medio de pago</h2>
                    @forelse ($porMedio as $m)
                        <div class="flex justify-between text-sm py-1 border-b last:border-0"><span>{{ $m->nombre ?: '—' }}</span><span class="font-bold">{{ $s($m->monto) }}</span></div>
                    @empty
                        <p class="text-sm text-gray-400">—</p>
                    @endforelse
                </div>
                <div>
                    <h2 class="font-bold text-gray-800 mb-2">👤 Por cajero</h2>
                    @forelse ($porCajero as $c)
                        <div class="flex justify-between text-sm py-1 border-b last:border-0"><span>{{ $c->nombre }} <span class="text-gray-400">· {{ $c->cantidad }}</span></span><span class="font-bold">{{ $s($c->monto) }}</span></div>
                    @empty
                        <p class="text-sm text-gray-400">—</p>
                    @endforelse
                </div>
            </section>
        </div>

        {{-- Asistencia --}}
        <section class="bg-white rounded-2xl shadow-sm p-5">
            <h2 class="font-bold text-gray-800 mb-3 flex items-center justify-between">📈 Personas por día
                <span class="text-xs font-normal text-gray-400">Toca un día para ver quiénes entraron</span></h2>
            @php $maxDia = $max($porDia, 'personas'); @endphp
            <div class="flex items-end gap-1 h-40 overflow-x-auto">
                @foreach ($porDia as $d)
                    @php $c = \Carbon\Carbon::parse($d->dia); @endphp
                    <a href="{{ route('gimnasio.asistencia', ['fecha' => $c->toDateString()]) }}" class="flex-1 min-w-[14px] flex flex-col items-center justify-end h-full hover:opacity-70" title="{{ $c->format('d/m') }}: {{ $d->personas }} persona(s) · ver quiénes entraron">
                        <span class="text-[9px] text-gray-500">{{ $d->personas ?: '' }}</span>
                        <div class="w-full rounded-t {{ $c->isWeekend() ? 'bg-orange-300' : 'bg-orange-500' }}" style="height: {{ max(2, $d->personas / $maxDia * 100) }}%"></div>
                        <span class="text-[9px] text-gray-400 mt-0.5">{{ $c->format('d') }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <div class="grid lg:grid-cols-2 gap-5">
            <section class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-bold text-gray-800 mb-1">⏰ Horas pico</h2>
                <p class="text-xs text-gray-500 mb-3">Para saber cuándo reforzar con entrenadores y recepción.</p>
                @php $maxHora = $max($porHora, 'ingresos'); @endphp
                @foreach ($porHora as $h)
                    <div class="flex items-center gap-2 text-xs mb-1">
                        <span class="w-12 text-gray-500">{{ str_pad($h->hora, 2, '0', STR_PAD_LEFT) }}:00</span>
                        <div class="flex-1 h-3 rounded-full bg-gray-100 overflow-hidden"><div class="h-full rounded-full {{ $h->ingresos == $maxHora && $h->ingresos > 0 ? 'bg-rose-500' : 'bg-amber-400' }}" style="width: {{ $h->ingresos / $maxHora * 100 }}%"></div></div>
                        <span class="w-8 text-right font-semibold">{{ $h->ingresos ?: '' }}</span>
                    </div>
                @endforeach
            </section>
            <section class="bg-white rounded-2xl shadow-sm p-5 space-y-5">
                <div>
                    <h2 class="font-bold text-gray-800 mb-3">📅 Días de la semana</h2>
                    @php $maxSem = $max($porSemana, 'ingresos'); @endphp
                    @foreach ($porSemana as $d)
                        <div class="flex items-center gap-2 text-xs mb-1">
                            <span class="w-20 text-gray-500">{{ $d->dia }}</span>
                            <div class="flex-1 h-3 rounded-full bg-gray-100 overflow-hidden"><div class="h-full rounded-full bg-orange-500" style="width: {{ $d->ingresos / $maxSem * 100 }}%"></div></div>
                            <span class="w-8 text-right font-semibold">{{ $d->ingresos ?: '' }}</span>
                        </div>
                    @endforeach
                </div>
                <div>
                    <h2 class="font-bold text-gray-800 mb-2">🏆 Los más constantes</h2>
                    @forelse ($top as $i => $t)
                        <div class="flex justify-between text-sm py-1 border-b last:border-0"><span><b class="text-orange-500">{{ $i + 1 }}.</b> {{ $t->nombre }} <span class="text-gray-400 text-xs">N° {{ $t->codigo }}</span></span><span class="font-bold">{{ $t->dias }} día(s)</span></div>
                    @empty
                        <p class="text-sm text-gray-400">Sin ingresos en el periodo.</p>
                    @endforelse
                </div>
            </section>
        </div>

        {{-- Recuperar clientes --}}
        <div class="grid lg:grid-cols-2 gap-5">
            <section class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-bold text-gray-800">⏳ Vencen en los próximos 7 días ({{ $porVencer->count() }})</h2>
                <p class="text-xs text-gray-500 mb-3">Recuérdales renovar antes de que se vayan.</p>
                <div class="divide-y">
                    @forelse ($porVencer as $c)
                        <div class="py-2 flex items-center gap-2 text-sm">
                            <div class="flex-1 min-w-0"><p class="font-semibold truncate">{{ $c->clinom }}</p><p class="text-xs text-gray-500">{{ $c->plan }} · vence {{ $fecha($c->vence) }}</p></div>
                            @if ($url = $wa($c, 'vence'))<a href="{{ $url }}" target="_blank" rel="noopener" class="no-print px-3 py-1.5 rounded-lg bg-green-600 text-white text-xs font-bold">WhatsApp</a>@endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 py-2">Nadie vence esta semana.</p>
                    @endforelse
                </div>
            </section>
            <section class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-bold text-gray-800">💔 Vencieron y no renovaron ({{ $sinRenovar->count() }})</h2>
                <p class="text-xs text-gray-500 mb-3">Vencidos en los últimos 30 días: escríbeles para que vuelvan.</p>
                <div class="divide-y max-h-80 overflow-y-auto">
                    @forelse ($sinRenovar as $c)
                        <div class="py-2 flex items-center gap-2 text-sm">
                            <div class="flex-1 min-w-0"><p class="font-semibold truncate">{{ $c->clinom }}</p><p class="text-xs text-gray-500">{{ $c->plan }} · venció {{ $fecha($c->vence) }}</p></div>
                            @if ($url = $wa($c, 'vencio'))<a href="{{ $url }}" target="_blank" rel="noopener" class="no-print px-3 py-1.5 rounded-lg bg-green-600 text-white text-xs font-bold">WhatsApp</a>@endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 py-2">Todos renovaron. 💪</p>
                    @endforelse
                </div>
            </section>
        </div>

        {{-- Otros --}}
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <section class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-bold text-gray-800 mb-2">❄️ Congelamientos</h2>
                <p class="text-3xl font-black text-sky-500">{{ $congelamientos }}</p>
                <p class="text-xs text-gray-500 mb-2">{{ $diasCongelados }} día(s) congelados en el periodo</p>
                @foreach ($congeladosPorMotivo as $motivo => $n)<p class="text-sm flex justify-between"><span>{{ $motivo }}</span><b>{{ $n }}</b></p>@endforeach
            </section>
            <section class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-bold text-gray-800 mb-2">⛔ Ingresos denegados</h2>
                <p class="text-3xl font-black text-rose-600">{{ $denegados }}</p>
                @foreach ($motivosDenegados as $m)<p class="text-xs text-gray-600 mt-1 flex justify-between gap-2"><span class="truncate">{{ $m->motivo }}</span><b>{{ $m->veces }}</b></p>@endforeach
            </section>
            <section class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-bold text-gray-800 mb-2">🎂 Cumpleaños de {{ ucfirst(now()->locale('es')->isoFormat('MMMM')) }}</h2>
                <div class="max-h-48 overflow-y-auto">
                    @forelse ($cumpleanos as $c)
                        <p class="text-sm flex justify-between {{ \Carbon\Carbon::parse($c->fecha_nac)->format('m-d') === now()->format('m-d') ? 'font-bold text-orange-600' : '' }}">
                            <span class="truncate">{{ $c->clinom }}</span><span>{{ \Carbon\Carbon::parse($c->fecha_nac)->format('d/m') }}</span></p>
                    @empty
                        <p class="text-sm text-gray-400">Nadie cumple este mes.</p>
                    @endforelse
                </div>
            </section>
            <section class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-bold text-gray-800 mb-2">🧑‍🏫 Clientes por entrenador</h2>
                @forelse ($porEntrenador as $e)
                    <p class="text-sm flex justify-between"><span class="truncate">{{ $e->nombre }}</span><span><b>{{ $e->clientes }}</b> <span class="text-xs text-gray-400">({{ $e->vigentes }} vigentes)</span></span></p>
                @empty
                    <p class="text-sm text-gray-400">Sin clientes asignados a entrenadores.</p>
                @endforelse
                <p class="text-xs text-gray-500 mt-2">Clientes nuevos registrados en el periodo: <b>{{ $nuevosClientes }}</b></p>
            </section>
        </div>
    </div>
@endsection
