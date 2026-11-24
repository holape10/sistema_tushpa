@extends('layouts.app')
@section('title', 'Reportes avícolas')
@section('content')
    @php
        $t = $r['totVentas'];
        $prom = fn ($f) => (float) $f->kg > 0 ? number_format((float) $f->total / (float) $f->kg, 2) : '—';
        $tipos = \App\Support\Avicola::TIPOS_MERMA;
    @endphp
    <form class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex flex-wrap items-end gap-2">
        <label class="text-xs text-gray-500">Desde<input type="date" name="desde" value="{{ $desde->toDateString() }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <label class="text-xs text-gray-500">Hasta<input type="date" name="hasta" value="{{ $hasta->toDateString() }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <button class="h-9 px-4 rounded-lg bg-indigo-600 text-white text-sm font-semibold">Ver</button>
        <a href="{{ route('avicola.reportes.excel', ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()]) }}" class="ml-auto h-9 px-4 inline-flex items-center rounded-lg bg-emerald-600 text-white text-sm font-semibold">⬇ Excel</a>
    </form>

    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 mb-4">
        @foreach ([
            ['Vendido', 'S/ '.number_format((float) ($t->total ?? 0), 2), number_format((int) ($t->ventas ?? 0)).' ventas'],
            ['Kilos vendidos', number_format((float) ($t->kg ?? 0), 2).' kg', number_format((int) ($t->aves ?? 0)).' aves · '.number_format((int) ($t->jabas ?? 0)).' jabas'],
            ['Precio promedio', $r['precioProm'] ? 'S/ '.number_format($r['precioProm'], 2) : '—', 'por kilo vendido'],
            ['Costo promedio', $r['costoProm'] ? 'S/ '.number_format($r['costoProm'], 2) : '—', 'por kilo que entró'],
            ['Margen por kilo', $r['margenKg'] !== null ? 'S/ '.number_format($r['margenKg'], 2) : '—', $r['utilidad'] !== null ? 'utilidad aprox. S/ '.number_format($r['utilidad'], 2) : 'falta registrar ingresos'],
            ['Mortalidad al llegar', $r['mortalidadPct'] !== null ? $r['mortalidadPct'].' %' : '—', number_format((int) $r['ingresos']->muertas).' aves muertas'],
        ] as $k)
            <div class="rounded-2xl bg-white shadow-sm p-4"><p class="text-xs text-gray-500">{{ $k[0] }}</p><p class="text-xl font-extrabold text-gray-800">{{ $k[1] }}</p><p class="text-xs text-gray-400">{{ $k[2] }}</p></div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
        <section class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <p class="px-5 py-3 border-b border-gray-100 font-bold text-gray-800">Ventas por día</p>
            <table class="w-full text-sm"><thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr><th class="px-3 py-2 text-left">Día</th><th class="px-3 py-2 text-right">Aves</th><th class="px-3 py-2 text-right">Kg</th><th class="px-3 py-2 text-right">S/ kg</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2 text-right">Crédito</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($r['porDia'] as $f)
                        <tr><td class="px-3 py-2">{{ \Carbon\Carbon::parse($f->dia)->locale('es')->isoFormat('ddd D/MM') }}</td><td class="px-3 py-2 text-right">{{ number_format($f->aves) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $f->kg, 2) }}</td><td class="px-3 py-2 text-right">{{ $prom($f) }}</td>
                            <td class="px-3 py-2 text-right font-bold">{{ number_format((float) $f->total, 2) }}</td><td class="px-3 py-2 text-right text-amber-700">{{ number_format((float) $f->credito, 2) }}</td></tr>
                    @empty <tr><td colspan="6" class="px-3 py-6 text-center text-gray-400">Sin ventas.</td></tr> @endforelse
                </tbody></table>
        </section>
        <section class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <p class="px-5 py-3 border-b border-gray-100 font-bold text-gray-800">Por cliente</p>
            <table class="w-full text-sm"><thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr><th class="px-3 py-2 text-left">Cliente</th><th class="px-3 py-2 text-right">Kg</th><th class="px-3 py-2 text-right">S/ kg</th><th class="px-3 py-2 text-right">Total</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($r['porCliente']->take(20) as $f)
                        <tr><td class="px-3 py-2">{{ $f->clinom }}</td><td class="px-3 py-2 text-right">{{ number_format((float) $f->kg, 2) }}</td><td class="px-3 py-2 text-right">{{ $prom($f) }}</td>
                            <td class="px-3 py-2 text-right font-bold">{{ number_format((float) $f->total, 2) }}</td></tr>
                    @empty <tr><td colspan="4" class="px-3 py-6 text-center text-gray-400">Sin ventas.</td></tr> @endforelse
                </tbody></table>
        </section>
        <section class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <p class="px-5 py-3 border-b border-gray-100 font-bold text-gray-800">Por producto</p>
            <table class="w-full text-sm"><thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr><th class="px-3 py-2 text-left">Producto</th><th class="px-3 py-2 text-right">Aves</th><th class="px-3 py-2 text-right">Kg</th><th class="px-3 py-2 text-right">S/ kg</th><th class="px-3 py-2 text-right">Total</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($r['porProducto'] as $f)
                        <tr><td class="px-3 py-2">{{ $f->pronom }}</td><td class="px-3 py-2 text-right">{{ number_format($f->aves) }}</td><td class="px-3 py-2 text-right">{{ number_format((float) $f->kg, 2) }}</td>
                            <td class="px-3 py-2 text-right">{{ $prom($f) }}</td><td class="px-3 py-2 text-right font-bold">{{ number_format((float) $f->total, 2) }}</td></tr>
                    @empty <tr><td colspan="5" class="px-3 py-6 text-center text-gray-400">Sin ventas.</td></tr> @endforelse
                </tbody></table>
        </section>
        <section class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <p class="px-5 py-3 border-b border-gray-100 font-bold text-gray-800">Ingresos de pollo (compras y granja)</p>
            <table class="w-full text-sm"><thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr><th class="px-3 py-2 text-left">De</th><th class="px-3 py-2 text-right">Aves</th><th class="px-3 py-2 text-right">Muertas</th><th class="px-3 py-2 text-right">Kg</th><th class="px-3 py-2 text-right">Total</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($r['porProveedor'] as $f)
                        <tr><td class="px-3 py-2">{{ $f->proveedor }}</td><td class="px-3 py-2 text-right">{{ number_format($f->aves) }}</td><td class="px-3 py-2 text-right text-rose-600">{{ $f->muertas ?: '' }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $f->kg, 2) }}</td><td class="px-3 py-2 text-right font-bold">{{ number_format((float) $f->total, 2) }}</td></tr>
                    @empty <tr><td colspan="5" class="px-3 py-6 text-center text-gray-400">Sin ingresos.</td></tr> @endforelse
                </tbody></table>
        </section>
        <section class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <p class="px-5 py-3 border-b border-gray-100 font-bold text-gray-800">Mermas
                @if ($r['rendimiento'] !== null)<span class="ml-2 text-sm font-normal text-gray-500">· rendimiento del beneficio: <b>{{ $r['rendimiento'] }}%</b></span>@endif</p>
            <table class="w-full text-sm"><thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr><th class="px-3 py-2 text-left">Tipo</th><th class="px-3 py-2 text-right">Aves</th><th class="px-3 py-2 text-right">Kg</th><th class="px-3 py-2 text-right">Costo</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($r['mermas'] as $tipo => $f)
                        <tr><td class="px-3 py-2">{{ $tipos[$tipo][0] ?? '' }} {{ $tipos[$tipo][1] ?? $tipo }}</td><td class="px-3 py-2 text-right">{{ number_format($f->aves) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $f->kg, 2) }}</td><td class="px-3 py-2 text-right font-bold">{{ number_format((float) $f->costo, 2) }}</td></tr>
                    @empty <tr><td colspan="4" class="px-3 py-6 text-center text-gray-400">Sin mermas.</td></tr> @endforelse
                </tbody></table>
        </section>
        <section class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <p class="px-5 py-3 border-b border-gray-100 font-bold text-gray-800">Hoy te deben y jabas por devolver</p>
            <table class="w-full text-sm"><thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr><th class="px-3 py-2 text-left">Cliente</th><th class="px-3 py-2 text-right">Debe</th><th class="px-3 py-2 text-right">Vencido</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($r['saldos']->take(15) as $f)
                        <tr><td class="px-3 py-2"><a href="{{ route('avicola.cliente', $f->clicod) }}" class="text-indigo-600 hover:underline">{{ $f->clinom }}</a></td>
                            <td class="px-3 py-2 text-right font-bold">{{ number_format((float) $f->saldo, 2) }}</td><td class="px-3 py-2 text-right {{ $f->vencido > 0 ? 'text-rose-600 font-bold' : 'text-gray-300' }}">{{ number_format((float) $f->vencido, 2) }}</td></tr>
                    @empty <tr><td colspan="3" class="px-3 py-6 text-center text-gray-400">Nadie te debe.</td></tr> @endforelse
                </tbody></table>
            @if ($r['jabas']->isNotEmpty())
                <p class="px-5 pt-3 text-xs font-bold text-gray-500 uppercase">Jabas por devolver</p>
                <p class="px-5 pb-4 text-sm text-gray-600">{{ $r['jabas']->take(15)->map(fn ($j) => $j->clinom.': '.$j->jabas)->implode(' · ') }}</p>
            @endif
        </section>
    </div>
    <p class="mt-4 text-xs text-gray-400">Las ventas anuladas no cuentan. El margen es referencial: precio promedio de venta menos costo promedio de lo que entró, sin descontar gastos.
        Los reportes generales (ventas, productos, rentabilidad, cuentas por cobrar) siguen en el menú Ventas y Reportes.</p>
@endsection
