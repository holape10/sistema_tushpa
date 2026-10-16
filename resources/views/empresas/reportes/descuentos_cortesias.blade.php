@extends('layouts.app')
@section('title', 'Descuentos y Cortesías')
@section('content')
    @php
        $s = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $hoy = now()->toDateString();
        $rangos = [
            'Hoy' => [$hoy, $hoy],
            'Ayer' => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
            'Últimos 7 días' => [now()->subDays(6)->toDateString(), $hoy],
            'Este mes' => [now()->startOfMonth()->toDateString(), $hoy],
        ];
        $filtro = fn (array $extra) => route('descuentos.reporte', array_filter(array_merge(['desde' => $desde, 'hasta' => $hasta, 'cajero' => $cajero], $extra)));
        $numero = fn ($r) => $r->serdoc.'-'.str_pad($r->numdoc, 8, '0', STR_PAD_LEFT);
        $cant = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    @endphp
    <style>@media print { aside, header, nav, .no-print { display: none !important; } main { padding: 0 !important; } }</style>

    <div class="max-w-6xl space-y-5">
        {{-- Portada con los números del periodo --}}
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-amber-700 via-orange-600 to-rose-600 text-white shadow-lg">
            <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-white/10"></div>
            <div class="relative p-6 sm:p-8">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="flex-1 min-w-[240px]">
                        <p class="text-amber-100 text-sm font-semibold tracking-wide uppercase">🏷️ Descuentos y cortesías</p>
                        <h1 class="text-2xl sm:text-3xl font-black mt-1">Lo que se descontó y se regaló</h1>
                        <p class="text-amber-50 text-sm mt-1">
                            {{ $desde === $hasta ? ucfirst(\Carbon\Carbon::parse($desde)->translatedFormat('l d \d\e F')) : \Carbon\Carbon::parse($desde)->format('d/m/Y').' al '.\Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
                            · Ventas vigentes (sin anuladas)
                        </p>
                    </div>
                    <button type="button" onclick="window.print()" class="no-print px-3 py-2 rounded-xl bg-white/15 hover:bg-white/25 text-sm font-semibold">🖨 Imprimir</button>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6">
                    @foreach ([['Descuentos dados', $s($totalDescuentos)], ['Cortesías (precio de venta)', $s($totalCortesias)], ['Costo de las cortesías', $s($costoCortesias)], ['Descuentos con clave de admin', $conClave]] as [$titulo, $valor])
                        <div class="rounded-2xl bg-white/10 backdrop-blur px-4 py-3 ring-1 ring-white/15">
                            <p class="text-xs text-amber-50">{{ $titulo }}</p>
                            <p class="text-2xl font-black">{{ $valor }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Filtros --}}
        <section class="no-print bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-3">
            <div class="flex flex-wrap gap-2">
                @foreach ($rangos as $nombre => [$d, $h])
                    <a href="{{ $filtro(['desde' => $d, 'hasta' => $h]) }}"
                       class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $desde === $d && $hasta === $h ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ $nombre }}</a>
                @endforeach
            </div>
            <form class="flex flex-wrap items-end gap-2 text-sm ml-auto">
                <label>Cajero
                    <select name="cajero" class="block rounded-lg border-gray-300 text-sm">
                        <option value="">Todos</option>
                        @foreach ($cajeros as $c)<option value="{{ $c->IdUsuario }}" @selected($cajero === (int) $c->IdUsuario)>{{ $c->nombre }}</option>@endforeach
                    </select></label>
                <label>Desde<input type="date" name="desde" value="{{ $desde }}" class="block rounded-lg border-gray-300 text-sm"></label>
                <label>Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block rounded-lg border-gray-300 text-sm"></label>
                <button class="h-10 px-4 rounded-lg bg-orange-600 text-white font-semibold">Ver</button>
            </form>
        </section>

        {{-- Por cajero --}}
        @if ($porCajero->isNotEmpty())
            <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b"><h2 class="font-bold text-gray-800">👤 Por cajero</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <tr><th class="px-4 py-2 text-left">Cajero</th><th class="px-4 py-2 text-right">Descuentos</th><th class="px-4 py-2 text-right">Veces</th>
                                <th class="px-4 py-2 text-right">Cortesías</th><th class="px-4 py-2 text-right">Productos regalados</th></tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($porCajero as $c)
                                <tr><td class="px-4 py-2 font-semibold">{{ $c->nombre }}</td><td class="px-4 py-2 text-right">{{ $s($c->descuentos) }}</td>
                                    <td class="px-4 py-2 text-right">{{ $c->veces }}</td><td class="px-4 py-2 text-right">{{ $s($c->cortesias) }}</td>
                                    <td class="px-4 py-2 text-right">{{ $cant($c->items) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Descuentos --}}
        <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b flex items-center gap-2">
                <h2 class="font-bold text-gray-800 flex-1">🏷️ Descuentos a la cuenta</h2>
                <span class="text-xs text-gray-400">{{ $descuentos->count() }} venta(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                        <tr><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Comprobante</th><th class="px-4 py-2 text-left">Cajero</th>
                            <th class="px-4 py-2 text-right">Consumo</th><th class="px-4 py-2 text-right">Descuento</th><th class="px-4 py-2 text-right">Cobrado</th>
                            <th class="px-4 py-2 text-left">Motivo</th><th class="px-4 py-2 text-left">Autorizó</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($descuentos as $d)
                            <tr class="{{ $d->conClave ? 'bg-amber-50' : '' }}">
                                <td class="px-4 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($d->fecha_hora)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2"><a href="{{ route('cobros.voucher', $d->IdCpe_cabecera) }}" target="_blank" class="text-indigo-700 font-semibold hover:underline">{{ $numero($d) }}</a>
                                    <span class="block text-xs text-gray-400">{{ $d->mes_nom ? 'Mesa '.$d->mes_nom.' · ' : '' }}{{ $d->ccanom }}</span></td>
                                <td class="px-4 py-2">{{ $d->cajero }}</td>
                                <td class="px-4 py-2 text-right">{{ $s($d->consumo) }}</td>
                                <td class="px-4 py-2 text-right font-bold text-rose-600">-{{ $s($d->descuento) }}
                                    <span class="block text-xs font-normal text-gray-500">{{ $d->descuento_tipo === 'P' ? $cant($d->descuento_valor).'%' : $d->porcentaje.'%' }}</span></td>
                                <td class="px-4 py-2 text-right">{{ $s($d->ccaitv) }}</td>
                                <td class="px-4 py-2">{{ $d->descuento_motivo ?: '—' }}</td>
                                <td class="px-4 py-2">{{ $d->conClave ? '🔑 '.$d->autorizo : ($d->IdUsuario_descuento ? 'Administrador' : 'Caja') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No hubo descuentos en este periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Cortesías --}}
        <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b flex items-center gap-2">
                <h2 class="font-bold text-gray-800 flex-1">🎁 Cortesías</h2>
                <span class="text-xs text-gray-400">{{ $cortesias->count() }} producto(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                        <tr><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Comprobante</th><th class="px-4 py-2 text-left">Producto</th>
                            <th class="px-4 py-2 text-right">Cant.</th><th class="px-4 py-2 text-right">Valor</th><th class="px-4 py-2 text-left">Motivo</th><th class="px-4 py-2 text-left">Cajero</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($cortesias as $c)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($c->fecha_hora)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2"><a href="{{ route('cobros.voucher', $c->IdCpe_cabecera) }}" target="_blank" class="text-indigo-700 font-semibold hover:underline">{{ $numero($c) }}</a>
                                    <span class="block text-xs text-gray-400">{{ $c->mes_nom ? 'Mesa '.$c->mes_nom.' · ' : '' }}{{ $c->ccanom }}</span></td>
                                <td class="px-4 py-2 font-semibold">{{ $c->cdedes }}</td>
                                <td class="px-4 py-2 text-right">{{ $cant($c->cdecan) }}</td>
                                <td class="px-4 py-2 text-right">{{ $s($c->valor) }}</td>
                                <td class="px-4 py-2">{{ $c->cortesia_motivo }}</td>
                                <td class="px-4 py-2">{{ $c->cajero }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No hubo cortesías en este periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
