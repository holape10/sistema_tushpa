@extends('layouts.app')
@section('title', 'Reportes de envíos')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $tarjeta = 'bg-white rounded-2xl shadow-sm p-5';
        $maxDia = max(1, (float) collect($porDia)->max('total'));
        $estados = \App\Support\Courier::ESTADOS;
    @endphp

    <div class="space-y-5" x-data="reportesCourier()">
        @include('empresas.courier.partials.nav')
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-gray-800"><i class="fas fa-chart-column text-orange-600"></i> Reportes de envíos</h1>
                <p class="text-sm text-gray-500">De lo que sale de tu agencia del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}.</p>
            </div>
            <form class="flex flex-wrap gap-2 items-end">
                <label class="text-xs font-semibold text-gray-500">Desde<input type="date" name="desde" value="{{ $desde->toDateString() }}" class="mt-1 block h-10 rounded-lg border-gray-300 text-sm"></label>
                <label class="text-xs font-semibold text-gray-500">Hasta<input type="date" name="hasta" value="{{ $hasta->toDateString() }}" class="mt-1 block h-10 rounded-lg border-gray-300 text-sm"></label>
                <button class="h-10 px-4 rounded-lg bg-gray-800 text-white text-sm font-bold">Ver</button>
            </form>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ([['Envíos', $kpi->envios, $kpi->piezas.' bultos · '.rtrim(rtrim(number_format($kpi->kg, 2), '0'), '.').' kg', 'fa-box', 'text-orange-600'],
                ['Facturado en fletes', $soles($kpi->total), 'promedio '.$soles($kpi->ticket).' por envío', 'fa-sack-dollar', 'text-emerald-600'],
                ['Por cobrar', $soles($kpi->porCobrar), 'al recoger o a crédito', 'fa-clock', 'text-rose-600'],
                ['Entregados a tiempo', $kpi->entregados ? round($kpi->aTiempo * 100 / $kpi->entregados).'%' : '—', $kpi->aTiempo.' de '.$kpi->entregados.' entregados', 'fa-stopwatch', 'text-sky-600']]
                as [$titulo, $valor, $sub, $icono, $color])
                <div class="bg-white rounded-2xl shadow-sm p-4">
                    <p class="text-[11px] font-semibold uppercase text-gray-400"><i class="fas {{ $icono }} {{ $color }}"></i> {{ $titulo }}</p>
                    <p class="text-2xl font-black text-gray-800 mt-1">{{ $valor }}</p>
                    <p class="text-xs text-gray-400">{{ $sub }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid lg:grid-cols-3 gap-5 items-start">
            <div class="lg:col-span-2 {{ $tarjeta }}">
                <h2 class="font-black text-gray-800 mb-3">Por día</h2>
                @if ($porDia->isEmpty())<p class="text-sm text-gray-400">Sin envíos en estas fechas.</p>@endif
                <div class="flex items-end gap-1 h-40">
                    @foreach ($porDia as $dia => $d)
                        <div class="flex-1 min-w-2 max-w-12 flex flex-col items-center justify-end h-full" title="{{ \Carbon\Carbon::parse($dia)->format('d/m') }}: {{ $d['envios'] }} envío(s) · {{ $soles($d['total']) }}">
                            <div class="w-full rounded-t bg-orange-500" style="height: {{ max(3, round($d['total'] * 100 / $maxDia)) }}%"></div>
                            <span class="text-[9px] text-gray-400 mt-1">{{ \Carbon\Carbon::parse($dia)->format('d') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="{{ $tarjeta }} text-sm space-y-1">
                <h2 class="font-black text-gray-800 mb-2">Estados y forma de pago</h2>
                @foreach ($porEstado as $estado => $n)<p class="flex justify-between"><span>{{ $estados[$estado][0] ?? $estado }}</span><b>{{ $n }}</b></p>@endforeach
                <div class="border-t pt-2 mt-2"></div>
                @foreach ($porPago as $pago => $total)<p class="flex justify-between"><span>{{ \App\Support\Courier::PAGOS[$pago] ?? $pago }}</span><b>{{ $soles($total) }}</b></p>@endforeach
                @if ($reclamos->isNotEmpty())
                    <div class="border-t pt-2 mt-2"></div>
                    <p class="flex justify-between"><span>Reclamos en el periodo</span><a href="{{ route('courier.reclamos') }}" class="font-bold text-orange-700">{{ $reclamos->sum() }}</a></p>
                @endif
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-5 items-start">
            <div class="{{ $tarjeta }}">
                <h2 class="font-black text-gray-800 mb-2">Por destino</h2>
                <table class="w-full text-sm"><thead><tr class="text-left text-xs uppercase text-gray-400"><th class="py-1">Destino</th><th class="text-right">Envíos</th><th class="text-right">Kg</th><th class="text-right">Total</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($porDestino as $d)<tr><td class="py-1.5">{{ $d->agencia }}</td><td class="text-right">{{ $d->envios }}</td><td class="text-right">{{ rtrim(rtrim(number_format($d->kg, 2), '0'), '.') }}</td><td class="text-right font-bold">{{ $soles($d->total) }}</td></tr>@endforeach
                    </tbody></table>
            </div>
            <div class="{{ $tarjeta }}">
                <h2 class="font-black text-gray-800 mb-2">Mejores clientes</h2>
                <table class="w-full text-sm"><tbody class="divide-y divide-gray-100">
                    @foreach ($clientes as $c)<tr><td class="py-1.5">{{ $c->nombre }} <span class="text-xs text-gray-400">{{ $c->doc !== '00000000' ? $c->doc : '' }}</span></td><td class="text-right">{{ $c->envios }}</td><td class="text-right font-bold">{{ $soles($c->total) }}</td></tr>@endforeach
                </tbody></table>
            </div>
        </div>

        {{-- Rezagados --}}
        @if ($rezagados->isNotEmpty())
            <div class="{{ $tarjeta }}">
                <h2 class="font-black text-gray-800"><i class="fas fa-hourglass-half text-amber-500"></i> Rezagados: más de {{ $diasAlmacen }} días sin recoger</h2>
                <p class="text-sm text-gray-500 mb-2">Avisa al destinatario o devuélvelos al remitente desde su ficha.</p>
                <div class="divide-y divide-gray-100 text-sm">
                    @foreach ($rezagados as $e)
                        <a href="{{ route('courier.ver', $e->env_id) }}" class="py-2 flex justify-between gap-3 hover:text-orange-700"><span><b class="font-mono">{{ $e->codigo }}</b> · {{ $e->des_nombre }} {{ $e->des_telefono ? '· '.$e->des_telefono : '' }}</span>
                            <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($e->creado)->diffForHumans() }}</span></a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Aliados --}}
        <div class="{{ $tarjeta }}">
            <h2 class="font-black text-gray-800"><i class="fas fa-handshake text-orange-500"></i> Liquidación con agencias aliadas</h2>
            <p class="text-sm text-gray-500 mb-3">Lo que entregaron y aún no se liquida: lo que cobraron al entregar es tuyo; ellos ganan su comisión. Saldo positivo = el aliado te paga.</p>
            @forelse ($aliados as $a)
                <div class="rounded-xl ring-1 ring-gray-200 p-3 mb-2 flex flex-wrap items-center gap-3 text-sm">
                    <div class="flex-1 min-w-56"><p class="font-bold">{{ \App\Support\Courier::etiquetaAgencia($a->agencia) }}</p>
                        <p class="text-xs text-gray-500">{{ $a->envios->count() }} envío(s) entregados · comisión {{ rtrim(rtrim(number_format($a->agencia->comision_pct, 2), '0'), '.') }}%</p></div>
                    <p>Cobró <b>{{ $soles($a->cobrado) }}</b></p><p>Comisión <b>{{ $soles($a->comision) }}</b></p>
                    <p class="font-black {{ $a->saldo >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $a->saldo >= 0 ? 'Te paga' : 'Le pagas' }} {{ $soles(abs($a->saldo)) }}</p>
                    @if ($esAdmin)
                        <button type="button" @click="liquidar(@js(route('courier.aliados.liquidar', $a->agencia->age_id)))" class="h-9 px-3 rounded-lg bg-orange-600 text-white font-bold">Liquidar</button>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-400">No hay entregas de aliados por liquidar.</p>
            @endforelse
            @if ($liquidaciones->isNotEmpty())
                <p class="text-xs font-semibold uppercase text-gray-400 mt-4 mb-1">Últimas liquidaciones</p>
                <div class="divide-y divide-gray-100 text-sm">
                    @foreach ($liquidaciones as $l)
                        <p class="py-1.5 flex justify-between gap-2"><span>{{ \Carbon\Carbon::parse($l->creado)->format('d/m/Y') }} · {{ $l->ciudad }} · {{ $l->envios }} envío(s){{ $l->observacion ? ' · '.$l->observacion : '' }}</span>
                            <b>{{ $l->saldo >= 0 ? 'Pagó' : 'Se le pagó' }} {{ $soles(abs($l->saldo)) }}</b></p>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <script>
        function reportesCourier() {
            return {
                async liquidar(url) {
                    const observacion = prompt('¿Registrar la liquidación? Puedes anotar cómo se pagó (ej. depósito BCP 15/01):', '');
                    if (observacion === null) return;
                    const d = await fetch(url, { method: 'POST', body: JSON.stringify({ observacion }),
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } }).then(r => r.json());
                    (window.tushpaAviso || alert)(d.mensaje || 'Error', d.ok ? 'ok' : 'error');
                    if (d.ok) setTimeout(() => location.reload(), 700);
                },
            };
        }
    </script>
@endsection
