@extends('layouts.app')
@section('title', 'Envíos')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $colores = ['slate' => 'bg-slate-100 text-slate-700', 'sky' => 'bg-sky-100 text-sky-700', 'indigo' => 'bg-indigo-100 text-indigo-700',
            'amber' => 'bg-amber-100 text-amber-800', 'emerald' => 'bg-emerald-100 text-emerald-700', 'rose' => 'bg-rose-100 text-rose-700', 'gray' => 'bg-gray-100 text-gray-400'];
        // Lo que se puede hacer en bloque en cada bandeja
        $paso = ['salen' => ['EN_TRANSITO', 'Despachar (salió de la agencia)', 'fa-truck-fast'], 'camino' => ['EN_DESTINO', 'Recibir en mi agencia', 'fa-warehouse'],
            'llegan' => ['EN_REPARTO', 'Salió a reparto a domicilio', 'fa-motorcycle']][$vista] ?? null;
        $elegibles = $envios->filter(fn ($e) => match ($vista) {
            'salen' => $e->soyOrigen, 'camino' => $e->soyDestino, 'llegan' => $e->soyDestino && $e->entrega === 'DOMICILIO' && $e->estado === 'EN_DESTINO', default => false,
        })->pluck('env_id')->values();
    @endphp

    <div class="space-y-5" x-data="bandejaEnvios()">
        @include('empresas.courier.partials.nav')
        @include('empresas.partials.alert')

        {{-- Cabecera --}}
        <div class="relative overflow-hidden rounded-3xl p-6 sm:p-7 text-white bg-gradient-to-br from-orange-500 via-orange-600 to-rose-700 shadow-lg">
            <div class="absolute -right-10 -top-10 w-56 h-56 rounded-full bg-white/10"></div>
            <div class="absolute right-24 -bottom-16 w-40 h-40 rounded-full bg-white/5"></div>
            <div class="relative flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-widest text-orange-100/80"><i class="fas fa-truck-fast"></i> Courier · envíos</p>
                    <p class="mt-2 text-sm text-orange-100">Registrados hoy</p>
                    <p class="text-4xl sm:text-5xl font-black tracking-tight">{{ $kpi->hoy }}</p>
                    <p class="mt-1 text-sm text-orange-100">Entregados hoy <b class="text-white">{{ $kpi->entregadosHoy }}</b>
                        @if ($kpi->porCobrar > 0) · por cobrar al recoger <b class="text-white">{{ $soles($kpi->porCobrar) }}</b>@endif</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('courier.nuevo') }}" class="px-4 py-2.5 rounded-xl bg-white text-orange-700 font-extrabold text-sm shadow hover:bg-orange-50"><i class="fas fa-plus"></i> Nuevo envío</a>
                    <a href="{{ route('envios.web') }}" target="_blank" class="px-4 py-2.5 rounded-xl bg-white/15 font-bold text-sm hover:bg-white/25"><i class="fas fa-globe"></i> Tu web de envíos</a>
                </div>
            </div>
        </div>

        {{-- Lo que toca hacer en la agencia --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
            @foreach ([
                ['salen', 'Por despachar', $kpi->salen, 'fa-box', 'text-slate-600', 'esperan salir'],
                ['camino', 'Vienen en camino', $kpi->vienen, 'fa-truck-fast', 'text-sky-600', 'hacia tu agencia'],
                ['llegan', 'Por entregar', $kpi->llegan, 'fa-warehouse', 'text-indigo-600', 'esperan su recojo'],
                ['rezagados', 'Rezagados', $kpi->rezagados, 'fa-hourglass-half', 'text-amber-600', 'nadie los recoge'],
                ['entregados', 'Entregados hoy', $kpi->entregadosHoy, 'fa-circle-check', 'text-emerald-600', 'en tu agencia'],
            ] as [$clave, $titulo, $valor, $icono, $color, $sub])
                <a href="?vista={{ $clave }}" class="bg-white rounded-2xl shadow-sm p-4 ring-2 transition {{ $vista === $clave && $q === '' ? 'ring-orange-400' : 'ring-transparent hover:ring-gray-200' }}">
                    <p class="text-[11px] font-semibold uppercase text-gray-400"><i class="fas {{ $icono }} {{ $color }}"></i> {{ $titulo }}</p>
                    <p class="text-2xl font-black text-gray-800 mt-1">{{ $valor }}</p>
                    <p class="text-xs text-gray-400">{{ $sub }}</p>
                </a>
            @endforeach
        </div>

        <div class="bg-white rounded-2xl shadow-sm">
            <div class="p-4 flex flex-wrap gap-3 items-center border-b border-gray-100">
                <div class="flex flex-wrap gap-1">
                    @foreach ($vistas as $clave => $texto)
                        <a href="?vista={{ $clave }}" class="px-3 py-2 rounded-xl text-sm font-bold {{ $vista === $clave && $q === '' ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">{{ $texto }}</a>
                    @endforeach
                </div>
                <form class="flex-1 min-w-56 flex gap-2">
                    <input type="hidden" name="vista" value="{{ $vista }}">
                    <input type="search" name="q" value="{{ $q }}" placeholder="N° de guía, DNI, nombre o celular…" class="flex-1 h-11 rounded-xl border-gray-300 text-sm">
                    <button class="h-11 px-4 rounded-xl bg-gray-800 text-white text-sm font-bold"><i class="fas fa-magnifying-glass"></i></button>
                </form>
            </div>

            @if ($paso && $elegibles->isNotEmpty())
                <div class="px-4 py-3 flex flex-wrap items-center gap-3 bg-orange-50/60 border-b border-orange-100 text-sm">
                    <label class="flex items-center gap-2 font-semibold"><input type="checkbox" class="rounded" @change="elegidos = $event.target.checked ? [...ELEGIBLES] : []"> Todos</label>
                    <span class="text-gray-500" x-text="elegidos.length + ' elegido(s)'"></span>
                    <button type="button" :disabled="!elegidos.length || ocupado" @click="avanzar(@js($paso[0]))"
                            class="ml-auto h-10 px-4 rounded-xl bg-orange-600 text-white font-bold disabled:opacity-40"><i class="fas {{ $paso[2] }}"></i> {{ $paso[1] }}</button>
                </div>
            @endif

            <div class="divide-y divide-gray-100">
                @forelse ($envios as $e)
                    @php [$texto, , $color] = \App\Support\Courier::ESTADOS[$e->estado]; @endphp
                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-orange-50/40">
                        @if ($elegibles->contains($e->env_id))
                            <input type="checkbox" value="{{ $e->env_id }}" x-model.number="elegidos" class="rounded h-5 w-5">
                        @endif
                        <a href="{{ route('courier.ver', $e->env_id) }}" class="flex-1 min-w-56">
                            <p class="font-mono font-black text-gray-800 tracking-wider">{{ $e->codigo }}
                                <span class="ml-1 font-sans text-[11px] font-bold rounded-full px-2 py-0.5 {{ $colores[$color] }}">{{ $texto }}</span>
                                @if ($e->entrega === 'DOMICILIO')<span class="font-sans text-[11px] font-bold rounded-full px-2 py-0.5 bg-amber-50 text-amber-700"><i class="fas fa-house"></i> Domicilio</span>@endif
                            </p>
                            <p class="text-sm text-gray-600"><b>{{ $e->origen->ciudad ?? '—' }}</b> <i class="fas fa-arrow-right text-xs text-gray-400"></i> <b>{{ $e->destino->ciudad ?? '—' }}</b>
                                · {{ $e->piezas }} pza(s) · {{ rtrim(rtrim(number_format($e->peso_cobrado, 2), '0'), '.') }} kg</p>
                            <p class="text-xs text-gray-400">De {{ $e->rem_nombre }} para <b class="text-gray-600">{{ $e->des_nombre }}</b>{{ $e->des_telefono ? ' · '.$e->des_telefono : '' }}
                                · {{ \Carbon\Carbon::parse($e->creado)->format('d/m H:i') }}</p>
                        </a>
                        <div class="text-right">
                            <p class="font-black text-gray-800">{{ $soles($e->total) }}</p>
                            @if ($e->IdCpe_cabecera)
                                <p class="text-[11px] font-bold text-emerald-600">{{ $e->pago === 'CREDITO' ? 'Facturado' : 'Pagado' }}</p>
                            @elseif ($e->pago === 'CREDITO')
                                <p class="text-[11px] font-bold text-violet-600">A crédito</p>
                            @elseif ((float) $e->total <= 0)
                                <p class="text-[11px] font-bold text-gray-400">Sin costo</p>
                            @elseif (! in_array($e->estado, ['ANULADO', 'DEVUELTO'], true))
                                <p class="text-[11px] font-bold text-rose-600">{{ $e->pago === 'DESTINO' && $e->estado !== 'ENTREGADO' ? 'Paga al recoger' : 'Por cobrar' }}</p>
                            @endif
                        </div>
                        @if ($e->soyDestino && in_array($e->estado, ['EN_DESTINO', 'EN_REPARTO'], true))
                            <a href="{{ route('courier.ver', $e->env_id) }}?entregar=1" class="h-10 px-3 rounded-xl bg-emerald-600 text-white text-sm font-bold flex items-center gap-1"><i class="fas fa-hand-holding"></i> Entregar</a>
                        @endif
                    </div>
                @empty
                    <p class="p-10 text-center text-gray-400">
                        @if ($q !== '') No hay envíos con «{{ $q }}». @else No hay envíos en «{{ $vistas[$vista] }}». @endif
                    </p>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        const ELEGIBLES = @json($elegibles);
        function bandejaEnvios() {
            return {
                elegidos: [], ocupado: false,
                async avanzar(estado) {
                    this.ocupado = true;
                    try {
                        const r = await fetch(@json(route('courier.avanzar')), { method: 'POST', body: JSON.stringify({ ids: this.elegidos, estado }),
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } }).then(r => r.json());
                        (window.tushpaAviso || alert)(r.mensaje || (r.errors ? Object.values(r.errors).flat()[0] : 'Error'), r.ok ? 'ok' : 'error');
                        if (r.ok) setTimeout(() => location.reload(), 700);
                    } catch (e) { (window.tushpaAviso || alert)('Error de conexión.', 'error'); } finally { this.ocupado = false; }
                },
            };
        }
    </script>
@endsection
