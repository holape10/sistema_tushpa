{{--
    Resumen de la caja en el momento del arqueo: cómo se calcula lo que debe haber, ventas por medio de pago y por
    comprobante, y el detalle de los ingresos y egresos. Variable: $f (ArqueoCaja::foto).
--}}
@php $s = fn ($n) => 'S/ '.number_format((float) $n, 2); @endphp
<div class="grid md:grid-cols-2 gap-4">
    <div class="rounded-2xl ring-1 ring-gray-200 p-4">
        <p class="text-xs font-bold uppercase text-gray-400 mb-2">Efectivo que debe haber</p>
        <table class="w-full text-sm">
            <tr><td class="py-0.5">Fondo inicial</td><td class="text-right">{{ $s($f['fondo']) }}</td></tr>
            <tr><td class="py-0.5">+ Ventas en efectivo</td><td class="text-right">{{ $s($f['ventas_efectivo']) }}</td></tr>
            <tr><td class="py-0.5">+ Otros ingresos</td><td class="text-right text-emerald-700">{{ $s($f['ingresos']) }}</td></tr>
            <tr><td class="py-0.5">− Egresos / gastos</td><td class="text-right text-rose-600">{{ $s($f['egresos']) }}</td></tr>
            @if ($f['dolares_soles'] > 0)
                <tr><td class="py-0.5">− Cobrado en dólares <span class="text-xs text-gray-400">(US$ {{ number_format($f['dolares_usd'], 2) }}, se cuenta aparte)</span></td>
                    <td class="text-right text-rose-600">{{ $s($f['dolares_soles']) }}</td></tr>
            @endif
            <tr class="border-t border-gray-200 font-extrabold"><td class="pt-1">= Debe haber</td><td class="pt-1 text-right text-emerald-700">{{ $s($f['esperado']) }}</td></tr>
        </table>
    </div>

    <div class="rounded-2xl ring-1 ring-gray-200 p-4">
        <p class="text-xs font-bold uppercase text-gray-400 mb-2">Ventas del turno · {{ $f['operaciones'] }} comprobante(s)</p>
        <table class="w-full text-sm">
            @forelse ($f['por_medio'] as $m)
                <tr><td class="py-0.5">{{ $m['medio'] }} <span class="text-xs text-gray-400">({{ $m['operaciones'] }})</span></td><td class="text-right">{{ $s($m['monto']) }}</td></tr>
            @empty
                <tr><td class="py-0.5 text-gray-400">Aún no hay ventas cobradas.</td><td></td></tr>
            @endforelse
            @if ($f['ventas_credito'] > 0)
                <tr><td class="py-0.5">Al crédito <span class="text-xs text-gray-400">(no entra a caja)</span></td><td class="text-right">{{ $s($f['ventas_credito']) }}</td></tr>
            @endif
            <tr class="border-t border-gray-200 font-extrabold"><td class="pt-1">Total vendido</td><td class="pt-1 text-right text-indigo-700">{{ $s($f['ventas_total']) }}</td></tr>
        </table>
        @if ($f['por_comprobante'])
            <p class="mt-2 text-xs text-gray-500">{{ collect($f['por_comprobante'])->map(fn ($c) => $c['tipo'].': '.$c['cantidad'].' · '.$s($c['total']))->implode('  |  ') }}</p>
        @endif
    </div>
</div>

<div class="rounded-2xl ring-1 ring-gray-200 p-4 mt-4">
    <p class="text-xs font-bold uppercase text-gray-400 mb-2">Ingresos y egresos de caja</p>
    @if ($f['movimientos'])
        <table class="w-full text-sm">
            <thead class="text-xs text-gray-400"><tr><th class="text-left py-1">Hora</th><th class="text-left">Concepto</th><th class="text-left">Detalle</th><th class="text-left">Registró</th><th class="text-right">Importe</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($f['movimientos'] as $m)
                    <tr>
                        <td class="py-1 whitespace-nowrap">{{ $m['hora'] ? \Carbon\Carbon::parse($m['hora'])->format('H:i') : '' }}</td>
                        <td>{{ $m['concepto'] }}</td>
                        <td class="text-gray-600">{{ $m['detalle'] }}</td>
                        <td class="text-gray-500">{{ $m['usuario'] }}</td>
                        <td class="text-right font-semibold {{ $m['tipo'] === 'SALIDA' ? 'text-rose-600' : 'text-emerald-700' }}">{{ $m['tipo'] === 'SALIDA' ? '−' : '+' }} {{ $s($m['importe']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="text-sm text-gray-400">Sin ingresos ni egresos registrados en la caja.</p>
    @endif
</div>
