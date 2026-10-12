@extends('layouts.app')
@section('title', 'Liquidación Delivery')
@section('content')
    @include('empresas.partials.alert')
    @php $s = fn ($n) => 'S/ '.number_format((float) $n, 2); @endphp

    <div class="max-w-5xl space-y-5">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[240px]">
                <h1 class="text-2xl font-extrabold text-gray-800">💵 Liquidación de motorizados</h1>
                <p class="text-sm text-gray-500">Al terminar su turno, el motorizado rinde el efectivo que cobró en las entregas. Yape, Plin y tarjeta se muestran aparte: ese dinero no lo tiene él.</p>
            </div>
            <a href="{{ route('delivery.index') }}" class="px-3 py-2 rounded-lg bg-white border text-sm font-semibold text-gray-700 shadow-sm">🛵 Delivery en vivo</a>
            <a href="{{ route('delivery.reporte') }}" class="px-3 py-2 rounded-lg bg-white border text-sm font-semibold text-gray-700 shadow-sm">⏱️ Reporte de tiempos</a>
        </div>

        <div class="flex flex-wrap gap-2">
            @forelse ($motorizados as $m)
                <a href="{{ route('delivery.liquidacion', ['mot' => $m->mot_id]) }}"
                   class="flex items-center gap-2 px-3 py-1.5 rounded-full border text-sm shadow-sm {{ $motorizado && $motorizado->mot_id === $m->mot_id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700' }} {{ $m->activo ? '' : 'opacity-60' }}">
                    🛵 {{ $m->nombre }}
                    @if ($porLiquidar[$m->mot_id] ?? 0)
                        <span class="text-[11px] font-bold rounded-full px-1.5 {{ $motorizado && $motorizado->mot_id === $m->mot_id ? 'bg-white text-indigo-700' : 'bg-amber-100 text-amber-800' }}">{{ $porLiquidar[$m->mot_id] }}</span>
                    @endif
                </a>
            @empty
                <p class="text-sm text-gray-400">Aún no hay motorizados. <a href="{{ route('motorizados.index') }}" class="text-indigo-600 underline">Agrégalos aquí</a>.</p>
            @endforelse
        </div>

        @if ($motorizado && $pendientes)
            @if (! $pendientes['pedidos'])
                <div class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">{{ $motorizado->nombre }} no tiene entregas por liquidar.</div>
            @else
                <form method="POST" action="{{ route('delivery.liquidar') }}" class="space-y-4"
                      x-data="{ marcados: @js(collect($pendientes['pedidos'])->pluck('id')), filas: @js($pendientes['pedidos']), recibido: '',
                                get efectivo() { return Math.round(this.filas.filter(f => this.marcados.includes(f.id)).reduce((a, f) => a + f.efectivo, 0) * 100) / 100; },
                                get diferencia() { return this.recibido === '' ? null : Math.round((Number(this.recibido) - this.efectivo) * 100) / 100; } }">
                    @csrf
                    <input type="hidden" name="mot_id" value="{{ $motorizado->mot_id }}">

                    @if ($pendientes['sin_comprobante'])
                        <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                            ⚠️ {{ $pendientes['sin_comprobante'] }} pedido(s) entregado(s) aún sin comprobante. Cóbralos en caja (botón <b>Cobrar</b>) para que la venta quede registrada.
                        </div>
                    @endif

                    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                                <tr><th class="p-3 w-8"></th><th class="p-3 text-left">Pedido</th><th class="p-3 text-left">Entregado</th><th class="p-3 text-left">Comprobante / pago</th>
                                    <th class="p-3 text-right">Total</th><th class="p-3 text-right">Envío</th><th class="p-3 text-right">Efectivo a rendir</th><th class="p-3 text-right">Yape/Plin/Tarjeta</th></tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($pendientes['pedidos'] as $p)
                                    <tr>
                                        <td class="p-3"><input type="checkbox" name="pedidos[]" value="{{ $p['id'] }}" x-model.number="marcados" class="rounded"></td>
                                        <td class="p-3"><b>#{{ $p['id'] }}</b> {{ $p['cliente'] }}<div class="text-xs text-gray-400">{{ $p['direccion'] }}</div></td>
                                        <td class="p-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($p['fecha'])->format('d/m') }} {{ $p['hora'] }}</td>
                                        <td class="p-3">
                                            @if ($p['sin_comprobante'])
                                                <span class="text-amber-700 font-semibold">Sin comprobante</span>
                                                <a href="{{ route('cobros.cobrar', $p['id']) }}" class="ml-1 text-indigo-600 underline text-xs">Cobrar</a>
                                                <div class="text-xs text-gray-500">Dijo: {{ \App\Support\Delivery::MEDIOS_PAGO[$p['pago']] ?? 'Efectivo' }}</div>
                                            @else
                                                {{ $p['comprobantes'] }}<div class="text-xs text-gray-500">{{ $p['pago'] === 'PAGADO' ? 'Pagado antes de salir' : $p['medios'] }}</div>
                                            @endif
                                        </td>
                                        <td class="p-3 text-right">{{ $s($p['total']) }}</td>
                                        <td class="p-3 text-right text-gray-500">{{ $p['envio'] ? $s($p['envio']) : '–' }}</td>
                                        <td class="p-3 text-right font-bold">{{ $s($p['efectivo']) }}</td>
                                        <td class="p-3 text-right text-gray-500">{{ $s($p['otros']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 font-bold">
                                <tr><td colspan="4" class="p-3 text-right">Totales ({{ count($pendientes['pedidos']) }} pedidos)</td>
                                    <td class="p-3 text-right">{{ $s($pendientes['total']) }}</td><td class="p-3 text-right">{{ $s($pendientes['envios']) }}</td>
                                    <td class="p-3 text-right">{{ $s($pendientes['efectivo']) }}</td><td class="p-3 text-right">{{ $s($pendientes['otros']) }}</td></tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm p-5 grid sm:grid-cols-[1fr_1fr_2fr_auto] gap-4 items-end text-sm">
                        <div><p class="text-gray-500">Efectivo a rendir (marcados)</p><p class="text-2xl font-black text-gray-900" x-text="'S/ ' + efectivo.toFixed(2)"></p></div>
                        <label>Efectivo que entrega
                            <input type="number" name="recibido" x-model="recibido" min="0" step="0.10" required class="mt-1 block w-full rounded-lg border-gray-300 text-lg font-bold"></label>
                        <label>Observación (opcional)
                            <input name="observacion" maxlength="255" class="mt-1 block w-full rounded-lg border-gray-300" placeholder="Ej.: faltó vuelto de S/ 0.50"></label>
                        <button :disabled="!marcados.length" onclick="return confirm('¿Registrar la liquidación?')" class="h-11 px-6 rounded-lg bg-emerald-600 text-white font-bold disabled:opacity-50">Liquidar</button>
                        <p x-show="diferencia !== null" class="sm:col-span-4 font-bold" :class="diferencia === 0 ? 'text-emerald-700' : 'text-rose-600'"
                           x-text="diferencia === 0 ? '✓ Cuadra exacto' : (diferencia < 0 ? 'Faltan S/ ' + (-diferencia).toFixed(2) : 'Sobran S/ ' + diferencia.toFixed(2))"></p>
                    </div>
                </form>
            @endif
        @elseif ($motorizados->isNotEmpty())
            <div class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">No hay entregas por liquidar. Elige un motorizado para ver su detalle.</div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <h2 class="px-5 pt-4 font-bold text-gray-800">Últimas liquidaciones</h2>
            <table class="w-full text-sm mt-2">
                <thead class="text-gray-500 text-xs uppercase bg-gray-50">
                    <tr><th class="p-3 text-left">N°</th><th class="p-3 text-left">Fecha</th><th class="p-3 text-left">Motorizado</th><th class="p-3 text-right">Pedidos</th>
                        <th class="p-3 text-right">Efectivo</th><th class="p-3 text-right">Recibido</th><th class="p-3 text-right">Diferencia</th><th class="p-3 text-left">Registró</th></tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($historial as $l)
                        <tr>
                            <td class="p-3">{{ $l->id }}</td>
                            <td class="p-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($l->created_at)->format('d/m/Y H:i') }}</td>
                            <td class="p-3">{{ $l->nombre }}@if ($l->observacion)<div class="text-xs text-gray-400">{{ $l->observacion }}</div>@endif</td>
                            <td class="p-3 text-right">{{ $l->pedidos }}</td>
                            <td class="p-3 text-right">{{ $s($l->efectivo) }}</td>
                            <td class="p-3 text-right">{{ $s($l->recibido) }}</td>
                            <td class="p-3 text-right font-bold {{ (float) $l->diferencia == 0 ? 'text-emerald-700' : 'text-rose-600' }}">{{ $s($l->diferencia) }}</td>
                            <td class="p-3 text-xs text-gray-500">{{ $l->apeusu }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-6 text-center text-gray-400">Aún no hay liquidaciones.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
