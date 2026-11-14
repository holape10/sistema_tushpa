@extends('admin.layout')
@section('title', 'Servidor')

@section('content')
    @php
        $t = fn ($b) => \App\Support\Servidor::tamano($b);
        $pct = fn ($parte, $total) => $total > 0 ? (int) round($parte / $total * 100) : 0;
        $barra = fn (int $p) => $p >= 90 ? 'bg-rose-500' : ($p >= 75 ? 'bg-amber-500' : 'bg-emerald-500');
        $totalBases = $bases->sum('total');
        $mayor = max(1, (int) $bases->max('total'));
        $horas = intdiv($mysql['encendido'], 3600);
    @endphp

    <h1 class="text-xl font-bold mb-1">🖥️ Servidor</h1>
    <p class="text-sm text-slate-500 mb-4">Cómo está el VPS ahora y cuánto pesa la base de cada empresa. Solo muestra: no cambia nada.</p>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        {{-- Memoria: lo que importa es la disponible (lo que de verdad queda para usar) --}}
        <div class="rounded-2xl bg-white shadow-sm p-4">
            <p class="text-xs text-slate-500">Memoria RAM disponible</p>
            @if ($memoria)
                @php $usada = $pct($memoria['usada'], $memoria['total']); @endphp
                <p class="text-2xl font-extrabold {{ $memoria['disponible'] < 512 * 1048576 ? 'text-rose-600' : '' }}">{{ $t($memoria['disponible']) }}</p>
                <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full {{ $barra($usada) }}" style="width: {{ $usada }}%"></div></div>
                <p class="mt-1 text-xs text-slate-500">{{ $usada }}% usada de {{ $t($memoria['total']) }}</p>
            @else
                <p class="text-sm text-slate-400 mt-2">No se puede leer en este equipo (solo en el servidor Linux).</p>
            @endif
        </div>
        <div class="rounded-2xl bg-white shadow-sm p-4">
            <p class="text-xs text-slate-500">Swap (memoria de reserva en disco)</p>
            @if ($memoria && $memoria['swap_total'])
                @php $swap = $pct($memoria['swap_usada'], $memoria['swap_total']); @endphp
                <p class="text-2xl font-extrabold {{ $memoria['swap_usada'] > 1073741824 ? 'text-amber-600' : '' }}">{{ $t($memoria['swap_usada']) }}</p>
                <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full {{ $barra($swap) }}" style="width: {{ $swap }}%"></div></div>
                <p class="mt-1 text-xs text-slate-500">usada de {{ $t($memoria['swap_total']) }}</p>
            @else
                <p class="text-sm text-slate-400 mt-2">—</p>
            @endif
        </div>
        <div class="rounded-2xl bg-white shadow-sm p-4">
            <p class="text-xs text-slate-500">Disco libre</p>
            @if ($disco)
                @php $ocupado = $pct($disco['total'] - $disco['libre'], $disco['total']); @endphp
                <p class="text-2xl font-extrabold">{{ $t($disco['libre']) }}</p>
                <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full {{ $barra($ocupado) }}" style="width: {{ $ocupado }}%"></div></div>
                <p class="mt-1 text-xs text-slate-500">{{ $ocupado }}% ocupado de {{ $t($disco['total']) }}</p>
            @else
                <p class="text-sm text-slate-400 mt-2">—</p>
            @endif
        </div>
        <div class="rounded-2xl bg-white shadow-sm p-4">
            <p class="text-xs text-slate-500">MySQL · conexiones abiertas</p>
            @php $con = $pct($mysql['conexiones'], $mysql['max_conexiones']); @endphp
            <p class="text-2xl font-extrabold">{{ $mysql['conexiones'] }} <span class="text-sm font-semibold text-slate-400">de {{ $mysql['max_conexiones'] }}</span></p>
            <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full {{ $barra($con) }}" style="width: {{ max(2, $con) }}%"></div></div>
            <p class="mt-1 text-xs text-slate-500">Máximo usado: {{ $mysql['maximo_usado'] }} · caché {{ $t($mysql['cache']) }} · encendido {{ intdiv($horas, 24) }} d {{ $horas % 24 }} h</p>
        </div>
    </div>

    <div class="mb-6 rounded-xl bg-sky-50 border border-sky-200 text-sky-900 px-4 py-3 text-sm">
        <b>¿Cuándo migrar a más RAM?</b> Cuando la <b>memoria disponible</b> baje de 500 MB de forma seguida o la swap pase de 1 GB y se quede ahí.
        MySQL usa su caché ({{ $t($mysql['cache']) }}) a propósito para ir rápido; esa memoria es compartida por todas las bases.
    </div>

    <div class="grid lg:grid-cols-2 gap-4 items-start mb-6">
        {{-- Consultas en curso: si un cliente dice «está lento», aquí se ve si hay una pegada --}}
        <section class="rounded-2xl bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b flex items-center gap-2">
                <h2 class="flex-1 font-bold">Consultas en curso <span class="text-sm font-normal text-slate-500">· {{ $consultas['activas']->count() }} trabajando · {{ $consultas['dormidas'] }} conexiones en espera</span></h2>
                <a href="{{ route('admin.servidor.index', request()->only('base')) }}" class="text-xs font-bold text-indigo-700 hover:underline">↻ Actualizar</a>
            </div>
            @forelse ($consultas['activas'] as $p)
                <div class="px-4 py-2 border-b border-slate-100 text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2 py-0.5 text-xs font-extrabold {{ $p->segundos >= 30 ? 'bg-rose-100 text-rose-700' : ($p->segundos >= 5 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">{{ $p->segundos }} s</span>
                        <span class="font-mono text-xs font-bold">{{ $p->base ?: '—' }}</span>
                        <span class="text-xs text-slate-500">{{ $p->empresa }}</span>
                        <span class="ml-auto text-xs text-slate-400">{{ $p->estado ?: $p->comando }}</span>
                    </div>
                    @if ($p->consulta)<p class="mt-1 font-mono text-[11px] text-slate-600 break-all line-clamp-3" title="{{ $p->consulta }}">{{ $p->consulta }}</p>@endif
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-emerald-700">✔ No hay ninguna consulta trabajando: MySQL está tranquilo.</p>
            @endforelse
            <p class="px-4 py-2 text-[11px] text-slate-400">Más de 30 segundos (en rojo) suele ser una consulta pegada o un reporte muy pesado.</p>
        </section>

        {{-- Trabajos en segundo plano: importaciones, envíos a SUNAT, XML de json.pe… --}}
        <section class="rounded-2xl bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b flex flex-wrap items-center gap-2">
                <h2 class="flex-1 font-bold">Trabajos en segundo plano</h2>
                @if ($trabajos['vivo'])
                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-700">● Trabajador activo</span>
                @else
                    <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-700" title="Sin él, los trabajos corren mientras se espera en la página">● Trabajador detenido{{ $trabajos['latido'] !== null ? ' hace '.intdiv($trabajos['latido'], 60).' min' : '' }}</span>
                @endif
            </div>
            <div class="grid grid-cols-2 divide-x border-b text-center">
                <div class="py-2"><p class="text-xs text-slate-500">En espera</p><p class="text-xl font-extrabold">{{ $trabajos['total_pendientes'] }}</p></div>
                <div class="py-2"><p class="text-xs text-slate-500">Fallidos</p><p class="text-xl font-extrabold {{ $trabajos['total_fallidas'] ? 'text-rose-600' : '' }}">{{ $trabajos['total_fallidas'] }}</p></div>
            </div>
            @foreach ($trabajos['pendientes'] as $j)
                <div class="px-4 py-1.5 border-b border-slate-100 text-sm flex items-center gap-2">
                    <span class="font-mono text-xs">{{ $j->trabajo }}</span>
                    @if ($j->corriendo)<span class="rounded bg-sky-100 px-1.5 text-[11px] font-bold text-sky-700">corriendo</span>@endif
                    <span class="ml-auto text-xs text-slate-400">desde {{ \Carbon\Carbon::createFromTimestamp($j->desde)->diffForHumans() }} · intento {{ $j->intentos }}</span>
                </div>
            @endforeach
            @if ($trabajos['fallidas']->isNotEmpty())
                <p class="px-4 pt-3 pb-1 text-xs font-bold uppercase text-rose-700">Últimos fallidos</p>
                @foreach ($trabajos['fallidas'] as $j)
                    <div class="px-4 py-1.5 border-b border-slate-100 text-sm">
                        <div class="flex items-center gap-2"><span class="font-mono text-xs font-bold">{{ $j->trabajo }}</span>
                            <span class="ml-auto text-xs text-slate-400">{{ $j->cuando ? \Carbon\Carbon::parse($j->cuando)->format('d/m/Y H:i') : '' }}</span></div>
                        <p class="mt-0.5 font-mono text-[11px] text-rose-700 break-all line-clamp-2" title="{{ $j->error }}">{{ $j->error }}</p>
                    </div>
                @endforeach
            @elseif ($trabajos['pendientes']->isEmpty())
                <p class="px-4 py-6 text-center text-sm text-emerald-700">✔ No hay trabajos en espera ni fallidos.</p>
            @endif
        </section>
    </div>

    {{-- Programas del VPS (como htop, pero agrupados y explicados) --}}
    <section class="rounded-2xl bg-white shadow-sm overflow-hidden mb-6" x-data="{ todos: false }">
        <div class="px-4 py-3 border-b flex flex-wrap items-center gap-2">
            <h2 class="flex-1 font-bold">Procesos del servidor <span class="text-sm font-normal text-slate-500">· lo que muestra htop, agrupado y explicado</span></h2>
            @if ($procesos)<button type="button" @click="todos = !todos" class="text-xs font-bold text-indigo-700 hover:underline" x-text="todos ? 'Ver solo los principales' : 'Ver todos ({{ $procesos->count() }})'"></button>@endif
        </div>
        @if ($procesos === null)
            <p class="px-4 py-6 text-center text-sm text-slate-500">Solo se puede ver en el servidor Linux.</p>
        @else
            @php
                $niveles = ['revisar' => ['🔴 Revisar', 'bg-rose-100 text-rose-700'], 'desconocido' => ['❔ Desconocido', 'bg-slate-200 text-slate-700'],
                    'opcional' => ['🟡 Opcional', 'bg-amber-100 text-amber-800'], 'necesario' => ['✅ Necesario', 'bg-emerald-100 text-emerald-700']];
                $memTotal = $memoria['total'] ?? 0;
            @endphp
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr><th class="px-4 py-2 text-left">Programa</th><th class="px-3 py-2 text-left">Qué es</th><th class="px-3 py-2 text-right">Memoria</th>
                            <th class="px-3 py-2 text-right">Procesador</th><th class="px-3 py-2 text-left">¿Se toca?</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($procesos as $i => $p)
                            <tr @if ($i >= 12 && $p->nivel !== 'revisar') x-show="todos" x-cloak @endif class="{{ $p->nivel === 'revisar' ? 'bg-rose-50' : '' }}">
                                <td class="px-4 py-2">
                                    <span class="font-bold">{{ $p->nombre }}</span>
                                    <span class="block font-mono text-[11px] text-slate-400">{{ str_starts_with($p->clave, 'php:') ? 'php' : $p->clave }} · {{ $p->procesos }} proceso(s){{ $p->hilos > $p->procesos ? ' · '.$p->hilos.' hilos' : '' }} · {{ $p->usuario }}</span>
                                </td>
                                <td class="px-3 py-2 text-slate-600 min-w-[260px]">{{ $p->que }}
                                    @if ($p->nivel === 'revisar')
                                        <span class="block mt-1 text-xs text-rose-700">Si de verdad está pegado, en la consola: <code class="rounded bg-white px-1 font-mono">kill {{ implode(' ', array_slice($p->pids, 0, 5)) }}</code></span>
                                    @endif</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $t($p->memoria) }}
                                    @if ($memTotal)<span class="block text-[11px] text-slate-400">{{ round($p->memoria / $memTotal * 100, 1) }}% de la RAM</span>@endif</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap {{ $p->cpu >= 50 ? 'font-bold text-rose-600' : '' }}">{{ number_format($p->cpu, 1) }}%</td>
                                <td class="px-3 py-2"><span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-bold {{ $niveles[$p->nivel][1] }}">{{ $niveles[$p->nivel][0] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="px-4 py-2 text-[11px] text-slate-400">Procesador = % de todo el procesador, medido en medio segundo. Desde aquí no se detiene nada: si algo hay que parar, se hace por consola.</p>
        @endif
    </section>

    <div class="grid lg:grid-cols-[1fr_380px] gap-4 items-start">
        <section class="rounded-2xl bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b flex flex-wrap items-center gap-2">
                <h2 class="flex-1 font-bold">Bases de datos <span class="text-sm font-normal text-slate-500">· {{ $bases->count() }} · {{ $t($totalBases) }} en total</span></h2>
                <span class="text-xs text-slate-400">Toca una para ver sus tablas más pesadas</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr><th class="px-4 py-2 text-left">Base / empresa</th><th class="px-3 py-2 text-left w-1/3">Tamaño</th><th class="px-3 py-2 text-right">Datos</th>
                            <th class="px-3 py-2 text-right">Índices</th><th class="px-3 py-2 text-right">Filas aprox.</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($bases as $b)
                            <tr class="{{ $elegida && $elegida->base === $b->base ? 'bg-indigo-50' : 'hover:bg-slate-50' }}">
                                <td class="px-4 py-2">
                                    <a href="{{ route('admin.servidor.index', ['base' => $b->base]) }}" class="font-mono text-xs font-bold text-indigo-700 hover:underline">{{ $b->base }}</a>
                                    <span class="block text-xs text-slate-500">{{ $b->empresa ?? ($b->ruc ? 'RUC '.$b->ruc.' (sin cliente)' : 'del sistema') }}</span>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="font-bold">{{ $t($b->total) }}</span>
                                    <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-indigo-500" style="width: {{ max(1, $pct($b->total, $mayor)) }}%"></div></div>
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap text-slate-600">{{ $t($b->datos) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap text-slate-600">{{ $t($b->indices) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap text-slate-600">{{ number_format($b->filas) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl bg-white shadow-sm overflow-hidden lg:sticky lg:top-4">
            @if ($elegida)
                <div class="px-4 py-3 border-b">
                    <h2 class="font-bold font-mono text-sm">{{ $elegida->base }}</h2>
                    <p class="text-xs text-slate-500">{{ $elegida->empresa ?? 'del sistema' }} · {{ $t($elegida->total) }} · {{ $elegida->tablas }} tablas</p>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-2 text-left">Tabla</th><th class="px-3 py-2 text-right">Tamaño</th><th class="px-3 py-2 text-right">Filas aprox.</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($tablas as $tb)
                            <tr><td class="px-4 py-1.5 font-mono text-xs">{{ $tb->tabla }}</td><td class="px-3 py-1.5 text-right whitespace-nowrap">{{ $t($tb->total) }}</td>
                                <td class="px-3 py-1.5 text-right whitespace-nowrap text-slate-500">{{ number_format($tb->filas) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="px-4 py-2 text-[11px] text-slate-400">Las filas son aproximadas (lo que calcula MySQL).</p>
            @else
                <p class="p-6 text-sm text-slate-500 text-center">Elige una base de la lista para ver sus tablas más pesadas.</p>
            @endif
        </section>
    </div>
@endsection
