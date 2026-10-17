@extends('layouts.app')
@section('title', 'Reporte de la clínica')
@section('content')
    @php
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $barra = fn ($v, $max) => max($v > 0 ? 4 : 0, round($v / max(1, $max) * 100));
        $wa = function ($tel, $texto) { $n = preg_replace('/\D/', '', (string) $tel); return strlen($n) >= 9 ? 'https://wa.me/'.(strlen($n) === 9 ? '51'.$n : $n).'?text='.rawurlencode($texto) : null; };
        $tarjeta = 'bg-white rounded-2xl shadow-sm p-5 print:shadow-none print:border';
    @endphp
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <div class="space-y-5">
        {{-- Encabezado y periodo --}}
        <div class="rounded-2xl p-5 sm:p-6 text-white bg-gradient-to-r from-teal-600 via-cyan-600 to-sky-600 shadow-sm flex flex-wrap items-end gap-4 print:bg-none print:text-black print:border">
            <div class="flex-1 min-w-[220px]">
                <p class="text-xs uppercase tracking-widest text-white/70 print:text-gray-500">Reporte de la clínica</p>
                <h1 class="text-2xl font-extrabold">{{ $negocio }}</h1>
                <p class="text-sm text-white/80 print:text-gray-600">Del {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</p>
            </div>
            <form method="GET" class="flex flex-wrap items-end gap-2 print:hidden">
                <label class="text-xs">Desde<input type="date" name="desde" value="{{ $desde }}" class="block rounded-lg border-0 text-gray-800 text-sm"></label>
                <label class="text-xs">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block rounded-lg border-0 text-gray-800 text-sm"></label>
                <button class="h-9 px-4 rounded-lg bg-white/20 hover:bg-white/30 font-semibold text-sm">Ver</button>
                <button type="button" onclick="window.print()" class="h-9 px-4 rounded-lg bg-white text-teal-700 font-semibold text-sm"><i class="fas fa-print"></i> Imprimir</button>
            </form>
        </div>

        {{-- Indicadores --}}
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
            @foreach ([
                ['Atenciones', number_format($totalAtenciones), 'fa-stethoscope', 'text-teal-600', $pacientesAtendidos.' paciente(s)'],
                ['Pacientes nuevos', number_format($pacientesNuevos), 'fa-user-plus', 'text-sky-600', 'historias abiertas'],
                ['Ingresos', $soles($ingresos), 'fa-sack-dollar', 'text-emerald-600', 'ticket prom. '.$soles($ticketPromedio)],
                ['Citas', number_format($citas), 'fa-calendar-check', 'text-indigo-600', $citasAtendidas.' atendidas · '.$citasCanceladas.' canceladas'],
                ['Asistencia', $asistencia === null ? '—' : $asistencia.'%', 'fa-user-check', $asistencia !== null && $asistencia < 80 ? 'text-rose-600' : 'text-emerald-600', $citasNoVino.' no vinieron'],
                ['Saldo por cobrar', $soles($saldoPorCobrar), 'fa-hand-holding-dollar', 'text-amber-600', $tratActivos.' tratamiento(s) en curso'],
            ] as [$titulo, $valor, $icono, $color, $sub])
                <div class="{{ $tarjeta }} !p-4">
                    <p class="text-[11px] font-semibold uppercase text-gray-400"><i class="fas {{ $icono }} {{ $color }}"></i> {{ $titulo }}</p>
                    <p class="text-2xl font-black {{ $color }} mt-1">{{ $valor }}</p>
                    <p class="text-[11px] text-gray-400">{{ $sub }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid lg:grid-cols-3 gap-5">
            {{-- Por doctor --}}
            <div class="{{ $tarjeta }} lg:col-span-2">
                <h2 class="font-bold text-gray-800 mb-3"><i class="fas fa-user-doctor text-teal-600"></i> Por doctor</h2>
                @php $maxDoc = max(1, (int) $porDoctor->max('atenciones')); @endphp
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-400 uppercase"><tr><th class="text-left py-1">Doctor</th><th class="text-right">Atenciones</th><th class="text-right">Pacientes</th><th class="text-right">Ingresos</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($porDoctor as $d)
                            <tr><td class="py-2"><span class="font-semibold">{{ $d->nombre }}</span>
                                    <div class="h-1.5 mt-1 rounded-full bg-gray-100"><div class="h-1.5 rounded-full bg-teal-500" style="width: {{ $barra($d->atenciones, $maxDoc) }}%"></div></div></td>
                                <td class="text-right font-bold">{{ $d->atenciones }}</td><td class="text-right">{{ $d->pacientes }}</td><td class="text-right">{{ $soles($d->ingresos) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-gray-400">Sin atenciones en el periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{-- Por especialidad --}}
            <div class="{{ $tarjeta }}">
                <h2 class="font-bold text-gray-800 mb-3"><i class="fas fa-layer-group text-sky-600"></i> Por especialidad</h2>
                @php $maxEsp = max(1, (int) $porEspecialidad->max('atenciones')); @endphp
                @forelse ($porEspecialidad as $e)
                    <div class="py-1.5 text-sm"><div class="flex justify-between"><span>{{ $e->nombre }}</span><b>{{ $e->atenciones }}</b></div>
                        <div class="h-2 mt-1 rounded-full bg-gray-100"><div class="h-2 rounded-full bg-sky-500" style="width: {{ $barra($e->atenciones, $maxEsp) }}%"></div></div></div>
                @empty
                    <p class="text-sm text-gray-400">Sin datos.</p>
                @endforelse
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-5">
            {{-- Diagnósticos CIE-10 --}}
            <div class="{{ $tarjeta }}">
                <h2 class="font-bold text-gray-800"><i class="fas fa-file-medical text-rose-600"></i> Diagnósticos más frecuentes (CIE-10)</h2>
                <p class="text-xs text-gray-400 mb-3">{{ $conCie10 }} de {{ $totalAtenciones }} atenciones con código CIE-10.</p>
                @php $maxCie = max(1, (int) $topCie10->max('veces')); @endphp
                @forelse ($topCie10 as $c)
                    <div class="py-1.5 text-sm"><div class="flex justify-between gap-2"><span><b class="font-mono text-rose-700">{{ $c->codigo }}</b> {{ $c->descripcion }}</span><b>{{ $c->veces }}</b></div>
                        <div class="h-2 mt-1 rounded-full bg-gray-100"><div class="h-2 rounded-full bg-rose-400" style="width: {{ $barra($c->veces, $maxCie) }}%"></div></div></div>
                @empty
                    <p class="text-sm text-gray-400">Aún no se registraron diagnósticos con CIE-10.</p>
                @endforelse
            </div>
            {{-- Odontología: CPO-D y hallazgos --}}
            <div class="{{ $tarjeta }}">
                <h2 class="font-bold text-gray-800"><i class="fas fa-tooth text-teal-600"></i> Salud bucal</h2>
                @if ($cpod)
                    <p class="text-xs text-gray-400 mb-3">Índice CPO-D promedio de {{ $cpod->pacientes }} paciente(s): dientes permanentes Cariados, Perdidos y Obturados.</p>
                    <div class="grid grid-cols-4 gap-2 text-center mb-4">
                        @foreach (['C' => [$cpod->c, 'bg-rose-50 text-rose-700', 'Cariados'], 'P' => [$cpod->p, 'bg-gray-100 text-gray-700', 'Perdidos'], 'O' => [$cpod->o, 'bg-sky-50 text-sky-700', 'Obturados'], 'CPO-D' => [$cpod->total, 'bg-teal-600 text-white', 'Total']] as $k => [$v, $cl, $n])
                            <div class="rounded-xl py-2 {{ $cl }}"><p class="text-2xl font-black">{{ $v }}</p><p class="text-[10px] font-semibold uppercase">{{ $n }}</p></div>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-gray-400 mb-2">Referencia OMS a los 12 años: muy bajo &lt; 1.2 · bajo 1.2–2.6 · moderado 2.7–4.4 · alto 4.5–6.5 · muy alto &gt; 6.5</p>
                @else
                    <p class="text-sm text-gray-400 mb-3">Aún no hay odontogramas en las atenciones del periodo.</p>
                @endif
                @php $maxH = max(1, (int) $hallazgos->max()); @endphp
                @foreach ($hallazgos as $h => $n)
                    <div class="py-1 text-sm"><div class="flex justify-between"><span>{{ ucfirst(mb_strtolower($h)) }}</span><b>{{ $n }}</b></div>
                        <div class="h-1.5 mt-0.5 rounded-full bg-gray-100"><div class="h-1.5 rounded-full bg-teal-400" style="width: {{ $barra($n, $maxH) }}%"></div></div></div>
                @endforeach
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-5">
            {{-- Tratamientos --}}
            <div class="{{ $tarjeta }} lg:col-span-2">
                <h2 class="font-bold text-gray-800"><i class="fas fa-teeth-open text-amber-600"></i> Tratamientos</h2>
                <p class="text-xs text-gray-400 mb-3">{{ $tratNuevos }} nuevo(s) en el periodo · cobrado en el periodo {{ $soles($cobradoTratamientos) }} · saldo por cobrar {{ $soles($saldoPorCobrar) }}</p>
                <p class="text-sm font-semibold text-rose-700 mb-2">Con cuotas vencidas ({{ $conVencido->count() }})</p>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($conVencido as $t)
                            <tr><td class="py-2"><span class="font-semibold">{{ $t->clinom }}</span><span class="block text-xs text-gray-400">{{ $t->descripcion }} · {{ $t->his_cli_cod }}</span></td>
                                <td class="text-right text-rose-700 font-bold whitespace-nowrap">{{ $soles($t->vencido) }}<span class="block text-[11px] font-normal text-gray-400">saldo {{ $soles($t->saldo) }}</span></td>
                                <td class="text-right print:hidden">
                                    @if ($u = $wa($t->telefono, "Hola {$t->clinom}, le saludamos de {$negocio}. Le recordamos que tiene una cuota pendiente de su tratamiento ({$t->descripcion}) por ".$soles($t->vencido).'. ¡Le esperamos!'))
                                        <a href="{{ $u }}" target="_blank" rel="noopener" class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white text-xs font-bold"><i class="fab fa-whatsapp"></i> Recordar</a>
                                    @endif</td></tr>
                        @empty
                            <tr><td class="py-4 text-center text-gray-400">Ningún tratamiento con cuotas vencidas 🎉</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{-- Pacientes nuevos --}}
            <div class="{{ $tarjeta }} space-y-4">
                <h2 class="font-bold text-gray-800"><i class="fas fa-users text-indigo-600"></i> Pacientes nuevos</h2>
                @foreach (['¿Cómo nos conocieron?' => $porOrigen, 'Edad' => $porEdad, 'Sexo' => $porSexo] as $titulo => $datos)
                    <div>
                        <p class="text-xs font-semibold text-gray-500 mb-1">{{ $titulo }}</p>
                        @php $maxD = max(1, (int) $datos->max()); @endphp
                        @forelse ($datos as $k => $n)
                            <div class="flex items-center gap-2 text-xs py-0.5"><span class="w-32 truncate">{{ ucfirst(mb_strtolower($k)) }}</span>
                                <div class="flex-1 h-2 rounded-full bg-gray-100"><div class="h-2 rounded-full bg-indigo-400" style="width: {{ $barra($n, $maxD) }}%"></div></div><b class="w-6 text-right">{{ $n }}</b></div>
                        @empty
                            <p class="text-xs text-gray-400">Sin datos.</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-5">
            {{-- Volver a llamar --}}
            <div class="{{ $tarjeta }}">
                <h2 class="font-bold text-gray-800"><i class="fas fa-bell text-amber-500"></i> Les toca su control</h2>
                <p class="text-xs text-gray-400 mb-3">Su última atención fue hace 6 a 18 meses y no tienen cita programada.</p>
                <div class="divide-y divide-gray-100 text-sm">
                    @forelse ($paraControl as $p)
                        <div class="py-2 flex items-center gap-2">
                            <div class="flex-1"><a href="{{ route('clinica.historia', $p->his_cli_id) }}" class="font-semibold hover:underline">{{ $p->clinom }}</a>
                                <span class="block text-xs text-gray-400">Última atención: {{ \Carbon\Carbon::parse($p->ultima)->format('d/m/Y') }}</span></div>
                            @if ($u = $wa($p->telefono, "Hola {$p->clinom}, le saludamos de {$negocio}. Ya le toca su control dental. ¿Le reservamos una cita? 😊"))
                                <a href="{{ $u }}" target="_blank" rel="noopener" class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white text-xs font-bold print:hidden"><i class="fab fa-whatsapp"></i> Invitar</a>
                            @endif
                        </div>
                    @empty
                        <p class="py-4 text-center text-gray-400">Nadie pendiente de control.</p>
                    @endforelse
                </div>
            </div>
            {{-- Próximas citas --}}
            <div class="{{ $tarjeta }}">
                <h2 class="font-bold text-gray-800"><i class="fas fa-calendar-day text-sky-600"></i> Próximas citas (7 días)</h2>
                <div class="divide-y divide-gray-100 text-sm mt-2">
                    @forelse ($proximasCitas as $c)
                        <div class="py-2 flex items-center gap-2">
                            <div class="w-24 text-xs"><b>{{ \Carbon\Carbon::parse($c->fecha)->locale('es')->isoFormat('ddd D MMM') }}</b><br>{{ substr($c->hora, 0, 5) }}</div>
                            <div class="flex-1"><span class="font-semibold">{{ $c->paciente }}</span><span class="block text-xs text-gray-400">{{ $c->motivo }} · {{ $c->estado === 'CONFIRMADA' ? 'Confirmada' : 'Por confirmar' }}</span></div>
                            @if ($c->estado === 'PROGRAMADA' && ($u = $wa($c->telefono, "Hola {$c->paciente}, le recordamos su cita en {$negocio} el ".\Carbon\Carbon::parse($c->fecha)->locale('es')->isoFormat('dddd D [de] MMMM').' a las '.substr($c->hora, 0, 5).'. ¿Nos confirma su asistencia?')))
                                <a href="{{ $u }}" target="_blank" rel="noopener" class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white text-xs font-bold print:hidden"><i class="fab fa-whatsapp"></i> Confirmar</a>
                            @endif
                        </div>
                    @empty
                        <p class="py-4 text-center text-gray-400">No hay citas en los próximos 7 días.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
