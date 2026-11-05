@extends('layouts.app')
@section('title', 'Panel del dueño')

@section('content')
@use('App\Http\Controllers\PanelDuenoController')
@use('App\Support\PanelDueno')
@php
    $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
    $solesCorto = fn ($n) => 'S/ '.number_format((float) $n, 0);
    $var = fn ($v) => $v === null ? '' : '<span class="'.($v >= 0 ? 'text-emerald-600' : 'text-rose-600').' font-bold">'.($v >= 0 ? '▲ +' : '▼ ').$v.'%</span>';
    $urlVer = fn ($v) => route('dueno.index', ['ver' => $v] + ($v === $ver ? ['desde' => $desde, 'hasta' => $hasta] : []));
    $nAltas = $alertas->where('nivel', 'alta')->count();
@endphp
<div class="max-w-6xl mx-auto space-y-5" x-data="panelDueno()" x-init="if (@js(request()->boolean('resumen'))) abrirResumen()">
    <section class="rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-900 to-violet-800 text-white p-5 sm:p-6 shadow-lg relative isolate overflow-hidden">
        <x-kene-adorno patron="meandro" />
        <div class="flex flex-wrap items-center gap-3">
            <div class="mr-auto">
                <p class="text-xs uppercase tracking-widest text-indigo-200">{{ now()->locale('es')->translatedFormat('l d \d\e F') }}</p>
                <h1 class="text-2xl sm:text-3xl font-black">👑 Panel del dueño</h1>
                <p class="text-sm text-indigo-200">Todo tu negocio en una pantalla: cuánto vendes, cuándo, quién y qué revisar.</p>
            </div>
            <button type="button" @click="abrirResumen()" class="px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 font-black text-sm shadow">📲 Resumen del día por WhatsApp</button>
            @if (\App\Support\AvisosCelular::configurado())
                {{-- Avisos al celular del dueño: anulaciones, platos quitados, cortesías, descuentos y faltantes de caja al momento --}}
                <button type="button" data-avisos-celular data-para="dueno" data-clave="{{ config('services.push.publica') }}" data-guardar="{{ route('avisos_celular.suscribir') }}"
                    data-quitar="{{ route('avisos_celular.quitar') }}" data-icono="{{ asset(config('marca.icono192')) }}"
                    data-bienvenida="Te avisaré al momento si anulan una venta, quitan un plato de cocina, dan cortesías o descuentos, o falta plata en caja."
                    class="hidden px-4 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-gray-900 font-black text-sm shadow">🔔 Avisarme al celular</button>
            @endif
            <button type="button" @click="config = true" class="px-4 py-2.5 rounded-xl bg-white/15 hover:bg-white/25 font-bold text-sm">⚙ Meta y gastos</button>
        </div>
    </section>

    <nav class="flex gap-2 overflow-x-auto pb-1">
        @foreach (PanelDuenoController::VISTAS as $clave => [$nombre, $icono])
            <a href="{{ $urlVer($clave) }}" class="shrink-0 h-10 px-4 inline-flex items-center gap-1.5 rounded-xl shadow-sm text-sm font-bold {{ $ver === $clave ? 'bg-gray-900 text-white' : 'bg-white text-gray-700' }}">
                {{ $icono }} {{ $nombre }}
                @if ($clave === 'alertas' && $alertas->isNotEmpty())<span class="min-w-5 h-5 px-1 rounded-full text-[11px] flex items-center justify-center {{ $nAltas ? 'bg-rose-500 text-white' : 'bg-amber-400 text-amber-950' }}">{{ $alertas->count() }}</span>@endif
            </a>
        @endforeach
    </nav>

    @if (! in_array($ver, ['panel', 'turno'], true))
        <form class="flex flex-wrap items-end gap-2 text-xs">
            <input type="hidden" name="ver" value="{{ $ver }}">
            <label class="font-semibold text-gray-500">Desde<input type="date" name="desde" value="{{ $desde }}" class="mt-1 block h-9 rounded-lg border-gray-300 text-sm"></label>
            <label class="font-semibold text-gray-500">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="mt-1 block h-9 rounded-lg border-gray-300 text-sm"></label>
            <button class="h-9 px-4 rounded-lg bg-indigo-600 text-white font-bold">Ver</button>
            @if ($config->atiende_de_noche ?? false)<span class="self-center text-gray-500">🌙 Cada fecha es una noche: de 12 del mediodía a 12 del mediodía del día siguiente.</span>@endif
        </form>
    @endif

    {{-- ===================================================== Cómo va mi negocio --}}
    @if ($ver === 'panel')
        @php
            $r = $resumen;
            $noche = $r['nocturno'];
            $nombreDia = $r['inicioHoy']->locale('es')->translatedFormat('l');
        @endphp
        @if ($noche)
            <p class="rounded-2xl bg-indigo-50 ring-1 ring-indigo-100 p-3 text-sm text-indigo-900">🌙 <b>Tu panel cuenta por noches.</b>
                La noche del {{ $nombreDia }} empezó el {{ $r['inicioHoy']->format('d/m') }} a las 12 del mediodía y termina al día siguiente a las 12 del mediodía:
                lo que vendes de madrugada cuenta en la noche que atendiste.</p>
        @elseif ($sugerirNoche)
            <button type="button" @click="config = true" class="w-full text-left rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-3 text-sm text-amber-900">🌙 <b>Vendes mucho de madrugada.</b>
                ¿Tu negocio atiende de noche (discoteca, bar)? Actívalo en ⚙ y verás "Esta noche" en vez de "Hoy", sin que la madrugada se pase al día siguiente. →</button>
        @endif
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">{{ $noche ? '🌙 Esta noche ('.$nombreDia.')' : 'Hoy' }}</p>
                <p class="text-2xl font-black text-gray-800">{{ $soles($r['hoy']->total) }}</p>
                <p class="text-xs text-gray-500">{!! $var($r['varHoy']) !!} vs. {{ $noche ? 'la noche del' : 'el' }} {{ $nombreDia }} pasado a esta hora ({{ $solesCorto($r['semanaPasada']->total) }})</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">En el mes ({{ $noche ? 'a la noche' : 'al día' }} {{ $r['diaActual'] }})</p>
                <p class="text-2xl font-black text-gray-800">{{ $soles($r['mes']->total) }}</p>
                <p class="text-xs text-gray-500">{!! $var($r['varMes']) !!} vs. el mismo tramo del mes pasado</p></div>
            <div class="rounded-2xl shadow-sm p-4 bg-indigo-600 text-white"><p class="text-xs font-semibold text-indigo-100">A este ritmo cierras el mes en</p>
                <p class="text-2xl font-black">{{ $soles($r['proyeccion']) }}</p>
                <p class="text-xs text-indigo-100">el mes pasado: {{ $solesCorto($r['mesPasado']->total) }}@if ($r['varProyeccion'] !== null) · {{ $r['varProyeccion'] >= 0 ? '+' : '' }}{{ $r['varProyeccion'] }}%@endif</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">Ticket promedio del mes</p>
                <p class="text-2xl font-black text-gray-800">{{ $soles($r['mes']->promedio) }}</p>
                <p class="text-xs text-gray-500">{{ number_format($r['mes']->tickets) }} ventas · mes pasado {{ $soles($r['mesPasadoTramo']->promedio) }}</p></div>
        </div>

        <div class="grid lg:grid-cols-2 gap-4">
            {{-- Meta --}}
            <section class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-black text-gray-800">🎯 Meta del mes</h2>
                @if ($r['meta'] > 0)
                    <p class="text-sm text-gray-600 mt-1">{{ $soles($r['mes']->total) }} de {{ $soles($r['meta']) }}</p>
                    <div class="mt-2 h-4 rounded-full bg-gray-100 overflow-hidden"><div class="h-4 rounded-full {{ $r['avanceMeta'] >= 100 ? 'bg-emerald-500' : 'bg-indigo-500' }}" style="width: {{ min(100, $r['avanceMeta']) }}%"></div></div>
                    <p class="text-sm mt-2">
                        @if ($r['avanceMeta'] >= 100) <b class="text-emerald-600">🎉 ¡Meta cumplida! ({{ $r['avanceMeta'] }}%)</b>
                        @else Vas al <b>{{ $r['avanceMeta'] }}%</b> · te falta vender <b>{{ $soles($r['faltaPorDia']) }} por {{ $noche ? 'noche' : 'día' }}</b> para llegar.
                            @if ($r['proyeccion'] >= $r['meta'])<span class="text-emerald-600 font-semibold"> A este ritmo sí llegas.</span>@else<span class="text-amber-600 font-semibold"> A este ritmo no llegas: acelera.</span>@endif
                        @endif
                    </p>
                @else
                    <p class="text-sm text-gray-500 mt-2">Ponte una meta de ventas para el mes y te diré cada día cuánto te falta.</p>
                    <button type="button" @click="config = true" class="mt-3 px-4 py-2 rounded-xl bg-indigo-50 text-indigo-700 text-sm font-bold">Poner mi meta</button>
                @endif
            </section>

            {{-- Punto de equilibrio --}}
            <section class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-black text-gray-800">⚖️ Punto de equilibrio</h2>
                @if ($r['equilibrioDia'] !== null)
                    <p class="text-sm text-gray-600 mt-1">Con gastos fijos de {{ $soles($r['gastosFijos']) }} al mes y un margen de {{ $r['margen'] }}% sobre lo que vendes:</p>
                    <p class="mt-2 text-lg">Necesitas vender <b class="text-indigo-700">{{ $soles($r['equilibrioDia']) }} {{ $noche ? 'por noche' : 'al día' }}</b> para no perder.</p>
                    <p class="text-sm mt-1">{{ $noche ? 'Esta noche' : 'Hoy' }} vas <b class="{{ $r['hoy']->total >= $r['equilibrioDia'] ? 'text-emerald-600' : 'text-amber-600' }}">{{ $soles($r['hoy']->total) }}</b>
                        {{ $r['hoy']->total >= $r['equilibrioDia'] ? '✅ ya cubriste '.($noche ? 'la noche' : 'el día') : '· te faltan '.$soles($r['equilibrioDia'] - $r['hoy']->total) }}</p>
                    @if ($r['gananciaNeta'] !== null)
                        <p class="mt-3 rounded-xl p-3 text-sm font-semibold {{ $r['gananciaNeta'] >= 0 ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800' }}">
                            {{ $r['gananciaNeta'] >= 0 ? '💰 Si sigues así, este mes te quedan unos' : '⚠️ Si sigues así, este mes pierdes unos' }} {{ $soles(abs($r['gananciaNeta'])) }}
                            <span class="block text-xs font-normal">(ganancia bruta proyectada menos gastos fijos)</span></p>
                    @endif
                @else
                    <p class="text-sm text-gray-500 mt-2">Dime cuánto gastas al mes en alquiler, sueldos, luz y servicios, y te diré cuánto necesitas vender al día para no perder.
                        @if ($r['gastosSugeridos'] > 0)<br>El mes pasado registraste <b>{{ $soles($r['gastosSugeridos']) }}</b> en gastos.@endif</p>
                    @if ($r['margen'] === null)<p class="text-xs text-amber-600 mt-1">También necesito el costo de lo que vendes (recetas o costo de productos).</p>@endif
                    <button type="button" @click="config = true" class="mt-3 px-4 py-2 rounded-xl bg-indigo-50 text-indigo-700 text-sm font-bold">Poner mis gastos fijos</button>
                @endif
            </section>
        </div>

        <div class="grid lg:grid-cols-3 gap-4">
            {{-- Ventas de cada día del mes --}}
            <section class="lg:col-span-2 bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-black text-gray-800">Ventas de cada {{ $noche ? 'noche' : 'día' }} del mes</h2>
                @php $maxDia = max(1, (float) $r['porDia']->max()); @endphp
                <div class="mt-4 h-40 flex items-end gap-1">
                    @for ($d = 1; $d <= $r['diasMes']; $d++)
                        @php $v = (float) ($r['porDia'][$d] ?? 0); @endphp
                        <div class="flex-1 flex flex-col items-center justify-end h-full group relative" title="{{ $noche ? 'Noche del' : 'Día' }} {{ $d }}: {{ $soles($v) }}">
                            <div class="w-full rounded-t {{ $d === $r['diaActual'] ? 'bg-indigo-600' : ($d > $r['diaActual'] ? 'bg-gray-100' : 'bg-indigo-300') }}"
                                 style="height: {{ $d > $r['diaActual'] ? 4 : max(2, $v / $maxDia * 100) }}%"></div>
                        </div>
                    @endfor
                </div>
                <div class="flex justify-between text-[10px] text-gray-400 mt-1"><span>1</span><span>{{ intdiv($r['diasMes'], 2) }}</span><span>{{ $r['diasMes'] }}</span></div>
                @if ($r['equilibrioDia'])<p class="text-xs text-gray-500 mt-2">Punto de equilibrio: {{ $soles($r['equilibrioDia']) }} por {{ $noche ? 'noche' : 'día' }}.</p>@endif
            </section>
            {{-- Top productos --}}
            <section class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-black text-gray-800">🏆 Lo más vendido del mes</h2>
                <div class="mt-3 space-y-2">
                    @forelse ($r['topProductos'] as $i => $p)
                        <div class="flex items-center gap-2 text-sm"><span class="w-6 text-center">{{ ['🥇', '🥈', '🥉', '4.', '5.'][$i] }}</span>
                            <span class="flex-1 truncate font-semibold text-gray-700">{{ $p->nombre }}</span><span class="text-gray-500">{{ $solesCorto($p->total) }}</span></div>
                    @empty
                        <p class="text-sm text-gray-400">Aún sin ventas este mes.</p>
                    @endforelse
                </div>
                <a href="{{ route('recetas.rentabilidad') }}" class="mt-4 inline-block text-xs font-bold text-indigo-600">Ver rentabilidad por producto →</a>
            </section>
        </div>

        @if ($alertas->isNotEmpty())
            <a href="{{ $urlVer('alertas') }}" class="block rounded-2xl p-4 {{ $nAltas ? 'bg-rose-50 ring-1 ring-rose-200' : 'bg-amber-50 ring-1 ring-amber-200' }}">
                <p class="font-black {{ $nAltas ? 'text-rose-700' : 'text-amber-800' }}">🚨 {{ $alertas->count() }} cosa(s) para revisar este mes</p>
                <p class="text-sm text-gray-700">{{ $alertas->take(3)->pluck('titulo')->implode(' · ') }} →</p>
            </a>
        @endif
    @endif

    {{-- ===================================================== Horas pico --}}
    @if ($ver === 'horas')
        @php
            $m = $mapa;
            $color = fn ($v) => $v <= 0 ? '#f3f4f6' : 'rgba(79, 70, 229, '.(0.12 + 0.88 * $v / max(1, $m['maximo'])).')';
            $dia = fn ($d) => PanelDueno::DIAS[$d].($m['nocturno'] ? ' (noche)' : '');
            $franja = fn ($h) => PanelDueno::hora($h).' a '.PanelDueno::hora(($h + 1) % 24);
        @endphp
        @if ($m['pico'])
            <div class="grid sm:grid-cols-3 gap-3">
                <div class="rounded-2xl bg-indigo-600 text-white p-4"><p class="text-xs text-indigo-100">🔥 Tu momento más fuerte</p>
                    <p class="text-xl font-black">{{ $dia($m['pico']['dia']) }} de {{ $franja($m['pico']['hora']) }}</p>
                    <p class="text-xs text-indigo-100">{{ $soles($m['pico']['total'] / $m['semanas']) }} en promedio esa hora. Ten más personal y todo listo.</p></div>
                <div class="rounded-2xl bg-white shadow-sm p-4"><p class="text-xs text-gray-500">😴 El más flojo (con ventas)</p>
                    <p class="text-xl font-black text-gray-800">{{ $dia($m['flojo']['dia']) }} de {{ $franja($m['flojo']['hora']) }}</p>
                    <p class="text-xs text-gray-500">Buen momento para una promo o un happy hour.</p></div>
                @php $mejorDia = collect($m['porDia'])->sortDesc()->keys()->first(); $peorDia = collect($m['porDia'])->sort()->keys()->first(); @endphp
                <div class="rounded-2xl bg-white shadow-sm p-4"><p class="text-xs text-gray-500">📅 Días</p>
                    <p class="text-sm"><b class="text-emerald-600">Mejor: {{ $dia($mejorDia) }}</b> ({{ $solesCorto($m['porDia'][$mejorDia] / $m['semanas']) }} por semana)</p>
                    <p class="text-sm"><b class="text-rose-600">Más bajo: {{ $dia($peorDia) }}</b> ({{ $solesCorto($m['porDia'][$peorDia] / $m['semanas']) }} por semana)</p></div>
            </div>
            <section class="bg-white rounded-2xl shadow-sm p-4 overflow-x-auto">
                <p class="text-xs text-gray-500 mb-3">Más oscuro = más ventas. Pasa el mouse sobre un cuadro para ver el monto. Del {{ \Carbon\Carbon::parse($desde)->format('d/m') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}.
                    @if ($m['nocturno']) 🌙 Cada fila es una noche: lo del sábado a las 2 am está en la fila del viernes.@endif</p>
                <table class="text-xs border-separate" style="border-spacing: 3px">
                    <thead><tr><th></th>@foreach ($m['horas'] as $h)<th class="font-semibold text-gray-500 w-10 whitespace-nowrap">{{ PanelDueno::hora($h) }}</th>@endforeach<th class="pl-2 text-gray-500 text-right">Total</th></tr></thead>
                    <tbody>
                        @foreach (PanelDueno::DIAS as $dia => $nombre)
                            <tr>
                                <th class="pr-2 text-left font-bold text-gray-600">{{ $nombre }}</th>
                                @foreach ($m['horas'] as $h)
                                    @php $c = $m['celdas'][$dia][$h] ?? ['total' => 0, 'tickets' => 0]; @endphp
                                    <td class="h-9 w-10 rounded-md text-center text-[10px] font-bold {{ $c['total'] > $m['maximo'] * 0.6 ? 'text-white' : 'text-gray-600' }}" style="background: {{ $color($c['total']) }}"
                                        title="{{ $nombre }} {{ $franja($h) }} · {{ $soles($c['total']) }} · {{ $c['tickets'] }} ventas">{{ $c['total'] > 0 ? ($c['total'] >= 1000 ? round($c['total'] / 1000, 1).'k' : (int) round($c['total'])) : '' }}</td>
                                @endforeach
                                <td class="pl-2 text-right font-bold text-gray-700 whitespace-nowrap">{{ $solesCorto($m['porDia'][$dia] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @else
            <p class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">Sin ventas en estas fechas.</p>
        @endif
    @endif

    {{-- ===================================================== Ventas del turno --}}
    @if ($ver === 'turno')
        @php
            $t = $turno;
            $r = $t['rango'];
            $columnas = [['mozos', '🍽️ Por mozo', 'Las mesas que atendió cada uno'], ['cajeros', '💰 Por cajero', 'Lo que cobró cada uno en caja'], ['promotores', '📣 Por promotor', 'Lo que trajo cada promotor']];
            $chip = fn (bool $activo) => 'h-9 px-3 inline-flex items-center rounded-full text-sm font-bold '.($activo ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 shadow-sm');
        @endphp
        <div class="flex flex-wrap items-center gap-2">
            @foreach (PanelDuenoController::PERIODOS as $clave => $nombre)
                <a href="{{ route('dueno.index', ['ver' => 'turno', 'periodo' => $clave]) }}" class="{{ $chip($t['periodo'] === $clave) }}">{{ $nombre }}</a>
            @endforeach
        </div>
        {{-- Varias noches juntas: para pagar a mozos, promotores y cajeros según lo que hicieron --}}
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-bold text-gray-500">📅 Varios días:</span>
            @foreach (PanelDuenoController::RANGOS as $clave => $nombre)
                <a href="{{ route('dueno.index', ['ver' => 'turno', 'periodo' => $clave]) }}" class="{{ $chip($t['periodo'] === $clave) }}">{{ $nombre }}</a>
            @endforeach
            <form class="flex flex-wrap items-center gap-1.5 text-sm">
                <input type="hidden" name="ver" value="turno">
                <input type="hidden" name="periodo" value="rango">
                <label class="font-semibold text-gray-600">Del</label>
                <input type="date" name="desde" value="{{ $r['desde'] ?? now()->startOfWeek()->toDateString() }}" max="{{ now()->toDateString() }}" required
                       class="h-9 rounded-lg border-gray-300 text-sm {{ $t['periodo'] === 'rango' ? 'ring-2 ring-indigo-500' : '' }}">
                <label class="font-semibold text-gray-600">al</label>
                <input type="date" name="hasta" value="{{ $r['hasta'] ?? now()->toDateString() }}" max="{{ now()->toDateString() }}" required
                       class="h-9 rounded-lg border-gray-300 text-sm {{ $t['periodo'] === 'rango' ? 'ring-2 ring-indigo-500' : '' }}">
                <button class="h-9 px-4 rounded-lg bg-indigo-600 text-white font-bold">Ver</button>
            </form>
        </div>
        @if (! $r)
            {{-- Una noche o un turno --}}
            <div class="flex flex-wrap items-center gap-2">
                <form class="flex items-center gap-1.5 text-sm">
                    <input type="hidden" name="ver" value="turno">
                    <label class="font-semibold text-gray-600">Noche del</label>
                    <input type="date" name="fecha" value="{{ $t['fecha'] }}" max="{{ now()->toDateString() }}" onchange="this.form.submit()"
                           class="h-9 rounded-lg border-gray-300 text-sm {{ $t['periodo'] === 'fecha' ? 'ring-2 ring-indigo-500' : '' }}">
                </form>
                <form class="ml-auto">
                    <input type="hidden" name="ver" value="turno">
                    @if ($t['fecha'])<input type="hidden" name="fecha" value="{{ $t['fecha'] }}">@endif
                    <select name="turno" onchange="this.form.submit()" class="h-9 rounded-lg border-gray-300 text-sm {{ $t['elegido'] ? 'ring-2 ring-indigo-500' : '' }}">
                        <option value="">{{ $t['fecha'] ? 'Turnos de esa noche…' : 'Elegir un turno de caja…' }}</option>
                        @foreach ($t['turnos'] as $tu)
                            <option value="{{ $tu->id_turno }}" @selected($t['elegido'] == $tu->id_turno)>
                                N° {{ $tu->turno }} · {{ $tu->cajero }} · {{ \Carbon\Carbon::parse($tu->apertura)->format('d/m H:i') }}{{ $tu->estado === 'ABIERTO' ? ' (abierto)' : '' }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        @endif
        <p class="text-sm text-gray-600">🕒 {{ $t['texto'] }}
            @if ($t['periodo'] === 'jornada' || ($r && $r['unidad'] === 'noche'))<span class="text-gray-400">· La noche va de mediodía a mediodía: lo vendido de madrugada cuenta en la noche que se atendió.</span>@endif
            <a href="{{ request()->fullUrl() }}" class="ml-1 text-indigo-600 font-semibold">↻ Actualizar</a></p>

        @if ($r && $t['turnos']->isNotEmpty())
            {{-- Qué turnos de caja suman: todos, salvo los que se desmarquen --}}
            <details class="bg-white rounded-2xl shadow-sm" @if (count($r['marcados']) < $t['turnos']->count()) open @endif>
                <summary class="px-4 py-3 cursor-pointer text-sm font-bold text-gray-700">🧾 Turnos de caja que suman: {{ count($r['marcados']) }} de {{ $t['turnos']->count() }}
                    <span class="font-normal text-gray-400">· toca para elegir cuáles</span></summary>
                <form class="px-4 pb-4" x-data>
                    <input type="hidden" name="ver" value="turno">
                    <input type="hidden" name="periodo" value="rango">
                    <input type="hidden" name="desde" value="{{ $r['desde'] }}">
                    <input type="hidden" name="hasta" value="{{ $r['hasta'] }}">
                    <input type="hidden" name="elegir" value="1">
                    <div class="flex gap-3 mb-2 text-xs font-semibold">
                        <button type="button" class="text-indigo-600" @click="$root.querySelectorAll('[name=\'turnos[]\']').forEach(c => c.checked = true)">Marcar todos</button>
                        <button type="button" class="text-gray-500" @click="$root.querySelectorAll('[name=\'turnos[]\']').forEach(c => c.checked = false)">Desmarcar todos</button>
                    </div>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-1.5">
                        @foreach ($t['turnos'] as $tu)
                            <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" name="turnos[]" value="{{ $tu->id_turno }}" @checked(in_array((int) $tu->id_turno, $r['marcados'], true)) class="rounded text-indigo-600">
                                <span>N° {{ $tu->turno }} · <b>{{ $tu->cajero }}</b> · {{ \Carbon\Carbon::parse($tu->apertura)->locale('es')->translatedFormat('D d/m H:i') }}{{ $tu->estado === 'ABIERTO' ? ' (abierto)' : '' }}</span>
                            </label>
                        @endforeach
                    </div>
                    <button class="mt-3 h-10 px-5 rounded-xl bg-indigo-600 text-white text-sm font-bold">Ver con estos turnos</button>
                </form>
            </details>
        @endif

        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-2xl shadow-sm p-4 bg-indigo-600 text-white"><p class="text-xs font-semibold text-indigo-100">Vendido</p><p class="text-2xl font-black">{{ $soles($t['total']->total) }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">Ventas</p><p class="text-2xl font-black text-gray-800">{{ $t['total']->tickets }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">Ticket promedio</p><p class="text-2xl font-black text-gray-800">{{ $soles($t['total']->promedio) }}</p></div>
        </div>

        <div class="space-y-4" x-data="pagosEquipo(@js($r !== null), {{ (int) auth()->user()->id_empresa_negocio }})">
            @if ($r)
                <p class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">💵 <b>Para pagarles:</b> en cada columna escribe cuánto pagas por {{ $r['unidad'] }} trabajada y/o el % de lo que vendió.
                    El sistema calcula <b>"A pagar"</b> de cada uno. Se queda guardado en este equipo para la próxima vez.</p>
            @endif
            <div class="grid lg:grid-cols-3 gap-4 items-start">
                @foreach ($columnas as [$clave, $titulo, $ayuda])
                    <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
                        <div class="px-4 py-3 border-b"><p class="font-bold text-gray-800">{{ $titulo }}</p><p class="text-xs text-gray-400">{{ $ayuda }}</p>
                            @if ($r)
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-600">
                                    <label class="flex items-center gap-1">S/ <input type="number" min="0" step="0.5" x-model.number="pago.{{ $clave }}.base" placeholder="0" class="w-16 h-8 rounded-lg border-gray-300 text-xs text-center"> por {{ $r['unidad'] }}</label>
                                    <label class="flex items-center gap-1">+ <input type="number" min="0" max="100" step="0.5" x-model.number="pago.{{ $clave }}.pct" placeholder="0" class="w-14 h-8 rounded-lg border-gray-300 text-xs text-center">% de lo vendido</label>
                                </div>
                            @endif
                        </div>
                        <div class="divide-y divide-gray-100">
                            @forelse ($t[$clave] as $i => $p)
                                <div class="px-4 py-2.5">
                                    <div class="flex items-baseline gap-2">
                                        <span class="w-6 text-sm">{{ ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) }}</span>
                                        <span class="flex-1 min-w-0 truncate text-sm font-semibold text-gray-800">{{ $p->nombre }}</span>
                                        <span class="text-sm font-black text-gray-900">{{ $soles($p->total) }}</span>
                                    </div>
                                    <div class="ml-8 mt-1 h-1.5 rounded-full bg-gray-100 overflow-hidden"><div class="h-1.5 rounded-full bg-indigo-500" style="width: {{ min(100, $p->participacion) }}%"></div></div>
                                    <p class="ml-8 mt-0.5 text-[11px] text-gray-500">{{ $p->tickets }} {{ $p->tickets === 1 ? 'venta' : 'ventas' }} · ticket {{ $soles($p->promedio) }} · {{ $p->participacion }}%
                                        @if ($r)
                                            · <b>{{ $p->noches }} {{ $p->noches === 1 ? $r['unidad'] : ($r['unidad'] === 'noche' ? 'noches' : 'días') }}</b>
                                        @else
                                            · {{ \Carbon\Carbon::parse($p->primera)->format('H:i') }}–{{ \Carbon\Carbon::parse($p->ultima)->format('H:i') }}
                                        @endif
                                    </p>
                                    @if ($r)
                                        <p class="ml-8 text-xs font-bold text-emerald-700" x-show="hayPago('{{ $clave }}')">A pagar: <span x-text="soles(pagar('{{ $clave }}', {{ $p->total }}, {{ $p->noches }}))"></span></p>
                                    @endif
                                </div>
                            @empty
                                <p class="px-4 py-6 text-center text-sm text-gray-400">Sin ventas.</p>
                            @endforelse
                            @if ($clave === 'mozos' && $t['sinMozo'] > 0)
                                <p class="px-4 py-2.5 text-xs text-gray-500">Sin mozo (venta directa en caja): <b>{{ $soles($t['sinMozo']) }}</b></p>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>

            @if ($r && $t['dias'])
                {{-- Lo de cada uno, noche por noche --}}
                <section class="bg-white rounded-2xl shadow-sm overflow-hidden" x-data="{ grupo: 'mozos' }">
                    <div class="px-4 py-3 border-b flex flex-wrap items-center gap-2">
                        <p class="font-bold text-gray-800 mr-auto">📋 {{ $r['unidad'] === 'noche' ? 'Noche por noche' : 'Día por día' }}</p>
                        @foreach ($columnas as [$clave, $titulo])
                            <button type="button" @click="grupo = '{{ $clave }}'" class="h-8 px-3 rounded-full text-xs font-bold" :class="grupo === '{{ $clave }}' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700'">{{ $titulo }}</button>
                        @endforeach
                        <button type="button" @click="excel(grupo)" class="h-8 px-3 rounded-full text-xs font-bold bg-emerald-600 text-white">⬇️ Excel</button>
                    </div>
                    @foreach ($columnas as [$clave, $titulo])
                        <div class="overflow-x-auto" x-show="grupo === '{{ $clave }}'" @if ($clave !== 'mozos') x-cloak @endif>
                            <table class="w-full text-xs" data-tabla="{{ $clave }}">
                                <thead class="bg-gray-50 text-gray-500"><tr>
                                    <th class="px-3 py-2 text-left sticky left-0 bg-gray-50">Persona</th>
                                    @foreach ($t['dias'] as $dia)
                                        <th class="px-2 py-2 text-right whitespace-nowrap">{{ \Carbon\Carbon::parse($dia)->locale('es')->translatedFormat('D d/m') }}</th>
                                    @endforeach
                                    <th class="px-3 py-2 text-right">{{ $r['unidad'] === 'noche' ? 'Noches' : 'Días' }}</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                    <th class="px-3 py-2 text-right text-emerald-700" x-show="hayPago('{{ $clave }}')">A pagar</th>
                                </tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse ($t[$clave] as $p)
                                        <tr data-fila>
                                            <td class="px-3 py-1.5 font-semibold text-gray-800 whitespace-nowrap sticky left-0 bg-white">{{ $p->nombre }}</td>
                                            @foreach ($t['dias'] as $dia)
                                                <td class="px-2 py-1.5 text-right whitespace-nowrap {{ isset($p->porNoche[$dia]) ? 'text-gray-800' : 'text-gray-300' }}" data-valor="{{ $p->porNoche[$dia] ?? 0 }}">{{ isset($p->porNoche[$dia]) ? number_format($p->porNoche[$dia], 2) : '—' }}</td>
                                            @endforeach
                                            <td class="px-3 py-1.5 text-right" data-valor="{{ $p->noches }}">{{ $p->noches }}</td>
                                            <td class="px-3 py-1.5 text-right font-black" data-valor="{{ $p->total }}">{{ number_format($p->total, 2) }}</td>
                                            <td class="px-3 py-1.5 text-right font-black text-emerald-700" x-show="hayPago('{{ $clave }}')" x-text="soles(pagar('{{ $clave }}', {{ $p->total }}, {{ $p->noches }}))"></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="{{ count($t['dias']) + 4 }}" class="px-3 py-6 text-center text-gray-400">Sin ventas.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </section>
            @endif
        </div>
        <p class="text-xs text-gray-400">Las anuladas no cuentan; las notas de crédito restan. Mozo = quien atendió la mesa; cajero = quien cobró; promotor = quien trajo al cliente.</p>
    @endif

    {{-- ===================================================== Mi equipo --}}
    @if ($ver === 'equipo')
        <p class="text-sm text-gray-600">Quién vende más (como mozo), quién cobra (como cajero) y lo que conviene vigilar de cada uno. Lo rojo no siempre es malo, pero vale una pregunta.</p>
        <section class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm min-w-[900px]">
                <thead class="bg-gray-50 text-xs text-gray-500 text-left"><tr>
                    <th class="px-3 py-2">#</th><th class="px-3 py-2">Persona</th><th class="px-3 py-2 text-right">Vendió (mozo)</th><th class="px-3 py-2 text-right">Atenciones</th>
                    <th class="px-3 py-2 text-right">Ticket prom.</th><th class="px-3 py-2 text-right">Cobró (caja)</th><th class="px-3 py-2 text-right">Descuentos</th>
                    <th class="px-3 py-2 text-right">Cortesías</th><th class="px-3 py-2 text-right">Quitó de comanda</th><th class="px-3 py-2 text-right">Anuló</th><th class="px-3 py-2 text-right">Caja</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @php $promedioEquipo = $equipo->where('atenciones', '>', 0)->avg('promedio'); @endphp
                    @forelse ($equipo as $i => $u)
                        <tr>
                            <td class="px-3 py-2 text-lg">{{ $u->vendio > 0 ? (['🥇', '🥈', '🥉'][$i] ?? ($i + 1)) : '' }}</td>
                            <td class="px-3 py-2"><p class="font-semibold text-gray-800">{{ $u->nombre }}</p><p class="text-[11px] text-gray-400">{{ $u->rol }}</p></td>
                            <td class="px-3 py-2 text-right font-bold">{{ $u->vendio ? $soles($u->vendio) : '—' }}</td>
                            <td class="px-3 py-2 text-right">{{ $u->atenciones ?: '—' }}</td>
                            <td class="px-3 py-2 text-right {{ $u->promedio && $promedioEquipo && $u->promedio >= $promedioEquipo ? 'text-emerald-600 font-bold' : '' }}">{{ $u->promedio ? $soles($u->promedio) : '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-600">{{ $u->cobro ? $soles($u->cobro) : '—' }}</td>
                            <td class="px-3 py-2 text-right {{ $u->n_descuentos >= 5 ? 'text-rose-600 font-bold' : 'text-gray-600' }}">{{ $u->descuentos ? $soles($u->descuentos).' ('.$u->n_descuentos.')' : '—' }}</td>
                            <td class="px-3 py-2 text-right {{ $u->cortesias >= 5 ? 'text-rose-600 font-bold' : 'text-gray-600' }}">{{ $u->cortesias ?: '—' }}</td>
                            <td class="px-3 py-2 text-right {{ $u->quitados >= 5 ? 'text-rose-600 font-bold' : 'text-gray-600' }}">{{ $u->quitados ?: '—' }}</td>
                            <td class="px-3 py-2 text-right {{ $u->anulados >= 3 ? 'text-rose-600 font-bold' : 'text-gray-600' }}">{{ $u->anulados ? $u->anulados.' ('.$soles($u->monto_anulado).')' : '—' }}</td>
                            <td class="px-3 py-2 text-right font-bold {{ $u->caja < -0.009 ? 'text-rose-600' : ($u->caja > 0.009 ? 'text-amber-600' : 'text-gray-400') }}">{{ abs($u->caja) > 0.009 ? ($u->caja < 0 ? 'faltó ' : 'sobró ').$soles(abs($u->caja)) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-4 py-8 text-center text-gray-400">Sin movimientos en estas fechas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        <p class="text-xs text-gray-400">"Vendió" cuenta las ventas donde la persona figura como mozo; "Cobró", los comprobantes que emitió en caja. La columna Caja suma las diferencias de sus cierres de turno.</p>
    @endif

    {{-- ===================================================== Alertas --}}
    @if ($ver === 'alertas')
        <p class="text-sm text-gray-600">Lo que el sistema encontró raro del {{ \Carbon\Carbon::parse($desde)->format('d/m') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}. No todo es un problema, pero todo merece una mirada.</p>
        <section class="space-y-2">
            @forelse ($alertas as $a)
                @php $estilo = ['alta' => 'border-rose-500 bg-rose-50', 'media' => 'border-amber-400 bg-amber-50', 'info' => 'border-sky-400 bg-sky-50'][$a->nivel]; @endphp
                <div class="rounded-2xl border-l-4 {{ $estilo }} p-4 flex flex-wrap items-start gap-3">
                    <span class="text-2xl">{{ $a->icono }}</span>
                    <div class="flex-1 min-w-60"><p class="font-bold text-gray-800">{{ $a->titulo }}</p><p class="text-sm text-gray-600">{{ $a->detalle }}</p></div>
                    @if ($a->url)<a href="{{ $a->url }}" class="px-3 py-1.5 rounded-lg bg-white text-sm font-semibold text-gray-700 shadow-sm">Revisar →</a>@endif
                </div>
            @empty
                <p class="bg-white rounded-2xl shadow-sm p-10 text-center text-emerald-600 font-semibold">✅ Todo en orden: nada raro en estas fechas.</p>
            @endforelse
        </section>
    @endif

    {{-- Resumen del día --}}
    <div x-show="resumen" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center sm:p-4" @keydown.escape.window="resumen = false">
        <div class="bg-white w-full sm:max-w-lg rounded-t-3xl sm:rounded-3xl shadow-xl p-5 space-y-3 max-h-[92vh] overflow-y-auto" @click.outside="resumen = false">
            <div class="flex items-center gap-2"><h3 class="font-black text-lg flex-1">📲 Resumen del día</h3>
                <input type="date" x-model="fecha" @change="cargarResumen()" max="{{ now()->toDateString() }}" class="h-9 rounded-lg border-gray-300 text-sm"></div>
            <pre class="whitespace-pre-wrap rounded-2xl bg-emerald-50 p-4 text-sm text-gray-800 font-sans" x-text="texto || 'Cargando…'"></pre>
            {{-- Un botón por socio: WhatsApp abre el chat de cada uno con el resumen escrito, solo hay que tocar Enviar --}}
            <div class="grid gap-2" :class="enlaces.length > 1 ? 'grid-cols-1' : 'grid-cols-2'">
                <template x-for="e in enlaces" :key="e.numero">
                    <a :href="e.url" target="_blank" rel="noopener" class="py-3 rounded-xl bg-green-600 text-white font-black text-center"
                       x-text="e.numero ? 'Enviar por WhatsApp a ' + e.numero : 'Enviar por WhatsApp'"></a>
                </template>
                <button type="button" @click="copiar()" class="py-3 rounded-xl bg-gray-100 font-bold" x-text="copiado ? '✔ Copiado' : 'Copiar texto'"></button>
            </div>
            <p class="text-xs text-gray-500" x-show="!tieneNumero">Tip: guarda tu número (o el de cada socio) en ⚙ Meta y gastos para que el mensaje vaya directo.</p>
            <p class="text-xs text-gray-500">🔔 Con <b>Avisarme al celular</b> activado, este resumen te llega solo cada {{ ($config->atiende_de_noche ?? false) ? 'mediodía (la noche que terminó)' : 'noche a las 11 pm' }}.</p>
        </div>
    </div>

    {{-- Configuración --}}
    <div x-show="config" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @keydown.escape.window="config = false">
        <form @submit.prevent="guardar()" class="bg-white w-full max-w-md rounded-3xl shadow-xl p-5 space-y-3 text-sm" @click.outside="config = false">
            <h3 class="font-black text-lg">⚙ Meta y gastos</h3>
            <label class="block font-semibold text-gray-600">🎯 Meta de ventas del mes (S/)
                <input type="number" step="any" min="0" x-model="form.meta_mensual" placeholder="Ej. 60000" class="mt-1 w-full rounded-lg border-gray-300"></label>
            <label class="block font-semibold text-gray-600">⚖️ Gastos fijos del mes (S/)
                <input type="number" step="any" min="0" x-model="form.gastos_fijos_mes" placeholder="Alquiler + sueldos + luz + agua + internet…" class="mt-1 w-full rounded-lg border-gray-300">
                @if ($resumen && $resumen['gastosSugeridos'] > 0)
                    <button type="button" @click="form.gastos_fijos_mes = {{ $resumen['gastosSugeridos'] }}" class="mt-1 text-xs text-indigo-600 font-semibold">Usar lo registrado el mes pasado: {{ $soles($resumen['gastosSugeridos']) }}</button>
                @endif</label>
            <label class="flex items-start gap-2 rounded-xl bg-indigo-50 p-3 font-semibold text-gray-700">
                <input type="checkbox" x-model="form.atiende_de_noche" class="mt-0.5 rounded text-indigo-600">
                <span>🌙 Mi negocio atiende de noche (discoteca, bar)
                    <span class="block text-xs font-normal text-gray-500">El panel cuenta por noches, de 12 del mediodía a 12 del mediodía: lo vendido de madrugada cuenta en la noche que se atendió.</span></span></label>
            <label class="block font-semibold text-gray-600">📲 WhatsApp para el resumen del día
                <input x-model="form.whatsapp_dueno" inputmode="tel" placeholder="987654321, 912345678, 956789123" class="mt-1 w-full rounded-lg border-gray-300">
                <span class="block text-xs font-normal text-gray-500 mt-1">¿Varios socios? Pon el celular de cada uno separado por coma (hasta 5).</span></label>
            <p x-show="error" x-text="error" class="text-rose-600"></p>
            <div class="flex justify-end gap-2"><button type="button" @click="config = false" class="px-4 py-2 rounded-xl bg-gray-100">Cancelar</button>
                <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white font-bold">Guardar</button></div>
        </form>
    </div>
</div>

<script>
    /** Cuánto pagar a cada uno: S/ por noche trabajada + % de lo que vendió (por columna, guardado en este equipo) */
    function pagosEquipo(activo, sucursal) {
        const clave = 'tushpa_pagos_equipo_' + sucursal;
        const vacio = { mozos: { base: '', pct: '' }, cajeros: { base: '', pct: '' }, promotores: { base: '', pct: '' } };
        let guardado = {};
        try { guardado = JSON.parse(localStorage.getItem(clave) || '{}'); } catch (e) { guardado = {}; }
        return {
            pago: { mozos: { ...vacio.mozos, ...(guardado.mozos || {}) }, cajeros: { ...vacio.cajeros, ...(guardado.cajeros || {}) }, promotores: { ...vacio.promotores, ...(guardado.promotores || {}) } },
            init() {
                if (activo) this.$watch('pago', v => { try { localStorage.setItem(clave, JSON.stringify(v)); } catch (e) { /* sin almacenamiento */ } }, { deep: true });
            },
            hayPago(grupo) { return activo && (Number(this.pago[grupo].base) > 0 || Number(this.pago[grupo].pct) > 0); },
            pagar(grupo, total, noches) { return (Number(this.pago[grupo].base) || 0) * noches + total * (Number(this.pago[grupo].pct) || 0) / 100; },
            soles(n) { return 'S/ ' + (Math.round(n * 100) / 100).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            /** La tabla noche por noche en un archivo que abre Excel (separado por ; y con tildes) */
            excel(grupo) {
                const tabla = document.querySelector(`[data-tabla="${grupo}"]`);
                if (!tabla) return;
                const celda = t => '"' + String(t).trim().replace(/"/g, '""') + '"';
                const cabecera = [...tabla.querySelectorAll('thead th')].map(th => celda(th.textContent));
                const filas = [...tabla.querySelectorAll('tbody tr[data-fila]')].map(tr => {
                    const tds = [...tr.querySelectorAll('td')];
                    const datos = [celda(tds[0].textContent), ...tds.slice(1).filter(td => td.dataset.valor !== undefined).map(td => String(td.dataset.valor).replace('.', ','))];
                    const total = Number(tds[tds.length - 2].dataset.valor), noches = Number(tds[tds.length - 3].dataset.valor);
                    if (this.hayPago(grupo)) datos.push(String(this.pagar(grupo, total, noches).toFixed(2)).replace('.', ','));
                    return datos.join(';');
                });
                if (!this.hayPago(grupo)) cabecera.pop();
                const csv = '\ufeff' + [cabecera.join(';'), ...filas].join('\r\n');
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
                a.download = `produccion-${grupo}-` + @js(($turno['rango']['desde'] ?? '').'-al-'.($turno['rango']['hasta'] ?? '')) + '.csv';
                document.body.appendChild(a); a.click(); a.remove();
            },
        };
    }

    function panelDueno() {
        return {
            resumen: false, config: false, texto: '', enlaces: [], copiado: false, error: '', fecha: @js(request('fecha', now()->toDateString())),
            tieneNumero: @js((bool) ($config->whatsapp_dueno ?? null)),
            form: { meta_mensual: @js($config->meta_mensual ?? ''), gastos_fijos_mes: @js($config->gastos_fijos_mes ?? ''), whatsapp_dueno: @js($config->whatsapp_dueno ?? ''), atiende_de_noche: @js((bool) ($config->atiende_de_noche ?? false)) },
            abrirResumen() { this.resumen = true; this.cargarResumen(); },
            async cargarResumen() {
                this.texto = ''; this.copiado = false;
                const d = await fetch(@js(route('dueno.resumen_dia')) + '?fecha=' + this.fecha, { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => null);
                if (!d) { this.texto = 'No se pudo armar el resumen.'; return; }
                this.texto = d.texto; this.enlaces = d.enlaces || [{ numero: '', url: d.whatsapp }];
            },
            copiar() { navigator.clipboard?.writeText(this.texto).then(() => this.copiado = true); },
            async guardar() {
                this.error = '';
                const r = await fetch(@js(route('dueno.configuracion')), { method: 'POST', body: JSON.stringify(this.form),
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) } });
                const d = await r.json().catch(() => ({}));
                if (!r.ok) { this.error = Object.values(d.errors || {}).flat()[0] || 'No se pudo guardar.'; return; }
                location.reload();
            },
        };
    }
</script>
@endsection
