@php
    $caras = [1 => ['😡', 'Muy mal'], 2 => ['😕', 'Mal'], 3 => ['😐', 'Regular'], 4 => ['🙂', 'Bien'], 5 => ['😍', '¡Excelente!']];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="{{ $color }}">
    <meta name="robots" content="noindex">
    <title>¿Qué tal te atendimos? · {{ $nombre }}</title>
    @if ($logo)<link rel="icon" href="{{ asset($logo) }}">@endif
    <style>
        [x-cloak]{display:none!important}
        :root { --acento: {{ $color }}; }
        .bg-acento { background: var(--acento); }
        .cara { transition: transform .12s; }
        .cara.elegida { transform: scale(1.18); background: color-mix(in srgb, var(--acento) 14%, #fff); box-shadow: 0 0 0 3px var(--acento); }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen">
    <header class="bg-acento text-white rounded-b-[2rem] shadow-lg">
        <div class="max-w-md mx-auto px-5 pt-8 pb-7 text-center">
            @if ($logo)<img src="{{ asset($logo) }}" alt="{{ $nombre }}" class="mx-auto h-20 w-20 rounded-3xl object-contain bg-white p-1.5 shadow-xl">@endif
            <h1 class="text-2xl font-black mt-3">{{ $nombre }}</h1>
            <p class="text-white/85 text-sm mt-1">Tu opinión nos ayuda a mejorar</p>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 py-6">
        @if ($gracias !== null)
            <div class="bg-white rounded-3xl shadow-sm p-8 text-center">
                <div class="text-6xl">{{ $gracias >= 4 ? '🥰' : '🙏' }}</div>
                <h2 class="text-2xl font-black mt-3">¡Gracias!</h2>
                <p class="text-slate-600 mt-2">{{ $gracias >= 4 ? 'Nos alegra que te haya gustado. ¡Te esperamos pronto!' : 'Sentimos que no fue como esperabas. Lo vamos a revisar para mejorar.' }}</p>
            </div>
        @elseif ($motivo)
            <div class="bg-white rounded-3xl shadow-sm p-8 text-center">
                <div class="text-5xl">🙂</div>
                <p class="text-slate-700 font-semibold mt-3">{{ $motivo }}</p>
            </div>
        @else
            <form method="POST" action="{{ route('encuesta.responder', $codigo) }}" class="space-y-4"
                  x-data="{ r: { comida: @js((int) old('comida')), atencion: @js((int) old('atencion')), rapidez: @js((int) old('rapidez')) } }">
                @csrf
                @foreach (\App\Support\Encuestas::PREGUNTAS as $campo => $pregunta)
                    <div class="bg-white rounded-3xl shadow-sm p-5">
                        <p class="font-bold text-lg text-center">{{ $pregunta }}</p>
                        <input type="hidden" name="{{ $campo }}" :value="r.{{ $campo }} || ''">
                        <div class="flex justify-between mt-3">
                            @foreach ($caras as $valor => [$emoji, $texto])
                                <button type="button" @click="r.{{ $campo }} = {{ $valor }}" :class="r.{{ $campo }} === {{ $valor }} && 'elegida'"
                                        class="cara w-14 h-14 rounded-2xl text-3xl flex items-center justify-center" title="{{ $texto }}" aria-label="{{ $texto }}">{{ $emoji }}</button>
                            @endforeach
                        </div>
                        <p class="text-center text-sm font-semibold text-slate-500 h-5 mt-1" x-text="{{ json_encode(array_map(fn ($c) => $c[1], $caras)) }}[r.{{ $campo }}] || ''"></p>
                    </div>
                @endforeach

                <div class="bg-white rounded-3xl shadow-sm p-5 space-y-3">
                    <label class="block font-bold">¿Algo que quieras contarnos? <span class="font-normal text-slate-400 text-sm">(opcional)</span>
                        <textarea name="comentario" rows="3" maxlength="300" class="mt-2 w-full rounded-2xl border-slate-300" placeholder="Lo que más te gustó o lo que podemos mejorar">{{ old('comentario') }}</textarea>
                    </label>
                    <label class="block text-sm font-semibold text-slate-600">Tu celular, si quieres que te contactemos <span class="font-normal text-slate-400">(opcional)</span>
                        <input name="telefono" inputmode="tel" maxlength="15" value="{{ old('telefono') }}" class="mt-1 w-full rounded-2xl border-slate-300" placeholder="9xx xxx xxx">
                    </label>
                </div>

                @if ($errors->any())<div class="rounded-2xl bg-red-50 text-red-700 text-sm p-3 text-center">{{ $errors->first() }}</div>@endif
                <button class="w-full h-14 rounded-2xl bg-acento text-white text-lg font-black shadow-lg disabled:opacity-50" :disabled="!r.comida || !r.atencion || !r.rapidez">Enviar</button>
            </form>
        @endif
    </main>
</body>
</html>
