@extends('pasajes.layout')
@section('titulo', 'Mis pasajes')
@section('contenido')
    <h1 class="text-xl font-black text-gray-800">Mis pasajes</h1>
    @if ($config->web_texto)
        <p class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-3 text-sm font-semibold text-amber-900">{{ $config->web_texto }}</p>
    @endif
    @forelse ($grupos as $grupo => $pasajes)
        @php
            $p = $pasajes->first();
            $pagado = $p->estado === 'VENDIDO' && ! $p->ccabaj;
            $vencida = $p->estado === 'RESERVADO' && \Carbon\Carbon::parse($p->reservado_hasta)->isPast();
            [$texto, $clase] = match (true) {
                $pagado => ['Pagado', 'bg-emerald-100 text-emerald-700'],
                $p->estado === 'RESERVADO' && ! $vencida => ['Esperando tu pago', 'bg-amber-100 text-amber-800'],
                default => ['Sin efecto', 'bg-slate-100 text-slate-500'],
            };
        @endphp
        <div class="bg-white rounded-2xl shadow-sm p-4 space-y-2">
            <div class="flex items-center gap-2">
                <p class="flex-1 font-black text-gray-800">{{ \App\Support\Transportes::textoTramo($p, (int) $p->tramo_desde, (int) $p->tramo_hasta) }}</p>
                <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $clase }}">{{ $texto }}</span>
            </div>
            <p class="text-sm text-gray-600">{{ \Carbon\Carbon::parse($p->salida)->translatedFormat('l d/m/Y · H:i') }}</p>
            <p class="text-sm text-gray-600">
                @foreach ($pasajes as $x)Asiento <b>{{ $x->asiento }}</b> {{ $x->nombre }}@if (! $loop->last) · @endif @endforeach
                · <b>S/ {{ number_format($pasajes->sum('precio'), 2) }}</b></p>
            @if ($p->estado === 'RESERVADO' && ! $vencida)
                <p class="text-xs text-amber-700">Reservado hasta {{ \Carbon\Carbon::parse($p->reservado_hasta)->format('d/m H:i') }}. La agencia te llamará para confirmar tu pago.</p>
            @endif
            @if ($pagado || ($p->estado === 'RESERVADO' && ! $vencida))
                <a href="{{ route('pasajes.boleto', [$negocio->id_empresa_negocio, $grupo]) }}" target="_blank"
                   class="inline-flex items-center h-10 px-4 rounded-xl bg-indigo-600 text-sm font-bold text-white"><i class="fas fa-file-pdf mr-1"></i> Boleto PDF</a>
            @endif
        </div>
    @empty
        <p class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">Aún no tienes pasajes.
            <a href="{{ route('pasajes.web', $negocio->id_empresa_negocio) }}" class="block mt-2 font-bold text-indigo-600">Ver salidas →</a></p>
    @endforelse
@endsection
