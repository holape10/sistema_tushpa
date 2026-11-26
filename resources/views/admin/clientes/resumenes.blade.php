@extends('admin.layout')
@section('title', 'Resúmenes · '.$cliente->razon_social)

@section('content')
    {{-- Los resúmenes diarios enviados de la empresa (desde su sistema, el envío programado o este panel) --}}
    @php
        $color = ['ACEPTADO' => 'bg-emerald-100 text-emerald-700', 'RECHAZADO' => 'bg-rose-100 text-rose-700', 'ERROR' => 'bg-orange-100 text-orange-700',
            'EN PROCESO' => 'bg-sky-100 text-sky-700', 'ENVIADO' => 'bg-sky-100 text-sky-700'];
    @endphp
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="{{ route('admin.clientes.index') }}" class="h-9 px-3 rounded-xl bg-white shadow-sm text-sm font-semibold flex items-center">← Clientes</a>
        <div>
            <h1 class="text-xl font-bold">Comprobantes SUNAT</h1>
            <p class="text-sm text-slate-500">{{ $cliente->nombre_comercial ?: $cliente->razon_social }} · RUC {{ $cliente->ruc }}</p>
        </div>
    </div>
    <div class="flex gap-1 p-1 bg-white rounded-xl shadow-sm text-sm mb-4 w-fit">
        @foreach (\App\Http\Controllers\Admin\ComprobanteController::FILTROS as $valor => $texto)
            <a href="{{ route('admin.clientes.comprobantes', [$cliente->id, 'filtro' => $valor]) }}"
               class="px-3 py-1.5 rounded-lg font-semibold {{ $filtro === $valor ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $texto }}</a>
        @endforeach
    </div>

    <p class="mb-3 rounded-xl bg-sky-50 text-sky-800 px-4 py-2 text-sm">
        ⏱ Los que salen <b>ENVIADO</b> o <b>EN PROCESO</b> se consultan solos en SUNAT cada hora (pasada una hora del envío). No hace falta estar consultándolos.
    </p>

    <div x-data="{ estado: {}, async consultar(id) { this.estado[id] = { estado: '⏳', mensaje: 'Consultando…' };
            const r = await fetch(@js(route('admin.clientes.comprobantes.ticket', [$cliente->id, 0])).replace(/\/0\/ticket$/, '/' + id + '/ticket'), { method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) } });
            this.estado[id] = await r.json(); } }" class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr><th class="px-3 py-2 text-left">Enviado</th><th class="px-3 py-2 text-left">Boletas del</th><th class="px-3 py-2 text-left">Resumen</th>
                    <th class="px-3 py-2 text-right">Boletas</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2 text-left">SUNAT</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($resumenes as $r)
                    @php $pendiente = in_array($r->est_sunat, ['ENVIADO', 'EN PROCESO'], true) && $r->res_ticket; @endphp
                    <tr>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $r->fecha_hora ? \Carbon\Carbon::parse($r->fecha_hora)->format('d/m/Y H:i') : '—' }}
                            <span class="block text-[11px] text-slate-400">{{ trim((string) ($r->apeusu ?: $r->name)) ?: 'Sistema' }}{{ $r->nombre_comercial ? ' · '.$r->nombre_comercial : '' }}</span></td>
                        <td class="px-3 py-2 whitespace-nowrap font-semibold">{{ $r->res_fec_com ? \Carbon\Carbon::parse($r->res_fec_com)->format('d/m/Y') : '—' }}</td>
                        <td class="px-3 py-2"><b>{{ $r->nom_arch }}</b><span class="block text-[11px] text-slate-400">Ticket {{ $r->res_ticket ?: '—' }}</span></td>
                        <td class="px-3 py-2 text-right">{{ $r->res_cant }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $r->res_total, 2) }}</td>
                        <td class="px-3 py-2 max-w-sm">
                            <span class="px-2 py-0.5 rounded-lg text-xs font-bold {{ $color[$r->est_sunat] ?? 'bg-slate-100 text-slate-600' }}"
                                  x-text="estado[{{ $r->res_id }}]?.estado ?? @js($r->est_sunat ?: '—')"></span>
                            <span class="block text-[11px] text-slate-500 mt-0.5 line-clamp-2" x-text="estado[{{ $r->res_id }}]?.mensaje ?? @js((string) $r->res_est)"></span>
                        </td>
                        <td class="px-3 py-2 text-right">
                            @if ($pendiente)
                                <button type="button" @click="consultar({{ $r->res_id }})" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-bold">Consultar ya</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-10 text-center text-slate-400">Esta empresa aún no envió resúmenes diarios.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $resumenes->links() }}</div>
@endsection
