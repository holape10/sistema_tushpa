@extends('admin.layout')
@section('title', 'Alertas · '.$cliente->razon_social)

@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="{{ route('admin.clientes.index') }}" class="h-9 px-3 rounded-xl bg-white shadow-sm text-sm font-semibold flex items-center">← Clientes</a>
        <div class="flex-1 min-w-0">
            <h1 class="text-xl font-bold">⚠️ Alertas de la empresa</h1>
            <p class="text-sm text-slate-500">{{ $cliente->nombre_comercial ?: $cliente->razon_social }} · RUC {{ $cliente->ruc }}</p>
        </div>
        {{-- La bitácora completa de la empresa en Excel --}}
        <form method="GET" action="{{ route('admin.clientes.bitacora.excel', $cliente->id) }}" class="flex flex-wrap items-end gap-2 bg-white rounded-xl shadow-sm p-2">
            <label class="text-xs text-slate-500">Desde<input type="date" name="desde" value="{{ now()->subDays(30)->toDateString() }}" class="block rounded-lg border-slate-300 text-sm"></label>
            <label class="text-xs text-slate-500">Hasta<input type="date" name="hasta" value="{{ now()->toDateString() }}" class="block rounded-lg border-slate-300 text-sm"></label>
            <button class="h-9 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">⬇ Bitácora en Excel</button>
        </form>
    </div>

    @if (session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 text-sm">{{ session('success') }}</div>@endif

    <section class="bg-white rounded-2xl shadow-sm overflow-hidden mb-6">
        <header class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-slate-100">
            <h2 class="font-bold flex-1">Por revisar <span class="text-slate-400 font-normal">({{ $pendientes->count() }})</span></h2>
            @if ($pendientes->isNotEmpty())
                <form method="POST" action="{{ route('admin.clientes.alertas.descartar', $cliente->id) }}" onsubmit="return confirm('¿Descartar todas las alertas de esta empresa?')">
                    @csrf
                    <button class="h-8 px-3 rounded-lg bg-slate-900 text-white text-xs font-bold">✓ Descartar todas</button>
                </form>
            @endif
        </header>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Qué pasó</th><th class="px-4 py-2 text-left">Usuario</th><th class="px-4 py-2 text-left">IP</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($pendientes as $a)
                    <tr class="bg-amber-50/40">
                        <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ \Carbon\Carbon::parse($a->fecha)->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2"><span class="text-xs font-bold text-amber-700">{{ $acciones[$a->accion] ?? $a->accion }}</span>
                            <p class="text-slate-700">{{ $a->descripcion }}</p></td>
                        <td class="px-4 py-2 text-slate-600">{{ $a->usuario ?: '—' }}</td>
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $a->ip ?: '—' }}</td>
                        <td class="px-4 py-2 text-right">
                            <form method="POST" action="{{ route('admin.clientes.alertas.descartar', $cliente->id) }}">
                                @csrf <input type="hidden" name="id" value="{{ $a->id }}">
                                <button class="h-8 px-3 rounded-lg bg-white border border-slate-200 text-xs font-semibold hover:bg-slate-50" title="No es nada grave: que ya no salga">Descartar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">✓ Nada por revisar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @if ($descartadas->isNotEmpty())
        <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <h2 class="px-4 py-3 border-b border-slate-100 font-bold text-slate-500">Descartadas (últimas 30)</h2>
            <table class="w-full text-sm text-slate-500">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($descartadas as $a)
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($a->fecha)->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2">{{ $a->descripcion }}</td>
                            <td class="px-4 py-2">{{ $a->usuario }}</td>
                            <td class="px-4 py-2 text-xs whitespace-nowrap">Descartada por {{ $a->descartada_por }} · {{ \Carbon\Carbon::parse($a->descartada_el)->format('d/m H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
@endsection
