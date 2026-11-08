@extends('layouts.app')
@section('title', 'Descarga automática')
@section('content')
@use('App\Support\EstudioDescargas')
@use('App\Support\Contabilidad\Contabilidad')
@php
    $chip = ['slate' => 'bg-slate-100 text-slate-600', 'sky' => 'bg-sky-50 text-sky-700', 'emerald' => 'bg-emerald-50 text-emerald-700',
        'rose' => 'bg-rose-50 text-rose-700', 'amber' => 'bg-amber-50 text-amber-800'];
    $tareas = collect(EstudioDescargas::TAREAS);
@endphp
<div class="max-w-5xl mx-auto space-y-4">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-1">
        <h1 class="text-xl font-extrabold text-gray-800">📥 Descarga automática (SIRE y XML)</h1>
        <p class="text-sm text-gray-500">El día que elijas, el sistema trae solo, de todas las empresas, lo del mes anterior: las propuestas del SIRE y los XML de las compras.
            Al día siguiente el contador ya tiene todo listo para trabajar.</p>
    </div>

    @if ($esAdmin)
        <form method="POST" action="{{ route('estudio.descargas.programacion') }}" class="bg-white rounded-2xl shadow-sm p-5 space-y-4" x-data="{ activo: @js((bool) $config->activo) }">
            @csrf
            <div class="flex flex-wrap items-center gap-3">
                <p class="flex-1 font-bold text-gray-800">⏰ Programación</p>
                <input type="hidden" name="activo" :value="activo ? 1 : 0">
                <button type="button" @click="activo = !activo" class="h-9 px-4 rounded-full text-sm font-bold ring-1"
                        :class="activo ? 'bg-emerald-600 text-white ring-emerald-600' : 'bg-white text-gray-600 ring-gray-300'" x-text="activo ? '✅ Activada' : 'Apagada'"></button>
            </div>
            <div class="grid sm:grid-cols-2 gap-3" :class="!activo && 'opacity-50'">
                <label class="text-sm font-semibold text-gray-700">Día de cada mes
                    <select name="dia" class="mt-1 w-full h-11 rounded-xl border-gray-300">
                        @foreach (range(1, 28) as $d)<option value="{{ $d }}" @selected((int) $config->dia === $d)>Día {{ $d }}</option>@endforeach
                    </select></label>
                <label class="text-sm font-semibold text-gray-700">A partir de la hora
                    <select name="hora" class="mt-1 w-full h-11 rounded-xl border-gray-300">
                        @foreach (range(0, 23) as $h)<option value="{{ $h }}" @selected((int) $config->hora === $h)>{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}:00</option>@endforeach
                    </select></label>
            </div>
            <div class="flex flex-wrap gap-4 text-sm" :class="!activo && 'opacity-50'">
                @foreach (EstudioDescargas::TAREAS as $clave => $nombre)
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="{{ $clave }}" value="1" @checked($config->{$clave}) class="rounded border-gray-300"> {{ $nombre }}</label>
                @endforeach
            </div>
            <p class="text-xs text-gray-500">Los XML usan la propuesta del SIRE de compras para saber qué facturas pedir, y cada XML usa créditos de json.pe. Las empresas sin credenciales se saltan y quedan anotadas abajo.
                Se trae siempre el mes anterior (el día 5 de noviembre se trae octubre). Recomendado de madrugada.</p>
            <div class="flex justify-end"><button class="h-11 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">Guardar programación</button></div>
        </form>
    @endif

    <div class="bg-white rounded-2xl shadow-sm">
        <div class="p-4 sm:p-5 border-b border-gray-100 flex flex-wrap items-center gap-3">
            <form method="GET" class="flex items-center gap-2">
                <label class="text-sm font-semibold text-gray-700">Periodo</label>
                <select name="periodo" onchange="this.form.submit()" class="h-10 rounded-xl border-gray-300 text-sm">
                    @foreach ($periodos->push($periodo)->push($siguiente)->unique()->sortDesc() as $p)
                        <option value="{{ $p }}" @selected($p === $periodo)>{{ Contabilidad::nombrePeriodo($p) }}</option>
                    @endforeach
                </select>
            </form>
            <p class="flex-1 text-sm text-gray-600">
                @if ($resumen['empresas'])
                    <b class="text-emerald-700">{{ $resumen['listas'] }} listas</b>
                    @if ($resumen['pendientes']) · <b class="text-sky-700">{{ $resumen['pendientes'] }} en curso</b>@endif
                    @if ($resumen['errores']) · <b class="text-rose-700">{{ $resumen['errores'] }} con error</b>@endif
                    @if ($resumen['omitidas']) · <b class="text-amber-700">{{ $resumen['omitidas'] }} sin credenciales</b>@endif
                    · {{ $resumen['empresas'] }} empresa(s)
                @else
                    Aún no se descargó este periodo.
                @endif
            </p>
            @if ($esAdmin)
                <form method="POST" action="{{ route('estudio.descargas.ahora') }}">
                    @csrf <input type="hidden" name="periodo" value="{{ $periodo }}">
                    <button class="h-10 px-4 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-sm font-bold">
                        {{ $resumen['errores'] || $resumen['omitidas'] ? '🔁 Reintentar lo que falló' : '📥 Descargar ahora' }}</button>
                </form>
            @endif
        </div>
        @if ($resumen['pendientes'])
            <p class="px-5 pt-3 text-xs text-sky-700">Se va avanzando solo cada pocos minutos (SUNAT tarda en preparar las propuestas). Puedes cerrar esta pantalla; vuelve más tarde.</p>
        @endif
        <div class="divide-y divide-gray-100">
            @forelse ($empresas as $ruc => $filas)
                <div class="px-4 sm:px-5 py-3 space-y-1.5">
                    <p class="font-bold text-gray-800">{{ $filas->first()->NomEmpresa }} <span class="font-mono text-xs text-gray-400">{{ $ruc }}</span></p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($filas->sortBy(fn ($f) => array_search($f->tarea, array_keys(EstudioDescargas::TAREAS))) as $f)
                            @php [$nombreEstado, $color] = EstudioDescargas::ESTADOS[$f->estado]; @endphp
                            <span class="inline-flex flex-wrap items-center gap-1.5 rounded-xl px-2.5 py-1 text-xs {{ $chip[$color] }}" title="{{ $f->mensaje }}">
                                <b>{{ $tareas[$f->tarea] ?? $f->tarea }}:</b> {{ $nombreEstado }}
                                @if ($f->mensaje)<span class="opacity-80">· {{ \Illuminate\Support\Str::limit($f->mensaje, 70) }}</span>@endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="px-5 py-10 text-center text-gray-400">Nada descargado en este periodo.{{ $esAdmin ? ' Toca "Descargar ahora" o programa el día.' : '' }}</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
