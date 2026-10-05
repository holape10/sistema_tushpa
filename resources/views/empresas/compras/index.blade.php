@extends('layouts.app')
@section('title', 'Compras')

@section('content')
<div class="max-w-7xl mx-auto space-y-4">
    @if (session('success'))
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap items-end gap-3">
        <form method="GET" class="flex flex-wrap items-end gap-2 flex-1">
            <label class="text-xs font-semibold text-slate-500">Desde
                <input type="date" name="desde" value="{{ $desde }}" class="block mt-1 h-10 rounded-xl border-slate-300 text-sm">
            </label>
            <label class="text-xs font-semibold text-slate-500">Hasta
                <input type="date" name="hasta" value="{{ $hasta }}" class="block mt-1 h-10 rounded-xl border-slate-300 text-sm">
            </label>
            <label class="text-xs font-semibold text-slate-500">Estado
                <select name="estado" class="block mt-1 h-10 rounded-xl border-slate-300 text-sm">
                    <option value="Registrado" @selected($estado === 'Registrado')>Registradas</option>
                    <option value="Anulado" @selected($estado === 'Anulado')>Anuladas</option>
                    <option value="todos" @selected($estado === 'todos')>Todas</option>
                </select>
            </label>
            <label class="text-xs font-semibold text-slate-500 flex-1 min-w-[180px]">Buscar
                <input type="search" name="q" value="{{ $q }}" placeholder="Proveedor, RUC o serie-número" class="block w-full mt-1 h-10 rounded-xl border-slate-300 text-sm">
            </label>
            <button class="h-10 px-4 rounded-xl bg-slate-800 text-white text-sm font-semibold">Filtrar</button>
        </form>
        <a href="{{ route('compras.create') }}" class="h-10 px-5 inline-flex items-center rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">+ Nueva compra</a>
    </div>

    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs font-semibold text-slate-500">Compras</p>
            <p class="text-2xl font-extrabold">{{ $totales->cantidad ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs font-semibold text-slate-500">Total comprado (S/)</p>
            <p class="text-2xl font-extrabold text-indigo-700">{{ number_format($totales->total ?? 0, 2) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs font-semibold text-slate-500">Por pagar (crédito)</p>
            <p class="text-2xl font-extrabold {{ ($totales->por_pagar ?? 0) > 0 ? 'text-rose-600' : 'text-slate-300' }}">{{ number_format($totales->por_pagar ?? 0, 2) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-4 py-3">Fecha</th>
                    <th class="text-left px-4 py-3">Documento</th>
                    <th class="text-left px-4 py-3">Proveedor</th>
                    <th class="text-left px-4 py-3">Pago</th>
                    <th class="text-right px-4 py-3">Total</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($compras as $c)
                    <tr class="{{ $c->est_compra === 'Anulado' ? 'bg-rose-50/40 text-slate-400' : '' }}">
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($c->com_fec)->format('d/m/Y') }}
                            @if ($c->com_fec_ing && $c->com_fec_ing !== $c->com_fec)
                                <span class="block text-[11px] text-slate-400">Ingresó {{ \Carbon\Carbon::parse($c->com_fec_ing)->format('d/m/Y') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-semibold">{{ $c->com_doc_ser }}-{{ $c->com_doc_num }}</span>
                            <span class="block text-[11px] text-slate-400">{{ $documentos[$c->tdocod] ?? $c->tdocod }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-semibold">{{ $c->prov_raz }}</span>
                            <span class="block text-[11px] text-slate-400">{{ $c->prov_ruc }}</span>
                        </td>
                        <td class="px-4 py-3">
                            {{ $c->cre_dia_nom }}
                            @if ($c->saldofactura > 0)
                                <span class="block text-[11px] text-rose-600">Debe {{ number_format($c->saldofactura, 2) }} · vence {{ \Carbon\Carbon::parse($c->com_fec_ven)->format('d/m/Y') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-bold whitespace-nowrap">
                            {{ $c->mon_id === 'USD' ? '$' : 'S/' }} {{ number_format($c->total_com, 2) }}
                            @if ($c->est_compra === 'Anulado')<span class="block text-[11px] font-semibold text-rose-600">ANULADA</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('compras.edit', $c->com_cab_id) }}" class="text-xs font-semibold text-indigo-700 hover:underline mr-3">
                                {{ $c->est_compra === 'Registrado' ? 'Editar' : 'Ver' }}
                            </a>
                            @if ($c->est_compra === 'Registrado')
                                <form method="POST" action="{{ route('compras.anular', $c->com_cab_id) }}" class="inline"
                                      onsubmit="return confirm('¿Anular la compra {{ $c->com_doc_ser }}-{{ $c->com_doc_num }}? Su mercadería saldrá del stock.')">
                                    @csrf
                                    <button class="text-xs font-semibold text-rose-600 hover:underline">Anular</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">No hay compras en este rango. Registra una con “+ Nueva compra”.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $compras->links() }}</div>
</div>
@endsection
