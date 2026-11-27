@extends('layouts.app')
@section('title', 'Contabilidad DBF')

@section('content')
    {{-- Otros › Contabilidad DBF: el tienfact.dbf de ventas armado con los comprobantes del sistema --}}
    @php
        $mes = substr($periodo, 0, 4).'-'.substr($periodo, 4, 2);
        $nombres = ['01' => 'Factura', '03' => 'Boleta', '07' => 'Nota de crédito', '08' => 'Nota de débito'];
    @endphp
    <div class="max-w-6xl mx-auto space-y-4">
        <section class="rounded-2xl bg-white shadow-sm p-4 sm:p-5 flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[14rem]">
                <h1 class="text-xl font-black text-gray-800">📒 Contabilidad DBF</h1>
                <p class="text-sm text-gray-500">El <b>tienfact.dbf</b> de ventas del mes (la misma estructura que el del SIRE), armado con tus facturas, boletas y notas.</p>
            </div>
            <form method="GET" class="flex items-end gap-2">
                <label class="text-sm font-semibold text-gray-600">Mes
                    <input type="month" name="mes" value="{{ $mes }}" max="{{ now()->format('Y-m') }}" onchange="this.form.submit()" class="block mt-1 h-10 rounded-xl border-gray-300 text-sm"></label>
            </form>
            <a href="{{ route('contabilidad_dbf.descargar', ['mes' => $mes]) }}"
               class="h-10 px-5 inline-flex items-center rounded-xl bg-indigo-600 text-white text-sm font-bold {{ $totales['registros'] ? 'hover:bg-indigo-700' : 'opacity-40 pointer-events-none' }}">⬇ Descargar tienfact.dbf</a>
        </section>

        @if (session('error'))
            <p class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm font-semibold text-rose-700">{{ session('error') }}</p>
        @endif
        @if ($totales['pendientes'])
            <p class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
                ⚠️ <b>{{ $totales['pendientes'] }}</b> comprobante(s) aún sin respuesta de SUNAT (pendientes o en resumen). Van en el DBF; revisa que se envíen antes de declarar.</p>
        @endif

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach ([['Comprobantes', number_format($totales['registros']).($totales['anulados'] ? ' · '.$totales['anulados'].' anulado(s)' : '')],
                ['Gravada', 'S/ '.number_format($totales['gravada'], 2)], ['IGV', 'S/ '.number_format($totales['igv'], 2)],
                ['Total', 'S/ '.number_format($totales['total'], 2)]] as [$texto, $valor])
                <div class="rounded-2xl bg-white shadow-sm p-4"><p class="text-xs font-bold text-gray-400 uppercase">{{ $texto }}</p><p class="text-lg font-black text-gray-800">{{ $valor }}</p></div>
            @endforeach
        </div>
        <p class="text-xs text-gray-500">Cuentas: cargo <b>{{ $cfg['cta_por_cobrar'] }}</b> · abono <b>{{ $cfg['cta_ventas'] }}</b> (gravadas) y <b>{{ $cfg['cta_ventas_exo'] }}</b> (exoneradas)
            · las rechazadas por SUNAT y las notas de venta no van · las notas de crédito van en negativo, como en el SIRE.</p>

        <section class="rounded-2xl bg-white shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr><th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">Comprobante</th><th class="px-3 py-2 text-left">Cliente</th>
                        <th class="px-3 py-2 text-right">Gravada</th><th class="px-3 py-2 text-right">IGV</th><th class="px-3 py-2 text-right">Exo./Inaf.</th>
                        <th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse (array_slice($comprobantes, 0, 500) as $c)
                        <tr class="{{ $c['anulado'] ? 'text-gray-400' : '' }}">
                            <td class="px-3 py-1.5 whitespace-nowrap">{{ \Carbon\Carbon::parse($c['fecha'])->format('d/m/Y') }}</td>
                            <td class="px-3 py-1.5 whitespace-nowrap"><span class="text-xs text-gray-400">{{ $nombres[$c['tipo']] ?? $c['tipo'] }}</span>
                                <b>{{ $c['serie'] }}-{{ $c['numero'] }}</b></td>
                            <td class="px-3 py-1.5 max-w-[16rem] truncate">{{ $c['nombre'] }} <span class="text-xs text-gray-400">{{ $c['ruc'] }}</span></td>
                            <td class="px-3 py-1.5 text-right">{{ number_format($c['montos']['gravada'], 2) }}</td>
                            <td class="px-3 py-1.5 text-right">{{ number_format($c['montos']['igv'], 2) }}</td>
                            <td class="px-3 py-1.5 text-right">{{ number_format($c['montos']['exonerada'] + $c['montos']['inafecta'], 2) }}</td>
                            <td class="px-3 py-1.5 text-right font-semibold">{{ $c['moneda'] === 'USD' ? 'US$ ' : '' }}{{ number_format($c['montos']['total'], 2) }}</td>
                            <td class="px-3 py-1.5 text-xs whitespace-nowrap">
                                @if ($c['anulado'])<span class="px-1.5 rounded bg-gray-100 text-gray-500 font-bold">ANULADO</span>
                                @elseif ($c['pendiente'])<span class="px-1.5 rounded bg-amber-100 text-amber-800 font-bold">SIN RESPUESTA SUNAT</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-10 text-center text-gray-400">No hay facturas ni boletas en este mes.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if (count($comprobantes) > 500)
                <p class="px-4 py-2 text-xs text-gray-500">Se muestran los primeros 500; el DBF lleva los {{ number_format(count($comprobantes)) }}.</p>
            @endif
        </section>
    </div>
@endsection
