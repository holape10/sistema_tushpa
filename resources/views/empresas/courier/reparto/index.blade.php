@extends('layouts.app')
@section('title', 'Reparto a domicilio')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $wa = fn ($tel, $texto) => ($n = preg_replace('/\D/', '', (string) $tel)) ? 'https://wa.me/'.(strlen($n) === 9 ? '51'.$n : $n).'?text='.rawurlencode($texto) : null;
    @endphp

    <div class="space-y-5" x-data="reparto()">
        @include('empresas.courier.partials.nav')
        @include('empresas.partials.alert')
        <div>
            <h1 class="text-2xl font-black text-gray-800"><i class="fas fa-motorcycle text-orange-600"></i> Reparto a domicilio</h1>
            <p class="text-sm text-gray-500">Asigna los envíos a tus motorizados: cada uno entrega desde su celular con foto y firma. Lo que cobran se liquida aquí en caja.</p>
        </div>

        @if ($motorizados->isEmpty())
            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-5 text-amber-900">
                <p class="font-bold"><i class="fas fa-circle-info"></i> Primero registra a tus motorizados</p>
                <p class="text-sm mt-1">Son los mismos de Delivery: nombre, celular y placa. Cada uno tiene su enlace personal.</p>
                <a href="{{ url('/motorizados') }}" class="inline-block mt-3 px-4 py-2 rounded-xl bg-amber-500 text-white font-bold text-sm">Ir a motorizados →</a>
            </div>
        @endif

        <div class="grid lg:grid-cols-2 gap-5 items-start">
            {{-- Esperan en la agencia --}}
            <div class="bg-white rounded-2xl shadow-sm">
                <div class="p-4 border-b border-gray-100 flex flex-wrap items-center gap-2">
                    <h2 class="flex-1 font-black text-gray-800">En la agencia ({{ $enAgencia->count() }})</h2>
                    @if ($enAgencia->isNotEmpty() && $motorizados->isNotEmpty())
                        <select x-model="mot" class="h-10 rounded-xl border-gray-300 text-sm">
                            <option value="">Motorizado…</option>
                            @foreach ($motorizados as $mo)<option value="{{ $mo->mot_id }}">{{ $mo->nombre }} ({{ $mo->envios->count() }})</option>@endforeach
                        </select>
                        <button type="button" @click="asignar()" :disabled="!mot || !elegidos.length || ocupado" class="h-10 px-4 rounded-xl bg-orange-600 text-white text-sm font-bold disabled:opacity-40">
                            <i class="fas fa-motorcycle"></i> Salir a reparto</button>
                    @endif
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse ($enAgencia as $e)
                        <label class="flex items-start gap-3 px-4 py-3 hover:bg-orange-50/40 cursor-pointer">
                            <input type="checkbox" value="{{ $e->env_id }}" x-model.number="elegidos" class="rounded h-5 w-5 mt-1">
                            <div class="flex-1 text-sm">
                                <p><b class="font-mono">{{ $e->codigo }}</b> · {{ $e->piezas }} pza @if ($e->intentos)<span class="text-xs font-bold text-rose-600">{{ $e->intentos }} intento(s)</span>@endif</p>
                                <p class="text-gray-700">{{ $e->des_nombre }}{{ $e->des_telefono ? ' · '.$e->des_telefono : '' }}</p>
                                <p class="text-xs text-gray-500"><i class="fas fa-location-dot text-amber-500"></i> {{ $e->direccion_entrega }}</p>
                            </div>
                            @if ($e->pago === 'DESTINO' && ! $e->IdCpe_cabecera && (float) $e->total > 0)<span class="text-xs font-bold text-rose-600 whitespace-nowrap">Cobrar {{ $soles($e->total) }}</span>@endif
                        </label>
                    @empty
                        <p class="p-8 text-center text-gray-400">No hay envíos a domicilio esperando en la agencia.</p>
                    @endforelse
                </div>
            </div>

            {{-- Motorizados en ruta --}}
            <div class="space-y-4">
                @foreach ($motorizados as $mo)
                    <div class="bg-white rounded-2xl shadow-sm p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="h-10 w-10 rounded-full bg-rose-100 text-rose-700 flex items-center justify-center"><i class="fas fa-motorcycle"></i></span>
                            <div class="flex-1"><p class="font-black text-gray-800">{{ $mo->nombre }}</p><p class="text-xs text-gray-500">{{ $mo->envios->count() }} en ruta{{ $mo->placa ? ' · '.$mo->placa : '' }}</p></div>
                            <button type="button" @click="navigator.clipboard?.writeText(@js($mo->enlace)); (window.tushpaAviso || alert)('Enlace copiado', 'ok')" class="h-9 px-3 rounded-lg bg-gray-100 text-sm font-semibold"><i class="fas fa-link"></i> Enlace</button>
                            @if ($link = $wa($mo->telefono, 'Hola '.$mo->nombre.', tus entregas de hoy: '.$mo->enlace))
                                <a href="{{ $link }}" target="_blank" class="h-9 px-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm font-semibold flex items-center gap-1"><i class="fab fa-whatsapp"></i> Enviar</a>
                            @endif
                        </div>
                        @if ($mo->envios->isNotEmpty())
                            <div class="mt-3 divide-y divide-gray-100 text-sm">
                                @foreach ($mo->envios as $e)
                                    <a href="{{ route('courier.ver', $e->env_id) }}" class="py-1.5 flex justify-between gap-2 hover:text-orange-700"><span><b class="font-mono">{{ $e->codigo }}</b> · {{ $e->des_nombre }}</span>
                                        <span class="text-xs text-gray-400 truncate max-w-48">{{ $e->direccion_entrega }}</span></a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Liquidación: lo cobrado por los motorizados --}}
        @if ($porLiquidar->isNotEmpty())
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-black text-gray-800"><i class="fas fa-cash-register text-emerald-600"></i> Por liquidar: lo que cobraron al entregar</h2>
                <p class="text-sm text-gray-500 mb-3">Recibe el dinero del motorizado y emite el comprobante de cada envío (necesitas tu turno de caja abierto).</p>
                @foreach ($porLiquidar as $motId => $lista)
                    <div class="rounded-xl ring-1 ring-gray-200 p-3 mb-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="flex-1 font-bold">{{ $lista->first()->motorizado ?? 'Motorizado' }} · {{ $lista->count() }} envío(s) ·
                                @foreach ($lista->groupBy('medio_rep') as $medio => $g){{ $medio }} {{ $soles($g->sum('cobrado_rep')) }}@if (! $loop->last), @endif @endforeach</p>
                            <button type="button" @click="liquidar(@js($lista->pluck('env_id')->values()), @js($soles($lista->sum('cobrado_rep'))))" :disabled="ocupado"
                                    class="h-10 px-4 rounded-xl bg-emerald-600 text-white text-sm font-bold"><i class="fas fa-check"></i> Recibí {{ $soles($lista->sum('cobrado_rep')) }}</button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ $lista->pluck('codigo')->implode(', ') }}</p>
                    </div>
                @endforeach
            </div>
        @endif
        <iframe x-ref="imprimir" class="hidden"></iframe>
    </div>

    <script>
        function reparto() {
            const post = (url, data) => fetch(url, { method: 'POST', body: JSON.stringify(data),
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } }).then(r => r.json());
            return {
                mot: '', elegidos: [], ocupado: false,
                async enviar(url, datos, recargar = 700) {
                    this.ocupado = true;
                    try {
                        const d = await post(url, datos);
                        (window.tushpaAviso || alert)(d.mensaje || (d.errors ? Object.values(d.errors).flat()[0] : 'Error'), d.ok ? 'ok' : 'error');
                        if (d.ok) setTimeout(() => location.reload(), recargar);
                        return d;
                    } finally { this.ocupado = false; }
                },
                asignar() { this.enviar(@json(route('courier.reparto.asignar')), { ids: this.elegidos, mot_id: Number(this.mot) }); },
                async liquidar(ids, monto) {
                    if (!confirm('¿Recibiste ' + monto + ' del motorizado? Se emitirá el comprobante de cada envío.')) return;
                    this.enviar(@json(route('courier.reparto.liquidar')), { ids }, 1500);
                },
            };
        }
    </script>
@endsection
