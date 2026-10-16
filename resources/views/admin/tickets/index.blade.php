@extends('admin.layout')
@section('title', 'Tickets de soporte')

@section('content')
    @php
        $colorEstado = ['ABIERTO' => 'bg-amber-100 text-amber-800', 'EN_PROCESO' => 'bg-sky-100 text-sky-800', 'RESPONDIDO' => 'bg-emerald-100 text-emerald-800', 'CERRADO' => 'bg-slate-100 text-slate-500'];
        $pendientes = (int) $conteo->except('CERRADO')->sum();
    @endphp

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold">Tickets de soporte</h1>
            <p class="text-sm text-slate-500">Fallas y consultas que envían tus empresas desde Soporte de su sistema.</p>
        </div>
        <form method="GET" class="ml-auto flex gap-2">
            <input type="hidden" name="estado" value="{{ $estado }}">
            <input name="q" value="{{ $q }}" placeholder="Buscar asunto, empresa, RUC o N°" class="rounded-xl border-slate-300 text-sm w-64">
        </form>
    </div>

    <div class="flex flex-wrap gap-1 p-1 bg-white rounded-xl shadow-sm text-sm w-max mb-4">
        @foreach (['pendientes' => "Pendientes ({$pendientes})", 'cerrados' => 'Cerrados ('.((int) ($conteo['CERRADO'] ?? 0)).')', 'todos' => 'Todos'] as $valor => $texto)
            <a href="{{ route('admin.tickets.index', ['estado' => $valor, 'q' => $q ?: null]) }}" class="px-3 py-1.5 rounded-lg font-semibold {{ $estado === $valor ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $texto }}</a>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl shadow-sm divide-y divide-slate-100">
        @forelse ($tickets as $t)
            <a href="{{ route('admin.tickets.show', $t->id) }}" class="flex flex-wrap sm:flex-nowrap items-center gap-3 px-5 py-3 hover:bg-slate-50 {{ $t->leido_soporte ? '' : 'bg-indigo-50/60' }}">
                <span class="text-xs font-mono text-slate-400 w-12">#{{ $t->id }}</span>
                <span class="flex-1 min-w-0">
                    <span class="block font-semibold truncate">{{ $t->asunto }}
                        @unless ($t->leido_soporte)<span class="ml-1 px-1.5 rounded bg-indigo-600 text-white text-[10px] font-bold">NUEVO</span>@endunless</span>
                    <span class="block text-xs text-slate-500 truncate">{{ $t->nombre_comercial ?: $t->razon_social }} · RUC {{ $t->ruc }} · {{ $t->usuario }}</span>
                </span>
                <span class="text-xs text-slate-400 whitespace-nowrap">{{ \App\Support\Soporte::TIPOS[$t->tipo] ?? $t->tipo }}</span>
                @if ($t->prioridad === 'URGENTE')<span class="px-2 py-0.5 rounded-lg bg-rose-100 text-rose-700 text-[11px] font-bold">URGENTE</span>@endif
                <span class="px-2 py-0.5 rounded-lg text-xs font-bold {{ $colorEstado[$t->estado] ?? '' }}">{{ \App\Support\Soporte::ESTADOS[$t->estado] ?? $t->estado }}</span>
                <span class="text-xs text-slate-400 whitespace-nowrap w-24 text-right">{{ \Carbon\Carbon::parse($t->updated_at)->diffForHumans() }}</span>
            </a>
        @empty
            <p class="px-5 py-10 text-center text-slate-400">No hay tickets {{ $estado === 'pendientes' ? 'pendientes 🎉' : '' }}</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $tickets->links() }}</div>
@endsection
