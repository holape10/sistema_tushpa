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
