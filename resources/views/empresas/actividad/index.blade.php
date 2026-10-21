@extends('layouts.app')
@section('title', 'Línea de tiempo')
@section('content')
@php
    // Clases completas por color (Tailwind solo incluye las que ve escritas)
    $colores = [
        'emerald' => ['punto' => 'bg-emerald-500', 'chip' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'activo' => 'bg-emerald-600 text-white ring-emerald-600'],
        'indigo' => ['punto' => 'bg-indigo-500', 'chip' => 'bg-indigo-50 text-indigo-700 ring-indigo-200', 'activo' => 'bg-indigo-600 text-white ring-indigo-600'],
        'amber' => ['punto' => 'bg-amber-500', 'chip' => 'bg-amber-50 text-amber-800 ring-amber-200', 'activo' => 'bg-amber-500 text-white ring-amber-500'],
        'rose' => ['punto' => 'bg-rose-500', 'chip' => 'bg-rose-50 text-rose-700 ring-rose-200', 'activo' => 'bg-rose-600 text-white ring-rose-600'],
        'orange' => ['punto' => 'bg-orange-500', 'chip' => 'bg-orange-50 text-orange-700 ring-orange-200', 'activo' => 'bg-orange-500 text-white ring-orange-500'],
        'sky' => ['punto' => 'bg-sky-500', 'chip' => 'bg-sky-50 text-sky-700 ring-sky-200', 'activo' => 'bg-sky-600 text-white ring-sky-600'],
    ];
    $avatar = ['bg-indigo-500', 'bg-pink-500', 'bg-emerald-500', 'bg-amber-500', 'bg-sky-500', 'bg-violet-500', 'bg-rose-500', 'bg-teal-500'];
    $iniciales = fn ($n) => collect(preg_split('/\s+/', trim((string) $n)))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
    $url = fn (array $cambios) => route('actividad.index', array_filter(array_merge(['fecha' => $fecha, 'usuario' => $usuario, 'tipo' => $tipo], $cambios), fn ($v) => $v !== null && $v !== ''));
    $dia = \Carbon\Carbon::parse($fecha)->locale('es');
    $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
@endphp

<div class="max-w-5xl mx-auto space-y-5">
    {{-- Cabecera: el día --}}
    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-48">
            <h1 class="text-xl font-extrabold text-gray-800">🕒 Línea de tiempo</h1>
            <p class="text-sm text-gray-500">Todo lo que hizo cada persona en el día, en orden.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ $url(['fecha' => $dia->copy()->subDay()->toDateString()]) }}" class="h-10 w-10 flex items-center justify-center rounded-xl bg-gray-100 hover:bg-gray-200 font-bold" title="Día anterior">‹</a>
            <form method="GET" action="{{ route('actividad.index') }}">
                @if ($usuario)<input type="hidden" name="usuario" value="{{ $usuario }}">@endif
                <input type="date" name="fecha" value="{{ $fecha }}" max="{{ now()->toDateString() }}" onchange="this.form.submit()" class="h-10 rounded-xl border-gray-300 text-sm font-semibold">
            </form>
            <a href="{{ $url(['fecha' => $dia->copy()->addDay()->toDateString()]) }}" class="h-10 w-10 flex items-center justify-center rounded-xl bg-gray-100 hover:bg-gray-200 font-bold {{ $fecha >= now()->toDateString() ? 'pointer-events-none opacity-30' : '' }}" title="Día siguiente">›</a>
            @if ($fecha !== now()->toDateString())
                <a href="{{ $url(['fecha' => now()->toDateString()]) }}" class="h-10 px-3 flex items-center rounded-xl bg-indigo-50 text-indigo-700 text-sm font-bold">Hoy</a>
            @endif
        </div>
        <p class="w-full text-sm font-semibold text-gray-700">{{ \Illuminate\Support\Str::ucfirst($dia->isoFormat('dddd D [de] MMMM [de] YYYY')) }}</p>
    </div>

    @if ($total === 0)
        <div class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">
            <p class="text-4xl mb-2">😴</p>
            <p class="font-semibold">No hubo movimiento este día.</p>
        </div>
    @else
        {{-- Personas: toca una para ver solo lo suyo --}}
        <div class="flex gap-3 overflow-x-auto pb-1 sm:grid sm:grid-cols-3 lg:grid-cols-4 sm:overflow-visible">
            <a href="{{ $url(['usuario' => null]) }}"
               class="shrink-0 w-44 sm:w-auto rounded-2xl p-3 ring-2 {{ ! $usuario ? 'ring-indigo-500 bg-indigo-50' : 'ring-transparent bg-white hover:ring-gray-200' }} shadow-sm">
                <div class="flex items-center gap-2">
                    <span class="h-10 w-10 shrink-0 rounded-full bg-gray-800 text-white flex items-center justify-center text-lg">👥</span>
                    <div class="min-w-0"><p class="font-bold text-gray-800 leading-tight">Todos</p><p class="text-xs text-gray-500">{{ $resumen->count() }} persona(s)</p></div>
                </div>
                <p class="mt-2 text-xs text-gray-600">{{ $total }} {{ $total === 1 ? 'acción' : 'acciones' }}</p>
            </a>
            @foreach ($resumen as $i => $r)
                <a href="{{ $url(['usuario' => $r['id'] ?: null]) }}"
                   class="shrink-0 w-44 sm:w-auto rounded-2xl p-3 ring-2 {{ $usuario && (int) $usuario === (int) $r['id'] ? 'ring-indigo-500 bg-indigo-50' : 'ring-transparent bg-white hover:ring-gray-200' }} shadow-sm">
                    <div class="flex items-center gap-2">
                        <span class="h-10 w-10 shrink-0 rounded-full {{ $avatar[$i % count($avatar)] }} text-white flex items-center justify-center text-sm font-black">{{ $iniciales($r['nombre']) }}</span>
                        <div class="min-w-0"><p class="font-bold text-gray-800 leading-tight truncate" title="{{ $r['nombre'] }}">{{ $r['nombre'] }}</p>
                            <p class="text-xs text-gray-500">{{ $r['rol'] }}</p></div>
                    </div>
                    <p class="mt-2 text-xs text-gray-600">{{ $r['acciones'] }} {{ $r['acciones'] === 1 ? 'acción' : 'acciones' }} · {{ $r['desde'] === $r['hasta'] ? 'a las '.$r['desde'] : $r['desde'].' a '.$r['hasta'] }}</p>
                    <div class="mt-1 flex flex-wrap gap-1 text-[11px] font-bold">
                        @if ($r['cobrado'] > 0)<span class="rounded-full bg-indigo-100 text-indigo-700 px-2">Cobró {{ $soles($r['cobrado']) }}</span>@endif
                        @if ($r['alertas'])<span class="rounded-full bg-rose-100 text-rose-700 px-2">⚠ {{ $r['alertas'] }} para revisar</span>@endif
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Qué tipo de cosas ver --}}
        <div class="flex flex-wrap gap-2">
            <a href="{{ $url(['tipo' => null]) }}" class="px-3 py-1.5 rounded-full text-sm font-semibold ring-1 {{ ! $tipo ? 'bg-gray-800 text-white ring-gray-800' : 'bg-white text-gray-700 ring-gray-200' }}">Todo</a>
            @foreach ($tipos as $clave => [$nombre, $color])
                @if ($porTipo[$clave] ?? 0)
                    <a href="{{ $url(['tipo' => $clave]) }}" class="px-3 py-1.5 rounded-full text-sm font-semibold ring-1 {{ $tipo === $clave ? $colores[$color]['activo'] : $colores[$color]['chip'] }}">
                        {{ $nombre }} <span class="opacity-70">{{ $porTipo[$clave] }}</span></a>
                @endif
            @endforeach
        </div>

        {{-- La línea de tiempo, por hora --}}
        <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-6">
            @forelse ($eventos->groupBy(fn ($e) => substr($e['hora'], 11, 2)) as $hora => $grupo)
                <p class="text-xs font-black uppercase tracking-wider text-gray-400 {{ $loop->first ? '' : 'mt-5' }} mb-2">
                    {{ \Carbon\Carbon::createFromTime((int) $hora)->format('g:00 a') }}</p>
                <ol class="relative border-l-2 border-gray-100 ml-3 space-y-3">
                    @foreach ($grupo as $e)
                        @php [$nombreTipo, $color] = $tipos[$e['tipo']]; $persona = $personas[$e['usuario']] ?? null; @endphp
                        <li class="relative pl-6">
                            <span class="absolute -left-[13px] top-1 h-6 w-6 rounded-full {{ $colores[$color]['punto'] }} ring-4 ring-white flex items-center justify-center text-xs">{{ $e['icono'] }}</span>
                            <div class="rounded-xl px-3 py-2 {{ $e['alerta'] ? 'bg-rose-50 ring-1 ring-rose-200' : 'hover:bg-gray-50' }}">
                                <div class="flex flex-wrap items-baseline gap-x-2">
                                    <span class="font-mono text-xs text-gray-400">{{ substr($e['hora'], 11, 5) }}</span>
                                    @if (! $usuario)<span class="text-xs font-bold text-gray-500">{{ $persona->nombre ?? 'Sin usuario' }}</span>@endif
                                    @if ($e['alerta'])<span class="text-[10px] font-black uppercase text-rose-600">⚠ revisar</span>@endif
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <p class="font-semibold text-gray-800 leading-snug">{{ $e['titulo'] }}</p>
                                    @if ($e['monto'] !== null)<span class="shrink-0 font-bold {{ $e['alerta'] ? 'text-rose-700' : 'text-gray-800' }}">{{ $soles($e['monto']) }}</span>@endif
                                </div>
                                @if ($e['detalle'])<p class="text-sm text-gray-500 leading-snug">{{ $e['detalle'] }}</p>@endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @empty
                <p class="text-center text-gray-400 py-6">Nada de este tipo para mostrar.</p>
            @endforelse
        </div>
    @endif
</div>
@endsection
