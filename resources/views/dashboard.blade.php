@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    @php
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';
        $soles = fn($n) => 'S/ ' . number_format($n, 2);
        $rangos = [
            'Hoy'          => [now()->toDateString(), now()->toDateString()],
            'Ayer'         => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
            'Últimos 7 días' => [now()->subDays(6)->toDateString(), now()->toDateString()],
            'Este mes'     => [now()->startOfMonth()->toDateString(), now()->toDateString()],
            'Mes anterior' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        ];
        $linkVentas = fn($extra = []) => route('ventas.index', ['sucursal' => $sucursal, 'desde' => $desde, 'hasta' => $hasta, 'estado' => 'vigentes'] + $extra);
        $puedeVerVentas = auth()->user()->esAdminOCaja();
        // Nombre visible del tipo de pedido (ped_tip)
        $nombrePedido = ['Salon' => 'Salón', 'Llevar' => 'Para llevar', 'Delivery' => 'Delivery', 'PV' => 'Punto de venta', 'FARMACIA' => 'PV Farmacia', 'POS' => 'POS', 'Directa' => 'Venta directa'];
    @endphp

    {{-- Filtros --}}
    <form class="bg-white rounded-2xl shadow-sm p-4 mb-5">
        <div class="flex flex-wrap items-end gap-3">
            <label class="text-sm min-w-56 flex-1">Establecimiento
                <select name="sucursal" class="{{ $in }}">
                    @foreach ($sucursales as $s)
                        <option value="{{ $s->id_empresa_negocio }}" @selected($sucursal == $s->id_empresa_negocio)>{{ $s->nombre_comercial }} - {{ $s->IdEmpresa }}</option>
                    @endforeach
                </select></label>
            <label class="text-sm">Fecha del<input type="date" name="desde" value="{{ $desde }}" class="{{ $in }}"></label>
            <label class="text-sm">Fecha al<input type="date" name="hasta" value="{{ $hasta }}" class="{{ $in }}"></label>
            <button class="px-5 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700"><i class="fas fa-search"></i> Consultar</button>
        </div>
        <div class="flex flex-wrap gap-2 mt-3">
            @foreach ($rangos as $nombre => [$d, $h])
                <a href="{{ route('dashboard', ['sucursal' => $sucursal, 'desde' => $d, 'hasta' => $h]) }}"
                   class="px-3 py-1 rounded-full text-xs font-semibold {{ $desde === $d && $hasta === $h ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">{{ $nombre }}</a>
            @endforeach
        </div>
    </form>

    {{-- Ahora mismo --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
        <div class="bg-white rounded-2xl shadow-sm p-4 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg"><i class="fas fa-sack-dollar"></i></div>
            <div><p class="text-xs text-gray-500">Vendido hoy</p><p class="font-bold text-gray-800">{{ $soles($ahora['hoy']) }}</p></div>
        </div>
        <a href="{{ route('comandas.seleccion') }}" class="bg-white rounded-2xl shadow-sm p-4 flex items-center gap-3 hover:shadow-md transition">
            <div class="w-11 h-11 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-lg"><i class="fas fa-utensils"></i></div>
            <div><p class="text-xs text-gray-500">Mesas ocupadas</p><p class="font-bold text-gray-800">{{ $ahora['mesas'] }} / {{ $ahora['totalMesas'] }}
                @if ($ahora['llevar'])<span class="text-xs font-normal text-gray-500">+ {{ $ahora['llevar'] }} llevar</span>@endif</p></div>
        </a>
        <div class="bg-white rounded-2xl shadow-sm p-4 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg"><i class="fas fa-hourglass-half"></i></div>
            <div><p class="text-xs text-gray-500">Pedidos por cobrar</p><p class="font-bold text-gray-800">{{ $soles($ahora['porCobrar']) }}</p></div>
        </div>
        <a href="{{ route('turnos.index') }}" class="bg-white rounded-2xl shadow-sm p-4 flex items-center gap-3 hover:shadow-md transition">
            <div class="w-11 h-11 rounded-xl {{ $ahora['turno']->isNotEmpty() ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500' }} flex items-center justify-center text-lg"><i class="fas fa-cash-register"></i></div>
            <div><p class="text-xs text-gray-500">Caja</p>
                <p class="font-bold text-gray-800 text-sm">{{ $ahora['turno']->isNotEmpty() ? $ahora['turno']->count() . ' turno(s) abierto(s)' : 'Sin turno abierto' }}</p></div>
        </a>
        <a href="{{ route('sunat.envios') }}" class="bg-white rounded-2xl shadow-sm p-4 flex items-center gap-3 hover:shadow-md transition col-span-2 lg:col-span-1">
            <div class="w-11 h-11 rounded-xl {{ $ahora['sunat'] ? 'bg-orange-100 text-orange-600' : 'bg-green-100 text-green-600' }} flex items-center justify-center text-lg"><i class="fas fa-paper-plane"></i></div>
            <div><p class="text-xs text-gray-500">Pendientes SUNAT</p><p class="font-bold text-gray-800">{{ $ahora['sunat'] ?: 'Todo enviado ✔' }}</p></div>
        </a>
    </div>

    {{-- Tarjetas del periodo --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 mb-5">
        @foreach ([
            ['Notas de venta', $kpi['notas'], 'from-sky-400 to-cyan-400', 'fa-file-lines', ['tipo' => '13']],
            ['Facturas', $kpi['facturas'], 'from-emerald-400 to-teal-400', 'fa-file-invoice', ['tipo' => '01']],
            ['Boletas', $kpi['boletas'], 'from-pink-500 to-amber-400', 'fa-receipt', ['tipo' => '03']],
        ] as [$titulo, $valor, $gradiente, $icono, $filtro])
            <a href="{{ $puedeVerVentas ? $linkVentas($filtro) : '#' }}" class="relative overflow-hidden rounded-2xl p-5 text-white bg-gradient-to-br {{ $gradiente }} shadow-lg hover:-translate-y-1 transition">
                <p class="text-[11px] font-bold uppercase tracking-wider opacity-90">{{ $titulo }}</p>
                <p class="text-2xl font-extrabold mt-1">{{ $soles($valor) }}</p>
                <p class="text-xs opacity-80 mt-1">{{ $kpi['total'] > 0 ? round($valor / $kpi['total'] * 100) : 0 }}% del total</p>
                <i class="fas {{ $icono }} absolute right-4 bottom-3 text-4xl opacity-25"></i>
            </a>
        @endforeach

        <a href="{{ $puedeVerVentas ? $linkVentas() : '#' }}" class="relative overflow-hidden rounded-2xl p-5 bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-lg hover:-translate-y-1 transition md:col-span-1 xl:col-span-1">
            <p class="text-[11px] font-bold uppercase tracking-wider opacity-90">Total ventas</p>
            <p class="text-2xl font-extrabold mt-1">{{ $soles($kpi['total']) }}</p>
            @if ($kpi['variacion'] !== null)
                <p class="text-xs mt-1 font-semibold {{ $kpi['variacion'] >= 0 ? 'text-emerald-200' : 'text-rose-200' }}">
                    <i class="fas {{ $kpi['variacion'] >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}"></i> {{ $kpi['variacion'] > 0 ? '+' : '' }}{{ $kpi['variacion'] }}% vs periodo anterior</p>
            @else
                <p class="text-xs opacity-80 mt-1">Sin ventas en el periodo anterior</p>
            @endif
            <i class="fas fa-cart-shopping absolute right-4 bottom-3 text-4xl opacity-25"></i>
        </a>

        <div class="relative overflow-hidden rounded-2xl p-5 bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white shadow-lg">
            <p class="text-[11px] font-bold uppercase tracking-wider opacity-90">Utilidad</p>
            <p class="text-2xl font-extrabold mt-1">{{ $soles($kpi['utilidad']) }}</p>
            <p class="text-xs opacity-80 mt-1">{{ $kpi['sinCosto'] ? 'Productos sin costo registrado' : 'Margen ' . $kpi['margen'] . '%' }}</p>
            <i class="fas fa-chart-line absolute right-4 bottom-3 text-4xl opacity-25"></i>
        </div>

        <div class="relative overflow-hidden rounded-2xl p-5 bg-white shadow-lg">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Ticket promedio</p>
            <p class="text-2xl font-extrabold mt-1 text-gray-800">{{ $soles($kpi['ticket']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $kpi['cantidad'] }} comprobantes</p>
            <i class="fas fa-ticket absolute right-4 bottom-3 text-4xl text-gray-200"></i>
        </div>
    </div>

    {{-- Gráficos --}}
    <div class="grid lg:grid-cols-3 gap-5 mb-5">
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm p-5">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h3 class="font-bold text-gray-800"><i class="fas fa-chart-column text-indigo-500"></i> Ventas por día</h3>
                <span class="text-xs text-gray-400">Línea punteada: {{ $antDesde->format('d/m') }} – {{ $antHasta->format('d/m') }}</span>
            </div>
            <div class="h-80"><canvas id="graficoDias"></canvas></div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <h3 class="font-bold text-gray-800 mb-3"><i class="fas fa-wallet text-emerald-500"></i> Medios de pago</h3>
            @if ($medios->isEmpty())
                <p class="text-sm text-gray-400 text-center py-20">Sin ventas en el periodo.</p>
            @else
                <div class="h-56"><canvas id="graficoMedios"></canvas></div>
                <div class="mt-3 space-y-1 text-sm">
                    @foreach ($medios as $m)
                        <div class="flex justify-between"><span class="text-gray-600">{{ $m->nombre ?? 'SIN MEDIO' }}</span><span class="font-semibold">{{ $soles($m->total) }}</span></div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-5 mb-5">
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm p-5">
            <h3 class="font-bold text-gray-800 mb-3"><i class="fas fa-clock text-amber-500"></i> ¿A qué hora se vende más?</h3>
            <div class="h-64"><canvas id="graficoHoras"></canvas></div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <h3 class="font-bold text-gray-800 mb-3"><i class="fas fa-bell-concierge text-rose-500"></i> Por tipo de pedido</h3>
            @if ($porPedido->isEmpty())
                <p class="text-sm text-gray-400 text-center py-20">Sin ventas en el periodo.</p>
            @else
                <div class="h-56"><canvas id="graficoPedido"></canvas></div>
                <div class="mt-3 space-y-1 text-sm">
                    @foreach ($porPedido as $p)
                        <div class="flex justify-between"><span class="text-gray-600">{{ $nombrePedido[$p->tipo] ?? $p->tipo }} <span class="text-xs text-gray-400">({{ $p->n }})</span></span><span class="font-semibold">{{ $soles($p->total) }}</span></div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Rankings e inventario --}}
    {{-- Farmacia: productos pronto a vencer (solo si la sucursal usa lotes) --}}
    @if ($vencimientos['total'] > 0)
        @php $hoyD = now()->startOfDay(); @endphp
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-5">
            <div class="px-4 sm:px-5 py-3 bg-gradient-to-r from-teal-600 to-cyan-600 text-white flex flex-wrap items-center justify-between gap-2">
                <span class="font-bold"><i class="fas fa-pills"></i> Productos pronto a vencer</span>
                <a href="{{ route('lotes.index') }}" class="text-xs bg-white/20 hover:bg-white/30 rounded-full px-3 py-1">Ver todos los lotes →</a>
            </div>

            <div class="grid grid-cols-3 divide-x divide-gray-100 border-b border-gray-100 text-center">
                <a href="{{ route('lotes.index', ['estado' => 'vencidos']) }}" class="p-3 hover:bg-rose-50">
                    <p class="text-xl sm:text-2xl font-extrabold text-rose-600">{{ $vencimientos['vencidos'] }}</p>
                    <p class="text-[11px] sm:text-xs text-gray-500">Vencidos</p>
                </a>
                <a href="{{ route('lotes.index', ['estado' => 'proximos']) }}" class="p-3 hover:bg-orange-50">
                    <p class="text-xl sm:text-2xl font-extrabold text-orange-500">{{ $vencimientos['en30'] }}</p>
                    <p class="text-[11px] sm:text-xs text-gray-500">Vencen en 30 días</p>
                </a>
                <a href="{{ route('lotes.index', ['estado' => 'proximos']) }}" class="p-3 hover:bg-amber-50">
                    <p class="text-xl sm:text-2xl font-extrabold text-amber-500">{{ $vencimientos['alerta'] }}</p>
                    <p class="text-[11px] sm:text-xs text-gray-500">Vencen en {{ $diasAlerta }} días</p>
                </a>
            </div>

            @if ($porVencer->isEmpty())
                <p class="px-5 py-6 text-center text-sm text-gray-400">✔ Ningún lote vence en los próximos {{ $diasAlerta }} días.</p>
            @else
                <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3 p-3 sm:p-4">
                    @foreach ($porVencer as $l)
                        @php
                            $r = (int) $hoyD->diffInDays($l->vencimiento, false);
                            [$txt, $cls] = $r < 0 ? ['Vencido hace ' . abs($r) . ' d', 'bg-rose-600']
                                : ($r === 0 ? ['Vence HOY', 'bg-rose-600'] : ($r <= 30 ? ["En {$r} días", 'bg-orange-500'] : ["En {$r} días", 'bg-amber-500']));
                        @endphp
                        <div class="flex items-center gap-3 rounded-xl border p-3 {{ $r < 0 ? 'border-rose-200 bg-rose-50/50' : 'border-gray-100' }}">
                            <div class="w-12 shrink-0 text-center">
                                <p class="text-lg font-extrabold leading-none {{ $r < 0 ? 'text-rose-600' : 'text-gray-800' }}">{{ \Carbon\Carbon::parse($l->vencimiento)->format('d') }}</p>
                                <p class="text-[10px] uppercase text-gray-500">{{ \Carbon\Carbon::parse($l->vencimiento)->locale('es')->isoFormat('MMM YY') }}</p>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-gray-700 text-sm truncate" title="{{ $l->pronom }}">{{ $l->pronom }}</p>
                                <p class="text-xs text-gray-400 truncate">Lote {{ $l->lote }} · {{ rtrim(rtrim(number_format($l->stock, 2), '0'), '.') }} {{ $l->umecod }} · {{ $l->almacen }}</p>
                            </div>
                            <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold text-white {{ $cls }}">{{ $txt }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <div class="grid lg:grid-cols-2 xl:grid-cols-3 gap-5">
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden xl:col-span-2">
            <div class="px-5 py-3 bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-bold"><i class="fas fa-star"></i> Productos más vendidos</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50"><tr>
                        <th class="px-4 py-2 text-left">#</th><th class="px-4 py-2 text-left">Producto</th><th class="px-4 py-2 text-right">Cantidad</th>
                        <th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2 text-right">Utilidad</th><th class="px-4 py-2 w-40"></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @php $maxProd = max(1, (float) ($topProductos->max('total') ?? 1)); @endphp
                        @forelse ($topProductos as $i => $p)
                            <tr>
                                <td class="px-4 py-2 text-gray-400 font-bold">{{ $i + 1 }}</td>
                                <td class="px-4 py-2 font-semibold text-gray-700">{{ $p->cdedes }}<span class="block text-xs font-normal text-gray-400">{{ $p->procod }}</span></td>
                                <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($p->cantidad, 2), '0'), '.') }}</td>
                                <td class="px-4 py-2 text-right font-semibold text-emerald-700">{{ number_format($p->total, 2) }}</td>
                                <td class="px-4 py-2 text-right text-gray-600">{{ number_format($p->utilidad, 2) }}</td>
                                <td class="px-4 py-2"><div class="h-2 rounded-full bg-gray-100"><div class="h-2 rounded-full bg-emerald-500" style="width: {{ round($p->total / $maxProd * 100) }}%"></div></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Sin ventas en el periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-gradient-to-r from-rose-500 to-orange-500 text-white font-bold flex justify-between items-center">
                <span><i class="fas fa-triangle-exclamation"></i> Productos por agotarse</span>
                <a href="{{ route('kardex.movimiento', ['tipo' => 'I']) }}" class="text-xs bg-white/20 hover:bg-white/30 rounded-full px-3 py-1">+ Ingreso</a>
            </div>
            <div class="max-h-96 overflow-y-auto divide-y divide-gray-100">
                @forelse ($porAgotarse as $p)
                    <div class="flex items-center justify-between px-5 py-2.5">
                        <div><p class="font-semibold text-gray-700 text-sm">{{ $p->pronom }}</p>
                            <p class="text-xs text-gray-400">Stock: {{ rtrim(rtrim(number_format($p->stock, 2), '0'), '.') }} · mínimo {{ rtrim(rtrim(number_format($p->stock_min, 2), '0'), '.') }}</p></div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold text-white {{ $p->stock <= 0 ? 'bg-red-600' : 'bg-orange-500' }}">{{ $p->stock <= 0 ? 'AGOTADO' : 'POCAS' }}</span>
                            <a href="{{ route('kardex.index', ['producto' => $p->IdProducto]) }}" title="Ver kardex" class="text-indigo-500 hover:text-indigo-700"><i class="fas fa-clipboard-list"></i></a>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-gray-400">✔ Ningún producto bajo su stock mínimo.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-gradient-to-r from-sky-500 to-cyan-500 text-white font-bold"><i class="fas fa-users"></i> Mejores clientes</div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    @forelse ($topClientes as $c)
                        <tr><td class="px-4 py-2"><span class="font-semibold text-gray-700">{{ $c->ccanom }}</span><span class="block text-xs text-gray-400">{{ $c->ccandi }} · {{ $c->compras }} compra(s)</span></td>
                            <td class="px-4 py-2 text-right font-semibold text-sky-700">{{ number_format($c->total, 2) }}</td></tr>
                    @empty
                        <tr><td class="px-4 py-6 text-center text-gray-400">Sin clientes identificados en el periodo.</td></tr>
                    @endforelse
                    @if ($ventasVarios > 0)
                        <tr class="bg-gray-50"><td class="px-4 py-2 text-xs text-gray-500">Clientes varios (sin documento)</td><td class="px-4 py-2 text-right text-xs text-gray-500">{{ number_format($ventasVarios, 2) }}</td></tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-gradient-to-r from-indigo-500 to-violet-500 text-white font-bold"><i class="fas fa-user-tie"></i> Ventas por mozo</div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    @php $maxMozo = max(1, (float) ($topMozos->max('total') ?? 1)); @endphp
                    @forelse ($topMozos as $m)
                        <tr><td class="px-4 py-2">
                                <span class="font-semibold text-gray-700">{{ $m->apeusu ?? 'Sin mozo' }}</span>
                                <span class="block text-xs text-gray-400">{{ $m->atenciones }} atención(es)</span>
                                <div class="h-1.5 mt-1 rounded-full bg-gray-100"><div class="h-1.5 rounded-full bg-indigo-500" style="width: {{ round($m->total / $maxMozo * 100) }}%"></div></div>
                            </td>
                            <td class="px-4 py-2 text-right font-semibold text-indigo-700 align-top">{{ number_format($m->total, 2) }}</td></tr>
                    @empty
                        <tr><td class="px-4 py-6 text-center text-gray-400">Sin ventas en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
        (function () {
            if (!window.Chart) return;
            const soles = v => 'S/ ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            Chart.defaults.font.family = 'ui-sans-serif, system-ui, sans-serif';
            Chart.defaults.color = '#64748b';
            const tooltipSoles = { callbacks: { label: c => ' ' + (c.dataset.label ? c.dataset.label + ': ' : '') + soles(c.parsed.y ?? c.parsed) } };

            // Ventas por día: barras del periodo actual + línea del anterior
            const dias = @json($graficoDias);
            const ctxDias = document.getElementById('graficoDias').getContext('2d');
            const degradado = ctxDias.createLinearGradient(0, 0, 0, 320);
            degradado.addColorStop(0, 'rgba(99, 102, 241, 0.9)');
            degradado.addColorStop(1, 'rgba(139, 92, 246, 0.35)');
            new Chart(ctxDias, {
                data: {
                    labels: dias.labels,
                    datasets: [
                        { type: 'bar', label: 'Periodo actual', data: dias.actual, backgroundColor: degradado, borderRadius: 6, maxBarThickness: 38, order: 2 },
                        { type: 'line', label: 'Periodo anterior', data: dias.anterior, borderColor: '#f59e0b', borderDash: [6, 4], borderWidth: 2,
                          pointRadius: 0, tension: 0.35, fill: false, order: 1 },
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' },
                    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true } }, tooltip: tooltipSoles },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => 'S/ ' + v.toLocaleString('es-PE') }, grid: { color: 'rgba(0,0,0,.05)' } },
                              x: { grid: { display: false } } }
                }
            });

            // Ventas por hora
            const horas = @json($graficoHoras);
            const maxHora = Math.max(...horas.datos);
            new Chart(document.getElementById('graficoHoras'), {
                type: 'bar',
                data: { labels: horas.labels, datasets: [{ label: 'Ventas', data: horas.datos, borderRadius: 4,
                    backgroundColor: horas.datos.map(v => v === maxHora && v > 0 ? '#f59e0b' : 'rgba(245, 158, 11, 0.35)') }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: tooltipSoles },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => 'S/ ' + v.toLocaleString('es-PE') }, grid: { color: 'rgba(0,0,0,.05)' } }, x: { grid: { display: false } } } }
            });

            // Dona con el total al centro
            const centro = {
                id: 'centro',
                afterDraw(chart) {
                    const { ctx, chartArea: { left, right, top, bottom } } = chart;
                    const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.fillStyle = '#1f2937';
                    ctx.font = 'bold 15px ui-sans-serif, system-ui';
                    ctx.fillText(soles(total), (left + right) / 2, (top + bottom) / 2 + 5);
                    ctx.restore();
                }
            };
            const colorMedio = n => {
                n = (n || '').toUpperCase();
                if (n.includes('EFECTIVO')) return '#10b981';
                if (n.includes('YAPE')) return '#8b5cf6';
                if (n.includes('PLIN')) return '#06b6d4';
                if (n.includes('CRÉDITO')) return '#f59e0b';
                if (n.includes('TARJETA') || n.includes('VISA') || n.includes('POS')) return '#3b82f6';
                return '#94a3b8';
            };
            const dona = (id, labels, datos, colores) => {
                const el = document.getElementById(id);
                if (!el) return;
                new Chart(el, {
                    type: 'doughnut', plugins: [centro],
                    data: { labels, datasets: [{ data: datos, backgroundColor: colores, borderWidth: 2, borderColor: '#fff', hoverOffset: 8 }] },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '68%',
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ' ' + c.label + ': ' + soles(c.parsed) } } } }
                });
            };

            const medios = @json($medios);
            dona('graficoMedios', medios.map(m => m.nombre || 'SIN MEDIO'), medios.map(m => Number(m.total)), medios.map(m => colorMedio(m.nombre)));

            const pedidos = @json($porPedido);
            const nombrePedido = @json($nombrePedido);
            const colorPedido = { Salon: '#f43f5e', Llevar: '#3b82f6', Delivery: '#22c55e', PV: '#a855f7', POS: '#6366f1', Directa: '#94a3b8' };
            dona('graficoPedido', pedidos.map(p => nombrePedido[p.tipo] || p.tipo), pedidos.map(p => Number(p.total)),
                pedidos.map(p => colorPedido[p.tipo] || '#a855f7'));
        })();
    </script>
@endsection
