@extends('layouts.app')
@section('title', 'Reporte: ' . $tx['titulo'])

@section('content')
<div class="max-w-7xl mx-auto space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('cuentas.index', $tipo) }}" class="text-sm text-slate-500 hover:text-indigo-700">← {{ $tx['titulo'] }}</a>
        <div class="flex gap-1 p-1 bg-slate-200/60 rounded-xl text-sm font-semibold ml-auto">
            @foreach (['antiguedad' => 'Antigüedad de saldos', 'estado' => 'Estado de cuenta', 'pagos' => $tx['pasado'] . 's por fecha'] as $k => $v)
                <a href="{{ route('cuentas.reporte', [$tipo, 'vista' => $k]) }}" class="px-3 h-8 inline-flex items-center rounded-lg {{ $vista === $k ? 'bg-white shadow text-indigo-700' : 'text-slate-500' }}">{{ $v }}</a>
            @endforeach
        </div>
        <a href="{{ request()->fullUrlWithQuery(['excel' => 1, 'vista' => $vista]) }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">Descargar Excel</a>
    </div>

    @if ($vista === 'antiguedad')
        @php $tot = fn($k) => $d['filas']->sum($k); @endphp
        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-slate-100">
                <h1 class="font-bold">Antigüedad de saldos {{ $tipo === 'cobrar' ? 'por cobrar' : 'por pagar' }}</h1>
                <p class="text-xs text-slate-400">Saldo pendiente de cada {{ mb_strtolower($tx['persona']) }}, según cuántos días tiene vencido · al {{ now()->format('d/m/Y') }}</p>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr><th class="text-left px-4 py-2">{{ $tx['persona'] }}</th><th class="text-right px-3 py-2">Docs</th>
                        <th class="text-right px-3 py-2">Por vencer</th><th class="text-right px-3 py-2">1-30 días</th><th class="text-right px-3 py-2">31-60</th>
                        <th class="text-right px-3 py-2">61-90</th><th class="text-right px-3 py-2">+90 días</th><th class="text-right px-4 py-2">Total</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($d['filas'] as $f)
                        <tr>
                            <td class="px-4 py-2"><a href="{{ route('cuentas.reporte', [$tipo, 'vista' => 'estado', 'persona' => $f['doc']]) }}" class="font-semibold hover:text-indigo-700">{{ $f['persona'] }}</a>
                                <span class="block text-[11px] text-slate-400">{{ $f['doc'] }}</span></td>
                            <td class="px-3 py-2 text-right">{{ $f['docs'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $f['por_vencer'] ? number_format($f['por_vencer'], 2) : '' }}</td>
                            <td class="px-3 py-2 text-right text-amber-600">{{ $f['d30'] ? number_format($f['d30'], 2) : '' }}</td>
                            <td class="px-3 py-2 text-right text-orange-600">{{ $f['d60'] ? number_format($f['d60'], 2) : '' }}</td>
                            <td class="px-3 py-2 text-right text-rose-600">{{ $f['d90'] ? number_format($f['d90'], 2) : '' }}</td>
                            <td class="px-3 py-2 text-right text-rose-700 font-semibold">{{ $f['mas90'] ? number_format($f['mas90'], 2) : '' }}</td>
                            <td class="px-4 py-2 text-right font-bold">{{ number_format($f['total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">No hay saldos pendientes.</td></tr>
                    @endforelse
                </tbody>
                @if ($d['filas']->isNotEmpty())
                    <tfoot class="bg-slate-50 font-bold">
                        <tr><td class="px-4 py-2">Total</td><td class="px-3 py-2 text-right">{{ $tot('docs') }}</td>
                            @foreach (['por_vencer', 'd30', 'd60', 'd90', 'mas90'] as $k)<td class="px-3 py-2 text-right">{{ number_format($tot($k), 2) }}</td>@endforeach
                            <td class="px-4 py-2 text-right">{{ number_format($tot('total'), 2) }}</td></tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif

    @if ($vista === 'estado')
        <div class="bg-white rounded-2xl shadow-sm">
            <form method="GET" class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-end gap-3">
                <input type="hidden" name="vista" value="estado">
                <h1 class="font-bold flex-1">Estado de cuenta</h1>
                <label class="text-xs font-semibold text-slate-500">{{ $tx['persona'] }}
                    <select name="persona" onchange="this.form.submit()" class="block mt-1 h-10 min-w-[260px] rounded-xl border-slate-300 text-sm">
                        @forelse ($d['personas'] as $p)
                            <option value="{{ $p['doc'] }}" @selected($p['doc'] === $d['persona'])>{{ $p['nombre'] }} · {{ $p['doc'] }}</option>
                        @empty
                            <option>Sin {{ mb_strtolower($tx['persona']) }}s con crédito</option>
                        @endforelse
                    </select>
                </label>
            </form>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                        <tr><th class="text-left px-4 py-2">Fecha</th><th class="text-left px-4 py-2">Documento / pago</th><th class="text-left px-4 py-2">Vence</th>
                            <th class="text-right px-4 py-2">Cargo</th><th class="text-right px-4 py-2">{{ $tx['pasado'] }}</th><th class="text-right px-4 py-2">Saldo</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($d['cuentas'] as $c)
                            <tr class="border-t border-slate-100 bg-slate-50/50">
                                <td class="px-4 py-2">{{ \Carbon\Carbon::parse($c->fecha)->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 font-semibold">{{ $tiposDoc[$c->tdocod] ?? '' }} {{ $c->serie }}-{{ $c->numero }}
                                    <span class="ml-1 text-[10px] font-bold {{ $c->estado_cob === 'PAGADO' ? 'text-emerald-600' : 'text-amber-600' }}">{{ $c->estado_cob }}</span></td>
                                <td class="px-4 py-2">{{ $c->fec_ven ? \Carbon\Carbon::parse($c->fec_ven)->format('d/m/Y') : '' }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($c->total, 2) }}</td>
                                <td class="px-4 py-2"></td>
                                <td class="px-4 py-2 text-right font-bold">{{ number_format($c->saldo, 2) }}</td>
                            </tr>
                            @foreach ($c->pagos as $p)
                                <tr class="text-slate-600">
                                    <td class="px-4 py-1.5 pl-8">{{ \Carbon\Carbon::parse($p->fec_dep)->format('d/m/Y') }}</td>
                                    <td class="px-4 py-1.5">↳ {{ $p->numero_recibo }} <span class="text-xs text-slate-400">{{ $p->medios->map(fn($m) => $m->nom_med_pag)->implode(' + ') }}{{ $p->num_oper ? ' · Op. ' . $p->num_oper : '' }}</span></td>
                                    <td></td><td></td>
                                    <td class="px-4 py-1.5 text-right text-emerald-700">{{ number_format($p->abono, 2) }}</td>
                                    <td class="px-4 py-1.5 text-right">{{ number_format($p->saldo_detalle, 2) }}</td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Sin documentos al crédito.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($d['cuentas']->isNotEmpty())
                        <tfoot class="border-t-2 border-slate-200 font-bold">
                            <tr><td colspan="3" class="px-4 py-2">Totales</td><td class="px-4 py-2 text-right">{{ number_format($d['cuentas']->sum('total'), 2) }}</td>
                                <td class="px-4 py-2 text-right text-emerald-700">{{ number_format($d['cuentas']->sum('abono'), 2) }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($d['cuentas']->sum('saldo'), 2) }}</td></tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif

    @if ($vista === 'pagos')
        <div class="bg-white rounded-2xl shadow-sm">
            <form method="GET" class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-end gap-3">
                <input type="hidden" name="vista" value="pagos">
                <h1 class="font-bold flex-1">{{ $tx['pasado'] }}s registrados</h1>
                <label class="text-xs font-semibold text-slate-500">Desde<input type="date" name="desde" value="{{ $d['desde'] }}" class="block mt-1 h-10 rounded-xl border-slate-300 text-sm"></label>
                <label class="text-xs font-semibold text-slate-500">Hasta<input type="date" name="hasta" value="{{ $d['hasta'] }}" class="block mt-1 h-10 rounded-xl border-slate-300 text-sm"></label>
                <button class="h-10 px-4 rounded-xl bg-slate-800 text-white text-sm font-semibold">Ver</button>
            </form>
            @if ($d['porMedio']->isNotEmpty())
                <div class="px-4 py-3 flex flex-wrap gap-2 border-b border-slate-100">
                    @foreach ($d['porMedio'] as $medio => $monto)
                        <span class="px-3 py-1.5 rounded-xl bg-slate-50 text-sm"><span class="text-slate-500">{{ $medio }}</span> <strong>{{ number_format($monto, 2) }}</strong></span>
                    @endforeach
                    <span class="px-3 py-1.5 rounded-xl bg-emerald-50 text-sm ml-auto">Total <strong>{{ number_format($d['pagos']->sum('abono'), 2) }}</strong></span>
                </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                        <tr><th class="text-left px-4 py-2">Fecha</th><th class="text-left px-4 py-2">Recibo</th><th class="text-left px-4 py-2">Documento</th>
                            <th class="text-left px-4 py-2">{{ $tx['persona'] }}</th><th class="text-left px-4 py-2">Medios</th><th class="text-right px-4 py-2">Monto</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse ($d['pagos'] as $p)
                            <tr>
                                <td class="px-4 py-2">{{ \Carbon\Carbon::parse($p->fec_dep)->format('d/m/Y') }}</td>
                                <td class="px-4 py-2"><a href="{{ route('cuentas.recibo', [$tipo, $p->id]) }}" target="_blank" class="font-semibold text-indigo-700">{{ $p->numero_recibo }}</a></td>
                                <td class="px-4 py-2">{{ $p->doc['numero'] }}</td>
                                <td class="px-4 py-2">{{ $p->doc['nombre'] }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ $p->medios }}{{ $p->num_oper ? ' · Op. ' . $p->num_oper : '' }}</td>
                                <td class="px-4 py-2 text-right font-bold">{{ number_format($p->abono, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Sin {{ mb_strtolower($tx['pasado']) }}s en ese rango.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
