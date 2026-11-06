@extends('layouts.app')
@section('title', $codigo)
@section('content')
@use('App\Support\OrdenesCompra')
@php
    [$nombreEstado, $color] = OrdenesCompra::ESTADOS[$orden->estado];
    $chip = ['amber' => 'bg-amber-100 text-amber-800', 'emerald' => 'bg-emerald-100 text-emerald-800', 'rose' => 'bg-rose-100 text-rose-700',
        'indigo' => 'bg-indigo-100 text-indigo-700', 'slate' => 'bg-slate-200 text-slate-600'];
    $mon = $orden->moneda === 'USD' ? 'US$' : 'S/';
    $esAdmin = auth()->user()->esAdmin();
@endphp
<div class="max-w-5xl mx-auto space-y-4">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 flex flex-wrap items-start gap-3">
        <div class="flex-1 min-w-56">
            <a href="{{ route('ordenes_compra.index') }}" class="text-xs font-semibold text-indigo-700 hover:underline">← Órdenes de compra</a>
            <h1 class="text-xl font-extrabold text-gray-800">{{ $codigo }} <span class="ml-1 align-middle rounded-full px-2.5 py-0.5 text-xs font-bold {{ $chip[$color] }}">{{ $nombreEstado }}</span></h1>
            <p class="text-sm text-gray-600"><b>{{ $orden->prov_nom }}</b> · {{ $orden->prov_num }}</p>
            <p class="text-xs text-gray-500">Pidió {{ $pidio ?: '—' }} el {{ \Carbon\Carbon::parse($orden->fecha)->format('d/m/Y') }}
                @if ($aprobo) · {{ $orden->estado === 'RECHAZADA' ? 'Rechazó' : 'Aprobó' }} {{ $aprobo }} el {{ \Carbon\Carbon::parse($orden->aprobada_at)->format('d/m/Y H:i') }}@endif</p>
            @if ($orden->motivo)<p class="mt-1 text-sm text-rose-700">Motivo: {{ $orden->motivo }}</p>@endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('ordenes_compra.imprimir', $orden->id) }}" target="_blank" class="h-10 px-4 inline-flex items-center rounded-xl bg-white ring-1 ring-gray-300 text-sm font-semibold hover:bg-gray-50">🖨 Imprimir / PDF</a>
            @if ($orden->estado === 'PENDIENTE')
                <a href="{{ route('ordenes_compra.edit', $orden->id) }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-white ring-1 ring-gray-300 text-sm font-semibold hover:bg-gray-50">Editar</a>
            @endif
            @if ($orden->estado === 'APROBADA' && (auth()->user()->esAdminOCaja()))
                <a href="{{ route('compras.create', ['orden' => $orden->id]) }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">→ Convertir en compra</a>
            @endif
        </div>
    </div>

    {{-- El administrador decide --}}
    @if ($orden->estado === 'PENDIENTE')
        @if ($esAdmin)
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex flex-wrap items-end gap-3" x-data="{ rechazar: false }">
                <p class="w-full font-semibold text-amber-900">Esta orden espera tu aprobación.</p>
                <form method="POST" action="{{ route('ordenes_compra.decidir', $orden->id) }}" x-show="!rechazar">
                    @csrf <input type="hidden" name="accion" value="aprobar">
                    <button class="h-10 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">✔ Aprobar</button>
                </form>
                <button type="button" x-show="!rechazar" @click="rechazar = true" class="h-10 px-5 rounded-xl bg-white ring-1 ring-rose-300 text-rose-700 text-sm font-bold">Rechazar</button>
                <form method="POST" action="{{ route('ordenes_compra.decidir', $orden->id) }}" x-show="rechazar" x-cloak class="flex flex-wrap items-end gap-2 w-full">
                    @csrf <input type="hidden" name="accion" value="rechazar">
                    <label class="flex-1 min-w-56 text-xs font-semibold text-gray-600">¿Por qué se rechaza?
                        <input name="motivo" required maxlength="255" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm" placeholder="Ej.: el precio está alto, pide otra cotización"></label>
                    <button class="h-10 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-bold">Rechazar orden</button>
                    <button type="button" @click="rechazar = false" class="h-10 px-4 rounded-xl bg-white ring-1 ring-gray-300 text-sm">Cancelar</button>
                </form>
            </div>
        @else
            <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">⏳ Espera la aprobación del administrador.</div>
        @endif
    @endif

    @if ($compra)
        <div class="rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-800 px-4 py-3 text-sm">
            Se convirtió en la compra <a href="{{ route('compras.edit', $compra->com_cab_id) }}" class="font-bold underline">{{ $compra->com_doc_ser }}-{{ $compra->com_doc_num }}</a>
            @if ($compra->est_compra === 'Anulado') (anulada) @elseif ($compra->en_transito) · 🚚 la mercadería está en tránsito @else · ✔ ya está en el almacén @endif
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr><th class="text-left px-4 py-3">Producto</th><th class="text-right px-4 py-3">Cantidad</th><th class="text-right px-4 py-3">Costo unit.</th><th class="text-right px-4 py-3">Total</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($items as $i)
                    <tr>
                        <td class="px-4 py-2"><span class="font-semibold">{{ $i->pronom }}</span> <span class="text-xs text-gray-400">{{ $i->procod }}</span></td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">{{ rtrim(rtrim(number_format($i->cantidad, 3, '.', ''), '0'), '.') }} {{ $i->umecod }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">{{ $mon }} {{ number_format($i->costo, 2) }}</td>
                        <td class="px-4 py-2 text-right font-semibold whitespace-nowrap">{{ $mon }} {{ number_format($i->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="3" class="px-4 py-3 text-right font-bold">Total (con IGV)</td><td class="px-4 py-3 text-right text-lg font-extrabold text-indigo-700 whitespace-nowrap">{{ $mon }} {{ number_format($orden->total, 2) }}</td></tr></tfoot>
        </table>
    </div>

    <div class="grid sm:grid-cols-3 gap-3 text-sm">
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-bold uppercase text-gray-500">Entrega</p>
            <p class="font-semibold">{{ $orden->fecha_entrega ? \Carbon\Carbon::parse($orden->fecha_entrega)->format('d/m/Y') : 'Sin fecha' }}</p>
            <p class="text-gray-500">{{ $almacen ?: 'Almacén por definir' }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-bold uppercase text-gray-500">Condiciones</p><p>{{ $orden->condiciones ?: '—' }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-bold uppercase text-gray-500">Observaciones</p><p>{{ $orden->observaciones ?: '—' }}</p></div>
    </div>

    @if (! in_array($orden->estado, ['COMPRADA', 'ANULADA'], true) && ($esAdmin || (int) $orden->IdUsuario === (int) auth()->id()))
        <details class="bg-white rounded-2xl shadow-sm">
            <summary class="cursor-pointer list-none px-4 py-3 text-sm font-semibold text-rose-600">Anular esta orden ▾</summary>
            <form method="POST" action="{{ route('ordenes_compra.anular', $orden->id) }}" class="px-4 pb-4 flex flex-wrap items-end gap-2">
                @csrf
                <label class="flex-1 min-w-56 text-xs font-semibold text-gray-600">Motivo
                    <input name="motivo" required minlength="4" maxlength="255" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
                <button class="h-10 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-bold">Anular</button>
            </form>
        </details>
    @endif
</div>
@endsection
