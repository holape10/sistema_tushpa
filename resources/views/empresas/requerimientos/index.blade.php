@extends('layouts.app')
@section('title', 'Requerimientos del día')
@section('content')
@use('App\Support\Requerimientos')
@php
    $chip = ['amber' => 'bg-amber-50 text-amber-800 ring-amber-200', 'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'slate' => 'bg-slate-100 text-slate-500 ring-slate-200', 'sky' => 'bg-sky-50 text-sky-700 ring-sky-200'];
@endphp
<div class="max-w-5xl mx-auto space-y-4">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-56">
            <h1 class="text-xl font-extrabold text-gray-800">📝 Requerimientos del día</h1>
            <p class="text-sm text-gray-500">Cocina o almacén pide lo que falta; {{ $atiende ? 'tú lo compras, marcas lo comprado y lo registras como compra' : 'el administrador lo compra' }}.</p>
        </div>
        @if (\App\Support\Requerimientos::sedes(auth()->user())->isNotEmpty())
            <a href="{{ route('requerimientos.enviar') }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-sky-50 text-sky-800 ring-1 ring-sky-200 text-sm font-bold">🚚 Enviar a otra sede</a>
        @endif
        <a href="{{ route('requerimientos.produccion') }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 text-sm font-bold">🍳 Producción del día</a>
        <a href="{{ route('requerimientos.create') }}" class="h-10 px-5 inline-flex items-center rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">+ Nuevo requerimiento</a>
    </div>

    <div class="flex flex-wrap items-center gap-2 text-sm">
        <a href="{{ route('requerimientos.index') }}" class="px-3 py-1.5 rounded-full font-semibold ring-1 {{ ! $estado ? 'bg-gray-800 text-white ring-gray-800' : 'bg-white text-gray-700 ring-gray-200' }}">Todos</a>
        @foreach (Requerimientos::ESTADOS as $clave => [$nombre, $color])
            <a href="{{ route('requerimientos.index', ['estado' => $clave]) }}" class="px-3 py-1.5 rounded-full font-semibold ring-1 {{ $estado === $clave ? 'bg-gray-800 text-white ring-gray-800' : $chip[$color] }}">
                {{ $nombre }} <span class="opacity-70">{{ $conteo[$clave] ?? 0 }}</span></a>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl shadow-sm divide-y divide-gray-100">
        @forelse ($requerimientos as $r)
            @php [$nombreEstado, $color] = Requerimientos::ESTADOS[$r->estado]; @endphp
            <a href="{{ route('requerimientos.show', $r->id) }}" class="flex flex-wrap items-center gap-3 px-4 sm:px-5 py-3 hover:bg-gray-50">
                <span class="font-mono font-bold text-indigo-700">{{ Requerimientos::codigo($r) }}</span>
                @if ($r->urgente)<span class="rounded-md bg-rose-600 px-1.5 text-xs font-black text-white">URGENTE</span>@endif
                @if ((int) $r->sucursal_origen === (int) $sucursal)<span class="rounded-md bg-sky-100 px-2 py-0.5 text-xs font-bold text-sky-800">🏬 te lo pide {{ $r->sede_pide }}</span>
                @elseif ($r->sucursal_origen)<span class="rounded-md bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700">pedido a {{ $r->sede_envia }}</span>@endif
                <span class="flex-1 min-w-48 text-sm text-gray-700">Para el <b>{{ \Carbon\Carbon::parse($r->fecha_para)->locale('es')->isoFormat('ddd D MMM') }}</b>
                    · {{ $r->lineas }} producto(s) · {{ $r->almacen }} · pidió {{ $r->pidio }}
                    <span class="text-gray-400">{{ \Carbon\Carbon::parse($r->created_at)->format('d/m H:i') }}</span></span>
                <span class="px-2.5 py-1 rounded-full text-xs font-bold ring-1 {{ $chip[$color] }}">{{ $nombreEstado }}</span>
            </a>
        @empty
            <p class="px-5 py-10 text-center text-gray-400">Aún no hay requerimientos.</p>
        @endforelse
    </div>
    {{ $requerimientos->links() }}
</div>
@endsection
