@extends('layouts.app')
@section('title', 'Reclamos')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $colores = ['ABIERTO' => 'bg-rose-100 text-rose-700', 'EN_PROCESO' => 'bg-amber-100 text-amber-800', 'RESUELTO' => 'bg-emerald-100 text-emerald-700', 'RECHAZADO' => 'bg-gray-100 text-gray-600'];
        $wa = fn ($tel, $texto) => ($n = preg_replace('/\D/', '', (string) $tel)) ? 'https://wa.me/'.(strlen($n) === 9 ? '51'.$n : $n).'?text='.rawurlencode($texto) : null;
    @endphp

    <div class="space-y-5" x-data="reclamos()">
        @include('empresas.courier.partials.nav')
        <div>
            <h1 class="text-2xl font-black text-gray-800"><i class="fas fa-comment-dots text-orange-600"></i> Reclamos</h1>
            <p class="text-sm text-gray-500">Los que tus clientes presentan desde el rastreo y los que se registran en la agencia. El cliente ve la respuesta con su N° de guía.</p>
        </div>
        <div class="flex flex-wrap gap-1.5">
            <a href="{{ route('courier.reclamos') }}" class="px-3 py-2 rounded-xl text-sm font-bold {{ ! $estado ? 'bg-orange-600 text-white' : 'bg-white ring-1 ring-gray-200 text-gray-600' }}">Por atender ({{ ($conteo['ABIERTO'] ?? 0) + ($conteo['EN_PROCESO'] ?? 0) }})</a>
            @foreach ($estados as $clave => $texto)
                <a href="?estado={{ $clave }}" class="px-3 py-2 rounded-xl text-sm font-bold {{ $estado === $clave ? 'bg-orange-600 text-white' : 'bg-white ring-1 ring-gray-200 text-gray-600' }}">{{ $texto }} ({{ $conteo[$clave] ?? 0 }})</a>
            @endforeach
        </div>

        <div class="space-y-3">
            @forelse ($reclamos as $r)
                <div class="bg-white rounded-2xl shadow-sm p-4">
                    <div class="flex flex-wrap items-start gap-3">
                        <div class="flex-1 min-w-60">
                            <p class="font-black text-gray-800">Reclamo N° {{ $r->numero }} · {{ $tipos[$r->tipo] ?? $r->tipo }}
                                <span class="ml-1 text-[11px] font-bold rounded-full px-2 py-0.5 {{ $colores[$r->estado] }}">{{ $estados[$r->estado] }}</span>
                                <span class="text-[11px] text-gray-400">{{ $r->origen === 'WEB' ? 'por la web' : 'en agencia' }}</span></p>
                            <p class="text-sm text-gray-500">Guía <a href="{{ route('courier.ver', $r->env_id) }}" class="font-mono font-bold text-orange-700">{{ $r->codigo }}</a>
                                · {{ $r->rem_nombre }} → {{ $r->des_nombre }} · valor declarado S/ {{ number_format($r->valor_declarado, 2) }}</p>
                            <p class="text-sm mt-2 whitespace-pre-line">{{ $r->detalle }}</p>
                            @if ($r->pedido)<p class="text-sm text-gray-600 mt-1"><b>Pide:</b> {{ $r->pedido }}</p>@endif
                            <p class="text-xs text-gray-400 mt-1">{{ $r->nombre }}{{ $r->documento ? ' · '.$r->documento : '' }}{{ $r->telefono ? ' · '.$r->telefono : '' }}{{ $r->correo ? ' · '.$r->correo : '' }}
                                · {{ \Carbon\Carbon::parse($r->creado)->format('d/m/Y H:i') }}</p>
                            @if ($r->respuesta)<p class="mt-2 rounded-xl bg-gray-50 p-3 text-sm"><b>Respuesta:</b> {{ $r->respuesta }}@if ((float) $r->compensacion > 0) · compensación S/ {{ number_format($r->compensacion, 2) }}@endif</p>@endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($link = $wa($r->telefono, 'Hola '.$r->nombre.', te escribimos por tu reclamo N° '.$r->numero.' de la guía '.$r->codigo.'.'))
                                <a href="{{ $link }}" target="_blank" class="h-9 px-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm font-semibold flex items-center gap-1"><i class="fab fa-whatsapp"></i></a>
                            @endif
                            <button type="button" @click="abrir(@js(['id' => $r->rec_id, 'numero' => $r->numero, 'estado' => $r->estado === 'ABIERTO' ? 'EN_PROCESO' : $r->estado, 'respuesta' => $r->respuesta ?? '', 'compensacion' => (float) $r->compensacion]))"
                                    class="h-9 px-3 rounded-lg bg-orange-600 text-white text-sm font-bold"><i class="fas fa-reply"></i> Responder</button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">No hay reclamos {{ $estado ? 'en este estado' : 'por atender' }}. 🎉</div>
            @endforelse
        </div>

        <template x-if="r">
        <div class="fixed inset-0 z-[70] bg-slate-900/60 flex items-center justify-center p-3">
            <form @submit.prevent="guardar()" class="bg-white rounded-3xl shadow-2xl w-full max-w-lg p-6 space-y-3" @click.outside="r = null">
                <h2 class="text-lg font-black text-gray-800" x-text="'Reclamo N° ' + r.numero"></h2>
                <select x-model="r.estado" class="w-full h-10 rounded-lg border-gray-300 text-sm">
                    @foreach ($estados as $clave => $texto)<option value="{{ $clave }}">{{ $texto }}</option>@endforeach
                </select>
                <textarea x-model="r.respuesta" rows="4" maxlength="2000" placeholder="Respuesta para el cliente (la ve en el rastreo)" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                <label class="block text-xs font-semibold text-gray-500">Compensación S/ (si corresponde)
                    <input type="number" step="0.01" min="0" x-model="r.compensacion" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm text-right"></label>
                <div class="flex gap-3 justify-end"><button type="button" @click="r = null" class="px-4 h-10 rounded-xl bg-gray-100 font-bold">Cancelar</button>
                    <button class="px-5 h-10 rounded-xl bg-orange-600 text-white font-bold">Guardar</button></div>
            </form>
        </div>
        </template>
    </div>

    <script>
        function reclamos() {
            return {
                r: null,
                abrir(r) { this.r = { ...r }; },
                async guardar() {
                    const d = await fetch(@json(url('courier/reclamos')) + '/' + this.r.id, { method: 'POST', body: JSON.stringify(this.r),
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } }).then(r => r.json());
                    (window.tushpaAviso || alert)(d.mensaje || (d.errors ? Object.values(d.errors).flat()[0] : 'Error'), d.ok ? 'ok' : 'error');
                    if (d.ok) setTimeout(() => location.reload(), 700);
                },
            };
        }
    </script>
@endsection
