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

    {{-- Candado de meses: lo ya declarado no se toca (el mismo cierre del Libro Diario) --}}
    @php
        $nombreMes = fn ($p) => \Illuminate\Support\Str::ucfirst(\Carbon\Carbon::createFromFormat('!Ym', $p)->locale('es')->isoFormat('MMMM YYYY'));
        $mesCerrado = fn ($fecha) => $fecha && in_array(substr(str_replace('-', '', (string) $fecha), 0, 6), $mesesCerrados, true);
    @endphp
    @if (auth()->user()->esAdmin())
        <details class="bg-white rounded-2xl shadow-sm" @if (session('success') && str_contains((string) session('success'), 'cerrado') || str_contains((string) session('success'), 'abierto')) open @endif>
            <summary class="cursor-pointer list-none px-4 py-3 flex flex-wrap items-center gap-2">
                <span class="font-bold text-slate-700">🔒 Meses cerrados</span>
                @forelse (array_slice($mesesCerrados, 0, 4) as $p)
                    <span class="rounded-full bg-slate-100 text-slate-600 text-xs font-semibold px-2 py-0.5">{{ $nombreMes($p) }}</span>
                @empty
                    <span class="text-xs text-slate-400">Ninguno todavía</span>
                @endforelse
                @if (count($mesesCerrados) > 4)<span class="text-xs text-slate-400">y {{ count($mesesCerrados) - 4 }} más</span>@endif
                <span class="ml-auto text-xs text-indigo-700 font-semibold">Administrar ▾</span>
            </summary>
            <div class="px-4 pb-4 space-y-3 text-sm">
                <p class="text-slate-500">Cuando ya declaraste un mes, ciérralo: sus compras y gastos ya no se podrán registrar, editar ni anular
                    (el stock, los costos y lo declarado quedan como están). Es el mismo cierre del Libro Diario.</p>
                <form method="POST" action="{{ route('compras.mes') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="hidden" name="accion" value="cerrar">
                    <label class="text-xs font-semibold text-slate-500">Mes a cerrar
                        <input type="month" name="mes" value="{{ now()->subMonth()->format('Y-m') }}" max="{{ now()->format('Y-m') }}" required class="block mt-1 h-10 rounded-xl border-slate-300 text-sm"></label>
                    <button class="h-10 px-4 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-sm font-bold"
                            onclick="return confirm('¿Cerrar este mes? Sus compras y gastos ya no se podrán cambiar.')">🔒 Cerrar mes</button>
                </form>
                @if ($mesesCerrados)
                    <div class="flex flex-wrap gap-2">
                        @foreach ($mesesCerrados as $p)
                            <form method="POST" action="{{ route('compras.mes') }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-50 ring-1 ring-slate-200 pl-3 pr-1 py-1">
                                @csrf
                                <input type="hidden" name="accion" value="abrir">
                                <input type="hidden" name="mes" value="{{ substr($p, 0, 4).'-'.substr($p, 4, 2) }}">
                                <span class="font-semibold text-slate-700">🔒 {{ $nombreMes($p) }}</span>
                                <button class="text-xs font-semibold text-rose-600 hover:bg-rose-50 rounded-lg px-2 py-1"
                                        onclick="return confirm('¿Reabrir {{ $nombreMes($p) }}? Se podrán cambiar sus compras y gastos (queda en la bitácora).')">Reabrir</button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>
        </details>
    @endif

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
                            @if ($c->est_compra !== 'Anulado' && in_array($c->tdocod, ['01', '03', '07', '08'], true))
                                <span class="mr-3">@include('empresas.sunat._boton_consultar', ['url' => route('compras.consultar_sunat', $c->com_cab_id),
                                    'estado' => $c->sunat_consultado ?? null ? $c->sunat_estado : null, 'consultado' => $c->sunat_consultado ?? null])</span>
                            @endif
                            @php $bloqueada = $mesCerrado($c->com_fec) || $mesCerrado($c->com_fec_ing); @endphp
                            @if ($bloqueada)<span class="text-xs font-semibold text-slate-400 mr-3" title="Mes cerrado: ya no se edita ni se anula">🔒 Mes cerrado</span>@endif
                            <a href="{{ route('compras.edit', $c->com_cab_id) }}" class="text-xs font-semibold text-indigo-700 hover:underline mr-3">
                                {{ $c->est_compra === 'Registrado' && ! $bloqueada ? 'Editar' : 'Ver' }}
                            </a>
                            @if ($c->est_compra === 'Registrado' && ! $bloqueada)
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
