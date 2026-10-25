@extends('layouts.app')
@section('title', 'Propinas')
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
    @endphp
    <style>@media print { aside, header, nav, .no-print { display: none !important; } main { padding: 0 !important; } }</style>

    <div class="max-w-6xl space-y-5">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-pink-700 via-rose-600 to-orange-500 text-white shadow-lg">
            <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-white/10"></div>
            <div class="relative p-6 sm:p-8">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="flex-1 min-w-[240px]">
                        <p class="text-pink-100 text-sm font-semibold tracking-wide uppercase">💝 Propinas</p>
                        <h1 class="text-2xl sm:text-3xl font-black mt-1">Lo que dejaron los clientes</h1>
                        <p class="text-pink-50 text-sm mt-1">
                            {{ $desde === $hasta ? ucfirst(\Carbon\Carbon::parse($desde)->translatedFormat('l d \d\e F')) : \Carbon\Carbon::parse($desde)->format('d/m/Y').' al '.\Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
                            · No son venta: no van en el comprobante
                        </p>
                    </div>
                    <button type="button" onclick="window.print()" class="no-print px-3 py-2 rounded-xl bg-white/15 hover:bg-white/25 text-sm font-semibold">🖨 Imprimir</button>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6">
                    <div class="rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15"><p class="text-xs text-pink-50">Total de propinas</p><p class="text-2xl font-black">{{ $s($total) }}</p></div>
                    <div class="rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15"><p class="text-xs text-pink-50">Cobros con propina</p><p class="text-2xl font-black">{{ $propinas->count() }}</p></div>
                    @foreach ($porMedio->take(2) as $m)
                        <div class="rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15"><p class="text-xs text-pink-50">En {{ mb_strtolower($m->medio) }}</p><p class="text-2xl font-black">{{ $s($m->monto) }}</p></div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="no-print bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-3">
            <div class="flex flex-wrap gap-2">
                @foreach ($rangos as $nombre => [$d, $h])
                    <a href="{{ route('propinas.reporte', ['desde' => $d, 'hasta' => $h]) }}"
                       class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $desde === $d && $hasta === $h ? 'bg-pink-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ $nombre }}</a>
                @endforeach
            </div>
            <form class="flex flex-wrap items-end gap-2 text-sm ml-auto">
                <label>Desde<input type="date" name="desde" value="{{ $desde }}" class="block rounded-lg border-gray-300 text-sm"></label>
                <label>Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block rounded-lg border-gray-300 text-sm"></label>
                <button class="h-10 px-4 rounded-lg bg-pink-600 text-white font-semibold">Ver</button>
            </form>
        </section>

        @if ($propinas->isEmpty())
            <section class="bg-white rounded-2xl shadow-sm p-6 text-sm text-gray-500">No hubo propinas en estas fechas. Para recibirlas, activa "Recibir propina" en la sucursal.</section>
        @else
            <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b"><h2 class="font-bold text-gray-800">👤 Por mozo <span class="font-normal text-xs text-gray-500">(el que atendió la mesa; en venta directa, el cajero)</span></h2></div>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr><th class="px-4 py-2 text-left">Mozo</th><th class="px-4 py-2 text-center">Cobros</th>
                        <th class="px-4 py-2 text-right">Ventas de esas mesas</th><th class="px-4 py-2 text-right">Propinas</th></tr></thead>
                    <tbody class="divide-y">
                    @foreach ($porMozo as $m)
                        <tr><td class="px-4 py-2 font-semibold">{{ $m->mozo }}</td><td class="px-4 py-2 text-center">{{ $m->cobros }}</td>
                            <td class="px-4 py-2 text-right">{{ $s($m->ventas) }}</td><td class="px-4 py-2 text-right font-bold text-pink-700">{{ $s($m->monto) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </section>

            <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b"><h2 class="font-bold text-gray-800">🧾 Detalle</h2></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Comprobante</th>
                            <th class="px-4 py-2 text-left">Mesa</th><th class="px-4 py-2 text-left">Mozo</th><th class="px-4 py-2 text-left">Pagó con</th>
                            <th class="px-4 py-2 text-right">Cuenta</th><th class="px-4 py-2 text-right">Propina</th></tr></thead>
                        <tbody class="divide-y">
                        @foreach ($propinas as $p)
                            <tr><td class="px-4 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($p->created_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2">{{ $p->serdoc }}-{{ str_pad($p->numdoc, 8, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-4 py-2">{{ $p->mes_nom ?? '—' }}</td><td class="px-4 py-2">{{ $p->mozo ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $p->nom_med_pag ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">{{ $s($p->ccaitv) }}</td><td class="px-4 py-2 text-right font-bold">{{ $s($p->monto) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection
