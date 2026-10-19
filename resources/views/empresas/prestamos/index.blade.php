@extends('layouts.app')
@section('title', 'Préstamos')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $tarjeta = 'bg-white rounded-2xl shadow-sm p-5';
        $meta = $kpi->cobradoHoy + $kpi->faltaHoy;
        $avanceHoy = $meta > 0 ? min(100, round($kpi->cobradoHoy / $meta * 100)) : 0;
        $maxG = max(1, collect($grafico)->max(fn ($g) => max($g['cobrado'], $g['prestado'])));
        $totalEstados = max(1, collect($porEstado)->sum('n'));
        $grados = 0; $partes = [];
        foreach (['AL_DIA' => '#10b981', 'ATRASADO' => '#f59e0b', 'MOROSO' => '#f43f5e'] as $e => $color) {
            $g = $porEstado[$e]['n'] / $totalEstados * 360; $partes[] = "{$color} {$grados}deg ".($grados + $g).'deg'; $grados += $g;
        }
        $dona = collect($porEstado)->sum('n') ? 'conic-gradient('.implode(',', $partes).')' : 'conic-gradient(#e5e7eb 0deg 360deg)';
    @endphp

    <div class="space-y-5" x-data="carteraPrestamos()">
        {{-- Cabecera --}}
        <div class="relative overflow-hidden rounded-3xl p-6 sm:p-7 text-white bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-900 shadow-lg">
            <div class="absolute -right-10 -top-10 w-56 h-56 rounded-full bg-white/10"></div>
            <div class="absolute right-24 -bottom-16 w-40 h-40 rounded-full bg-white/5"></div>
            <div class="relative flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-widest text-emerald-100/80"><i class="fas fa-hand-holding-dollar"></i> Préstamos · {{ $negocio->nombre_comercial }}</p>
                    <p class="mt-2 text-sm text-emerald-100">Capital en la calle</p>
                    <p class="text-4xl sm:text-5xl font-black tracking-tight">{{ $soles($kpi->capital) }}</p>
                    <p class="mt-1 text-sm text-emerald-100">Por cobrar {{ $soles($kpi->porCobrar) }} · ganancia por cobrar <b class="text-white">{{ $soles($kpi->gananciaPorCobrar) }}</b></p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('prestamos.nuevo') }}" class="px-4 py-2.5 rounded-xl bg-white text-emerald-800 font-extrabold text-sm shadow hover:bg-emerald-50"><i class="fas fa-plus"></i> Nuevo préstamo</a>
                    <a href="{{ route('prestamos.cobranza') }}" class="px-4 py-2.5 rounded-xl bg-amber-400 text-amber-950 font-extrabold text-sm shadow hover:bg-amber-300"><i class="fas fa-motorcycle"></i> Cobrar hoy</a>
                    <a href="{{ route('prestamos.clientes') }}" class="px-4 py-2.5 rounded-xl bg-white/15 font-bold text-sm hover:bg-white/25"><i class="fas fa-users"></i> Clientes</a>
                    <a href="{{ route('prestamos.reportes') }}" class="px-4 py-2.5 rounded-xl bg-white/15 font-bold text-sm hover:bg-white/25"><i class="fas fa-chart-pie"></i> Reportes</a>
                    @if ($puedeConfigurar)<a href="{{ route('prestamos.configuracion') }}" class="px-3 py-2.5 rounded-xl bg-white/15 text-sm hover:bg-white/25" title="Configuración"><i class="fas fa-gear"></i></a>@endif
                </div>
            </div>
            {{-- Cobranza de hoy --}}
            <div class="relative mt-5 rounded-2xl bg-black/20 p-4">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <p class="text-sm">Cobrado hoy <b class="text-2xl font-black">{{ $soles($kpi->cobradoHoy) }}</b> <span class="text-emerald-100">de {{ $soles($meta) }}</span></p>
                    <p class="text-sm text-emerald-100">{{ $kpi->pagaronHoy }} pagaron · faltan {{ $kpi->deudoresHoy }} ({{ $soles($kpi->faltaHoy) }})</p>
                </div>
                <div class="mt-2 h-3 rounded-full bg-white/20 overflow-hidden"><div class="h-3 rounded-full bg-gradient-to-r from-amber-300 to-lime-300" style="width: {{ $avanceHoy }}%"></div></div>
            </div>
        </div>

        {{-- Indicadores --}}
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
            @foreach ([
                ['Préstamos activos', $kpi->activos, 'fa-file-invoice-dollar', 'text-emerald-600', $kpi->clientes.' cliente(s)'],
                ['Prestado hoy', $soles($kpi->prestadoHoy), 'fa-money-bill-transfer', 'text-sky-600', 'este mes '.$soles($kpi->prestadoMes)],
                ['Cobrado del mes', $soles($kpi->cobradoMes), 'fa-sack-dollar', 'text-teal-600', 'cuotas + mora'],
                ['Ganancia del mes', $soles($kpi->gananciaMes), 'fa-arrow-trend-up', 'text-lime-600', 'interés + mora cobrados'],
                ['Atrasados', $porEstado['ATRASADO']['n'], 'fa-clock', 'text-amber-600', $soles($porEstado['ATRASADO']['saldo'])],
                ['Morosos', $porEstado['MOROSO']['n'], 'fa-triangle-exclamation', 'text-rose-600', $soles($porEstado['MOROSO']['saldo'])],
            ] as [$titulo, $valor, $icono, $color, $sub])
                <div class="{{ $tarjeta }} !p-4">
                    <p class="text-[11px] font-semibold uppercase text-gray-400"><i class="fas {{ $icono }} {{ $color }}"></i> {{ $titulo }}</p>
                    <p class="text-2xl font-black {{ $color }} mt-1">{{ $valor }}</p>
                    <p class="text-[11px] text-gray-400">{{ $sub }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid lg:grid-cols-3 gap-5">
            {{-- Gráfico de 14 días --}}
            <div class="{{ $tarjeta }} lg:col-span-2 min-w-0">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h2 class="font-bold text-gray-800"><i class="fas fa-chart-column text-emerald-600"></i> Últimos 14 días</h2>
                    <p class="text-xs text-gray-500"><span class="inline-block w-3 h-3 rounded bg-emerald-500 align-middle"></span> Cobrado
                        <span class="inline-block w-3 h-3 rounded bg-sky-300 align-middle ml-2"></span> Prestado</p>
                </div>
                <div class="flex items-end gap-1.5 h-48">
                    @foreach ($grafico as $g)
                        <div class="flex-1 h-full flex flex-col justify-end items-center gap-0.5 group relative" title="{{ \Carbon\Carbon::parse($g['dia'])->format('d/m') }} · cobrado {{ $soles($g['cobrado']) }} · prestado {{ $soles($g['prestado']) }}">
                            <div class="w-full flex items-end gap-0.5 h-full">
                                <div class="flex-1 rounded-t bg-emerald-500" style="height: {{ max($g['cobrado'] > 0 ? 3 : 0, $g['cobrado'] / $maxG * 100) }}%"></div>
                                <div class="flex-1 rounded-t bg-sky-300" style="height: {{ max($g['prestado'] > 0 ? 3 : 0, $g['prestado'] / $maxG * 100) }}%"></div>
                            </div>
                            <span class="text-[10px] {{ \Carbon\Carbon::parse($g['dia'])->isSunday() ? 'text-rose-400' : 'text-gray-400' }}">{{ \Carbon\Carbon::parse($g['dia'])->format('d') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            {{-- Estado de la cartera --}}
            <div class="{{ $tarjeta }}">
                <h2 class="font-bold text-gray-800 mb-3"><i class="fas fa-circle-half-stroke text-emerald-600"></i> Cartera</h2>
                <div class="flex items-center gap-5">
                    <div class="relative w-32 h-32 shrink-0 rounded-full" style="background: {{ $dona }}">
                        <div class="absolute inset-4 rounded-full bg-white flex flex-col items-center justify-center">
                            <p class="text-2xl font-black text-gray-800">{{ $kpi->activos }}</p><p class="text-[10px] text-gray-400 uppercase">activos</p>
                        </div>
                    </div>
                    <div class="space-y-2 text-sm flex-1">
                        @foreach (['AL_DIA' => ['Al día', 'bg-emerald-500'], 'ATRASADO' => ['Atrasados', 'bg-amber-400'], 'MOROSO' => ['Morosos', 'bg-rose-500']] as $e => [$n, $c])
                            <div><p class="flex items-center justify-between"><span><span class="inline-block w-2.5 h-2.5 rounded-full {{ $c }}"></span> {{ $n }}</span><b>{{ $porEstado[$e]['n'] }}</b></p>
                                <p class="text-[11px] text-gray-400 pl-4">{{ $soles($porEstado[$e]['saldo']) }}</p></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-5">
            <div class="{{ $tarjeta }}">
                <h2 class="font-bold text-gray-800 mb-2"><i class="fas fa-fire text-rose-500"></i> Los que más deben</h2>
                @forelse ($morosos as $p)
                    <a href="{{ route('prestamos.ver', $p->pre_id) }}" class="flex items-center gap-3 py-2 border-b last:border-0 hover:bg-gray-50 rounded-lg px-1">
                        <span class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-white {{ $p->estado === 'MOROSO' ? 'bg-rose-500' : 'bg-amber-400' }}">{{ mb_substr($p->clinom, 0, 1) }}</span>
                        <span class="flex-1 min-w-0"><span class="block font-semibold truncate">{{ $p->clinom }}</span>
                            <span class="block text-xs text-gray-500">{{ $p->vencidas }} cuota(s) atrasada(s) · {{ $p->zona ?: 'sin zona' }}</span></span>
                        <span class="text-right"><b class="text-rose-600">{{ $soles($p->cobrar_hoy) }}</b><span class="block text-[11px] text-gray-400">saldo {{ $soles($p->saldo) }}</span></span>
                    </a>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400">🎉 Nadie está atrasado.</p>
                @endforelse
            </div>
            <div class="{{ $tarjeta }}">
                <h2 class="font-bold text-gray-800 mb-2"><i class="fas fa-rotate text-emerald-600"></i> Por terminar: ofréceles renovar</h2>
                @forelse ($renovar as $p)
                    <a href="{{ route('prestamos.ver', $p->pre_id) }}" class="flex items-center gap-3 py-2 border-b last:border-0 hover:bg-gray-50 rounded-lg px-1">
                        <span class="flex-1 min-w-0"><span class="block font-semibold truncate">{{ $p->clinom }}</span>
                            <span class="block h-1.5 mt-1 rounded-full bg-gray-100"><span class="block h-1.5 rounded-full bg-emerald-500" style="width: {{ $p->avance }}%"></span></span></span>
                        <span class="text-right text-sm"><b>{{ $p->avance }}%</b><span class="block text-[11px] text-gray-400">le falta {{ $soles($p->saldo) }}</span></span>
                    </a>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400">Aún nadie está por terminar.</p>
                @endforelse
            </div>
        </div>

        {{-- Cartera completa --}}
        <div class="{{ $tarjeta }} !p-0 overflow-hidden">
            <div class="p-4 border-b flex flex-wrap items-center gap-2">
                <h2 class="font-bold text-gray-800 mr-auto"><i class="fas fa-list text-emerald-600"></i> Préstamos vigentes</h2>
                <div class="flex rounded-xl bg-gray-100 p-1 text-xs font-bold">
                    <template x-for="f in [['', 'Todos'], ['AL_DIA', 'Al día'], ['ATRASADO', 'Atrasados'], ['MOROSO', 'Morosos']]" :key="f[0]">
                        <button type="button" @click="filtro = f[0]" class="px-3 py-1.5 rounded-lg" :class="filtro === f[0] ? 'bg-white shadow text-emerald-700' : 'text-gray-500'" x-text="f[1]"></button>
                    </template>
                </div>
                <input x-model="q" placeholder="Buscar cliente, DNI o zona" class="rounded-xl border-gray-300 text-sm w-full sm:w-64">
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-[11px] uppercase text-gray-500">
                        <tr><th class="text-left px-4 py-2">Cliente</th><th class="text-right px-2">Prestó</th><th class="text-right px-2">Cuota</th><th class="px-2 text-left w-40">Avance</th>
                            <th class="text-right px-2">Saldo</th><th class="text-center px-2">Estado</th><th class="text-right px-4">Cobrar hoy</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        <template x-for="p in visibles" :key="p.pre_id">
                            <tr class="hover:bg-emerald-50/40 cursor-pointer" @click="location.href = '{{ url('prestamos') }}/' + p.pre_id">
                                <td class="px-4 py-2.5"><b x-text="p.cliente"></b><span class="block text-[11px] text-gray-400" x-text="'N° ' + String(p.numero).padStart(5, '0') + ' · ' + (p.zona || 'sin zona') + (p.cobrador ? ' · ' + p.cobrador : '')"></span></td>
                                <td class="text-right px-2" x-text="soles(p.monto)"></td>
                                <td class="text-right px-2"><span x-text="soles(p.cuota)"></span><span class="block text-[10px] text-gray-400" x-text="frec[p.frecuencia]"></span></td>
                                <td class="px-2"><div class="h-2 rounded-full bg-gray-100"><div class="h-2 rounded-full bg-emerald-500" :style="'width:' + p.avance + '%'"></div></div>
                                    <span class="text-[10px] text-gray-400" x-text="p.avance + '% · termina ' + fecha(p.ultima)"></span></td>
                                <td class="text-right px-2 font-semibold" x-text="soles(p.saldo)"></td>
                                <td class="text-center px-2"><span class="px-2 py-0.5 rounded-full text-[11px] font-extrabold" :class="color[p.estado]" x-text="nombre[p.estado] + (p.vencidas ? ' · ' + p.vencidas : '')"></span></td>
                                <td class="text-right px-4 font-black" :class="p.cobrar_hoy > 0 ? 'text-rose-600' : 'text-gray-300'" x-text="p.cobrar_hoy > 0 ? soles(p.cobrar_hoy) : '—'"></td>
                            </tr>
                        </template>
                        <tr x-show="!visibles.length"><td colspan="7" class="py-10 text-center text-gray-400">
                            <p class="text-3xl mb-1">💸</p>No hay préstamos aquí. <a href="{{ route('prestamos.nuevo') }}" class="text-emerald-700 font-bold underline">Registra el primero</a>.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function carteraPrestamos() {
            return {
                lista: @js($cartera), filtro: '', q: '',
                frec: @js(\App\Support\Prestamos::FRECUENCIAS),
                nombre: { AL_DIA: 'Al día', ATRASADO: 'Atrasado', MOROSO: 'Moroso', PAGADO: 'Pagado' },
                color: { AL_DIA: 'bg-emerald-100 text-emerald-700', ATRASADO: 'bg-amber-100 text-amber-800', MOROSO: 'bg-rose-100 text-rose-700', PAGADO: 'bg-sky-100 text-sky-700' },
                soles: n => 'S/ ' + (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                fecha: f => f ? f.slice(8, 10) + '/' + f.slice(5, 7) : '',
                get visibles() {
                    const q = this.q.trim().toLowerCase();
                    return this.lista.filter(p => (!this.filtro || p.estado === this.filtro) && (!q || (p.cliente + ' ' + p.dni + ' ' + (p.zona || '')).toLowerCase().includes(q)));
                },
            };
        }
    </script>
@endsection
