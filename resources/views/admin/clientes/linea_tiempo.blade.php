@extends('admin.layout')
@section('title', 'Línea de tiempo · '.($cliente->nombre_comercial ?: $cliente->razon_social))

@section('content')
    {{-- Qué pasó en el sistema de la empresa un día, hora por hora: para entender un "ayer en la tarde me salió un error" --}}
    @php
        $colorTipo = ['emerald' => 'bg-emerald-100 text-emerald-700', 'indigo' => 'bg-indigo-100 text-indigo-700', 'amber' => 'bg-amber-100 text-amber-800',
            'rose' => 'bg-rose-100 text-rose-700', 'orange' => 'bg-orange-100 text-orange-700', 'sky' => 'bg-sky-100 text-sky-700', 'violet' => 'bg-violet-100 text-violet-700'];
    @endphp
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="{{ route('admin.clientes.diagnostico', $cliente->id) }}" class="h-9 px-3 rounded-xl bg-white shadow-sm text-sm font-semibold flex items-center">← Diagnóstico</a>
        <div class="flex-1 min-w-[14rem]">
            <h1 class="text-xl font-bold">🕑 Línea de tiempo</h1>
            <p class="text-sm text-slate-500">{{ $cliente->nombre_comercial ?: $cliente->razon_social }} · RUC {{ $cliente->ruc }}</p>
        </div>
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="date" name="fecha" value="{{ $fecha }}" max="{{ now()->toDateString() }}" onchange="this.form.submit()" class="h-9 rounded-xl border-slate-300 text-sm">
            @if ($sucursales->count() > 1)
                <select name="sucursal" onchange="this.form.submit()" class="h-9 rounded-xl border-slate-300 text-sm">
                    @foreach ($sucursales as $id => $nombre)<option value="{{ $id }}" @selected($id === $sucursal)>{{ $nombre }}</option>@endforeach
                </select>
            @endif
        </form>
    </div>

    @if ($errores->isNotEmpty())
        <section class="mb-4 rounded-2xl bg-rose-50 ring-1 ring-rose-200 p-4">
            <h2 class="font-bold text-rose-800 mb-2">🚨 Errores del sistema ese día</h2>
            <ul class="space-y-1 text-sm">
                @foreach ($errores as $e)
                    <li><b>{{ \Carbon\Carbon::parse($e->ultima_vez)->format('H:i') }}</b> · {{ $e->usuario ?: '—' }} · <span class="text-slate-500">{{ $e->url }}</span>
                        <span class="block text-xs text-rose-700">{{ mb_substr($e->mensaje, 0, 220) }} ({{ $e->veces }} vez/veces)</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    <div x-data="{ tipo: '' }" class="bg-white rounded-2xl shadow-sm">
        <div class="flex flex-wrap gap-1 p-3 border-b border-slate-100 text-xs font-bold">
            <button type="button" @click="tipo = ''" :class="tipo === '' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'" class="px-3 py-1.5 rounded-lg">Todo ({{ $eventos->count() }})</button>
            @foreach ($tipos as $clave => [$nombre, $color])
                @if ($n = $eventos->where('tipo', $clave)->count())
                    <button type="button" @click="tipo = @js($clave)" :class="tipo === @js($clave) ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'" class="px-3 py-1.5 rounded-lg">{{ $nombre }} ({{ $n }})</button>
                @endif
            @endforeach
        </div>
        <ol class="divide-y divide-slate-100">
            @forelse ($eventos as $e)
                <li x-show="tipo === '' || tipo === @js($e['tipo'])" class="flex gap-3 px-4 py-2.5 text-sm {{ $e['alerta'] ? 'bg-rose-50/60' : '' }}">
                    <span class="w-12 shrink-0 font-mono text-slate-500">{{ \Carbon\Carbon::parse($e['hora'])->format('H:i') }}</span>
                    <span class="shrink-0">{{ $e['icono'] }}</span>
                    <span class="flex-1 min-w-0">
                        <span class="font-semibold text-slate-800">{{ $e['titulo'] }}</span>
                        @if ($e['detalle'])<span class="block text-xs text-slate-500 break-words">{{ $e['detalle'] }}</span>@endif
                        <span class="inline-block mt-0.5 px-1.5 rounded text-[10px] font-bold {{ $colorTipo[$tipos[$e['tipo']][1] ?? ''] ?? 'bg-slate-100 text-slate-600' }}">{{ $tipos[$e['tipo']][0] ?? $e['tipo'] }}</span>
                        <span class="text-[11px] text-slate-400">{{ $e['usuario'] ? ($usuarios[$e['usuario']] ?? 'Usuario '.$e['usuario']) : '' }}</span>
                    </span>
                    @if ($e['monto'] !== null)<span class="shrink-0 font-semibold text-slate-700">S/ {{ number_format($e['monto'], 2) }}</span>@endif
                </li>
            @empty
                <li class="px-4 py-10 text-center text-slate-400">No hubo movimientos ese día en esta sucursal.</li>
            @endforelse
        </ol>
    </div>
@endsection
