@extends('layouts.app')
@section('title', 'Órdenes de compra')
@section('content')
@use('App\Support\OrdenesCompra')
@php
    $chip = [
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-200', 'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-200', 'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-200', 'slate' => 'bg-slate-100 text-slate-500 ring-slate-200',
    ];
@endphp
<div class="max-w-6xl mx-auto space-y-4">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-56">
            <h1 class="text-xl font-extrabold text-gray-800">🧾 Órdenes de compra</h1>
            <p class="text-sm text-gray-500">Pide al proveedor, el administrador aprueba y con un clic se vuelve compra.</p>
        </div>
        <a href="{{ route('ordenes_compra.create') }}" class="h-10 px-5 inline-flex items-center rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">+ Nueva orden</a>
    </div>

    @if (($conteo['PENDIENTE'] ?? 0) && auth()->user()->esAdmin() && $estado !== 'PENDIENTE')
        <a href="{{ route('ordenes_compra.index', ['estado' => 'PENDIENTE']) }}" class="block rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm font-semibold hover:bg-amber-100">
            ⏳ {{ $conteo['PENDIENTE'] }} orden(es) esperan tu aprobación →</a>
    @endif

    <form method="GET" class="flex flex-wrap items-center gap-2 text-sm">
        <a href="{{ route('ordenes_compra.index') }}" class="px-3 py-1.5 rounded-full font-semibold ring-1 {{ ! $estado ? 'bg-gray-800 text-white ring-gray-800' : 'bg-white text-gray-700 ring-gray-200' }}">Todas</a>
        @foreach (OrdenesCompra::ESTADOS as $clave => [$nombre, $color])
            <a href="{{ route('ordenes_compra.index', ['estado' => $clave]) }}" class="px-3 py-1.5 rounded-full font-semibold ring-1 {{ $estado === $clave ? 'bg-gray-800 text-white ring-gray-800' : $chip[$color] }}">
                {{ $nombre }} <span class="opacity-70">{{ $conteo[$clave] ?? 0 }}</span></a>
        @endforeach
        @if ($estado)<input type="hidden" name="estado" value="{{ $estado }}">@endif
        <input type="search" name="q" value="{{ $q }}" placeholder="Proveedor, RUC o N° de orden" class="ml-auto h-9 w-full sm:w-64 rounded-xl border-gray-300 text-sm">
    </form>

    <div class="bg-white rounded-2xl shadow-sm divide-y divide-gray-100">
        @forelse ($ordenes as $o)
            @php [$nombreEstado, $color] = OrdenesCompra::ESTADOS[$o->estado]; @endphp
            <a href="{{ route('ordenes_compra.show', $o->id) }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-gray-50">
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-gray-800">{{ OrdenesCompra::codigo($o) }} · <span class="font-semibold">{{ $o->prov_nom }}</span></p>
                    <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($o->fecha)->format('d/m/Y') }} · pidió {{ $o->pidio ?: '—' }}
                        @if ($o->fecha_entrega) · entrega {{ \Carbon\Carbon::parse($o->fecha_entrega)->format('d/m/Y') }}@endif</p>
                </div>
                <span class="rounded-full ring-1 px-2.5 py-0.5 text-xs font-bold {{ $chip[$color] }}">{{ $nombreEstado }}</span>
                <span class="w-32 text-right font-extrabold text-gray-900">{{ $o->moneda === 'USD' ? 'US$' : 'S/' }} {{ number_format($o->total, 2) }}</span>
            </a>
        @empty
            <p class="px-4 py-12 text-center text-gray-400">No hay órdenes {{ $estado ? 'en este estado' : 'todavía' }}. Crea una con “+ Nueva orden”.</p>
        @endforelse
    </div>
    <div>{{ $ordenes->links() }}</div>
</div>
@endsection
