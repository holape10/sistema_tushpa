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

    @if ($ver !== 'panel')
        <form class="flex flex-wrap items-end gap-2 text-xs">
            <input type="hidden" name="ver" value="{{ $ver }}">
            <label class="font-semibold text-gray-500">Desde<input type="date" name="desde" value="{{ $desde }}" class="mt-1 block h-9 rounded-lg border-gray-300 text-sm"></label>
            <label class="font-semibold text-gray-500">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="mt-1 block h-9 rounded-lg border-gray-300 text-sm"></label>
            <button class="h-9 px-4 rounded-lg bg-indigo-600 text-white font-bold">Ver</button>
        </form>
    @endif

    {{-- ===================================================== Cómo va mi negocio --}}
    @if ($ver === 'panel')
        @php $r = $resumen; @endphp
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">Hoy</p>
                <p class="text-2xl font-black text-gray-800">{{ $soles($r['hoy']->total) }}</p>
                <p class="text-xs text-gray-500">{!! $var($r['varHoy']) !!} vs. el {{ now()->subWeek()->locale('es')->translatedFormat('l') }} pasado a esta hora ({{ $solesCorto($r['semanaPasada']->total) }})</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">En el mes (al día {{ $r['diaActual'] }})</p>
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
                        @else Vas al <b>{{ $r['avanceMeta'] }}%</b> · te falta vender <b>{{ $soles($r['faltaPorDia']) }} por día</b> para llegar.
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
                    <p class="mt-2 text-lg">Necesitas vender <b class="text-indigo-700">{{ $soles($r['equilibrioDia']) }} al día</b> para no perder.</p>
                    <p class="text-sm mt-1">Hoy vas <b class="{{ $r['hoy']->total >= $r['equilibrioDia'] ? 'text-emerald-600' : 'text-amber-600' }}">{{ $soles($r['hoy']->total) }}</b>
                        {{ $r['hoy']->total >= $r['equilibrioDia'] ? '✅ ya cubriste el día' : '· te faltan '.$soles($r['equilibrioDia'] - $r['hoy']->total) }}</p>
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
                <h2 class="font-black text-gray-800">Ventas de cada día del mes</h2>
                @php $maxDia = max(1, (float) $r['porDia']->max()); @endphp
                <div class="mt-4 h-40 flex items-end gap-1">
                    @for ($d = 1; $d <= $r['diasMes']; $d++)
                        @php $v = (float) ($r['porDia'][$d] ?? 0); @endphp
                        <div class="flex-1 flex flex-col items-center justify-end h-full group relative" title="Día {{ $d }}: {{ $soles($v) }}">
                            <div class="w-full rounded-t {{ $d === $r['diaActual'] ? 'bg-indigo-600' : ($d > $r['diaActual'] ? 'bg-gray-100' : 'bg-indigo-300') }}"
                                 style="height: {{ $d > $r['diaActual'] ? 4 : max(2, $v / $maxDia * 100) }}%"></div>
                        </div>
                    @endfor
                </div>
                <div class="flex justify-between text-[10px] text-gray-400 mt-1"><span>1</span><span>{{ intdiv($r['diasMes'], 2) }}</span><span>{{ $r['diasMes'] }}</span></div>
                @if ($r['equilibrioDia'])<p class="text-xs text-gray-500 mt-2">Punto de equilibrio: {{ $soles($r['equilibrioDia']) }} por día.</p>@endif
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
        @endphp
        @if ($m['pico'])
            <div class="grid sm:grid-cols-3 gap-3">
                <div class="rounded-2xl bg-indigo-600 text-white p-4"><p class="text-xs text-indigo-100">🔥 Tu momento más fuerte</p>
                    <p class="text-xl font-black">{{ PanelDueno::DIAS[$m['pico']['dia']] }} de {{ $m['pico']['hora'] }}:00 a {{ $m['pico']['hora'] + 1 }}:00</p>
                    <p class="text-xs text-indigo-100">{{ $soles($m['pico']['total'] / $m['semanas']) }} en promedio esa hora. Ten más personal y todo listo.</p></div>
                <div class="rounded-2xl bg-white shadow-sm p-4"><p class="text-xs text-gray-500">😴 El más flojo (con ventas)</p>
                    <p class="text-xl font-black text-gray-800">{{ PanelDueno::DIAS[$m['flojo']['dia']] }} de {{ $m['flojo']['hora'] }}:00 a {{ $m['flojo']['hora'] + 1 }}:00</p>
                    <p class="text-xs text-gray-500">Buen momento para una promo o un happy hour.</p></div>
                @php $mejorDia = collect($m['porDia'])->sortDesc()->keys()->first(); $peorDia = collect($m['porDia'])->sort()->keys()->first(); @endphp
                <div class="rounded-2xl bg-white shadow-sm p-4"><p class="text-xs text-gray-500">📅 Días</p>
                    <p class="text-sm"><b class="text-emerald-600">Mejor: {{ PanelDueno::DIAS[$mejorDia] }}</b> ({{ $solesCorto($m['porDia'][$mejorDia] / $m['semanas']) }} por semana)</p>
                    <p class="text-sm"><b class="text-rose-600">Más bajo: {{ PanelDueno::DIAS[$peorDia] }}</b> ({{ $solesCorto($m['porDia'][$peorDia] / $m['semanas']) }} por semana)</p></div>
            </div>
            <section class="bg-white rounded-2xl shadow-sm p-4 overflow-x-auto">
                <p class="text-xs text-gray-500 mb-3">Más oscuro = más ventas. Pasa el mouse sobre un cuadro para ver el monto. Del {{ \Carbon\Carbon::parse($desde)->format('d/m') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}.</p>
                <table class="text-xs border-separate" style="border-spacing: 3px">
                    <thead><tr><th></th>@foreach ($m['horas'] as $h)<th class="font-semibold text-gray-500 w-10">{{ $h }}h</th>@endforeach<th class="pl-2 text-gray-500 text-right">Total</th></tr></thead>
                    <tbody>
                        @foreach (PanelDueno::DIAS as $dia => $nombre)
                            <tr>
                                <th class="pr-2 text-left font-bold text-gray-600">{{ $nombre }}</th>
                                @foreach ($m['horas'] as $h)
                                    @php $c = $m['celdas'][$dia][$h] ?? ['total' => 0, 'tickets' => 0]; @endphp
                                    <td class="h-9 w-10 rounded-md text-center text-[10px] font-bold {{ $c['total'] > $m['maximo'] * 0.6 ? 'text-white' : 'text-gray-600' }}" style="background: {{ $color($c['total']) }}"
                                        title="{{ $nombre }} {{ $h }}:00 · {{ $soles($c['total']) }} · {{ $c['tickets'] }} ventas">{{ $c['total'] > 0 ? ($c['total'] >= 1000 ? round($c['total'] / 1000, 1).'k' : (int) round($c['total'])) : '' }}</td>
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
            <div class="grid grid-cols-2 gap-2">
                <a :href="enlace" target="_blank" rel="noopener" class="py-3 rounded-xl bg-green-600 text-white font-black text-center">Enviar por WhatsApp</a>
                <button type="button" @click="copiar()" class="py-3 rounded-xl bg-gray-100 font-bold" x-text="copiado ? '✔ Copiado' : 'Copiar texto'"></button>
            </div>
            <p class="text-xs text-gray-500" x-show="!tieneNumero">Tip: guarda tu número en ⚙ Meta y gastos para que el mensaje te llegue directo.</p>
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
            <label class="block font-semibold text-gray-600">📲 Tu WhatsApp (para el resumen del día)
                <input x-model="form.whatsapp_dueno" inputmode="tel" placeholder="987654321" class="mt-1 w-full rounded-lg border-gray-300"></label>
            <p x-show="error" x-text="error" class="text-rose-600"></p>
            <div class="flex justify-end gap-2"><button type="button" @click="config = false" class="px-4 py-2 rounded-xl bg-gray-100">Cancelar</button>
                <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white font-bold">Guardar</button></div>
        </form>
    </div>
</div>

<script>
    function panelDueno() {
        return {
            resumen: false, config: false, texto: '', enlace: '#', copiado: false, error: '', fecha: @js(now()->toDateString()),
            tieneNumero: @js((bool) ($config->whatsapp_dueno ?? null)),
            form: { meta_mensual: @js($config->meta_mensual ?? ''), gastos_fijos_mes: @js($config->gastos_fijos_mes ?? ''), whatsapp_dueno: @js($config->whatsapp_dueno ?? '') },
            abrirResumen() { this.resumen = true; this.cargarResumen(); },
            async cargarResumen() {
                this.texto = ''; this.copiado = false;
                const d = await fetch(@js(route('dueno.resumen_dia')) + '?fecha=' + this.fecha, { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => null);
                if (!d) { this.texto = 'No se pudo armar el resumen.'; return; }
                this.texto = d.texto; this.enlace = d.whatsapp;
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
