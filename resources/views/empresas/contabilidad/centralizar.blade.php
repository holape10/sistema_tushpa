@extends('layouts.app')
@section('title', $tipo === 'ventas' ? 'Centralizar Ventas' : 'Centralizar Compras')
@section('content')
    @include('empresas.contabilidad._nav')

    @use('App\Support\Contabilidad\Contabilidad')
    @php
        $mes = substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2);
        $totalDebe = $detalle->flatten()->sum('debe');
        $origenColor = ['VENTAS' => 'bg-emerald-100 text-emerald-700', 'COBRANZAS' => 'bg-sky-100 text-sky-700',
                        'COMPRAS' => 'bg-amber-100 text-amber-800', 'PAGOS' => 'bg-rose-100 text-rose-700'];
    @endphp

    <div class="grid lg:grid-cols-3 gap-4 mb-4">
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-4">
            <label class="text-sm font-semibold text-gray-600">Periodo
                <input type="month" value="{{ $mes }}" onchange="this.form.periodo.value = this.value.replace('-', ''); this.form.submit()" class="block w-full mt-1 rounded-xl border-gray-300 text-lg font-bold">
            </label>
            <input type="hidden" name="periodo" value="{{ $periodo }}">
            <p class="mt-2 text-sm font-semibold {{ $cerrado ? 'text-rose-600' : 'text-emerald-600' }}">
                <i class="fas {{ $cerrado ? 'fa-lock' : 'fa-lock-open' }}"></i> {{ Contabilidad::nombrePeriodo($periodo) }} · {{ $cerrado ? 'CERRADO' : 'ABIERTO' }}</p>
        </form>

        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs uppercase font-bold text-gray-400">{{ $tipo === 'ventas' ? 'Comprobantes del periodo (facturas, boletas y notas)' : 'Compras registradas en el periodo' }}</p>
            <p class="text-3xl font-black text-gray-800">{{ (int) $pendiente->n }}</p>
            <p class="text-sm text-gray-500">Total S/ {{ number_format((float) $pendiente->total, 2) }}</p>
            @if ($ultimaVez && $pendiente->ultimo && $pendiente->ultimo > $ultimaVez)
                <p class="mt-1 text-xs font-semibold text-amber-600"><i class="fas fa-triangle-exclamation"></i> Hubo cambios después de la última centralización.</p>
            @endif
        </div>

        <div class="bg-gradient-to-br from-indigo-600 to-violet-600 text-white rounded-2xl shadow-sm p-4 flex flex-col">
            <p class="text-xs uppercase font-bold text-indigo-100">Asientos generados</p>
            <p class="text-3xl font-black">{{ $asientos->count() }}</p>
            <p class="text-sm text-indigo-100">{{ $ultimaVez ? 'Última centralización: ' . \Carbon\Carbon::parse($ultimaVez)->format('d/m/Y H:i') : 'Aún no se centraliza este periodo.' }}</p>
            @unless ($cerrado)
                <form method="POST" action="{{ route('contabilidad.centralizar.ejecutar', ['tipo' => $tipo]) }}" class="mt-auto pt-3"
                      onsubmit="return confirm('{{ $asientos->count() ? 'Se borrarán los asientos automáticos de este periodo y se generarán de nuevo. ' : '' }}¿Centralizar {{ $tipo }} de {{ Contabilidad::nombrePeriodo($periodo) }}?')">
                    @csrf <input type="hidden" name="periodo" value="{{ $periodo }}">
                    <button class="w-full py-2.5 rounded-xl bg-white text-indigo-700 font-black hover:bg-indigo-50"><i class="fas fa-gears"></i> {{ $asientos->count() ? 'VOLVER A CENTRALIZAR' : 'CENTRALIZAR' }}</button>
                </form>
            @endunless
        </div>
    </div>

    <details class="bg-white rounded-2xl shadow-sm p-4 mb-4 text-sm text-gray-600">
        <summary class="font-semibold text-gray-700 cursor-pointer"><i class="fas fa-circle-info text-indigo-500"></i> ¿Qué asientos se generan?</summary>
        @if ($tipo === 'ventas')
            <ul class="mt-2 list-disc list-inside space-y-1">
                <li><strong>Facturas y notas de débito</strong> (subdiario 05, uno por documento): 12 Cuentas por cobrar (Debe) → 40 IGV y 70 Ventas (Haber).</li>
                <li><strong>Boletas</strong>: un asiento por día y serie con el rango de números (ej. B001-1 al 120).</li>
                <li><strong>Notas de crédito</strong>: el asiento al revés (70 y 40 al Debe → 12 al Haber).</li>
                <li><strong>Costo de ventas</strong> por día: 69 Costo de ventas → 20 Mercaderías (si está activado en la configuración).</li>
                <li><strong>Cobranzas</strong> (subdiario 01) por día: caja o banco según el medio de pago → 12. Incluye los cobros de ventas al crédito.</li>
                <li>Las <strong>notas de venta</strong> no se contabilizan (no son comprobantes de pago) y las anuladas se excluyen.</li>
            </ul>
        @else
            <ul class="mt-2 list-disc list-inside space-y-1">
                <li><strong>Provisión</strong> (subdiario 11, una por documento): 60 Compras + 40 IGV (Debe) → 42 Cuentas por pagar (Haber). Las compras en dólares se convierten con su tipo de cambio.</li>
                <li><strong>Destino</strong> por día de compra: 20 Mercaderías → 61 Variación de inventarios (si está activado).</li>
                <li><strong>Pagos</strong> (subdiario 01) por día: 42 → caja (contado) o la cuenta del medio de pago (pagos de cuentas por pagar).</li>
            </ul>
        @endif
        <p class="mt-2">Las cuentas se configuran en <a href="{{ route('contabilidad.plan', ['tab' => 'config']) }}" class="text-indigo-600 font-semibold hover:underline">Plan contable → Cuentas de centralización</a>.</p>
    </details>

    <div class="space-y-3" x-data="{ abierto: null }">
        @forelse ($asientos as $a)
            @php $det = $detalle[$a->id] ?? collect(); @endphp
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <button type="button" @click="abierto = abierto === {{ $a->id }} ? null : {{ $a->id }}" class="w-full flex flex-wrap items-center gap-3 px-4 py-3 text-left hover:bg-gray-50">
                    <span class="font-mono text-xs font-bold text-gray-500 w-16">{{ $a->subdiario }}-{{ str_pad($a->numero, 4, '0', STR_PAD_LEFT) }}</span>
                    <span class="text-sm text-gray-500 w-20">{{ \Carbon\Carbon::parse($a->fecha)->format('d/m/Y') }}</span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold {{ $origenColor[$a->origen] ?? 'bg-gray-100' }}">{{ $a->origen }}</span>
                    <span class="flex-1 min-w-[200px] font-semibold text-gray-700 text-sm">{{ $a->glosa }}</span>
                    <span class="font-bold text-gray-800">S/ {{ number_format($a->total, 2) }}</span>
                    <i class="fas fa-chevron-down text-gray-400 transition" :class="abierto === {{ $a->id }} ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="abierto === {{ $a->id }}" x-cloak class="border-t border-gray-100 overflow-x-auto">
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($det as $d)
                                <tr>
                                    <td class="px-4 py-1.5 font-mono w-24 {{ $d->haber > 0 ? 'pl-10' : '' }}">{{ $d->cuenta }}</td>
                                    <td class="px-2 py-1.5 text-gray-600">{{ $nombres[$d->cuenta] ?? '' }}
                                        @if ($d->anexo_nombre)<span class="block text-xs text-gray-400">{{ $d->anexo_doc }} {{ $d->anexo_nombre }} {{ $d->documento ? '· ' . $d->documento : '' }}</span>@endif</td>
                                    <td class="px-4 py-1.5 text-right w-28">{{ $d->debe > 0 ? number_format($d->debe, 2) : '' }}</td>
                                    <td class="px-4 py-1.5 text-right w-28">{{ $d->haber > 0 ? number_format($d->haber, 2) : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">
                <i class="fas fa-book text-4xl mb-3"></i>
                <p>Aún no hay asientos de {{ $tipo }} en {{ Contabilidad::nombrePeriodo($periodo) }}. Pulsa <strong>CENTRALIZAR</strong> para generarlos.</p>
            </div>
        @endforelse
    </div>
    @if ($asientos->isNotEmpty())
        <p class="text-right text-sm text-gray-500 mt-3">Total Debe = Haber: <strong class="text-gray-800">S/ {{ number_format($totalDebe, 2) }}</strong>
            · <a href="{{ route('contabilidad.diario', ['periodo' => $periodo]) }}" class="text-indigo-600 font-semibold hover:underline">Ver en el Libro diario →</a></p>
    @endif
@endsection
