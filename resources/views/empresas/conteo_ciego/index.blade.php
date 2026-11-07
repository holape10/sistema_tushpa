@extends('layouts.app')
@section('title', 'Inventario ciego')
@section('content')
@use('App\Support\ConteosCiegos')
@php
    $chip = ['amber' => 'bg-amber-50 text-amber-800 ring-amber-200', 'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'slate' => 'bg-slate-100 text-slate-500 ring-slate-200'];
@endphp
<div class="max-w-4xl mx-auto space-y-4">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-56">
            <h1 class="text-xl font-extrabold text-gray-800">🙈 Inventario ciego</h1>
            <p class="text-sm text-gray-500">El almacenero cuenta sin ver el stock; {{ $revisa ? 'tú revisas las diferencias y decides si ajustas' : 'el administrador revisa las diferencias' }}. Recomendado: una vez por semana.</p>
        </div>
        <a href="{{ route('conteo_ciego.create') }}" class="h-10 px-5 inline-flex items-center rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">+ Nuevo conteo</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm divide-y divide-gray-100">
        @forelse ($conteos as $c)
            @php [$nombreEstado, $color] = ConteosCiegos::ESTADOS[$c->estado]; @endphp
            <div class="flex flex-wrap items-center gap-3 px-4 sm:px-5 py-3">
                <span class="font-mono font-bold text-indigo-700">N° {{ $c->id }}</span>
                <span class="flex-1 min-w-48 text-sm text-gray-700">{{ \Carbon\Carbon::parse($c->created_at)->format('d/m/Y H:i') }} · {{ $c->almacen }} · {{ $c->lineas }} producto(s) · contó {{ $c->conto }}</span>
                @if ($revisa)
                    <span class="text-sm font-bold {{ $c->valor < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $c->valor < 0 ? 'faltante' : 'diferencia' }} S/ {{ number_format((float) $c->valor, 2) }}</span>
                    <a href="{{ route('conteo_ciego.show', $c->id) }}" class="text-sm font-semibold text-indigo-700 hover:underline">Revisar →</a>
                @endif
                <span class="px-2.5 py-1 rounded-full text-xs font-bold ring-1 {{ $chip[$color] }}">{{ $nombreEstado }}</span>
            </div>
        @empty
            <p class="px-5 py-10 text-center text-gray-400">Aún no hay conteos.</p>
        @endforelse
    </div>
    {{ $conteos->links() }}
</div>
@endsection
