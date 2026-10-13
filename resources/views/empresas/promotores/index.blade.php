@extends('layouts.app')
@section('title', 'Ventas de Promotores')
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
        $filtro = fn (array $extra) => route('promotores.index', array_filter(array_merge(['desde' => $desde, 'hasta' => $hasta, 'promotor' => $elegido], $extra)));
        $maximo = max(1, (float) ($ranking->max('total') ?? 1));
    @endphp
    <style>@media print { aside, header, nav, .no-print { display: none !important; } main { padding: 0 !important; } }</style>

    <div class="max-w-6xl space-y-5">
        {{-- Portada con los números del periodo --}}
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-900 via-indigo-700 to-fuchsia-600 text-white shadow-lg">
            <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-white/10"></div>
            <div class="absolute right-24 bottom-[-5rem] w-48 h-48 rounded-full bg-fuchsia-300/20"></div>
            <div class="relative p-6 sm:p-8">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="flex-1 min-w-[240px]">
                        <p class="text-indigo-200 text-sm font-semibold tracking-wide uppercase">⭐ Ventas de promotores</p>
                        <h1 class="text-2xl sm:text-3xl font-black mt-1">{{ $esPromotor ? 'Hola, '.$nombreElegido : ($nombreElegido ?? 'Todos los promotores') }}</h1>
                        <p class="text-indigo-100 text-sm mt-1">
                            {{ $desde === $hasta ? ucfirst(\Carbon\Carbon::parse($desde)->translatedFormat('l d \d\e F')) : \Carbon\Carbon::parse($desde)->format('d/m/Y').' al '.\Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
                            · Pedidos con promotor, los suyos y los que le incluyó un mozo o caja
                        </p>
                    </div>
                    <button type="button" onclick="window.print()" class="no-print px-3 py-2 rounded-xl bg-white/15 hover:bg-white/25 text-sm font-semibold">🖨 Imprimir</button>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6">
                    @foreach ([['Total vendido', $s($total)], ['Ventas', $documentos], ['Ticket promedio', $s($ticket)], ['Por cobrar ahora', $s($porCobrar)]] as [$titulo, $valor])
                        <div class="rounded-2xl bg-white/10 backdrop-blur px-4 py-3 ring-1 ring-white/15">
                            <p class="text-xs text-indigo-100">{{ $titulo }}</p>
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
                       class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $desde === $d && $hasta === $h ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ $nombre }}</a>
                @endforeach
            </div>
            <form class="flex flex-wrap items-end gap-2 text-sm ml-auto">
                @if (! $esPromotor)
                    <label>Promotor
                        <select name="promotor" class="block rounded-lg border-gray-300 text-sm">
                            <option value="">Todos</option>
                            @foreach ($promotores as $p)<option value="{{ $p->IdUsuario }}" @selected($elegido === (int) $p->IdUsuario)>{{ $p->nombre }}</option>@endforeach
                        </select></label>
                @endif
                <label>Desde<input type="date" name="desde" value="{{ $desde }}" class="block rounded-lg border-gray-300 text-sm"></label>
                <label>Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block rounded-lg border-gray-300 text-sm"></label>
                <button class="h-10 px-4 rounded-lg bg-indigo-600 text-white font-semibold">Ver</button>
            </form>
        </section>

        <div class="grid lg:grid-cols-[1fr_340px] gap-5 items-start">
            {{-- Ventas cobradas --}}
            <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b flex items-center gap-2">
                    <h2 class="font-bold text-gray-800 flex-1">🧾 Ventas cobradas</h2>
                    <span class="text-xs text-gray-400">{{ $ventas->count() }} comprobante(s)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <tr><th class="px-4 py-2 text-left">Hora</th><th class="px-4 py-2 text-left">Comprobante</th><th class="px-4 py-2 text-left">Mesa / tipo</th>
                                <th class="px-4 py-2 text-left">Atendió</th>@if (! $elegido)<th class="px-4 py-2 text-left">Promotor</th>@endif<th class="px-4 py-2 text-right">Total</th></tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse ($ventas as $v)
                                <tr class="hover:bg-indigo-50/40">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-gray-500">{{ \Carbon\Carbon::parse($v->fecha_hora)->format($desde === $hasta ? 'H:i' : 'd/m H:i') }}</td>
                                    <td class="px-4 py-2.5 whitespace-nowrap font-semibold text-gray-800">{{ $v->serdoc }}-{{ ltrim((string) $v->numdoc, '0') }}
                                        <div class="text-[11px] font-normal text-gray-400 truncate max-w-[180px]">{{ $v->ccanom }}</div></td>
                                    <td class="px-4 py-2.5">{{ $v->lugar }}</td>
                                    <td class="px-4 py-2.5 text-gray-600">{{ $v->mozo ?: '—' }}</td>
                                    @if (! $elegido)<td class="px-4 py-2.5"><span class="inline-flex items-center gap-1 rounded-full bg-fuchsia-50 text-fuchsia-700 px-2 py-0.5 text-xs font-semibold">⭐ {{ $v->promotor }}</span></td>@endif
                                    <td class="px-4 py-2.5 text-right font-bold {{ $v->total < 0 ? 'text-rose-600' : 'text-gray-900' }}">{{ $s($v->total) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">
                                    <p class="text-4xl">⭐</p><p class="mt-2">Aún no hay ventas con promotor en estas fechas.</p></td></tr>
                            @endforelse
                        </tbody>
                        @if ($ventas->isNotEmpty())
                            <tfoot><tr class="bg-gray-50 font-black"><td colspan="{{ $elegido ? 4 : 5 }}" class="px-4 py-3 text-right">Total</td><td class="px-4 py-3 text-right">{{ $s($total) }}</td></tr></tfoot>
                        @endif
                    </table>
                </div>
            </section>

            <div class="space-y-5">
                {{-- Ranking (solo administrador / caja viendo a todos) --}}
                @if (! $elegido && $ranking->isNotEmpty())
                    <section class="bg-white rounded-2xl shadow-sm p-5">
                        <h2 class="font-bold text-gray-800 mb-3">🏆 Ranking</h2>
                        <div class="space-y-3">
                            @foreach ($ranking as $i => $r)
                                <div>
                                    <div class="flex items-center gap-2 text-sm">
                                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-black {{ $i === 0 ? 'bg-amber-400 text-amber-950' : 'bg-gray-100 text-gray-500' }}">{{ $i + 1 }}</span>
                                        <span class="flex-1 font-semibold text-gray-800 truncate">{{ $r->nombre }}</span>
                                        <span class="font-bold text-gray-900">{{ $s($r->total) }}</span>
                                    </div>
                                    <div class="ml-8 mt-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                        <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-fuchsia-500" style="width: {{ max(3, round($r->total / $maximo * 100)) }}%"></div>
                                    </div>
                                    <p class="ml-8 text-[11px] text-gray-400 mt-0.5">{{ $r->ventas }} venta(s)</p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Pedidos en curso --}}
                <section class="bg-white rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-gray-800">🍹 Pedidos en curso</h2>
                    <p class="text-xs text-gray-400 mb-3">Comandas con promotor que aún no se cobran.</p>
                    <div class="space-y-2">
                        @forelse ($abiertos as $a)
                            <div class="flex items-center gap-3 rounded-xl ring-1 ring-gray-100 px-3 py-2.5">
                                <span class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center text-xs font-black text-center leading-tight">
                                    {{ $a->mes_nom ? \Illuminate\Support\Str::limit($a->mes_nom, 7, '') : mb_strtoupper(mb_substr((string) $a->ped_tip, 0, 4)) }}</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 truncate">#{{ $a->ped_id }} · {{ $a->mes_nom ? trim(($a->pis_nom ? $a->pis_nom.' - ' : '').$a->mes_nom) : $a->ped_tip }}</p>
                                    <p class="text-[11px] text-gray-500 truncate">{{ $a->mozo ?: '—' }}@if (! $elegido) · ⭐ {{ $a->promotor }}@endif · desde {{ \Carbon\Carbon::parse($a->fecha_hora)->format('H:i') }}</p>
                                </div>
                                <span class="text-sm font-bold text-gray-900">{{ $s($a->ped_tot) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 text-center py-4">No hay pedidos abiertos.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
