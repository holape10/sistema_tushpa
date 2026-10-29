@extends('layouts.app')
@section('title', 'Estado de cuenta · '.$c->clinom)
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $pendientes = $envios->whereNull('IdCpe_cabecera')->pluck('env_id')->values();
    @endphp
    <style>@media print { aside, header, nav, .no-imprimir { display: none !important; } main, body { background: #fff !important; } }</style>

    <div class="space-y-5" x-data="estadoCuenta()">
        <div class="no-imprimir">@include('empresas.courier.partials.nav')</div>
        <div class="bg-white rounded-3xl shadow-sm p-5 sm:p-6 flex flex-wrap items-start gap-4">
            <div class="flex-1 min-w-60">
                <a href="{{ route('courier.creditos.index') }}" class="text-sm text-gray-500 no-imprimir">← Clientes con crédito</a>
                <p class="text-xs uppercase tracking-widest text-gray-400 mt-1">Estado de cuenta{{ $mes ? ' · '.\Carbon\Carbon::parse($mes.'-01')->translatedFormat('F Y') : '' }}</p>
                <h1 class="text-2xl font-black text-gray-800">{{ $c->clinom }}</h1>
                <p class="text-sm text-gray-500">{{ strlen($c->clinum) === 11 ? 'RUC' : 'DNI' }} {{ $c->clinum }} · factura a {{ $c->dias }} días · límite {{ (float) $c->limite > 0 ? $soles($c->limite) : 'sin límite' }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-400">Por facturar</p>
                <p class="text-3xl font-black text-rose-700">{{ $soles($deuda) }}</p>
            </div>
            <div class="w-full flex flex-wrap gap-2 no-imprimir">
                <select onchange="location.href = '?mes=' + this.value" class="h-10 rounded-xl border-gray-300 text-sm">
                    <option value="">Pendientes y últimos 2 meses</option>
                    @foreach ($meses as $m)<option value="{{ $m }}" @selected($m === $mes)>{{ \Carbon\Carbon::parse($m.'-01')->translatedFormat('F Y') }}</option>@endforeach
                </select>
                <button type="button" onclick="window.print()" class="h-10 px-3 rounded-xl bg-white ring-1 ring-gray-200 text-sm font-semibold"><i class="fas fa-print"></i> Imprimir</button>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-gray-400 border-b">
                    <th class="p-3 no-imprimir"><input type="checkbox" class="rounded" @change="elegidos = $event.target.checked ? [...PENDIENTES] : []"></th>
                    <th class="p-3">Fecha</th><th>Guía</th><th>Ruta y destinatario</th><th class="text-right">Bultos</th><th class="text-right">Kg</th><th class="text-right">Importe</th><th class="p-3">Factura</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($envios as $e)
                        <tr>
                            <td class="p-3 no-imprimir">@unless ($e->IdCpe_cabecera)<input type="checkbox" value="{{ $e->env_id }}" x-model.number="elegidos" class="rounded">@endunless</td>
                            <td class="p-3">{{ \Carbon\Carbon::parse($e->creado)->format('d/m/Y') }}</td>
                            <td class="font-mono font-bold"><a href="{{ route('courier.ver', $e->env_id) }}" class="hover:text-orange-700">{{ $e->codigo }}</a></td>
                            <td>{{ $agencias[$e->origen_id]->ciudad ?? '?' }} → {{ $agencias[$e->destino_id]->ciudad ?? '?' }} · {{ $e->des_nombre }}</td>
                            <td class="text-right">{{ $e->piezas }}</td><td class="text-right">{{ rtrim(rtrim(number_format($e->peso_cobrado, 2), '0'), '.') }}</td>
                            <td class="text-right font-bold">{{ $soles($e->total) }}</td>
                            <td class="p-3">@if ($e->IdCpe_cabecera && ($cp = $comprobantes[$e->IdCpe_cabecera] ?? null))<a href="{{ route('cobros.voucher', $cp->IdCpe_cabecera) }}" target="_blank" class="text-emerald-700 font-semibold">{{ $cp->serdoc }}-{{ $cp->numdoc }}</a>@else<span class="text-rose-600 font-semibold">Pendiente</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-8 text-center text-gray-400">Sin envíos a crédito en este periodo.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr class="border-t font-black"><td colspan="6" class="p-3 text-right">Total del periodo</td><td class="text-right">{{ $soles($envios->sum('total')) }}</td><td></td></tr></tfoot>
            </table>
        </div>

        <div x-show="elegidos.length" x-cloak class="sticky bottom-3 z-20 no-imprimir">
            <div class="mx-auto max-w-3xl rounded-2xl bg-gray-900 text-white shadow-2xl px-4 py-3 flex flex-wrap items-center gap-3">
                <p class="flex-1"><b x-text="elegidos.length"></b> envío(s) · <b class="text-xl" x-text="'S/ ' + total.toFixed(2)"></b></p>
                @unless ($turno)<a href="{{ route('turnos.index') }}" class="text-amber-300 text-sm underline">Abre tu turno de caja para facturar</a>@endunless
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="imprimir" class="rounded"> Imprimir</label>
                <button type="button" @click="facturar()" :disabled="ocupado" class="h-11 px-6 rounded-xl bg-emerald-500 font-extrabold hover:bg-emerald-400 disabled:opacity-50">
                    <i class="fas fa-file-invoice-dollar"></i> {{ strlen($c->clinum) === 11 ? 'Emitir factura al crédito' : 'Emitir boleta al crédito' }}</button>
            </div>
        </div>
        <iframe x-ref="imprimir" class="hidden"></iframe>
    </div>

    <script>
        const PENDIENTES = @json($pendientes);
        function estadoCuenta() {
            const IMPORTES = @json($envios->pluck('total', 'env_id')->map(fn ($t) => (float) $t));
            return {
                elegidos: [], ocupado: false, imprimir: true,
                get total() { return this.elegidos.reduce((s, id) => s + (IMPORTES[id] || 0), 0); },
                async facturar() {
                    if (!confirm('¿Emitir el comprobante al crédito por ' + this.elegidos.length + ' envío(s)?')) return;
                    this.ocupado = true;
                    try {
                        const d = await fetch(@json(route('courier.creditos.facturar', $c->clicod)), { method: 'POST', body: JSON.stringify({ ids: this.elegidos, imprimir: this.imprimir }),
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } }).then(r => r.json());
                        if (!d.ok) return (window.tushpaAviso || alert)(d.mensaje || 'Error', 'error');
                        (window.tushpaAviso || alert)('✔ ' + d.numero + ' · S/ ' + d.total.toFixed(2) + ' (en Cuentas por cobrar)', 'ok');
                        if (this.imprimir && !d.impreso) this.$refs.imprimir.src = d.ticket + '&imprimir=1';
                        setTimeout(() => location.reload(), this.imprimir && !d.impreso ? 2500 : 1000);
                    } finally { this.ocupado = false; }
                },
            };
        }
    </script>
@endsection
