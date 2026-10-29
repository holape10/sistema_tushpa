@php
    $estados = \App\Support\Courier::ESTADOS;
    $pasos = ['REGISTRADO', 'EN_TRANSITO', 'EN_DESTINO', 'ENTREGADO'];
    $actual = $e ? ($e->estado === 'EN_REPARTO' ? 2 : array_search($e->estado, $pasos, true)) : false;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#c2410c">
    <meta name="robots" content="noindex">
    <title>{{ $e ? 'Envío '.$e->codigo : 'Rastrea tu envío' }} · {{ $nombre }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    <header class="bg-gradient-to-br from-orange-600 via-orange-600 to-rose-700 text-white rounded-b-[2rem] shadow-lg">
        <div class="max-w-lg mx-auto px-5 pt-7 pb-8">
            <div class="flex items-center gap-3">
                @if ($logo)<img src="{{ $logo }}" alt="" class="h-12 w-12 rounded-xl bg-white object-contain p-1">@endif
                <div>
                    <p class="font-black text-lg leading-tight">{{ $nombre }}</p>
                    <p class="text-sm text-orange-100">Rastrea tu envío</p>
                </div>
            </div>
            <form action="{{ route('rastreo') }}" class="mt-5 flex gap-2">
                <input name="codigo" value="{{ $e->codigo ?? ($noEncontrado ?? old('codigo')) }}" maxlength="12" required autocomplete="off" placeholder="N° de guía (10 letras y números)"
                       class="flex-1 h-12 rounded-xl border-0 text-slate-800 font-mono font-bold uppercase tracking-widest placeholder:font-sans placeholder:tracking-normal placeholder:font-normal">
                <button class="h-12 px-5 rounded-xl bg-slate-900 font-bold"><i class="fas fa-magnifying-glass"></i></button>
            </form>
        </div>
    </header>

    <main class="max-w-lg mx-auto px-5 -mt-4 pb-10 space-y-4">
        @if (session('error'))
            <div class="bg-white rounded-2xl shadow-sm p-4 text-sm font-semibold text-rose-700">{{ session('error') }}</div>
        @endif

        @if (! empty($noEncontrado))
            <div class="bg-white rounded-2xl shadow-sm p-6 text-center">
                <p class="text-5xl">🔎</p>
                <p class="mt-2 font-black text-lg">No encontramos la guía {{ $noEncontrado }}</p>
                <p class="text-sm text-slate-500 mt-1">Revisa el número en tu comprobante o consulta con la agencia.</p>
            </div>
        @elseif ($e)
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <p class="text-xs text-slate-400">N° de guía</p>
                <p class="font-mono text-2xl font-black tracking-widest">{{ $e->codigo }}</p>
                <p class="mt-2 text-lg font-black">{{ $origen->ciudad ?? '' }} <i class="fas fa-arrow-right text-slate-300"></i> {{ $destino->ciudad ?? '' }}</p>
                <p class="text-sm text-slate-500">Para {{ \App\Support\Courier::enmascarar($e->des_nombre) }} · {{ $e->piezas }} pieza(s)</p>

                @if ($e->estado === 'ANULADO')
                    <p class="mt-4 rounded-xl bg-slate-100 p-4 text-center font-bold text-slate-600"><i class="fas fa-ban"></i> Este envío fue anulado</p>
                @elseif ($e->estado === 'DEVUELTO')
                    <p class="mt-4 rounded-xl bg-rose-50 p-4 text-center font-bold text-rose-700"><i class="fas fa-rotate-left"></i> Este envío se está devolviendo al remitente</p>
                @else
                    {{-- Avance del envío --}}
                    <div class="mt-5 flex items-start">
                        @foreach ($pasos as $i => $p)
                            @php $hecho = $actual !== false && $i <= $actual; @endphp
                            <div class="flex-1 text-center relative">
                                @if ($i > 0)<span class="absolute top-4 right-1/2 w-full h-1 {{ $hecho ? 'bg-orange-500' : 'bg-slate-200' }}"></span>@endif
                                <span class="relative z-10 mx-auto h-9 w-9 rounded-full flex items-center justify-center {{ $hecho ? 'bg-orange-500 text-white' : 'bg-slate-200 text-slate-400' }}">
                                    <i class="fas {{ $p === 'EN_DESTINO' && $e->estado === 'EN_REPARTO' ? 'fa-motorcycle' : $estados[$p][1] }} text-sm"></i></span>
                                <p class="mt-1 text-[11px] font-semibold leading-tight {{ $hecho ? 'text-slate-800' : 'text-slate-400' }}">
                                    {{ $p === 'EN_DESTINO' && $e->estado === 'EN_REPARTO' ? 'En reparto' : $estados[$p][0] }}</p>
                            </div>
                        @endforeach
                    </div>
                    @if ($e->estado === 'EN_DESTINO' && $e->entrega === 'AGENCIA')
                        <div class="mt-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">
                            <p class="font-black"><i class="fas fa-box-open"></i> ¡Ya llegó! Puedes recogerlo</p>
                            <p>{{ $destino->nombre ?? '' }}{{ ($destino->direccion ?? null) ? ' · '.$destino->direccion : '' }}</p>
                            <p class="mt-1">Lleva tu DNI y la clave que te dio quien te lo envió.</p>
                        </div>
                    @elseif ($e->estado === 'ENTREGADO')
                        <p class="mt-4 rounded-xl bg-emerald-50 p-4 text-sm font-bold text-emerald-800"><i class="fas fa-circle-check"></i> Entregado el {{ \Carbon\Carbon::parse($e->entregado)->format('d/m/Y \a \l\a\s h:i a') }}</p>
                    @elseif ($e->llegada_estimada)
                        <p class="mt-4 text-sm text-slate-500 text-center">Llegada estimada: <b>{{ \Carbon\Carbon::parse($e->llegada_estimada)->translatedFormat('l d \d\e F') }}</b></p>
                    @endif
                    @if ($e->pago === 'DESTINO' && ! $e->IdCpe_cabecera && $e->estado !== 'ENTREGADO')
                        <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 text-center">Pago al recoger: <b>S/ {{ number_format($e->total, 2) }}</b></p>
                    @endif
                @endif
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-5">
                <p class="text-xs font-semibold uppercase text-slate-400 mb-3">Historial</p>
                <ol class="relative border-l-2 border-orange-100 ml-2 space-y-4">
                    @foreach ($eventos as $v)
                        <li class="ml-5">
                            <span class="absolute -left-[9px] h-4 w-4 rounded-full {{ $loop->first ? 'bg-orange-500' : 'bg-slate-300' }}"></span>
                            <p class="font-bold">{{ $v->detalle ?: ($estados[$v->estado][0] ?? $v->estado) }}</p>
                            <p class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($v->fecha)->format('d/m/Y h:i a') }}{{ $v->ciudad ? ' · '.$v->ciudad : '' }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
            @if ($web)
                <a href="{{ route('rastreo.reclamo', $e->codigo) }}" class="block text-center text-sm font-semibold text-slate-500"><i class="fas fa-comment-dots"></i> ¿Algún problema con tu envío? Presenta un reclamo</a>
            @endif
        @else
            <div class="bg-white rounded-2xl shadow-sm p-6 text-center">
                <p class="text-5xl">📦</p>
                <p class="mt-2 font-black text-lg">¿Dónde está mi envío?</p>
                <p class="text-sm text-slate-500 mt-1">Escribe el número de guía que está en tu comprobante, o escanea su código QR.</p>
            </div>
            @if ($web)
                <a href="{{ route('envios.web') }}" class="block bg-white rounded-2xl shadow-sm p-5 text-center font-bold text-orange-700"><i class="fas fa-box"></i> ¿Quieres enviar algo? Cotiza y pre-regístrate aquí</a>
            @endif
        @endif

        @if ($telefono)
            <a href="tel:{{ preg_replace('/\D/', '', $telefono) }}" class="block text-center text-sm font-semibold text-orange-700"><i class="fas fa-phone"></i> ¿Dudas? Llámanos al {{ $telefono }}</a>
        @endif
    </main>
</body>
</html>
