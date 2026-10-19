@extends('layouts.app')
@section('title', 'Reportes de préstamos')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $tarjeta = 'bg-white rounded-2xl shadow-sm p-5 print:shadow-none print:border';
        $maxMes = max(1, collect($meses)->max(fn ($m) => max($m['cobrado'], $m['prestado'])));
        $maxTramo = max(1, collect($tramos)->max('saldo'));
        $estados = \App\Support\Prestamos::ESTADOS;
        $chip = ['emerald' => 'bg-emerald-100 text-emerald-700', 'amber' => 'bg-amber-100 text-amber-800', 'rose' => 'bg-rose-100 text-rose-700', 'sky' => 'bg-sky-100 text-sky-700', 'slate' => 'bg-slate-100 text-slate-600'];
    @endphp
    <div x-data="{ tab: new URLSearchParams(location.search).get('tab') || 'cuadre', filtro: '', q: '' }" class="space-y-5">
        <div class="rounded-3xl p-6 text-white bg-gradient-to-br from-slate-800 via-emerald-900 to-emerald-700 shadow-lg print:bg-none print:text-black">
            <a href="{{ route('prestamos.index') }}" class="text-sm text-white/70 print:hidden">← Préstamos</a>
            <p class="text-xs uppercase tracking-widest text-emerald-200/80">Reportes de préstamos</p>
            <h1 class="text-2xl font-black">{{ $negocio }}</h1>
            <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-2xl bg-white/10 p-3"><p class="text-xs text-emerald-100">Capital en la calle</p><p class="text-xl font-black">{{ $soles($capitalCalle) }}</p></div>
                <div class="rounded-2xl bg-white/10 p-3"><p class="text-xs text-emerald-100">Ganancia histórica</p><p class="text-xl font-black text-lime-300">{{ $soles($gananciaTotal) }}</p></div>
                <div class="rounded-2xl bg-white/10 p-3"><p class="text-xs text-emerald-100">Con atraso</p><p class="text-xl font-black text-amber-300">{{ $atraso }}%</p><p class="text-[10px] text-emerald-100">de lo que se debe</p></div>
                <div class="rounded-2xl bg-white/10 p-3"><p class="text-xs text-emerald-100">Morosidad</p><p class="text-xl font-black text-rose-300">{{ $morosidad }}%</p><p class="text-[10px] text-emerald-100">saldo de morosos</p></div>
            </div>
        </div>

        <div class="flex flex-wrap gap-1.5 print:hidden">
            @foreach (['cuadre' => ['fa-cash-register', 'Cuadre del día'], 'deudores' => ['fa-users', 'Clientes que deben'], 'mora' => ['fa-triangle-exclamation', 'Morosidad'],
                'ganancias' => ['fa-arrow-trend-up', 'Ganancias'], 'cobradores' => ['fa-motorcycle', 'Cobradores']] as $k => [$i, $n])
                <button type="button" @click="tab = '{{ $k }}'" class="px-4 py-2.5 rounded-xl text-sm font-bold" :class="tab === '{{ $k }}' ? 'bg-emerald-600 text-white shadow' : 'bg-white text-gray-600'"><i class="fas {{ $i }}"></i> {{ $n }}</button>
            @endforeach
            <button type="button" onclick="window.print()" class="ml-auto px-4 py-2.5 rounded-xl bg-white text-sm font-bold text-gray-600"><i class="fas fa-print"></i> Imprimir</button>
        </div>

        {{-- Cuadre del día --}}
        <div x-show="tab === 'cuadre'" class="space-y-5">
            <form method="GET" class="{{ $tarjeta }} !p-4 flex flex-wrap items-end gap-3 print:hidden">
                <label class="text-xs font-semibold text-gray-500">Día<input type="date" name="fecha" value="{{ $fecha }}" max="{{ now()->toDateString() }}" class="block mt-1 rounded-lg border-gray-300 text-sm"></label>
                <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-bold">Ver</button>
                <p class="text-sm text-gray-500 ml-auto">{{ \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM YYYY') }}</p>
            </form>
            <div class="grid sm:grid-cols-3 gap-3">
                <div class="{{ $tarjeta }}"><p class="text-xs uppercase font-semibold text-gray-400">Entró (cobros)</p><p class="text-3xl font-black text-emerald-600">{{ $soles($cobradoDia) }}</p></div>
                <div class="{{ $tarjeta }}"><p class="text-xs uppercase font-semibold text-gray-400">Salió (préstamos)</p><p class="text-3xl font-black text-sky-600">{{ $soles($prestadoDia) }}</p></div>
                <div class="{{ $tarjeta }}"><p class="text-xs uppercase font-semibold text-gray-400">Diferencia del día</p><p class="text-3xl font-black {{ $cobradoDia - $prestadoDia >= 0 ? 'text-gray-800' : 'text-rose-600' }}">{{ $soles($cobradoDia - $prestadoDia) }}</p></div>
            </div>
            <div class="grid lg:grid-cols-2 gap-5">
                <div class="{{ $tarjeta }}">
                    <h2 class="font-bold text-gray-800 mb-2">Por medio de pago</h2>
                    <table class="w-full text-sm"><thead class="text-xs text-gray-400 uppercase"><tr><th class="text-left py-1">Medio</th><th class="text-right">Entró</th><th class="text-right">Salió</th><th class="text-right">Queda</th></tr></thead>
                        <tbody class="divide-y">
                            @forelse ($medios as $m => $v)
                                <tr><td class="py-2 font-semibold">{{ $m }}</td><td class="text-right text-emerald-700">{{ $soles($v['entra']) }}</td><td class="text-right text-sky-700">{{ $soles($v['sale']) }}</td><td class="text-right font-bold">{{ $soles($v['entra'] - $v['sale']) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="py-4 text-center text-gray-400">Sin movimientos.</td></tr>
                            @endforelse
                        </tbody></table>
                    <h2 class="font-bold text-gray-800 mt-5 mb-2">Por usuario (lo que debe entregar)</h2>
                    @forelse ($porUsuario as $x)
                        <div class="flex justify-between text-sm border-b last:border-0 py-1.5"><span>{{ $x['nombre'] }} <span class="text-xs text-gray-400">· {{ $x['cobros'] }} cobro(s)</span></span>
                            <b>{{ $soles($x['monto'] + $x['mora']) }}</b></div>
                    @empty
                        <p class="text-sm text-gray-400">Nadie cobró ese día.</p>
                    @endforelse
                </div>
                <div class="{{ $tarjeta }}">
                    <h2 class="font-bold text-gray-800 mb-2">Préstamos entregados</h2>
                    @forelse ($desembolsos as $d)
                        <a href="{{ route('prestamos.ver', $d->pre_id) }}" class="flex justify-between text-sm border-b last:border-0 py-1.5 hover:bg-gray-50">
                            <span>{{ $d->clinom }} <span class="text-xs text-gray-400">· N° {{ str_pad((string) $d->numero, 5, '0', STR_PAD_LEFT) }}{{ $d->renueva_pre_id ? ' · renovación' : '' }} · {{ $d->medio_entrega }}</span></span>
                            <b class="text-sky-700">{{ $soles($d->entregado) }}</b></a>
                    @empty
                        <p class="text-sm text-gray-400">No se prestó ese día.</p>
                    @endforelse
                </div>
            </div>
            <div class="{{ $tarjeta }} !p-0 overflow-hidden">
                <h2 class="font-bold text-gray-800 p-4 pb-2">Cobros del día ({{ $pagos->where('anulado', 0)->count() }})</h2>
                <div class="overflow-x-auto"><table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-400"><tr><th class="text-left px-4 py-2">Hora</th><th class="text-left">Cliente</th><th class="text-left">Medio</th><th class="text-left">Cobró</th><th class="text-right px-4">Monto</th></tr></thead>
                    <tbody class="divide-y">
                        @forelse ($pagos as $g)
                            <tr class="{{ $g->anulado ? 'line-through text-gray-400' : '' }}"><td class="px-4 py-2">{{ \Carbon\Carbon::parse($g->fecha_hora)->format('H:i') }}</td>
                                <td><a href="{{ route('prestamos.ver', $g->pre_id) }}" class="hover:underline">{{ $g->clinom }}</a></td>
                                <td>{{ $g->medio }}{{ $g->operacion ? ' · '.$g->operacion : '' }}</td><td>{{ $g->usuario }}</td>
                                <td class="text-right px-4 font-bold">{{ $soles($g->monto + $g->mora) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-gray-400">Sin cobros.</td></tr>
                        @endforelse
                    </tbody></table></div>
            </div>
        </div>

        {{-- Clientes que deben --}}
        <div x-show="tab === 'deudores'" x-cloak class="{{ $tarjeta }} !p-0 overflow-hidden">
            <div class="p-4 flex flex-wrap gap-2 items-center border-b print:hidden">
                <h2 class="font-bold text-gray-800 mr-auto">Clientes con préstamo vigente ({{ $deudores->count() }})</h2>
                <select x-model="filtro" class="rounded-lg border-gray-300 text-sm"><option value="">Todos</option><option value="AL_DIA">Al día</option><option value="ATRASADO">Atrasados</option><option value="MOROSO">Morosos</option></select>
                <input x-model="q" placeholder="Buscar" class="rounded-lg border-gray-300 text-sm">
            </div>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-400"><tr><th class="text-left px-4 py-2">Cliente</th><th class="text-right">Prestó</th><th class="text-right">Total</th><th class="text-right">Pagado</th>
                    <th class="text-right">Saldo</th><th class="text-center">Atraso</th><th class="text-right">Debe a hoy</th><th class="text-center">Estado</th><th class="print:hidden"></th></tr></thead>
                <tbody class="divide-y">
                    @foreach ($deudores as $p)
                        @php [$n, $c] = $estados[$p->estado]; @endphp
                        <tr x-show="(!filtro || filtro === '{{ $p->estado }}') && (!q || @js(mb_strtolower($p->clinom.' '.$p->clinum.' '.$p->zona)).includes(q.toLowerCase()))">
                            <td class="px-4 py-2"><a href="{{ route('prestamos.ver', $p->pre_id) }}" class="font-semibold hover:underline">{{ $p->clinom }}</a>
                                <span class="block text-[11px] text-gray-400">{{ $p->clinum }} · {{ $p->zona ?: 'sin zona' }} · {{ $p->telefono }}</span></td>
                            <td class="text-right">{{ $soles($p->monto) }}</td><td class="text-right">{{ $soles($p->total) }}</td><td class="text-right text-emerald-700">{{ $soles($p->pagado) }}</td>
                            <td class="text-right font-bold">{{ $soles($p->saldo) }}</td><td class="text-center">{{ $p->vencidas ?: '—' }}</td>
                            <td class="text-right font-bold {{ $p->cobrar_hoy > 0 ? 'text-rose-600' : 'text-gray-300' }}">{{ $p->cobrar_hoy > 0 ? $soles($p->cobrar_hoy) : '—' }}</td>
                            <td class="text-center"><span class="px-2 py-0.5 rounded-full text-[11px] font-extrabold {{ $chip[$c] }}">{{ $n }}</span></td>
                            <td class="px-2 print:hidden">@if ($w = \App\Support\Prestamos::whatsapp($p->telefono, "Hola {$p->clinom}, le saludamos de {$negocio}. Su saldo es S/ ".number_format($p->saldo, 2).($p->cobrar_hoy > 0 ? ' y a hoy debe S/ '.number_format($p->cobrar_hoy, 2) : '').'. ¡Gracias!'))
                                <a href="{{ $w }}" target="_blank" class="px-2 py-1 rounded-lg bg-green-600 text-white text-xs"><i class="fab fa-whatsapp"></i></a>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 font-bold"><tr><td class="px-4 py-2">Total</td><td class="text-right">{{ $soles($deudores->sum('monto')) }}</td><td class="text-right">{{ $soles($deudores->sum('total')) }}</td>
                    <td class="text-right">{{ $soles($deudores->sum('pagado')) }}</td><td class="text-right">{{ $soles($deudores->sum('saldo')) }}</td><td></td><td class="text-right">{{ $soles($deudores->sum('cobrar_hoy')) }}</td><td colspan="2"></td></tr></tfoot>
            </table></div>
        </div>

        {{-- Morosidad --}}
        <div x-show="tab === 'mora'" x-cloak class="grid lg:grid-cols-2 gap-5">
            <div class="{{ $tarjeta }}">
                <h2 class="font-bold text-gray-800 mb-1">Atraso por antigüedad</h2>
                <p class="text-xs text-gray-400 mb-3">Mientras más cuotas atrasadas, más difícil de recuperar.</p>
                @foreach ($tramos as $t => $v)
                    <div class="py-2"><div class="flex justify-between text-sm"><span>{{ $t }} <span class="text-xs text-gray-400">· {{ $v['n'] }} préstamo(s)</span></span><b>{{ $soles($v['saldo']) }}</b></div>
                        <div class="h-3 mt-1 rounded-full bg-gray-100"><div class="h-3 rounded-full {{ $loop->index === 0 ? 'bg-amber-400' : ($loop->index === 1 ? 'bg-orange-500' : 'bg-rose-600') }}" style="width: {{ $v['saldo'] / $maxTramo * 100 }}%"></div></div></div>
                @endforeach
            </div>
            <div class="{{ $tarjeta }}">
                <h2 class="font-bold text-gray-800 mb-2">Los más atrasados</h2>
                @foreach ($deudores->where('vencidas', '>', 0)->take(15) as $p)
                    <a href="{{ route('prestamos.ver', $p->pre_id) }}" class="flex justify-between text-sm border-b last:border-0 py-1.5 hover:bg-gray-50">
                        <span>{{ $p->clinom }} <span class="text-xs text-gray-400">· {{ $p->vencidas }} cuotas · {{ $p->cobrador ?: 'sin cobrador' }}</span></span><b class="text-rose-600">{{ $soles($p->cobrar_hoy) }}</b></a>
                @endforeach
                @if (! $deudores->where('vencidas', '>', 0)->count())<p class="py-6 text-center text-gray-400">🎉 Nadie está atrasado.</p>@endif
            </div>
        </div>

        {{-- Ganancias --}}
        <div x-show="tab === 'ganancias'" x-cloak class="{{ $tarjeta }}">
            <h2 class="font-bold text-gray-800 mb-4">Últimos 6 meses</h2>
            <div class="flex items-end gap-4 h-56 mb-4">
                @foreach ($meses as $m)
                    <div class="flex-1 h-full flex flex-col justify-end items-center">
                        <div class="w-full flex items-end gap-1 h-full">
                            <div class="flex-1 rounded-t bg-sky-300" style="height: {{ max($m['prestado'] > 0 ? 2 : 0, $m['prestado'] / $maxMes * 100) }}%" title="Prestado {{ $soles($m['prestado']) }}"></div>
                            <div class="flex-1 rounded-t bg-emerald-500" style="height: {{ max($m['cobrado'] > 0 ? 2 : 0, $m['cobrado'] / $maxMes * 100) }}%" title="Cobrado {{ $soles($m['cobrado']) }}"></div>
                            <div class="flex-1 rounded-t bg-lime-400" style="height: {{ max($m['interes'] + $m['mora'] > 0 ? 2 : 0, ($m['interes'] + $m['mora']) / $maxMes * 100) }}%" title="Ganancia {{ $soles($m['interes'] + $m['mora']) }}"></div>
                        </div>
                        <span class="text-xs text-gray-500 mt-1">{{ $m['mes'] }}</span>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-gray-500 mb-3"><span class="inline-block w-3 h-3 rounded bg-sky-300 align-middle"></span> Prestado <span class="inline-block w-3 h-3 rounded bg-emerald-500 align-middle ml-2"></span> Cobrado
                <span class="inline-block w-3 h-3 rounded bg-lime-400 align-middle ml-2"></span> Ganancia (interés + mora)</p>
            <table class="w-full text-sm"><thead class="text-xs uppercase text-gray-400"><tr><th class="text-left py-1">Mes</th><th class="text-right">Préstamos</th><th class="text-right">Prestado</th><th class="text-right">Cobrado</th><th class="text-right">Interés</th><th class="text-right">Mora</th><th class="text-right">Ganancia</th></tr></thead>
                <tbody class="divide-y">
                    @foreach ($meses as $m)
                        <tr><td class="py-2 font-semibold">{{ $m['mes'] }}</td><td class="text-right">{{ $m['nuevos'] }}</td><td class="text-right">{{ $soles($m['prestado']) }}</td><td class="text-right">{{ $soles($m['cobrado']) }}</td>
                            <td class="text-right">{{ $soles($m['interes']) }}</td><td class="text-right">{{ $soles($m['mora']) }}</td><td class="text-right font-black text-lime-700">{{ $soles($m['interes'] + $m['mora']) }}</td></tr>
                    @endforeach
                </tbody></table>
            <p class="text-[11px] text-gray-400 mt-3">La ganancia es la parte de interés de cada cuota cobrada (ej. de una cuota de S/ 6 de un préstamo de S/ 100 al 20%, S/ 1 es ganancia) más la mora.</p>
        </div>

        {{-- Cobradores --}}
        <div x-show="tab === 'cobradores'" x-cloak class="{{ $tarjeta }} !p-0 overflow-hidden">
            <h2 class="font-bold text-gray-800 p-4 pb-2">Rendimiento por cobrador · {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</h2>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-400"><tr><th class="text-left px-4 py-2">Cobrador</th><th class="text-right">Préstamos</th><th class="text-right">Cartera</th><th class="text-right">Al día</th><th class="text-right">Morosos</th>
                    <th class="text-right">Cobrado del día</th><th class="text-right">Falta hoy</th><th class="text-right">Efectividad</th><th class="text-right px-4">Cobrado del mes</th></tr></thead>
                <tbody class="divide-y">
                    @foreach ($cobradores as $c)
                        @php $ef = $c['cobrado_dia'] + $c['pendiente_dia'] > 0 ? round($c['cobrado_dia'] / ($c['cobrado_dia'] + $c['pendiente_dia']) * 100) : null; @endphp
                        <tr><td class="px-4 py-2 font-semibold">{{ $c['nombre'] }}</td><td class="text-right">{{ $c['prestamos'] }}</td><td class="text-right">{{ $soles($c['saldo']) }}</td>
                            <td class="text-right text-emerald-700">{{ $c['al_dia'] }}</td><td class="text-right text-rose-600">{{ $c['morosos'] }}</td><td class="text-right font-bold">{{ $soles($c['cobrado_dia']) }}</td>
                            <td class="text-right text-rose-600">{{ $soles($c['pendiente_dia']) }}</td>
                            <td class="text-right">@if ($ef !== null)<span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $ef >= 80 ? 'bg-emerald-100 text-emerald-700' : ($ef >= 50 ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-700') }}">{{ $ef }}%</span>@else — @endif</td>
                            <td class="text-right px-4 font-bold">{{ $soles($c['cobrado_mes']) }}</td></tr>
                    @endforeach
                </tbody></table></div>
        </div>
    </div>
@endsection
